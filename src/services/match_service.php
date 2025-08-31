<?php
declare(strict_types=1);

require_once __DIR__ . '/../elo.php';
require_once __DIR__ . '/../validation.php';
require_once __DIR__ . '/../repo.php';

/** -------- HTML-Helfer (View-Daten) -------- */
function svc_match_new_form(PDO $pdo): array {
    return [
        'title'  => 'New Match',
        'teams'  => repo_list_teams($pdo),
        'errors' => [],
        'old'    => [],
    ];
}

function svc_match_create(PDO $pdo, array $post): array {
    $d = normalize_match_create_input($post);
    $errors = validate_match_create_basic($d);

    if (empty($errors) && !repo_teams_exist($pdo, $d['team_a_id'], $d['team_b_id'])) {
        $errors[] = 'Unknown team selected.';
    }

    if (!empty($errors)) {
        return ['ok'=>false, 'errors'=>$errors, 'old'=>$d, 'teams'=>repo_list_teams($pdo)];
    }

    $id = repo_match_insert($pdo, $d['mode'], $d['team_a_id'], $d['team_b_id'], $d['target_score'], $d['notes']);
    return ['ok'=>true, 'match_id'=>$id];
}

function svc_match_show_data(PDO $pdo, int $matchId, array $query): array {
    $match = repo_match_full($pdo, $matchId);
    if (!$match) return ['ok'=>false, 'http'=>404, 'error'=>'not_found'];

    return [
        'ok'          => true,
        'title'       => 'Match #'.$matchId,
        'match'       => $match,
        'isInProgress'=> (($match['status'] ?? 'in_progress') !== 'finished'),
        'created'     => isset($query['created'])  && $query['created']  === '1',
        'finishedMsg' => isset($query['finished']) && $query['finished'] === '1',
        'err'         => $query['err'] ?? null,
    ];
}

/** -------- API-Payloads (JSON) -------- */
function svc_match_get_state(PDO $pdo, int $matchId): array {
    $row = repo_match_state($pdo, $matchId, false);
    if (!$row) return ['ok'=>false,'http'=>404,'error'=>'not_found'];

    $reached = ((int)$row['score_a'] >= (int)$row['target_score'])
            || ((int)$row['score_b'] >= (int)$row['target_score']);

    return ['ok'=>true] + $row + ['reached'=>$reached];
}

function svc_match_change_score(PDO $pdo, int $matchId, $teamRaw, $deltaRaw): array {
    $team  = strtoupper(substr((string)$teamRaw, 0, 1));
    $delta = (int)$deltaRaw;
    if ($delta > 1)  $delta = 1;
    if ($delta < -1) $delta = -1;
    if (!in_array($team, ['A','B'], true) || $delta === 0) {
        return ['ok'=>false,'http'=>400,'error'=>'bad_input'];
    }

    try {
        $pdo->beginTransaction();

        $state = repo_match_state($pdo, $matchId, true);
        if (!$state) { $pdo->rollBack(); return ['ok'=>false,'http'=>404,'error'=>'not_found']; }
        if (($state['status'] ?? '') === 'finished') {
            $pdo->rollBack(); return ['ok'=>false,'http'=>409,'error'=>'not_in_progress'];
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

        return [
            'ok'           => true,
            'match_id'     => $matchId,
            'score_a'      => $scoreA,
            'score_b'      => $scoreB,
            'target_score' => $target,
            'status'       => $state['status'],
            'reached'      => $reached,
            'leader'       => $leader,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok'=>false,'http'=>500,'error'=>'score_update_failed','message'=>$e->getMessage()];
    }
}

function svc_match_undo(PDO $pdo, int $matchId): array {
    try {
        $pdo->beginTransaction();

        $state = repo_match_state($pdo, $matchId, true);
        if (!$state) { $pdo->rollBack(); return ['ok'=>false,'http'=>404,'error'=>'not_found']; }
        if (($state['status'] ?? '') === 'finished') {
            $pdo->rollBack(); return ['ok'=>false,'http'=>409,'error'=>'not_in_progress'];
        }

        $evt = repo_match_last_event($pdo, $matchId);
        if (!$evt) { $pdo->rollBack(); return ['ok'=>false,'http'=>404,'error'=>'no_events']; }

        $applied = (int)$evt['applied'];
        $team    = ($evt['team'] === 'A') ? 'A' : 'B';

        if ($applied !== 0) {
            repo_match_decrement_score($pdo, $matchId, $team, $applied);
        }
        repo_match_delete_event($pdo, (int)$evt['id']);

        $state2 = repo_match_state($pdo, $matchId, false);

        $pdo->commit();

        $reached = ((int)$state2['score_a'] >= (int)$state2['target_score'])
                || ((int)$state2['score_b'] >= (int)$state2['target_score']);

        return [
            'ok'           => true,
            'match_id'     => $matchId,
            'score_a'      => (int)$state2['score_a'],
            'score_b'      => (int)$state2['score_b'],
            'target_score' => (int)$state2['target_score'],
            'status'       => $state2['status'],
            'reached'      => $reached,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok'=>false,'http'=>500,'error'=>'undo_failed','message'=>$e->getMessage()];
    }
}

/** Finish (bestehend) belassen; Wrapper für Konsistenz */
function svc_match_finish(PDO $pdo, int $matchId): array {
    $res = finish_match($pdo, $matchId);
    if (($res['ok'] ?? false) === true) return $res;
    return $res + ['http' => (($res['error'] ?? '') === 'not_found' ? 404 : 500)];
}

function finish_match(PDO $pdo, int $matchId): array {
    // Match holen
    $stmt = $pdo->prepare("SELECT id, team_a_id, team_b_id, score_a, score_b, status FROM matches WHERE id=? LIMIT 1");
    $stmt->execute([$matchId]);
    $m = $stmt->fetch();

    if (!$m) return ['ok'=>false,'error'=>'not_found'];
    if (($m['status'] ?? '') === 'finished') return ['ok'=>true,'status'=>'finished'];

    $teamA = (int)$m['team_a_id'];
    $teamB = (int)$m['team_b_id'];
    $scoreA= (int)$m['score_a'];
    $scoreB= (int)$m['score_b'];

    $pdo->beginTransaction();
    try {
        // Stats aktualisieren
        $pdo->prepare("UPDATE teams SET games_played = games_played + 1 WHERE id IN (?, ?)")->execute([$teamA,$teamB]);
        if ($scoreA > $scoreB) {
            $pdo->prepare("UPDATE teams SET wins = wins + 1 WHERE id=?")->execute([$teamA]);
        } elseif ($scoreB > $scoreA) {
            $pdo->prepare("UPDATE teams SET wins = wins + 1 WHERE id=?")->execute([$teamB]);
        }

        // Elo neu berechnen (Ratings sperren)
        $stmt = $pdo->prepare("SELECT id, rating FROM teams WHERE id IN (?, ?) FOR UPDATE");
        $stmt->execute([$teamA,$teamB]);
        $rows = $stmt->fetchAll();
        $ratings = [];
        foreach ($rows as $r) $ratings[(int)$r['id']] = (int)$r['rating'];

        [$raNew, $rbNew] = elo_update($ratings[$teamA] ?? 1500, $ratings[$teamB] ?? 1500, $scoreA, $scoreB);
        $pdo->prepare("UPDATE teams SET rating=? WHERE id=?")->execute([$raNew, $teamA]);
        $pdo->prepare("UPDATE teams SET rating=? WHERE id=?")->execute([$rbNew, $teamB]);

        // Match auf finished setzen
        $pdo->prepare("UPDATE matches SET status='finished', finished_at=NOW() WHERE id=?")->execute([$matchId]);

        $pdo->commit();
        return ['ok'=>true,'status'=>'finished','score_a'=>$scoreA,'score_b'=>$scoreB];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok'=>false,'error'=>'finish_failed','message'=>$e->getMessage()];
    }
}