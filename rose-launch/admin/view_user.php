<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/auth.php';
require_once '../core/middleware.php';

require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_SUPPORT]);

$db = new Database();
$conn = $db->connect();

$user_id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    die('User not found');
}

include 'partials/header.php';
include 'partials/sidebar.php';
?>
<h1>User details</h1>
<div class="card">
    <p><strong>Name:</strong> <?= htmlspecialchars($user['full_name'] ?? '') ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? '') ?></p>
    <p><strong>WhatsApp:</strong> <?= htmlspecialchars($user['whatsapp_number'] ?? '') ?></p>
    <p><strong>Account state:</strong> <?= htmlspecialchars($user['account_state'] ?? '') ?></p>
    <p><strong>Trust:</strong> <?= (int)($user['trust_score'] ?? 50) ?></p>
    <p><strong>Profile complete:</strong> <?= !empty($user['profile_completed']) ? 'Yes' : 'No' ?></p>
</div>
<div class="card">
    <a class="btn btn-verify" href="verify_account.php?id=<?= (int)$user['id'] ?>">Identity review</a>
</div>
<?php include 'partials/footer.php'; 