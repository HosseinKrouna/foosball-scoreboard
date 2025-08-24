<?php
declare(strict_types=1);

require_once __DIR__ . '/../elo.php';

function finish_match(PDO $pdo, int $matchId): array {
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
        $pdo->prepare("UPDATE teams SET games_played = games_played + 1 WHERE id IN (?, ?)")->execute([$teamA,$teamB]);
        if ($scoreA > $scoreB) {
            $pdo->prepare("UPDATE teams SET wins = wins + 1 WHERE id=?")->execute([$teamA]);
        } elseif ($scoreB > $scoreA) {
            $pdo->prepare("UPDATE teams SET wins = wins + 1 WHERE id=?")->execute([$teamB]);
        }

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