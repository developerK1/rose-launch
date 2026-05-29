<?php
require_once '../core/session.php';
require_once '../core/auth.php';

$auth = new Auth();
session_unset();
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($auth->login($whatsapp, $password)) {
        $role = (int)($_SESSION['role_id'] ?? 0);
        if ($role === ROLE_SUPER_ADMIN || $role === ROLE_ADMIN || $role === ROLE_SUPPORT) {
            header("Location: ../admin/index.php");
        } else {
            header("Location: ../landlord/index.php");
        }
        exit;
    }
    $error = 'Invalid credentials.';
}
?>
<!DOCTYPE html>
<html>
<head><title>Login</title></head>
<body>
<h2>Login</h2>
<?php if ($error !== ''): ?><p style="color:red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="POST">
    <input name="whatsapp" placeholder="WhatsApp Number" required>
    <input name="password" type="password" placeholder="Password" required>
    <button type="submit">Login</button>
</form>
<p><a href="register.php">Register</a> | <a href="request_reset.php">Forgot password?</a></p>
</body>
</html>
