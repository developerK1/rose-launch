<?php
require_once '../config/database.php';
require_once '../app/governance/listing_governance.php';
require_once '../app/governance/media_helper.php';

$db = new Database();
$conn = $db->connect();

$sql = <<<SQL
    SELECT l.*, t.name AS town_name, p.name AS province_name, li.file_path AS cover_image, u.trust_score AS landlord_trust, u.account_state
    FROM listings l
    LEFT JOIN towns t ON l.town_id = t.id
    LEFT JOIN provinces p ON l.province_id = p.id
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN listing_images li ON li.listing_id = l.id AND li.is_cover = 1 AND li.archived_at IS NULL
    WHERE {WHERE_CLAUSE}
    ORDER BY {ORDER_CLAUSE}
    LIMIT 20
SQL;
$sql = str_replace('{WHERE_CLAUSE}', pick_listing_public_where('l', 'u'), $sql);
$sql = str_replace('{ORDER_CLAUSE}', pick_listing_public_order_sql('l', 'u'), $sql);

$stmt = $conn->prepare($sql);
$stmt->execute();
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head><title>PickMzansi</title></head>
<body>
<h2>PickMzansi Rooms</h2>
<p><a href="search.php">Search</a></p>
<?php foreach ($listings as $listing): ?>
    <div style="margin-bottom:16px;padding:12px;border:1px solid #ddd;">
        <?php if (!empty($listing['cover_image'])): ?>
            <div><img src="../<?= htmlspecialchars(pick_storage_relative_to_public($listing['cover_image'])) ?>" alt="" style="max-width:220px;height:auto;"></div>
        <?php endif; ?>
        <h3><?= htmlspecialchars($listing['title']) ?></h3>
        <p>R<?= htmlspecialchars((string)$listing['price']) ?></p>
        <p><?= htmlspecialchars(trim(($listing['area'] ?? '') . ', ' . ($listing['town_name'] ?? '') . ', ' . ($listing['province_name'] ?? ''))) ?></p>
        <p><?= pick_listing_badge_visible($listing) ? 'Identity Reviewed' : 'Reviewed by platform' ?></p>
        <a href="view.php?id=<?= (int)$listing['id'] ?>">View</a>
    </div>
<?php endforeach; ?>
</body>
</html>
