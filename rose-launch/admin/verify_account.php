<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/auth.php';
require_once '../core/middleware.php';
require_once '../core/csrf.php';
require_once '../app/governance/admin_logger.php';
require_once '../app/governance/notification_helper.php';
require_once '../app/governance/trust_helper.php';

require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

$db = new Database();
$conn = $db->connect();

$user_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($user_id <= 0) {
    die('Invalid user.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_post();
    $action = $_POST['action'] ?? 'approve';
    $note = trim($_POST['note'] ?? '');

    $stmt = $conn->prepare('SELECT id, full_name, account_state, whatsapp_verified, trust_score FROM users WHERE id = :id');
    $stmt->execute(['id' => $user_id]);
    $before = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if ($action === 'approve') {
        $stmt = $conn->prepare(<<<SQL
            UPDATE users
            SET whatsapp_verified = 1,
                account_state = 'verified',
                identity_review_status = 'verified',
                identity_reviewed_at = NOW(),
                verified_at = NOW(),
                trust_score = LEAST(100, COALESCE(trust_score, 50) + 15),
                last_activity_at = NOW()
            WHERE id = :id
        SQL);
        $stmt->execute(['id' => $user_id]);

        pick_notify_user($conn, $user_id, 'verification_completed', 'Identity review completed', 'Your landlord identity review has been completed.', 'user', $user_id, 2);
        log_admin_action($conn, (int)Auth::user(), 'verify_account', 'user', $user_id, $note ?: 'Identity reviewed and approved', $before, ['account_state' => 'verified']);
    } else {
        $stmt = $conn->prepare(<<<SQL
            UPDATE users
            SET account_state = 'identity_review_required',
                identity_review_status = 'trust_review_required',
                last_activity_at = NOW()
            WHERE id = :id
        SQL);
        $stmt->execute(['id' => $user_id]);

        pick_notify_user($conn, $user_id, 'identity_review_required', 'Identity review required', $note ?: 'Please complete the requested identity review steps.', 'user', $user_id, 2);
        log_admin_action($conn, (int)Auth::user(), 'request_identity_review', 'user', $user_id, $note ?: 'Identity review requested', $before, ['account_state' => 'identity_review_required']);
    }

    header('Location: accounts.php');
    exit;
}

$stmt = $conn->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head><title>Identity Review</title></head>
<body>
<h2>Identity review for <?= htmlspecialchars($user['full_name'] ?? '') ?></h2>
<form method="POST">
    <?= csrf_input() ?>
    <input type="hidden" name="id" value="<?= (int)$user_id ?>">
    <textarea name="note" placeholder="Admin note"></textarea>
    <button name="action" value="approve" type="submit">Approve Identity Review</button>
    <button name="action" value="request" type="submit">Request More Proof</button>
</form>
</body>
</html>
