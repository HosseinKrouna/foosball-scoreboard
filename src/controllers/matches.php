<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/http.php';

function ctrl_matches_index(): string {
    $title = 'Matches';
    $pdo = db();

    $teamId = isset($_GET['team_id']) ? max(0, (int)$_GET['team_id']) : 0;
    $status = $_GET['status'] ?? 'all';
    $status = in_array($status, ['all','in_progress','finished'], true) ? $status : 'all';
    $from = $_GET['from'] ?? '';
    $to   = $_GET['to']   ?? '';

    $fromValid = is_ymd($from);
    $toValid   = is_ymd($to);

    $page      = max(1, (int)($_GET['page'] ?? 1));
    $pageSize  = 20;
    $limitPlus = $pageSize + 1;
    $offset    = ($page - 1) * $pageSize;

    $teams = $pdo->query("SELECT id, name FROM teams ORDER BY name ASC")->fetchAll();

    $sql = "
        SELECT m.id, m.played_at, m.status, m.score_a, m.score_b,
               a.name AS team_a, b.name AS team_b
        FROM matches m
        JOIN teams a ON a.id = m.team_a_id
        JOIN teams b ON b.id = m.team_b_id
    ";
    $where = []; $args = [];
    if ($teamId > 0) { $where[]="(m.team_a_id = ? OR m.team_b_id = ?)"; $args[]=$teamId; $args[]=$teamId; }
    if ($status !== 'all') { $where[]="m.status = ?"; $args[]=$status; }
    if ($fromValid) { $where[]="m.played_at >= ?"; $args[]=$from.' 00:00:00'; }
    if ($toValid)   { $end = date('Y-m-d', strtotime($to.' +1 day')); $where[]="m.played_at < ?"; $args[]=$end.' 00:00:00'; }

    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY m.id DESC LIMIT $limitPlus OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    $matches = $stmt->fetchAll();

    $hasNext = false;
    if (count($matches) > $pageSize) { $hasNext = true; array_pop($matches); }
    $hasPrev = $page > 1;

    $selectedTeamId = $teamId;
    $selectedStatus = $status;
    $selectedFrom   = $fromValid ? $from : '';
    $selectedTo     = $toValid   ? $to   : '';

    return render('matches_index.php', compact(
        'title','teams','matches','hasNext','hasPrev',
        'selectedTeamId','selectedStatus','selectedFrom','selectedTo'
    ));
}