<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';

function ctrl_home_index(): string {
    $title = 'Foosball Scoreboard';
    $pdo = db();

    $stats = [
        'teams'    => (int)$pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn(),
        'matches'  => (int)$pdo->query("SELECT COUNT(*) FROM matches")->fetchColumn(),
        'finished' => (int)$pdo->query("SELECT COUNT(*) FROM matches WHERE status='finished'")->fetchColumn(),
    ];
    $recent = $pdo->query("
        SELECT m.id, m.played_at, m.status, m.score_a, m.score_b,
               a.name AS team_a, b.name AS team_b
        FROM matches m
        JOIN teams a ON a.id = m.team_a_id
        JOIN teams b ON b.id = m.team_b_id
        ORDER BY m.id DESC
        LIMIT 5
    ")->fetchAll();

    return render('home.php', compact('title','stats','recent'));
}