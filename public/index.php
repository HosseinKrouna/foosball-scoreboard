<?php
declare(strict_types=1);

require __DIR__ . '/../src/security.php';
csrf_start();
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../routes/web.php';


$reqPath   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
if ($scriptDir !== '' && $scriptDir !== '/') {
    if (strpos($reqPath, $scriptDir) === 0) {
        $reqPath = substr($reqPath, strlen($scriptDir)) ?: '/';
    }
}
$path   = $reqPath;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

echo route($method, $path);