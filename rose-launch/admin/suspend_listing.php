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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
csrf_require_post();

$id = (int)($_POST['id'] ?? 0);
$reason = trim($_POST['reason'] ?? 'Suspended after moderation review');
if ($id <= 0) {
    die('Invalid listing.');
}

$stmt = $conn->prepare('SELECT * FROM listings WHERE id = :id');
$stmt->execute(['id' => $id]);
$before = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $conn->prepare(<<<SQL
    UPDATE listings
    SET listing_status = 'suspended',
        moderation_status = 'rejected',
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
    pick_notify_user($conn, (int)$before['user_id'], 'listing_suspended', 'Listing suspended', $reason, 'listing', $id, 3);
}

log_admin_action($conn, (int)Auth::user(), 'suspend_listing', 'listing', $id, $reason, $before, ['listing_status' => 'suspended']);

header('Location: listings.php');
exit;
