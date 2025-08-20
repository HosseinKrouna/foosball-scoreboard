<?php
declare(strict_types=1);

function route(string $method, string $path): string {
    if ($method === 'GET' && $path === '/') {
        $title = 'Foosball Scoreboard';
        ob_start();
        include __DIR__ . '/../views/home.php';
        return (string)ob_get_clean();
    }

    http_response_code(404);
    return 'Not found';
}