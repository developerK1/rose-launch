<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../app/governance/roles.php';
require_once __DIR__ . '/../app/governance/security_helper.php';

class Auth {
    private PDO $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function login(string $whatsapp, string $password): bool {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE whatsapp_number = :whatsapp LIMIT 1");
        $stmt->execute(['whatsapp' => $whatsapp]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        if (!empty($user['locked_until']) && strtotime((string)$user['locked_until']) > time()) {
            return false;
        }

        if (in_array((string)($user['status'] ?? 'active'), ['inactive', 'suspended', 'archived'], true)) {
            return false;
        }

        if (!password_verify($password, (string)$user['password_hash'])) {
            pick_apply_login_failure($this->conn, (int)$user['id'], (int)($user['failed_login_attempts'] ?? 0));
            return false;
        }

        pick_reset_login_failures($this->conn, (int)$user['id']);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['role_id'] = (int)($user['role_id'] ?? 1);
        $_SESSION['account_state'] = (string)($user['account_state'] ?? 'pending_verification');

        $stmt = $this->conn->prepare("UPDATE users SET last_login = NOW(), last_activity_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => (int)$user['id']]);

        return true;
    }

    public static function check(): bool {
        return isset($_SESSION['user_id']);
    }

    public static function user(): ?int {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function role(): ?int {
        return isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : null;
    }

    public static function logout(): void {
        session_unset();
        session_destroy();
        header("Location: /auth/login.php");
        exit;
    }
}
