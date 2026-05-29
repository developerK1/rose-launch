<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/auth.php';
require_once '../core/middleware.php';
require_once '../core/csrf.php';
require_once '../app/governance/admin_logger.php';
require_once '../app/governance/notification_helper.php';

require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

$db = new Database();
$conn = $db->connect();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    die('Invalid listing.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_post();
    $reason = trim($_POST['reason'] ?? 'Rejected after review');

    $stmt = $conn->prepare('SELECT * FROM listings WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $before = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $conn->prepare(<<<SQL
        UPDATE listings
        SET moderation_status = 'rejected',
            listing_status = 'suspended',
            public_note = :note,
            moderation_reviewed_at = NOW(),
            last_reviewed_by = :admin_id,
            updated_at = NOW()
        WHERE id = :id
SQL);
    $stmt->execute([
        'note' => $reason,
        'admin_id' => Auth::user(),
        'id' => $id,
    ]);

    if (!empty($before['user_id'])) {
        pick_notify_user($conn, (int)$before['user_id'], 'listing_rejected', 'Listing rejected', $reason, 'listing', $id, 3);
    }

    log_admin_action($conn, (int)Auth::user(), 'reject_listing', 'listing', $id, $reason, $before, ['moderation_status' => 'rejected','listing_status' => 'suspended']);

    header('Location: listings.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Reject Listing</title></head>
<body>
<h2>Reject listing</h2>
<form method="POST">
    <?= csrf_input() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <textarea name="reason" placeholder="Reason for rejection" required></textarea>
    <button type="submit">Reject</button>
</form>
</body>
</html>
