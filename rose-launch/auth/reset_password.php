<?php
require_once '../config/database.php';
require_once '../app/governance/security_helper.php';

$db = new Database();
$conn = $db->connect();

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    if ($token === '' || strlen($password) < 6) {
        $error = 'Invalid token or password too short.';
    } else {
        if (pick_consume_password_reset($conn, $token, $password)) {
            $message = 'Password updated successfully.';
        } else {
            $error = 'Reset token expired or invalid.';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Choose New Password</title></head>
<body>
<h2>Set New Password</h2>
<?php if ($error !== ''): ?><p style="color:red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($message !== ''): ?><p><?= htmlspecialchars($message) ?></p><p><a href="login.php">Login</a></p><?php else: ?>
<form method="POST">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <input name="password" type="password" placeholder="New password" required>
    <button type="submit">Update password</button>
</form>
<?php endif; ?>
</body>
</html>
