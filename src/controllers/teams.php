<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';

function ctrl_teams_index(): string {
    $title = 'Teams';
    $pdo = db();
    $teams = $pdo->query("
        SELECT id, name, rating, wins, games_played, created_at
        FROM teams
        ORDER BY name ASC
    ")->fetchAll();

    return render('teams_index.php', compact('title','teams'));
}