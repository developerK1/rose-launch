<?php
require_once 'session.php';

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_input(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify($token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)($token ?? ''));
}

function csrf_require_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die('Method not allowed.');
    }

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        die('Invalid CSRF token.');
    }
}
