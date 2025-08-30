<?php
declare(strict_types=1);

/** Normalisiert Form-Input für Match-Create */
function normalize_match_create_input(array $in): array {
    return [
        'mode'         => isset($in['mode']) ? (string)$in['mode'] : '',
        'team_a_id'    => isset($in['team_a_id']) ? (int)$in['team_a_id'] : 0,
        'team_b_id'    => isset($in['team_b_id']) ? (int)$in['team_b_id'] : 0,
        'target_score' => isset($in['target_score']) ? (int)$in['target_score'] : 10,
        'notes'        => trim((string)($in['notes'] ?? '')),
    ];
}

/** Basis-Validierung ohne DB (Mode/IDs/Range) */
function validate_match_create_basic(array $d): array {
    $errors = [];
    if (!in_array($d['mode'], ['1v1','2v2'], true)) $errors[] = 'Invalid mode.';
    if ($d['team_a_id'] <= 0 || $d['team_b_id'] <= 0) $errors[] = 'Both teams are required.';
    if ($d['team_a_id'] === $d['team_b_id']) $errors[] = 'Team A and Team B must be different.';
    if ($d['target_score'] < 1 || $d['target_score'] > 50) $errors[] = 'Target score must be between 1 and 50.';
    return $errors;
}