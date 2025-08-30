<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/services/match_service.php';
require_once __DIR__ . '/../../src/validation.php';
require_once __DIR__ . '/../../src/repo.php';

/* --- HTML: New match form --- */
function ctrl_match_new(): string {
    $title = 'New Match';
    $pdo   = db();
    $teams = repo_list_teams($pdo);
    $errors = []; $old = [];
    return render('match_new.php', compact('title','teams','errors','old'));
}

/* --- HTML: Create match (POST) --- */
function ctrl_match_create(): string {
    $pdo = db();

    if (!csrf_ok_form()) {
        $title  = 'New Match';
        $errors = ['Invalid session. Please try again.'];
        $teams  = repo_list_teams($pdo);
        $old    = normalize_match_create_input($_POST);
        return render('match_new.php', compact('title','teams','errors','old'));
    }

    $d = normalize_match_create_input($_POST);
    $errors = validate_match_create_basic($d);

    if (empty($errors) && !repo_teams_exist($pdo, $d['team_a_id'], $d['team_b_id'])) {
        $errors[] = 'Unknown team selected.';
    }

    if (!empty($errors)) {
        $title = 'New Match';
        $teams = repo_list_teams($pdo);
        $old   = $d;
        return render('match_new.php', compact('title','teams','errors','old'));
    }

    $matchId = repo_match_insert($pdo, $d['mode'], $d['team_a_id'], $d['team_b_id'], $d['target_score'], $d['notes']);
    header('Location: /match/' . $matchId . '?created=1'); exit;
}

/* --- HTML: TV view --- */
function ctrl_match_show(int $matchId): string {
    $pdo   = db();
    $match = repo_match_full($pdo, $matchId);
    if (!$match) { http_response_code(404); return 'Match not found'; }

    $title        = 'Match #'.$matchId;
    $isInProgress = (($match['status'] ?? 'in_progress') !== 'finished');
    $created      = isset($_GET['created'])  && $_GET['created'] === '1';
    $finishedMsg  = isset($_GET['finished']) && $_GET['finished'] === '1';
    $err          = $_GET['err'] ?? null;

    return render('match_show.php', compact('title','match','isInProgress','created','finishedMsg','err'));
}

/* --- API: GET state --- */
function ctrl_api_match_get(int $matchId): void {

 session_unblock();

    $pdo = db();
    $row = repo_match_state($pdo, $matchId, false);
    if (!$row) json_response(['ok'=>false,'error'=>'not_found'], 404);

    $reached = ((int)$row['score_a'] >= (int)$row['target_score'])
            || ((int)$row['score_b'] >= (int)$row['target_score']);

    json_response(['ok'=>true] + $row + ['reached'=>$reached]);
}

/* --- API: POST score +/- --- */
function ctrl_api_match_score(int $matchId): void {
    if (!csrf_ok_header()) json_response(['ok'=>false,'error'=>'csrf'], 403);

    session_unblock();
    
    $pdo = db();

    $team  = strtoupper(substr((string)($_POST['team'] ?? ''), 0, 1));
    $delta = (int)($_POST['delta'] ?? 0);
    if ($delta > 1)  $delta = 1;
    if ($delta < -1) $delta = -1;
    if (!in_array($team, ['A','B'], true) || $delta === 0) {
        json_response(['ok'=>false,'error'=>'bad_input'], 400);
    }

    try {
        $pdo->beginTransaction();

        $state = repo_match_state($pdo, $matchId, true);
        if (!$state) { $pdo->rollBack(); json_response(['ok'=>false,'error'=>'not_found'], 404); }
        if (($state['status'] ?? '') === 'finished') {
            $pdo->rollBack(); json_response(['ok'=>false,'error'=>'not_in_progress'], 409);
        }

        $target = (int)$state['target_score'];
        $curA   = (int)$state['score_a'];
        $curB   = (int)$state['score_b'];

        if ($team === 'A') {
            $newA    = max(0, min($target, $curA + $delta));
            $applied = $newA - $curA;
            if ($applied !== 0) {
                repo_match_update_score($pdo, $matchId, 'A', $newA);
                repo_match_insert_event($pdo, $matchId, 'A', $delta, $applied);
            }
            $scoreA = $newA; $scoreB = $curB;
        } else {
            $newB    = max(0, min($target, $curB + $delta));
            $applied = $newB - $curB;
            if ($applied !== 0) {
                repo_match_update_score($pdo, $matchId, 'B', $newB);
                repo_match_insert_event($pdo, $matchId, 'B', $delta, $applied);
            }
            $scoreA = $curA; $scoreB = $newB;
        }

        $pdo->commit();

        $reached = ($scoreA >= $target) || ($scoreB >= $target);
        $leader  = $scoreA > $scoreB ? 'A' : ($scoreB > $scoreA ? 'B' : 'tie');

        json_response([
            'ok'           => true,
            'match_id'     => $matchId,
            'score_a'      => $scoreA,
            'score_b'      => $scoreB,
            'target_score' => $target,
            'status'       => $state['status'],
            'reached'      => $reached,
            'leader'       => $leader,
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_response(['ok'=>false,'error'=>'score_update_failed','message'=>$e->getMessage()], 500);
    }
}

/* --- API: POST undo --- */
function ctrl_api_match_undo(int $matchId): void {
    if (!csrf_ok_header()) json_response(['ok'=>false,'error'=>'csrf'], 403);

    session_unblock();

    $pdo = db();

    try {
        $pdo->beginTransaction();

        $state = repo_match_state($pdo, $matchId, true);
        if (!$state) { $pdo->rollBack(); json_response(['ok'=>false,'error'=>'not_found'], 404); }
        if (($state['status'] ?? '') === 'finished') {
            $pdo->rollBack(); json_response(['ok'=>false,'error'=>'not_in_progress'], 409);
        }

        $evt = repo_match_last_event($pdo, $matchId);
        if (!$evt) { $pdo->rollBack(); json_response(['ok'=>false,'error'=>'no_events'], 404); }

        $applied = (int)$evt['applied']; // +1/-1
        $team    = ($evt['team'] === 'A') ? 'A' : 'B';

        if ($applied !== 0) {
            repo_match_decrement_score($pdo, $matchId, $team, $applied);
        }
        repo_match_delete_event($pdo, (int)$evt['id']);

        $state2 = repo_match_state($pdo, $matchId, false);

        $pdo->commit();

        $reached = ((int)$state2['score_a'] >= (int)$state2['target_score'])
                || ((int)$state2['score_b'] >= (int)$state2['target_score']);

        json_response([
            'ok'           => true,
            'match_id'     => $matchId,
            'score_a'      => (int)$state2['score_a'],
            'score_b'      => (int)$state2['score_b'],
            'target_score' => (int)$state2['target_score'],
            'status'       => $state2['status'],
            'reached'      => $reached,
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_response(['ok'=>false,'error'=>'undo_failed','message'=>$e->getMessage()], 500);
    }
}

/* --- API: POST finish --- */
function ctrl_api_match_finish(int $matchId): void {
    if (!csrf_ok_header()) json_response(['ok'=>false,'error'=>'csrf'], 403);

    session_unblock();

    $pdo = db();
    $res = finish_match($pdo, $matchId);
    if (($res['ok'] ?? false) === true) json_response($res);
    json_response($res, ($res['error'] ?? '') === 'not_found' ? 404 : 500);
}