<?php
declare(strict_types=1);

/** View rendern (View-Datei enthält am Ende das layout.php-include) */
function render(string $view, array $vars = []): string {
    extract($vars, EXTR_SKIP);
    ob_start();
    include __DIR__ . '/../views/' . ltrim($view, '/');
    return (string)ob_get_clean();
}

/** JSON-Antwort + Exit */
function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/** YYYY-MM-DD prüfen */
function is_ymd(string $d): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
    [$Y,$m,$day] = array_map('intval', explode('-', $d));
    return checkdate($m, $day, $Y);
}

/** CSRF: Form-Token prüfen */
function csrf_ok_form(): bool {
    return function_exists('csrf_validate') ? csrf_validate($_POST['_csrf'] ?? null) : true;
}

/** CSRF: AJAX-Header prüfen */
function csrf_ok_header(): bool {
    return function_exists('csrf_validate') ? csrf_validate($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null) : true;
}