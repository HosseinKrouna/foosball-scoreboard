<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    // Bestehende Datei (CSS/JS/Bilder) direkt ausliefern
    return false;
}
// Alles andere durch index.php routen
require __DIR__ . '/index.php';