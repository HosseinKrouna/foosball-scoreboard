<?php
declare(strict_types=1);

/* ---- Teams ---- */
function repo_list_teams(PDO $pdo): array {
    return $pdo->query("SELECT id, name FROM teams ORDER BY name ASC")->fetchAll();
}
function repo_teams_exist(PDO $pdo, int $a, int $b): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) c FROM teams WHERE id IN (?, ?)");
    $stmt->execute([$a, $b]);
    return (int)$stmt->fetch()['c'] === 2;
}

/* ---- Matches (reads) ---- */
function repo_match_full(PDO $pdo, int $id): ?array {
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
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function repo_match_state(PDO $pdo, int $id, bool $forUpdate = false): ?array {
    $sql = "SELECT score_a, score_b, target_score, status FROM matches WHERE id=? LIMIT 1";
    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}


/* ---- Matches (writes) ---- */
function repo_match_insert(PDO $pdo, string $mode, int $teamA, int $teamB, int $target, ?string $notes): int {
    $stmt = $pdo->prepare("
        INSERT INTO matches (mode, team_a_id, team_b_id, score_a, score_b, target_score, notes, status)
        VALUES (?, ?, ?, 0, 0, ?, ?, 'in_progress')
    ");
    $stmt->execute([$mode, $teamA, $teamB, $target, $notes !== '' ? $notes : null]);
    return (int)$pdo->lastInsertId();
}

function repo_match_update_score(PDO $pdo, int $id, string $team, int $newScore): void {
    if ($team === 'A') {
        $pdo->prepare("UPDATE matches SET score_a=? WHERE id=?")->execute([$newScore, $id]);
    } else {
        $pdo->prepare("UPDATE matches SET score_b=? WHERE id=?")->execute([$newScore, $id]);
    }
}

function repo_match_insert_event(PDO $pdo, int $id, string $team, int $delta, int $applied): void {
    $pdo->prepare("INSERT INTO match_events (match_id, team, delta, applied) VALUES (?,?,?,?)")
        ->execute([$id, $team, $delta, $applied]);
}

function repo_match_last_event(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT id, team, applied FROM match_events WHERE match_id=? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function repo_match_delete_event(PDO $pdo, int $eventId): void {
    $pdo->prepare("DELETE FROM match_events WHERE id=?")->execute([$eventId]);
}

/* gezielte Dekrement-Updates (Undo), in SQL gekapselt */
function repo_match_decrement_score(PDO $pdo, int $id, string $team, int $amount): void {
    if ($team === 'A') {
        $pdo->prepare("
            UPDATE matches
               SET score_a = GREATEST(0, LEAST(target_score, score_a - ?))
             WHERE id = ?
        ")->execute([$amount, $id]);
    } else {
        $pdo->prepare("
            UPDATE matches
               SET score_b = GREATEST(0, LEAST(target_score, score_b - ?))
             WHERE id = ?
        ")->execute([$amount, $id]);
    }
}