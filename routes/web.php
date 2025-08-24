<?php
declare(strict_types=1);

/**
 * Routes (thin):
 *  - Delegiert an Controller-Funktionen
 */

require_once __DIR__ . '/../src/http.php';
require_once __DIR__ . '/../src/services/match_service.php';

require_once __DIR__ . '/../src/controllers/home.php';
require_once __DIR__ . '/../src/controllers/leaderboard.php';
require_once __DIR__ . '/../src/controllers/teams.php';
require_once __DIR__ . '/../src/controllers/matches.php';
require_once __DIR__ . '/../src/controllers/match.php';

function route(string $method, string $path): string {

    // HTML
    if ($method==='GET' && $path==='/')               return ctrl_home_index();
    if ($method==='GET' && $path==='/leaderboard')    return ctrl_leaderboard_index();
    if ($method==='GET' && $path==='/teams')          return ctrl_teams_index();
    if ($method==='GET' && $path==='/matches')        return ctrl_matches_index();
    if ($method==='GET' && $path==='/match/new')      return ctrl_match_new();
    if ($method==='POST' && $path==='/match')         return ctrl_match_create();

    if ($method==='GET' && preg_match('#^/match/(\d+)$#',$path,$m))
        return ctrl_match_show((int)$m[1]);

    // API
    if ($method==='GET'  && preg_match('#^/api/match/(\d+)$#',$path,$m))
        ctrl_api_match_get((int)$m[1]);

    if ($method==='POST' && preg_match('#^/api/match/(\d+)/score$#',$path,$m))
        ctrl_api_match_score((int)$m[1]);

    if ($method==='POST' && preg_match('#^/api/match/(\d+)/undo$#',$path,$m))
        ctrl_api_match_undo((int)$m[1]);

    if ($method==='POST' && preg_match('#^/api/match/(\d+)/finish$#',$path,$m))
        ctrl_api_match_finish((int)$m[1]);

    http_response_code(404);
    return 'Not found';
}