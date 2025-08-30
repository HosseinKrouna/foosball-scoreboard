<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';

function ctrl_leaderboard_index(): string {
    $title = 'Leaderboard';
    $pdo = db();
    $teams = $pdo->query("
        SELECT id, name, rating, wins, games_played
        FROM teams
        ORDER BY rating DESC, wins DESC, games_played DESC
        LIMIT 50
    ")->fetchAll();

    return render('leaderboard.php', compact('title','teams'));
}