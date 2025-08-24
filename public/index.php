<?php
declare(strict_types=1);

// require __DIR__ . '/../vendor/autoload.php';

require __DIR__ . '/../src/security.php';
csrf_start(); // Session + CSRF-Token vorbereiten

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../routes/web.php';

$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

echo route($method, $path);