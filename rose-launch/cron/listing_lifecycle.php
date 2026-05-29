<?php
require_once '../config/database.php';
require_once '../app/governance/notification_helper.php';

$db = new Database();
$conn = $db->connect();

$expiringStmt = $conn->prepare(<<<SQL
    SELECT l.id, l.user_id
    FROM listings l
    WHERE l.moderation_status = 'approved'
      AND l.listing_status = 'active'
      AND l.expires_at IS NOT NULL
      AND l.expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 5 DAY)
SQL);
$expiringStmt->execute();
$expiring = $expiringStmt->fetchAll(PDO::FETCH_ASSOC);

$conn->exec(<<<SQL
    UPDATE listings
    SET listing_status = 'grace_period'
    WHERE moderation_status = 'approved'
      AND listing_status = 'active'
      AND expires_at IS NOT NULL
      AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 5 DAY)
SQL);

foreach ($expiring as $row) {
    pick_notify_user($conn, (int)$row['user_id'], 'listing_expiring_soon', 'Listing expiring soon', 'Your listing will enter the grace period soon. Please reconfirm it.', 'listing', (int)$row['id'], 2);
}

$expiredStmt = $conn->prepare(<<<SQL
    SELECT l.id, l.user_id
    FROM listings l
    WHERE l.moderation_status = 'approved'
      AND l.listing_status IN ('active', 'grace_period')
      AND l.expires_at IS NOT NULL
      AND l.expires_at < NOW()
SQL);
$expiredStmt->execute();
$expired = $expiredStmt->fetchAll(PDO::FETCH_ASSOC);

$conn->exec(<<<SQL
    UPDATE listings
    SET listing_status = 'expired'
    WHERE moderation_status = 'approved'
      AND listing_status IN ('active', 'grace_period')
      AND expires_at IS NOT NULL
      AND expires_at < NOW()
SQL);

foreach ($expired as $row) {
    pick_notify_user($conn, (int)$row['user_id'], 'listing_expired', 'Listing expired', 'Your listing is no longer public. Please reconfirm to restore visibility.', 'listing', (int)$row['id'], 2);
}

$conn->exec(<<<SQL
    UPDATE listings
    SET listing_status = 'archived'
    WHERE listing_status = 'expired'
      AND updated_at IS NOT NULL
      AND updated_at < DATE_SUB(NOW(), INTERVAL 8 MONTH)
SQL);
