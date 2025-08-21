<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/elo.php';


function route(string $method, string $path): string {
    // Home
    if ($method === 'GET' && $path === '/') {
        $title = 'Foosball Scoreboard';
        ob_start();
        include __DIR__ . '/../views/home.php';
        return (string)ob_get_clean();
    }

    // Leaderboard (DB)
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

    // New Match (Form)
    if ($method === 'GET' && $path === '/match/new') {
        $title = 'New Match';

        $pdo = db();
        $teams = $pdo->query("SELECT id, name FROM teams ORDER BY name ASC")->fetchAll();

        // Defaults für View (damit keine Notices auftreten)
        $errors = [];
        $old    = [];

        ob_start();
        include __DIR__ . '/../views/match_new.php';
        return (string)ob_get_clean();
    }

    // Create Match (POST)
   if ($method === 'POST' && $path === '/match') {
    $pdo = db();

    // Eingaben
    $mode   = $_POST['mode']      ?? '';
    $teamA  = isset($_POST['team_a_id']) ? (int)$_POST['team_a_id'] : 0;
    $teamB  = isset($_POST['team_b_id']) ? (int)$_POST['team_b_id'] : 0;
    $scoreA = isset($_POST['score_a'])   ? (int)$_POST['score_a']   : -1;
    $scoreB = isset($_POST['score_b'])   ? (int)$_POST['score_b']   : -1;
    $notes  = trim((string)($_POST['notes'] ?? ''));

    $errors = [];
    $old = [
        'mode'      => $mode,
        'team_a_id' => $teamA,
        'team_b_id' => $teamB,
        'score_a'   => $scoreA >= 0 ? $scoreA : '',
        'score_b'   => $scoreB >= 0 ? $scoreB : '',
        'notes'     => $notes,
    ];

    // Validierung
    if (!in_array($mode, ['1v1','2v2'], true)) $errors[] = 'Invalid mode.';
    if ($teamA <= 0 || $teamB <= 0)            $errors[] = 'Both teams are required.';
    if ($teamA === $teamB)                     $errors[] = 'Team A and Team B must be different.';
    if ($scoreA < 0)                           $errors[] = 'Score A must be a non-negative integer.';
    if ($scoreB < 0)                           $errors[] = 'Score B must be a non-negative integer.';

    // Teams existieren?
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM teams WHERE id IN (?, ?)");
        $stmt->execute([$teamA, $teamB]);
        $row = $stmt->fetch();
        if ((int)$row['c'] !== 2) $errors[] = 'Unknown team selected.';
    }

    if (!empty($errors)) {
        $title = 'New Match';
        $teams = $pdo->query("SELECT id, name FROM teams ORDER BY name ASC")->fetchAll();
        ob_start();
        include __DIR__ . '/../views/match_new.php';
        return (string)ob_get_clean();
    }

    // Speichern + Stats + Elo in einer Transaktion
    $pdo->beginTransaction();
    try {
        // Match speichern
        $stmt = $pdo->prepare("
            INSERT INTO matches (mode, team_a_id, team_b_id, score_a, score_b, notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$mode, $teamA, $teamB, $scoreA, $scoreB, $notes !== '' ? $notes : null]);

        // Spiele hochzählen
        $stmt = $pdo->prepare("UPDATE teams SET games_played = games_played + 1 WHERE id IN (?, ?)");
        $stmt->execute([$teamA, $teamB]);

        // Siege hochzählen (kein Sieger bei Draw)
        if ($scoreA > $scoreB) {
            $stmt = $pdo->prepare("UPDATE teams SET wins = wins + 1 WHERE id = ?");
            $stmt->execute([$teamA]);
        } elseif ($scoreB > $scoreA) {
            $stmt = $pdo->prepare("UPDATE teams SET wins = wins + 1 WHERE id = ?");
            $stmt->execute([$teamB]);
        }

        // Aktuelle Ratings sperren & lesen
        $stmt = $pdo->prepare("SELECT id, rating FROM teams WHERE id IN (?, ?) FOR UPDATE");
        $stmt->execute([$teamA, $teamB]);
        $rows = $stmt->fetchAll();
        $ratings = [];
        foreach ($rows as $r) $ratings[(int)$r['id']] = (int)$r['rating'];
        $ra = $ratings[$teamA] ?? 1500;
        $rb = $ratings[$teamB] ?? 1500;

        // Elo berechnen & updaten
        [$raNew, $rbNew] = elo_update($ra, $rb, $scoreA, $scoreB);
        $stmt = $pdo->prepare("UPDATE teams SET rating = ? WHERE id = ?");
        $stmt->execute([$raNew, $teamA]);
        $stmt->execute([$rbNew, $teamB]);

        $pdo->commit();
        header('Location: /leaderboard');
        exit;
    } catch (Throwable $e) {
        $pdo->rollBack();
        $errors[] = 'Saving failed: ' . $e->getMessage();
        $title = 'New Match';
        $teams = $pdo->query("SELECT id, name FROM teams ORDER BY name ASC")->fetchAll();
        ob_start();
        include __DIR__ . '/../views/match_new.php';
        return (string)ob_get_clean();
    }
}

    // Fallback 404
    http_response_code(404);
    return 'Not found';
}