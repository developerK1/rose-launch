<?php
if (!defined('ROLE_LANDLORD')) {
    define('ROLE_LANDLORD', 1);
}
if (!defined('ROLE_ADMIN')) {
    define('ROLE_ADMIN', 2);
}
if (!defined('ROLE_SUPER_ADMIN')) {
    define('ROLE_SUPER_ADMIN', 3);
}
if (!defined('ROLE_SUPPORT')) {
    define('ROLE_SUPPORT', 4);
}

function pick_role_name(int $roleId): string {
    return match ($roleId) {
        ROLE_LANDLORD => 'landlord',
        ROLE_ADMIN => 'admin',
        ROLE_SUPER_ADMIN => 'super_admin',
        ROLE_SUPPORT => 'support',
        default => 'unknown',
    };
}

function pick_role_permissions(int $roleId): array {
    return match ($roleId) {
        ROLE_SUPER_ADMIN => [
            'view_all', 'manage_users', 'manage_listings', 'manage_reports', 'manage_support',
            'manage_incidents', 'manage_logs', 'manage_roles', 'suspend_users', 'delete_soft'
        ],
        ROLE_ADMIN => [
            'view_all', 'manage_users', 'manage_listings', 'manage_reports', 'manage_support',
            'manage_incidents', 'manage_logs', 'suspend_users'
        ],
        ROLE_SUPPORT => [
            'view_all', 'manage_support', 'view_users', 'view_listings', 'create_notes'
        ],
        ROLE_LANDLORD => [
            'manage_own_profile', 'manage_own_listings', 'view_notifications', 'open_support'
        ],
        default => [],
    };
}

function pick_role_can(int $roleId, string $permission): bool {
    return in_array($permission, pick_role_permissions($roleId), true);
}
