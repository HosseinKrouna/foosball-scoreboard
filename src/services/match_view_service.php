<?php
declare(strict_types=1);

require_once __DIR__ . '/../validation.php';
require_once __DIR__ . '/../repo.php';

/** View-Daten: New Match */
function svc_match_new_form(PDO $pdo): array {
    return ['title'=>'New Match','teams'=>repo_list_teams($pdo),'errors'=>[],'old'=>[]];
}

/** Create (Form POST) */
function svc_match_create(PDO $pdo, array $post): array {
    $d = normalize_match_create_input($post);
    $errors = validate_match_create_basic($d);

    if (empty($errors) && !repo_teams_exist($pdo, $d['team_a_id'], $d['team_b_id'])) {
        $errors[] = 'Unknown team selected.';
    }
    if ($errors) return ['ok'=>false,'errors'=>$errors,'old'=>$d,'teams'=>repo_list_teams($pdo)];

    $id = repo_match_insert($pdo, $d['mode'], $d['team_a_id'], $d['team_b_id'], $d['target_score'], $d['notes']);
    return ['ok'=>true,'match_id'=>$id];
}

/** View-Daten: TV-Show */
function svc_match_show_data(PDO $pdo, int $matchId, array $query): array {
    $match = repo_match_full($pdo, $matchId);
    if (!$match) return ['ok'=>false,'http'=>404,'error'=>'not_found'];

    return [
        'ok'=>true,
        'title'=>'Match #'.$matchId,
        'match'=>$match,
        'isInProgress'=> (($match['status'] ?? 'in_progress') !== 'finished'),
        'created'=>     (isset($query['created'])  && $query['created']  === '1'),
        'finishedMsg'=> (isset($query['finished']) && $query['finished'] === '1'),
        'err'=>         ($query['err'] ?? null),
    ];
}