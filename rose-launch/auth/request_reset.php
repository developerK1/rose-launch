<?php
require_once '../config/database.php';
require_once '../app/governance/security_helper.php';
require_once '../app/governance/notification_helper.php';

$db = new Database();
$conn = $db->connect();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    if ($identifier === '') {
        $error = 'Enter your email or WhatsApp number.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = :identifier OR whatsapp_number = :identifier LIMIT 1");
        $stmt->execute(['identifier' => $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = 'Account not found.';
        } else {
            $reset = pick_store_password_reset($conn, (int)$user['id']);
            $resetUrl = sprintf('%s/auth/reset_password.php?token=%s', rtrim((string)($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '/'), rawurlencode($reset['token']));

            if (!empty($user['email'])) {
                @mail($user['email'], 'PickMzansi password reset', "Use this link to reset your password: {$resetUrl}\nThis link expires in 15 minutes.");
            }

            pick_notify_user(
                $conn,
                (int)$user['id'],
                'password_reset_requested',
                'Password reset requested',
                'A password reset link was generated for your account. Use it within 15 minutes.',
                'user',
                (int)$user['id'],
                2
            );

            $message = 'Password reset link generated. Use the link below within 15 minutes:';
            $generatedLink = $resetUrl;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Reset Password</title></head>
<body>
<h2>Reset Password</h2>
<?php if ($error !== ''): ?><p style="color:red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($message !== ''): ?>
    <p><?= htmlspecialchars($message) ?></p>
    <p><a href="<?= htmlspecialchars($generatedLink ?? '#') ?>">Open reset link</a></p>
<?php endif; ?>
<form method="POST">
    <input name="identifier" placeholder="Email or WhatsApp number" required>
    <button type="submit">Generate reset link</button>
</form>
</body>
</html>
