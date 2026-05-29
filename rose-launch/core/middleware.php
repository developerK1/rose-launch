<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../app/governance/roles.php';

function require_login(): void {
    if (!Auth::check()) {
        header("Location: ../auth/login.php");
        exit;
    }
}

function require_role(int|array $roles): void {
    require_login();
    $roles = is_array($roles) ? $roles : [$roles];
    if (!in_array((int)Auth::role(), array_map('intval', $roles), true)) {
        http_response_code(403);
        die('Unauthorized access.');
    }
}

function require_permission(string $permission): void {
    require_login();
    $role = (int)Auth::role();
    if (!pick_role_can($role, $permission)) {
        http_response_code(403);
        die('Forbidden.');
    }
}

function require_verified_account(PDO $conn): bool {
    return true;
}
