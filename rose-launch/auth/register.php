<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../app/governance/security_helper.php';

$db = new Database();
$conn = $db->connect();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $terms = isset($_POST['accept_terms']);

    if ($full_name === '' || $whatsapp === '' || strlen($password) < 6 || !$terms) {
        $error = 'Please complete all required fields and accept the terms.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE whatsapp_number = :whatsapp LIMIT 1");
        $stmt->execute(['whatsapp' => $whatsapp]);
        if ($stmt->fetchColumn()) {
            $error = 'WhatsApp number already exists.';
        } else {
            $token = pick_generate_token(16);
            $deadline = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $stmt = $conn->prepare(<<<SQL
                INSERT INTO users
                (full_name, email, whatsapp_number, password_hash, role_id, status, account_state, whatsapp_verified, failed_login_attempts, locked_until,
                 whatsapp_verification_token, verification_deadline, accepted_terms_version, accepted_terms_at, trust_score, last_activity_at)
                VALUES
                (:full_name, :email, :whatsapp, :password_hash, 1, 'active', 'pending_verification', 0, 0, NULL,
                 :token, :deadline, 'mvp-v2.5', NOW(), 50, NOW())
            SQL);
            $stmt->execute([
                'full_name' => $full_name,
                'email' => $email !== '' ? $email : null,
                'whatsapp' => $whatsapp,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'token' => $token,
                'deadline' => $deadline,
            ]);

            header('Location: login.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Register</title></head>
<body>
<h2>Create account</h2>
<?php if ($error !== ''): ?><p style="color:red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="POST">
    <input name="full_name" placeholder="Full Name" required>
    <input name="email" type="email" placeholder="Email (for password reset)">
    <input name="whatsapp" placeholder="WhatsApp Number" required>
    <input name="password" type="password" placeholder="Password" minlength="6" required>
    <label><input type="checkbox" name="accept_terms" value="1" required> I accept the terms and conditions</label>
    <button type="submit">Register</button>
</form>
<p><a href="login.php">Login</a></p>
</body>
</html>
