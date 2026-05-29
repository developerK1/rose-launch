<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/auth.php';
require_once '../core/middleware.php';

require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

$db = new Database();
$conn = $db->connect();

$users = $conn->query("SELECT * FROM users WHERE account_state IN ('pending_verification','identity_review_required') ORDER BY id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
$listings = $conn->query("SELECT l.*, u.full_name, u.whatsapp_number FROM listings l LEFT JOIN users u ON l.user_id = u.id WHERE l.moderation_status = 'pending' ORDER BY l.id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>
<h1>Identity & Listing Reviews</h1>
<div class="card">
    <h3>Landlord identity review queue</h3>
    <table>
        <tr><th>ID</th><th>Name</th><th>WhatsApp</th><th>State</th><th>Action</th></tr>
        <?php foreach ($users as $user): ?>
        <tr>
            <td><?= (int)$user['id'] ?></td>
            <td><?= htmlspecialchars($user['full_name'] ?? '') ?></td>
            <td><?= htmlspecialchars($user['whatsapp_number'] ?? '') ?></td>
            <td><?= htmlspecialchars($user['account_state'] ?? '') ?></td>
            <td><a class="btn btn-verify" href="verify_account.php?id=<?= (int)$user['id'] ?>">Review</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<div class="card">
    <h3>Pending listings</h3>
    <table>
        <tr><th>ID</th><th>Landlord</th><th>Listing</th><th>Action</th></tr>
        <?php foreach ($listings as $listing): ?>
        <tr>
            <td><?= (int)$listing['id'] ?></td>
            <td><?= htmlspecialchars($listing['full_name'] ?? '') ?></td>
            <td><?= htmlspecialchars($listing['title'] ?? '') ?></td>
            <td><a class="btn" href="view_listing.php?id=<?= (int)$listing['id'] ?>">Open</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php include 'partials/footer.php'; 