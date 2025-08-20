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
        // Platzhalter-Daten
        $teams = [
            ['rank'=>1,'name'=>'Alex','rating'=>1500,'wins'=>0,'games'=>0],
            ['rank'=>2,'name'=>'Ben','rating'=>1500,'wins'=>0,'games'=>0],
            ['rank'=>3,'name'=>'Chris','rating'=>1500,'wins'=>0,'games'=>0],
            ['rank'=>4,'name'=>'Dana','rating'=>1500,'wins'=>0,'games'=>0],
        ];
        ob_start();
        include __DIR__ . '/../views/leaderboard.php';
        return (string)ob_get_clean();
    }

    http_response_code(404);
    return 'Not found';
}