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
csrf_require_post();

$db = new Database();
$conn = $db->connect();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    die('Invalid listing.');
}

$stmt = $conn->prepare('SELECT l.*, u.trust_score, u.full_name FROM listings l LEFT JOIN users u ON l.user_id = u.id WHERE l.id = :id');
$stmt->execute(['id' => $id]);
$before = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $conn->prepare(<<<SQL
    UPDATE listings
    SET moderation_status = 'approved',
        listing_status = 'active',
        moderation_reviewed_at = NOW(),
        last_reviewed_by = :admin_id,
        last_confirmed_at = NOW(),
        updated_at = NOW()
    WHERE id = :id
SQL);
$stmt->execute([
    'admin_id' => Auth::user(),
    'id' => $id,
]);

if (!empty($before['user_id'])) {
    pick_adjust_trust_score($conn, (int)$before['user_id'], 5);
    pick_notify_user($conn, (int)$before['user_id'], 'listing_approved', 'Listing approved', 'Your listing passed review and is now eligible for public visibility.', 'listing', $id, 2);
}

log_admin_action($conn, (int)Auth::user(), 'approve_listing', 'listing', $id, 'Moderation approved', $before, ['moderation_status' => 'approved','listing_status' => 'active']);

header('Location: listings.php');
exit;
