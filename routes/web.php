<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/elo.php'; // Elo-Helper

function route(string $method, string $path): string {
    // ------------------ GET / (Home) ------------------
    if ($method === 'GET' && $path === '/') {
        $title = 'Foosball Scoreboard';
        ob_start();
        include __DIR__ . '/../views/home.php';
        return (string)ob_get_clean();
    }

    // ------------------ GET /leaderboard ------------------
    if ($method === 'GET' && $path === '/leaderboard') {
        $title = 'Leaderboard';
        $pdo = db();
        $stmt = $pdo->query("
            SELECT id, name, rating, wins, games_played
            FROM teams
            ORDER BY rating DESC, wins DESC, games_played DESC
            LIMIT 50
        ");
        $teams = $stmt->fetchAll();

        ob_start();
        include __DIR__ . '/../views/leaderboard.php';
        return (string)ob_get_clean();
    }

    // ------------------ GET /match/new (Form ohne Scores) ------------------
    if ($method === 'GET' && $path === '/match/new') {
        $title = 'New Match';
        $pdo = db();
        $teams = $pdo->query("SELECT id, name FROM teams ORDER BY name ASC")->fetchAll();
        $errors = [];
        $old    = [];

        ob_start();
        include __DIR__ . '/../views/match_new.php';
        return (string)ob_get_clean();
    }

    // ------------------ POST /match (nur anlegen, 0:0) ------------------
    if ($method === 'POST' && $path === '/match') {
        $pdo = db();

        $mode  = $_POST['mode'] ?? '';
        $teamA = isset($_POST['team_a_id']) ? (int)$_POST['team_a_id'] : 0;
        $teamB = isset($_POST['team_b_id']) ? (int)$_POST['team_b_id'] : 0;
        $notes = trim((string)($_POST['notes'] ?? ''));

        $errors = [];
        if (!in_array($mode, ['1v1','2v2'], true)) $errors[] = 'Invalid mode.';
        if ($teamA <= 0 || $teamB <= 0)            $errors[] = 'Both teams are required.';
        if ($teamA === $teamB)                     $errors[] = 'Team A and Team B must be different.';

        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM teams WHERE id IN (?, ?)");
            $stmt->execute([$teamA, $teamB]);
            $row = $stmt->fetch();
            if ((int)$row['c'] !== 2) $errors[] = 'Unknown team selected.';
        }

        if (!empty($errors)) {
            $title = 'New Match';
            $teams = $pdo->query("SELECT id, name FROM teams ORDER BY name ASC")->fetchAll();
            $old = ['mode'=>$mode,'team_a_id'=>$teamA,'team_b_id'=>$teamB,'notes'=>$notes];
            ob_start();
            include __DIR__ . '/../views/match_new.php';
            return (string)ob_get_clean();
        }

        $stmt = $pdo->prepare("
            INSERT INTO matches (mode, team_a_id, team_b_id, score_a, score_b, notes, status)
            VALUES (?, ?, ?, 0, 0, ?, 'in_progress')
        ");
        $stmt->execute([$mode, $teamA, $teamB, $notes !== '' ? $notes : null]);

        $matchId = (int)$pdo->lastInsertId();
        header('Location: /match/' . $matchId . '?created=1');
        exit;
    }

    // ------------------ GET /match/{id} (TV/Scoreboard) ------------------
    if ($method === 'GET' && preg_match('#^/match/(\d+)$#', $path, $m)) {
        $matchId = (int)$m[1];
        $pdo = db();

        $stmt = $pdo->prepare("
            SELECT m.id, m.played_at, m.mode,
                   m.team_a_id, m.team_b_id, m.score_a, m.score_b, m.notes,
                   m.status, m.finished_at,
                   a.name AS team_a_name, a.rating AS rating_a,
                   b.name AS team_b_name, b.rating AS rating_b
            FROM matches m
            JOIN teams a ON a.id = m.team_a_id
            JOIN teams b ON b.id = m.team_b_id
            WHERE m.id = ?
            LIMIT 1
        ");
        $stmt->execute([$matchId]);
        $match = $stmt->fetch();
        if (!$match) { http_response_code(404); return 'Match not found'; }

        $isInProgress = (($match['status'] ?? 'in_progress') !== 'finished');

        $title       = 'Match #' . $matchId;
        $created     = isset($_GET['created']) && $_GET['created'] === '1';
        $finishedMsg = isset($_GET['finished']) && $_GET['finished'] === '1';
        $err         = $_GET['err'] ?? null;

        ob_start();
        include __DIR__ . '/../views/match_show.php';
        return (string)ob_get_clean();
    }

    // =====================================================================
    //                           JSON API (AJAX)
    // =====================================================================

    // ----- GET /api/match/{id}  → aktuellen Stand liefern (Polling) -----
    if ($method === 'GET' && preg_match('#^/api/match/(\d+)$#', $path, $m)) {
        $matchId = (int)$m[1];
        $pdo = db();
        $stmt = $pdo->prepare("SELECT id, score_a, score_b, status FROM matches WHERE id = ? LIMIT 1");
        $stmt->execute([$matchId]);
        $row = $stmt->fetch();

        header('Content-Type: application/json; charset=utf-8');
        if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
        echo json_encode(['ok'=>true] + $row);
        exit;
    }

    // ----- POST /api/match/{id}/score  → Score ändern (ohne Reload) -----
    if ($method === 'POST' && preg_match('#^/api/match/(\d+)/score$#', $path, $m)) {
        $matchId = (int)$m[1];
        $pdo = db();

        $team  = strtoupper(substr((string)($_POST['team'] ?? ''), 0, 1)); // 'A'/'B'
        $delta = (int)($_POST['delta'] ?? 0);
        if ($delta > 1)  $delta = 1;
        if ($delta < -1) $delta = -1;

        header('Content-Type: application/json; charset=utf-8');

        if (!in_array($team, ['A','B'], true) || $delta === 0) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'bad_input']);
            exit;
        }

        // Nur wenn in_progress ODER (Alt-Daten) status IS NULL
        $col = ($team === 'A') ? 'score_a' : 'score_b';
        $sql = "UPDATE matches
                SET $col = GREATEST(0, $col + ?)
                WHERE id = ?
                  AND (status = 'in_progress' OR status IS NULL)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$delta, $matchId]);

        if ($stmt->rowCount() === 0) {
            http_response_code(409);
            echo json_encode(['ok'=>false,'error'=>'not_in_progress']);
            exit;
        }

        // neuen Stand zurückgeben
        $stmt = $pdo->prepare("SELECT score_a, score_b, status FROM matches WHERE id = ? LIMIT 1");
        $stmt->execute([$matchId]);
        $row = $stmt->fetch();

        echo json_encode(['ok'=>true,'match_id'=>$matchId] + $row);
        exit;
    }

    // ----- POST /api/match/{id}/finish → abschließen + Elo/Stats -----
    if ($method === 'POST' && preg_match('#^/api/match/(\d+)/finish$#', $path, $m)) {
        $matchId = (int)$m[1];
        $pdo = db();

        header('Content-Type: application/json; charset=utf-8');

        $stmt = $pdo->prepare("SELECT id, team_a_id, team_b_id, score_a, score_b, status FROM matches WHERE id = ? LIMIT 1");
        $stmt->execute([$matchId]);
        $match = $stmt->fetch();
        if (!$match) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

        if (($match['status'] ?? '') === 'finished') {
            echo json_encode(['ok'=>true,'status'=>'finished']); exit;
        }

        $teamA  = (int)$match['team_a_id'];
        $teamB  = (int)$match['team_b_id'];
        $scoreA = (int)$match['score_a'];
        $scoreB = (int)$match['score_b'];

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE teams SET games_played = games_played + 1 WHERE id IN (?, ?)");
            $stmt->execute([$teamA, $teamB]);

            if ($scoreA > $scoreB) {
                $stmt = $pdo->prepare("UPDATE teams SET wins = wins + 1 WHERE id = ?");
                $stmt->execute([$teamA]);
            } elseif ($scoreB > $scoreA) {
                $stmt = $pdo->prepare("UPDATE teams SET wins = wins + 1 WHERE id = ?");
                $stmt->execute([$teamB]);
            }

            $stmt = $pdo->prepare("SELECT id, rating FROM teams WHERE id IN (?, ?) FOR UPDATE");
            $stmt->execute([$teamA, $teamB]);
            $rows = $stmt->fetchAll();
            $ratings = [];
            foreach ($rows as $r) $ratings[(int)$r['id']] = (int)$r['rating'];
            $ra = $ratings[$teamA] ?? 1500;
            $rb = $ratings[$teamB] ?? 1500;
            [$raNew, $rbNew] = elo_update($ra, $rb, $scoreA, $scoreB);

            $stmt = $pdo->prepare("UPDATE teams SET rating = ? WHERE id = ?");
            $stmt->execute([$raNew, $teamA]);
            $stmt->execute([$rbNew, $teamB]);

            $stmt = $pdo->prepare("UPDATE matches SET status='finished', finished_at=NOW() WHERE id = ?");
            $stmt->execute([$matchId]);

            $pdo->commit();
            echo json_encode(['ok'=>true,'status'=>'finished','score_a'=>$scoreA,'score_b'=>$scoreB]);
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['ok'=>false,'error'=>'finish_failed','message'=>$e->getMessage()]);
            exit;
        }
    }

    // ------------------ Fallback 404 ------------------
    http_response_code(404);
    return 'Not found';
}