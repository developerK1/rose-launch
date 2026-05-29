<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/auth.php';
require_once '../core/middleware.php';
require_once '../core/csrf.php';
require_once '../app/governance/listing_governance.php';

require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

$db = new Database();
$conn = $db->connect();

$q = trim($_GET['q'] ?? '');
$town = trim($_GET['town'] ?? '');
$moderation = trim($_GET['moderation_status'] ?? '');
$verification = trim($_GET['verification_status'] ?? '');
$lifecycle = trim($_GET['listing_status'] ?? '');

$params = [];
$query = <<<SQL
    SELECT l.*, u.full_name, u.whatsapp_number, u.account_state, u.trust_score AS landlord_trust, p.name AS province_name, t.name AS town_name
    FROM listings l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN provinces p ON l.province_id = p.id
    LEFT JOIN towns t ON l.town_id = t.id
    WHERE 1=1
SQL;

$query .= pick_listing_admin_search_clause('l', $q, $params);
if ($town !== '') {
    $query .= ' AND (t.name LIKE :town OR p.name LIKE :town OR l.area LIKE :town)';
    $params['town'] = '%' . $town . '%';
}
if ($moderation !== '') {
    $query .= ' AND l.moderation_status = :moderation';
    $params['moderation'] = $moderation;
}
if ($verification !== '') {
    $query .= ' AND l.verification_status = :verification';
    $params['verification'] = $verification;
}
if ($lifecycle !== '') {
    $query .= ' AND l.listing_status = :lifecycle';
    $params['lifecycle'] = $lifecycle;
}
$query .= ' ORDER BY l.id DESC LIMIT 200';

$stmt = $conn->prepare($query);
$stmt->execute($params);
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head><title>Listings</title></head>
<body>
<h2>Listings Governance</h2>
<form method="GET">
    <input name="q" placeholder="Phone, name, reference, title" value="<?= htmlspecialchars($q) ?>">
    <input name="town" placeholder="Town" value="<?= htmlspecialchars($town) ?>">
    <select name="moderation_status">
        <option value="">All moderation</option>
        <?php foreach (['pending','approved','rejected'] as $opt): ?>
            <option value="<?= $opt ?>" <?= $moderation === $opt ? 'selected' : '' ?>><?= $opt ?></option>
        <?php endforeach; ?>
    </select>
    <select name="verification_status">
        <option value="">All verification</option>
        <?php foreach (['unverified','verified','reverification_required','rejected'] as $opt): ?>
            <option value="<?= $opt ?>" <?= $verification === $opt ? 'selected' : '' ?>><?= $opt ?></option>
        <?php endforeach; ?>
    </select>
    <select name="listing_status">
        <option value="">All lifecycle</option>
        <?php foreach (['inactive','active','grace_period','expired','archived','suspended','deleted'] as $opt): ?>
            <option value="<?= $opt ?>" <?= $lifecycle === $opt ? 'selected' : '' ?>><?= $opt ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Filter</button>
</form>
<table>
<tr><th>ID</th><th>Landlord</th><th>Contact</th><th>Title</th><th>Type</th><th>Location</th><th>Trust</th><th>Moderation</th><th>Lifecycle</th><th>Verification</th><th>Actions</th></tr>
<?php foreach ($listings as $listing): ?>
<tr>
    <td><?= (int)$listing['id'] ?></td>
    <td><?= htmlspecialchars($listing['full_name'] ?? '') ?></td>
    <td><?= htmlspecialchars($listing['whatsapp_number'] ?? '') ?></td>
    <td><?= htmlspecialchars($listing['title'] ?? '') ?></td>
    <td><?= htmlspecialchars($listing['property_type'] ?? '') ?></td>
    <td><?= htmlspecialchars(trim(($listing['area'] ?? '') . ', ' . ($listing['town_name'] ?? '') . ', ' . ($listing['province_name'] ?? ''))) ?></td>
    <td><?= (int)($listing['landlord_trust'] ?? 50) ?></td>
    <td><?= htmlspecialchars($listing['moderation_status'] ?? '') ?></td>
    <td><?= htmlspecialchars($listing['listing_status'] ?? '') ?></td>
    <td><?= htmlspecialchars($listing['verification_status'] ?? '') ?></td>
    <td>
        <a class="btn" href="view_listing.php?id=<?= (int)$listing['id'] ?>">View</a>
        <?php if (($listing['moderation_status'] ?? '') === 'pending'): ?>
            <form method="POST" action="approve_listing.php" style="display:inline;">
                <?= csrf_input() ?>
                <input type="hidden" name="id" value="<?= (int)$listing['id'] ?>">
                <button class="btn btn-verify" type="submit">Approve</button>
            </form>
            <a class="btn btn-reject" href="reject_listing.php?id=<?= (int)$listing['id'] ?>">Reject</a>
        <?php endif; ?>
        <?php if (($listing['listing_status'] ?? '') !== 'suspended'): ?>
            <form method="POST" action="suspend_listing.php" style="display:inline;">
                <?= csrf_input() ?>
                <input type="hidden" name="id" value="<?= (int)$listing['id'] ?>">
                <button class="btn btn-warning" type="submit">Suspend</button>
            </form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</table>
<?php include 'partials/footer.php'; 