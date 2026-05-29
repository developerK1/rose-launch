<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/auth.php';
require_once '../core/middleware.php';
require_once '../app/governance/notification_helper.php';

require_role(ROLE_LANDLORD);

$db = new Database();
$conn = $db->connect();

$listingId = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare(<<<SQL
    SELECT l.*, t.name AS town_name, p.name AS province_name, u.full_name, u.account_state, u.trust_score
    FROM listings l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN towns t ON l.town_id = t.id
    LEFT JOIN provinces p ON l.province_id = p.id
    WHERE l.id = :id AND l.user_id = :user_id
    LIMIT 1
SQL);
$stmt->execute(['id' => $listingId, 'user_id' => Auth::user()]);
$listing = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$listing) {
    die('Listing not found.');
}

$imgStmt = $conn->prepare("SELECT * FROM listing_images WHERE listing_id = :id AND archived_at IS NULL ORDER BY is_cover DESC, sort_order ASC, id ASC");
$imgStmt->execute(['id' => $listingId]);
$images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html>
<head><title>Listing</title></head>
<body>
<h2><?= htmlspecialchars($listing['title']) ?></h2>
<p>Type: <?= htmlspecialchars($listing['property_type'] ?? '') ?></p>
<p>Location: <?= htmlspecialchars(trim(($listing['province_name'] ?? '') . ', ' . ($listing['town_name'] ?? '') . ', ' . ($listing['area'] ?? ''))) ?></p>
<p>Moderation: <?= htmlspecialchars($listing['moderation_status']) ?></p>
<p>Lifecycle: <?= htmlspecialchars($listing['listing_status']) ?></p>
<p>Verification: <?= htmlspecialchars($listing['verification_status']) ?></p>
<p>Identity state: <?= htmlspecialchars($listing['account_state'] ?? '') ?></p>
<p>Trust score: <?= (int)($listing['trust_score'] ?? 50) ?></p>
<p><?= nl2br(htmlspecialchars($listing['description'] ?? '')) ?></p>
<?php foreach ($images as $image): ?>
    <img src="../<?= htmlspecialchars(pick_storage_relative_to_public($image['file_path'])) ?>" alt="" style="max-width:220px;margin:6px 6px 6px 0;">
<?php endforeach; ?>
</body>
</html>
