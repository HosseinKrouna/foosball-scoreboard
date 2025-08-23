<?php
declare(strict_types=1);

function csrf_start(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

function csrf_token(): string {
    csrf_start();
    return $_SESSION['csrf'];
}

function csrf_validate(?string $token): bool {
    csrf_start();
    return is_string($token) && hash_equals($_SESSION['csrf'] ?? '', $token);
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' .
           htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}