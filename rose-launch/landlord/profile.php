<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/auth.php';
require_once '../core/middleware.php';
require_once '../core/csrf.php';
require_once '../app/governance/notification_helper.php';

require_role(ROLE_LANDLORD);

$db = new Database();
$conn = $db->connect();
$user_id = Auth::user();
$message = '';
$error = '';

$stmt = $conn->prepare("SELECT full_name, email, whatsapp_number, id_number, account_state, whatsapp_verified, trust_score FROM users WHERE id = :id");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_post();

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');

    if ($full_name === '' || $whatsapp === '') {
        $error = 'Name and WhatsApp number are required.';
    } else {
        $requires_review = ((string)($user['full_name'] ?? '') !== $full_name) || ((string)($user['whatsapp_number'] ?? '') !== $whatsapp);

        $stmt = $conn->prepare(<<<SQL
            UPDATE users
            SET full_name = :full_name,
                email = :email,
                whatsapp_number = :whatsapp,
                id_number = :id_number,
                profile_completed = 1,
                account_state = CASE WHEN :requires_review = 1 THEN 'identity_review_required' ELSE account_state END,
                whatsapp_verified = CASE WHEN :requires_review = 1 THEN 0 ELSE whatsapp_verified END,
                updated_at = NOW(),
                last_activity_at = NOW()
            WHERE id = :id
        SQL);
        $stmt->execute([
            'full_name' => $full_name,
            'email' => $email !== '' ? $email : null,
            'whatsapp' => $whatsapp,
            'id_number' => $id_number,
            'requires_review' => $requires_review ? 1 : 0,
            'id' => $user_id,
        ]);

        if ($requires_review) {
            pick_notify_user($conn, $user_id, 'identity_review_required', 'Identity review required', 'Your name or WhatsApp number changed. Identity review is required before trust status is restored.', 'user', $user_id, 2);
            $message = 'Profile saved. Identity review is now required because personal trust details changed.';
        } else {
            pick_notify_user($conn, $user_id, 'profile_updated', 'Profile updated', 'Your profile information has been updated.', 'user', $user_id, 1);
            $message = 'Profile saved.';
        }

        $stmt = $conn->prepare("SELECT full_name, email, whatsapp_number, id_number, account_state, whatsapp_verified, trust_score FROM users WHERE id = :id");
        $stmt->execute(['id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Profile</title></head>
<body>
<h2>Profile</h2>
<?php if ($error !== ''): ?><p style="color:red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($message !== ''): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>
<form method="POST">
    <?= csrf_input() ?>
    <input name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" placeholder="Full Name" required>
    <input name="email" type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="Email">
    <input name="whatsapp" value="<?= htmlspecialchars($user['whatsapp_number'] ?? '') ?>" placeholder="WhatsApp Number" required>
    <input name="id_number" value="<?= htmlspecialchars($user['id_number'] ?? '') ?>" placeholder="ID Number">
    <button type="submit">Save</button>
</form>
</body>
</html>
