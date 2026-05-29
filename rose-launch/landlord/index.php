<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/auth.php';
require_once '../core/middleware.php';
require_once '../core/csrf.php';
require_once '../app/governance/notification_helper.php';

require_role(ROLE_LANDLORD);

$db = new Database();
$conn = $db->connect();
$user_id = Auth::user();

$stmt = $conn->prepare(<<<SQL
    SELECT l.id, l.title, l.moderation_status, l.listing_status, l.verification_status, l.expires_at, l.last_confirmed_at, l.updated_at,
           l.property_type, l.trust_score, t.name AS town_name, p.name AS province_name
    FROM listings l
    LEFT JOIN towns t ON l.town_id = t.id
    LEFT JOIN provinces p ON l.province_id = p.id
    WHERE l.user_id = :id
    ORDER BY l.id DESC
SQL);
$stmt->execute(['id' => $user_id]);
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$userStmt = $conn->prepare("SELECT full_name, account_state, trust_score, whatsapp_verified FROM users WHERE id = :id");
$userStmt->execute(['id' => $user_id]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$unread = pick_count_unread_notifications($conn, $user_id);

?>
<!DOCTYPE html>
<html>
<head><title>Landlord Dashboard</title></head>
<body>
<h2>Dashboard</h2>
<p>Welcome, <?= htmlspecialchars($user['full_name'] ?? '') ?> | State: <?= htmlspecialchars($user['account_state'] ?? '') ?> | Trust: <?= (int)($user['trust_score'] ?? 50) ?></p>
<p>Unread notifications: <?= (int)$unread ?></p>
<p><a href="create.php">Create new listing</a> | <a href="notifications.php">Notifications</a> | <a href="support.php">Support / Appeals</a> | <a href="profile.php">Profile</a></p>
<?php foreach ($listings as $listing): ?>
    <div style="margin-bottom:14px;padding:12px;border:1px solid #ddd;">
        <strong><?= htmlspecialchars($listing['title']) ?></strong><br>
        Type: <?= htmlspecialchars($listing['property_type'] ?? '') ?><br>
        Location: <?= htmlspecialchars(trim(($listing['province_name'] ?? '') . ', ' . ($listing['town_name'] ?? '') . ', ' . ($listing['area'] ?? ''))) ?><br>
        Moderation: <?= htmlspecialchars($listing['moderation_status']) ?><br>
        Lifecycle: <?= htmlspecialchars($listing['listing_status']) ?><br>
        Verification: <?= htmlspecialchars($listing['verification_status']) ?><br>
        Expires: <?= htmlspecialchars((string)$listing['expires_at']) ?><br>
        <?php if (in_array($listing['listing_status'], ['expired', 'grace_period'], true)): ?>
            <form method="POST" action="../renew.php" style="display:inline;">
                <?= csrf_input() ?>
                <input type="hidden" name="listing_id" value="<?= (int)$listing['id'] ?>">
                <button type="submit">Reconfirm listing</button>
            </form>
        <?php endif; ?>
        <a href="edit.php?id=<?= (int)$listing['id'] ?>">Edit</a> |
        <a href="listing.php?id=<?= (int)$listing['id'] ?>">Open</a>
    </div>
<?php endforeach; ?>
</body>
</html>
