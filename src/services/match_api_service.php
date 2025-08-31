<?php
declare(strict_types=1);

require_once __DIR__ . '/../elo.php';
require_once __DIR__ . '/../repo.php';
require_once __DIR__ . '/../domain/match_domain.php';

/* ---------- GET /api/match/{id} ---------- */
function svc_match_get_state(PDO $pdo, int $matchId): array {
    $row = repo_match_state($pdo, $matchId, false);
    if (!$row) return ['ok'=>false,'http'=>404,'error'=>'not_found'];
    return ['ok'=>true] + $row + ['reached'=> md_reached((int)$row['score_a'], (int)$row['score_b'], (int)$row['target_score'])];
}

/* interne Mini-Helfer */
function ms_state_lock(PDO $pdo, int $id): ?array {
    return repo_match_state($pdo, $id, true);
}
function ms_apply_delta(PDO $pdo, int $matchId, array $state, string $team, int $delta): array {
    $target = (int)$state['target_score'];
    $a = (int)$state['score_a'];
    $b = (int)$state['score_b'];

    if ($team === 'A') {
        $newA = max(0, min($target, $a + $delta));
        $applied = $newA - $a;
        if ($applied !== 0) {
            repo_match_update_score($pdo, $matchId, 'A', $newA);
            repo_match_insert_event($pdo, $matchId, 'A', $delta, $applied);
        }
        return [$newA, $b];
    }
    $newB = max(0, min($target, $b + $delta));
    $applied = $newB - $b;
    if ($applied !== 0) {
        repo_match_update_score($pdo, $matchId, 'B', $newB);
        repo_match_insert_event($pdo, $matchId, 'B', $delta, $applied);
    }
    return [$a, $newB];
}

/* ---------- POST /api/match/{id}/score ---------- */
function svc_match_change_score(PDO $pdo, int $matchId, $teamRaw, $deltaRaw): array {
    $team  = md_team((string)$teamRaw);
    $delta = md_delta($deltaRaw);
    if ($team === '' || $delta === 0) return ['ok'=>false,'http'=>400,'error'=>'bad_input'];

    try {
        $pdo->beginTransaction();

        $state = ms_state_lock($pdo, $matchId);
        if (!$state)                  { $pdo->rollBack(); return ['ok'=>false,'http'=>404,'error'=>'not_found']; }
        if (($state['status'] ?? '') === 'finished') {
            $pdo->rollBack(); return ['ok'=>false,'http'=>409,'error'=>'not_in_progress'];
        }

        [$scoreA, $scoreB] = ms_apply_delta($pdo, $matchId, $state, $team, $delta);
        $target = (int)$state['target_score'];

        $pdo->commit();

        return [
            'ok'=>true,
            'match_id'=>$matchId,
            'score_a'=>$scoreA,
            'score_b'=>$scoreB,
            'target_score'=>$target,
            'status'=>$state['status'],
            'reached'=> md_reached($scoreA, $scoreB, $target),
            'leader'=>  md_leader($scoreA, $scoreB),
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok'=>false,'http'=>500,'error'=>'score_update_failed','message'=>$e->getMessage()];
    }
}

/* ---------- POST /api/match/{id}/undo ---------- */
function svc_match_undo(PDO $pdo, int $matchId): array {
    try {
        $pdo->beginTransaction();

        $state = ms_state_lock($pdo, $matchId);
        if (!$state)                  { $pdo->rollBack(); return ['ok'=>false,'http'=>404,'error'=>'not_found']; }
        if (($state['status'] ?? '') === 'finished') {
            $pdo->rollBack(); return ['ok'=>false,'http'=>409,'error'=>'not_in_progress'];
        }

        $evt = repo_match_last_event($pdo, $matchId);
        if (!$evt) { $pdo->rollBack(); return ['ok'=>false,'http'=>404,'error'=>'no_events']; }

        $applied = (int)$evt['applied'];
        $team    = ($evt['team'] === 'A') ? 'A' : 'B';

        if ($applied !== 0) repo_match_decrement_score($pdo, $matchId, $team, $applied);
        repo_match_delete_event($pdo, (int)$evt['id']);

        $state2 = repo_match_state($pdo, $matchId, false);
        $pdo->commit();

        $a = (int)$state2['score_a']; $b = (int)$state2['score_b']; $t = (int)$state2['target_score'];

        return [
            'ok'=>true,
            'match_id'=>$matchId,
            'score_a'=>$a,
            'score_b'=>$b,
            'target_score'=>$t,
            'status'=>$state2['status'],
            'reached'=> md_reached($a, $b, $t),
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok'=>false,'http'=>500,'error'=>'undo_failed','message'=>$e->getMessage()];
    }
}

/* ---------- POST /api/match/{id}/finish ---------- */
function finish_match(PDO $pdo, int $matchId): array {
    $stmt = $pdo->prepare("SELECT id, team_a_id, team_b_id, score_a, score_b, status FROM matches WHERE id=? LIMIT 1");
    $stmt->execute([$matchId]);
    $m = $stmt->fetch();
    if (!$m) return ['ok'=>false,'error'=>'not_found'];
    if (($m['status'] ?? '') === 'finished') return ['ok'=>true,'status'=>'finished'];

    $teamA = (int)$m['team_a_id']; $teamB = (int)$m['team_b_id'];
    $scoreA= (int)$m['score_a'];   $scoreB= (int)$m['score_b'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE teams SET games_played = games_played + 1 WHERE id IN (?, ?)")->execute([$teamA,$teamB]);
        if ($scoreA > $scoreB) { $pdo->prepare("UPDATE teams SET wins = wins + 1 WHERE id=?")->execute([$teamA]); }
        elseif ($scoreB > $scoreA) { $pdo->prepare("UPDATE teams SET wins = wins + 1 WHERE id=?")->execute([$teamB]); }

        $stmt = $pdo->prepare("SELECT id, rating FROM teams WHERE id IN (?, ?) FOR UPDATE");
        $stmt->execute([$teamA,$teamB]);
        $rows = $stmt->fetchAll();
        $ratings=[]; foreach($rows as $r) $ratings[(int)$r['id']] = (int)$r['rating'];

        [$raNew,$rbNew] = elo_update($ratings[$teamA]??1500, $ratings[$teamB]??1500, $scoreA, $scoreB);
        $pdo->prepare("UPDATE teams SET rating=? WHERE id=?")->execute([$raNew,$teamA]);
        $pdo->prepare("UPDATE teams SET rating=? WHERE id=?")->execute([$rbNew,$teamB]);

        $pdo->prepare("UPDATE matches SET status='finished', finished_at=NOW() WHERE id=?")->execute([$matchId]);
        $pdo->commit();

        return ['ok'=>true,'status'=>'finished','score_a'=>$scoreA,'score_b'=>$scoreB];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok'=>false,'error'=>'finish_failed','message'=>$e->getMessage()];
    }
}
function svc_match_finish(PDO $pdo, int $matchId): array {
    $res = finish_match($pdo, $matchId);
    if (($res['ok'] ?? false) === true) return $res;
    return $res + ['http' => (($res['error'] ?? '') === 'not_found' ? 404 : 500)];
}