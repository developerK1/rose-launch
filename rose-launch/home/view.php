<?php
require_once '../config/database.php';
require_once '../app/governance/listing_governance.php';
require_once '../app/governance/media_helper.php';
require_once '../app/governance/notification_helper.php';

$db = new Database();
$conn = $db->connect();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('Listing not found.');
}

$sql = <<<SQL
    SELECT l.*, t.name AS town_name, p.name AS province_name, u.trust_score AS landlord_trust, u.account_state
    FROM listings l
    LEFT JOIN towns t ON l.town_id = t.id
    LEFT JOIN provinces p ON l.province_id = p.id
    LEFT JOIN users u ON l.user_id = u.id
    WHERE l.id = :id AND {WHERE_CLAUSE}
SQL;
$sql = str_replace('{WHERE_CLAUSE}', pick_listing_public_where('l', 'u'), $sql);
$stmt = $conn->prepare($sql);
$stmt->execute(['id' => $id]);
$listing = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$listing) {
    die('Listing not found.');
}

$conn->prepare("INSERT INTO listing_analytics (listing_id, metric_type, created_at) VALUES (:id, 'view', NOW())")
    ->execute(['id' => $id]);

$imagesStmt = $conn->prepare(<<<SQL
    SELECT file_path
    FROM listing_images
    WHERE listing_id = :id AND archived_at IS NULL
    ORDER BY is_cover DESC, sort_order ASC, id ASC
SQL);
$imagesStmt->execute(['id' => $id]);
$images = $imagesStmt->fetchAll(PDO::FETCH_COLUMN);

echo '<h2>' . htmlspecialchars($listing['title']) . '</h2>';
echo '<p>R' . htmlspecialchars((string)$listing['price']) . '</p>';
echo '<p>' . htmlspecialchars(trim(($listing['area'] ?? '') . ', ' . ($listing['town_name'] ?? '') . ', ' . ($listing['province_name'] ?? ''))) . '</p>';
echo '<p>' . nl2br(htmlspecialchars($listing['description'] ?? '')) . '</p>';
echo '<p>Contact: ' . htmlspecialchars($listing['contact_number'] ?? '') . '</p>';
echo '<p>' . (pick_listing_badge_visible($listing) ? 'Identity Reviewed' : 'Reviewed by platform') . '</p>';
echo '<p>Trust: ' . (int)($listing['landlord_trust'] ?? 50) . '</p>';
foreach ($images as $image) {
    echo '<img src="../' . htmlspecialchars(pick_storage_relative_to_public($image)) . '" alt="" style="max-width:240px;margin:6px 6px 6px 0;">';
}
echo '<p><a href="report.php?id=' . (int)$listing['id'] . '">Report this listing</a></p>';
