<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/services/match_service.php';

/* --- HTML: New match form --- */
function ctrl_match_new(): string {
    $title = 'New Match';
    $pdo = db();
    $teams = $pdo->query("SELECT id, name FROM teams ORDER BY name ASC")->fetchAll();
    $errors = []; $old = [];
    return render('match_new.php', compact('title','teams','errors','old'));
}

/* --- HTML: Create match (POST) --- */
function ctrl_match_create(): string {
    $pdo = db();

    if (!csrf_ok_form()) {
        $title='New Match';
        $errors=['Invalid session. Please try again.'];
        $teams = $pdo->query("SELECT id, name FROM teams ORDER BY name ASC")->fetchAll();
        $old = [
            'mode'         => $_POST['mode'] ?? '',
            'team_a_id'    => (int)($_POST['team_a_id'] ?? 0),
            'team_b_id'    => (int)($_POST['team_b_id'] ?? 0),
            'target_score' => (int)($_POST['target_score'] ?? 10),
            'notes'        => (string)($_POST['notes'] ?? ''),
        ];
        return render('match_new.php', compact('title','teams','errors','old'));
    }

    $mode   = $_POST['mode'] ?? '';
    $teamA  = (int)($_POST['team_a_id'] ?? 0);
    $teamB  = (int)($_POST['team_b_id'] ?? 0);
    $notes  = trim((string)($_POST['notes'] ?? ''));
    $target = (int)($_POST['target_score'] ?? 10);

    $errors = [];
    if (!in_array($mode, ['1v1','2v2'], true)) $errors[]='Invalid mode.';
    if ($teamA<=0 || $teamB<=0) $errors[]='Both teams are required.';
    if ($teamA===$teamB) $errors[]='Team A and Team B must be different.';
    if ($target < 1 || $target > 50) $errors[]='Target score must be between 1 and 50.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) c FROM teams WHERE id IN (?, ?)");
        $stmt->execute([$teamA,$teamB]);
        if ((int)$stmt->fetch()['c'] !== 2) $errors[]='Unknown team selected.';
    }

    if (!empty($errors)) {
        $title='New Match';
        $teams=$pdo->query("SELECT id, name FROM teams ORDER BY name ASC")->fetchAll();
        $old=['mode'=>$mode,'team_a_id'=>$teamA,'team_b_id'=>$teamB,'target_score'=>$target,'notes'=>$notes];
        return render('match_new.php', compact('title','teams','errors','old'));
    }

    $stmt = $pdo->prepare("
      INSERT INTO matches (mode, team_a_id, team_b_id, score_a, score_b, target_score, notes, status)
      VALUES (?, ?, ?, 0, 0, ?, ?, 'in_progress')
    ");
    $stmt->execute([$mode, $teamA, $teamB, $target, $notes !== '' ? $notes : null]);

    $matchId=(int)$pdo->lastInsertId();
    header('Location: /match/'.$matchId.'?created=1'); exit;
}

/* --- HTML: TV view --- */
function ctrl_match_show(int $matchId): string {
    $pdo = db();
    $stmt=$pdo->prepare("
        SELECT m.id,m.played_at,m.mode,m.team_a_id,m.team_b_id,m.score_a,m.score_b,m.target_score,
               m.notes,m.status,m.finished_at,
               a.name AS team_a_name,a.rating AS rating_a,
               b.name AS team_b_name,b.rating AS rating_b
        FROM matches m
        JOIN teams a ON a.id = m.team_a_id
        JOIN teams b ON b.id = m.team_b_id
        WHERE m.id = ? LIMIT 1
    ");
    $stmt->execute([$matchId]); $match=$stmt->fetch();
    if(!$match){ http_response_code(404); return 'Match not found'; }

    $isInProgress = (($match['status'] ?? 'in_progress') !== 'finished');
    $title = 'Match #'.$matchId;
    $created = isset($_GET['created']) && $_GET['created'] === '1';
    $finishedMsg = isset($_GET['finished']) && $_GET['finished'] === '1';
    $err = $_GET['err'] ?? null;

    return render('match_show.php', compact('title','match','isInProgress','created','finishedMsg','err'));
}

/* --- API: GET state --- */
function ctrl_api_match_get(int $matchId): void {
    $pdo = db();
    $stmt=$pdo->prepare("SELECT id, score_a, score_b, target_score, status FROM matches WHERE id=? LIMIT 1");
    $stmt->execute([$matchId]); $row=$stmt->fetch();
    if(!$row) json_response(['ok'=>false,'error'=>'not_found'], 404);
    $reached = ((int)$row['score_a'] >= (int)$row['target_score']) || ((int)$row['score_b'] >= (int)$row['target_score']);
    json_response(['ok'=>true] + $row + ['reached'=>$reached]);
}

/* --- API: POST score +/- --- */
function ctrl_api_match_score(int $matchId): void {
    if (!csrf_ok_header()) json_response(['ok'=>false,'error'=>'csrf'], 403);
    $pdo = db();

    $team=strtoupper(substr((string)($_POST['team']??''),0,1));
    $delta=(int)($_POST['delta']??0);
    if ($delta>1) $delta=1; if ($delta<-1) $delta=-1;
    if(!in_array($team,['A','B'],true) || $delta===0){
        json_response(['ok'=>false,'error'=>'bad_input'], 400);
    }

    try {
        $pdo->beginTransaction();

        $stmt=$pdo->prepare("SELECT score_a,score_b,target_score,status FROM matches WHERE id=? FOR UPDATE");
        $stmt->execute([$matchId]); $row=$stmt->fetch();
        if(!$row){ $pdo->rollBack(); json_response(['ok'=>false,'error'=>'not_found'], 404); }
        if (($row['status'] ?? '') === 'finished') {
            $pdo->rollBack(); json_response(['ok'=>false,'error'=>'not_in_progress'], 409);
        }

        $target=(int)$row['target_score'];
        $curA=(int)$row['score_a'];
        $curB=(int)$row['score_b'];

        if ($team==='A') {
            $newA=max(0, min($target, $curA+$delta));
            $applied=$newA-$curA;
            if($applied!==0){
                $pdo->prepare("UPDATE matches SET score_a=? WHERE id=?")->execute([$newA,$matchId]);
                $pdo->prepare("INSERT INTO match_events (match_id,team,delta,applied) VALUES (?,?,?,?)")
                    ->execute([$matchId,'A',$delta,$applied]);
            }
            $scoreA=$newA; $scoreB=$curB;
        } else {
            $newB=max(0, min($target, $curB+$delta));
            $applied=$newB-$curB;
            if($applied!==0){
                $pdo->prepare("UPDATE matches SET score_b=? WHERE id=?")->execute([$newB,$matchId]);
                $pdo->prepare("INSERT INTO match_events (match_id,team,delta,applied) VALUES (?,?,?,?)")
                    ->execute([$matchId,'B',$delta,$applied]);
            }
            $scoreA=$curA; $scoreB=$newB;
        }

        $pdo->commit();

        $reached=($scoreA>=$target) || ($scoreB>=$target);
        $leader = $scoreA>$scoreB?'A':($scoreB>$scoreA?'B':'tie');

        json_response([
            'ok'=>true,
            'match_id'=>$matchId,
            'score_a'=>$scoreA,
            'score_b'=>$scoreB,
            'target_score'=>$target,
            'status'=>$row['status'],
            'reached'=>$reached,
            'leader'=>$leader,
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_response(['ok'=>false,'error'=>'score_update_failed','message'=>$e->getMessage()], 500);
    }
}

/* --- API: POST undo --- */
function ctrl_api_match_undo(int $matchId): void {
    if (!csrf_ok_header()) json_response(['ok'=>false,'error'=>'csrf'], 403);
    $pdo = db();

    try {
        $pdo->beginTransaction();

        $stmt=$pdo->prepare("SELECT score_a,score_b,target_score,status FROM matches WHERE id=? FOR UPDATE");
        $stmt->execute([$matchId]); $row=$stmt->fetch();
        if(!$row){ $pdo->rollBack(); json_response(['ok'=>false,'error'=>'not_found'], 404); }
        if (($row['status'] ?? '') === 'finished') {
            $pdo->rollBack(); json_response(['ok'=>false,'error'=>'not_in_progress'], 409);
        }

        $evt=$pdo->prepare("SELECT id,team,applied FROM match_events WHERE match_id=? ORDER BY id DESC LIMIT 1");
        $evt->execute([$matchId]); $e=$evt->fetch();
        if(!$e){ $pdo->rollBack(); json_response(['ok'=>false,'error'=>'no_events'], 404); }

        $applied=(int)$e['applied'];
        $team=$e['team']==='A'?'A':'B';

        if($applied!==0){
            if($team==='A'){
                $pdo->prepare("UPDATE matches SET score_a = GREATEST(0, LEAST(target_score, score_a - ?)) WHERE id=?")
                    ->execute([$applied, $matchId]);
            } else {
                $pdo->prepare("UPDATE matches SET score_b = GREATEST(0, LEAST(target_score, score_b - ?)) WHERE id=?")
                    ->execute([$applied, $matchId]);
            }
        }

        $pdo->prepare("DELETE FROM match_events WHERE id=?")->execute([(int)$e['id']]);

        $stmt=$pdo->prepare("SELECT score_a,score_b,target_score,status FROM matches WHERE id=? LIMIT 1");
        $stmt->execute([$matchId]); $row2=$stmt->fetch();

        $pdo->commit();

        $reached=((int)$row2['score_a'] >= (int)$row2['target_score']) || ((int)$row2['score_b'] >= (int)$row2['target_score']);
        json_response([
            'ok'=>true,
            'match_id'=>$matchId,
            'score_a'=>(int)$row2['score_a'],
            'score_b'=>(int)$row2['score_b'],
            'target_score'=>(int)$row2['target_score'],
            'status'=>$row2['status'],
            'reached'=>$reached,
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_response(['ok'=>false,'error'=>'undo_failed','message'=>$e->getMessage()], 500);
    }
}

/* --- API: POST finish --- */
function ctrl_api_match_finish(int $matchId): void {
    if (!csrf_ok_header()) json_response(['ok'=>false,'error'=>'csrf'], 403);
    $pdo = db();
    $res = finish_match($pdo, $matchId);
    if (($res['ok'] ?? false) === true) json_response($res);
    json_response($res, ($res['error'] ?? '') === 'not_found' ? 404 : 500);
}