<?php
require_once '../config/database.php';
require_once '../app/governance/notification_helper.php';

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare(<<<SQL
    SELECT id, user_id
    FROM listings
    WHERE moderation_status = 'approved'
      AND listing_status IN ('active', 'grace_period')
      AND expires_at IS NOT NULL
      AND expires_at < NOW()
SQL);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$conn->exec(<<<SQL
    UPDATE listings
    SET listing_status = 'expired'
    WHERE moderation_status = 'approved'
      AND listing_status IN ('active', 'grace_period')
      AND expires_at IS NOT NULL
      AND expires_at < NOW()
SQL);

foreach ($rows as $row) {
    pick_notify_user($conn, (int)$row['user_id'], 'listing_expired', 'Listing expired', 'Your listing was hidden because it reached its expiry date.', 'listing', (int)$row['id'], 2);
}
