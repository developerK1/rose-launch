<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/auth.php';
require_once '../core/middleware.php';
require_once '../core/csrf.php';
require_once '../app/governance/notification_helper.php';
require_once '../app/governance/trust_helper.php';

require_role(ROLE_LANDLORD);
csrf_require_post();

$db = new Database();
$conn = $db->connect();

$listing_id = (int)($_POST['listing_id'] ?? 0);
if ($listing_id <= 0) {
    die('Invalid listing.');
}

$stmt = $conn->prepare(<<<SQL
    UPDATE listings
    SET listing_status = 'active',
        expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY),
        last_confirmed_at = NOW(),
        updated_at = NOW()
    WHERE id = :id AND user_id = :user_id
SQL);
$stmt->execute([
    'id' => $listing_id,
    'user_id' => Auth::user(),
]);

pick_adjust_trust_score($conn, Auth::user(), 1);
$conn->prepare("INSERT INTO listing_analytics (listing_id, metric_type, created_at) VALUES (:id, 'renewal', NOW())")
    ->execute(['id' => $listing_id]);

pick_notify_user($conn, Auth::user(), 'listing_renewed', 'Listing renewed', 'Your listing has been reconfirmed for another 30 days.', 'listing', $listing_id, 2);

echo 'Listing reconfirmed successfully.';
