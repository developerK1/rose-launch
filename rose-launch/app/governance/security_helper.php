<?php
function pick_generate_token(int $bytes = 24): string {
    return bin2hex(random_bytes($bytes));
}

function pick_password_reset_expires_at(int $minutes = 15): string {
    return date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"));
}

function pick_one_time_token_expires_at(int $minutes): string {
    return date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"));
}

function pick_lockout_until(int $minutes = 15): string {
    return date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"));
}

function pick_apply_login_failure(PDO $conn, int $userId, int $attempts): void {
    $attempts++;
    if ($attempts >= 5) {
        $stmt = $conn->prepare("
            UPDATE users
            SET failed_login_attempts = 0,
                locked_until = :locked_until
            WHERE id = :id
        ");
        $stmt->execute([
            'locked_until' => pick_lockout_until(15),
            'id' => $userId,
        ]);
        return;
    }

    $stmt = $conn->prepare("
        UPDATE users
        SET failed_login_attempts = :attempts
        WHERE id = :id
    ");
    $stmt->execute([
        'attempts' => $attempts,
        'id' => $userId,
    ]);
}

function pick_reset_login_failures(PDO $conn, int $userId): void {
    $stmt = $conn->prepare("
        UPDATE users
        SET failed_login_attempts = 0,
            locked_until = NULL
        WHERE id = :id
    ");
    $stmt->execute(['id' => $userId]);
}

function pick_store_password_reset(PDO $conn, int $userId): array {
    $token = pick_generate_token(24);
    $expiresAt = pick_password_reset_expires_at(15);

    $stmt = $conn->prepare("
        UPDATE users
        SET password_reset_token = :token,
            password_reset_expires_at = :expires_at
        WHERE id = :id
    ");
    $stmt->execute([
        'token' => $token,
        'expires_at' => $expiresAt,
        'id' => $userId,
    ]);

    return ['token' => $token, 'expires_at' => $expiresAt];
}

function pick_consume_password_reset(PDO $conn, string $token, string $newPassword): bool {
    $stmt = $conn->prepare("
        SELECT id
        FROM users
        WHERE password_reset_token = :token
          AND password_reset_expires_at IS NOT NULL
          AND password_reset_expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return false;
    }

    $stmt = $conn->prepare("
        UPDATE users
        SET password_hash = :password_hash,
            password_reset_token = NULL,
            password_reset_expires_at = NULL,
            failed_login_attempts = 0,
            locked_until = NULL
        WHERE id = :id
    ");
    $stmt->execute([
        'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
        'id' => (int)$user['id'],
    ]);

    return true;
}
