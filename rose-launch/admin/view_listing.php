<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/auth.php';
require_once '../core/middleware.php';
require_once '../core/csrf.php';

require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_SUPPORT]);

$db = new Database();
$conn = $db->connect();

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare(<<<SQL
    SELECT l.*, u.full_name, u.whatsapp_number, u.account_state, u.trust_score AS landlord_trust, p.name AS province_name, t.name AS town_name
    FROM listings l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN provinces p ON l.province_id = p.id
    LEFT JOIN towns t ON l.town_id = t.id
    WHERE l.id = :id
SQL);
$stmt->execute(['id' => $id]);
$listing = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$listing) {
    die('Listing not found');
}

$imagesStmt = $conn->prepare('SELECT * FROM listing_images WHERE listing_id = :id AND archived_at IS NULL ORDER BY is_cover DESC, sort_order ASC, id ASC');
$imagesStmt->execute(['id' => $id]);
$images = $imagesStmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>
<h1>Listing details</h1>
<div class="card">
    <h2><?= htmlspecialchars($listing['title']) ?></h2>
    <p><strong>Landlord:</strong> <?= htmlspecialchars($listing['full_name'] ?? '') ?></p>
    <p><strong>WhatsApp:</strong> <?= htmlspecialchars($listing['whatsapp_number'] ?? '') ?></p>
    <p><strong>Trust:</strong> <?= (int)($listing['landlord_trust'] ?? 50) ?> | Account: <?= htmlspecialchars($listing['account_state'] ?? '') ?></p>
    <p><strong>Location:</strong> <?= htmlspecialchars(trim(($listing['area'] ?? '') . ', ' . ($listing['town_name'] ?? '') . ', ' . ($listing['province_name'] ?? ''))) ?></p>
    <p><strong>Property type:</strong> <?= htmlspecialchars($listing['property_type'] ?? '') ?></p>
    <p><strong>Moderation:</strong> <?= htmlspecialchars($listing['moderation_status'] ?? '') ?></p>
    <p><strong>Lifecycle:</strong> <?= htmlspecialchars($listing['listing_status'] ?? '') ?></p>
    <p><strong>Verification:</strong> <?= htmlspecialchars($listing['verification_status'] ?? '') ?></p>
    <p><strong>Note:</strong> <?= htmlspecialchars($listing['public_note'] ?? '') ?></p>
</div>
<div class="card">
    <h3>Images</h3>
    <?php foreach ($images as $image): ?>
        <img src="../<?= htmlspecialchars(pick_storage_relative_to_public($image['file_path'])) ?>" width="180" style="margin:4px;">
    <?php endforeach; ?>
</div>
<div class="card">
    <?php if (($listing['moderation_status'] ?? '') === 'pending'): ?>
        <form method="POST" action="approve_listing.php" style="display:inline;">
            <?= csrf_input() ?>
            <input type="hidden" name="id" value="<?= (int)$listing['id'] ?>">
            <button class="btn btn-verify" type="submit">Approve</button>
        </form>
        <a class="btn btn-reject" href="reject_listing.php?id=<?= (int)$listing['id'] ?>">Reject</a>
    <?php endif; ?>
    <form method="POST" action="suspend_listing.php" style="display:inline;">
        <?= csrf_input() ?>
        <input type="hidden" name="id" value="<?= (int)$listing['id'] ?>">
        <button class="btn btn-warning" type="submit">Suspend</button>
    </form>
</div>
<?php include 'partials/footer.php'; 