<?php
declare(strict_types=1);

function route(string $method, string $path): string {
    if ($method === 'GET' && $path === '/') {
        $title = 'Foosball Scoreboard';
        ob_start();
        include __DIR__ . '/../views/home.php';
        return (string)ob_get_clean();
    }

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

    if ($method === 'GET' && $path === '/match/new') {
        $title = 'New Match';

        $pdo = db();
        $teams = $pdo->query("SELECT id, name FROM teams ORDER BY name ASC")->fetchAll();

        ob_start();
        include __DIR__ . '/../views/match_new.php';
        return (string)ob_get_clean();
    }

    http_response_code(404);
    return 'Not found';
}