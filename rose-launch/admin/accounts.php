<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/auth.php';
require_once '../core/middleware.php';

require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_SUPPORT]);

$db = new Database();
$conn = $db->connect();

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$query = 'SELECT * FROM users WHERE 1=1';
$params = [];
if ($search !== '') {
    $query .= ' AND (full_name LIKE :search OR whatsapp_number LIKE :search OR email LIKE :search OR CAST(id AS CHAR) LIKE :search)';
    $params['search'] = '%' . $search . '%';
}
if ($status !== '') {
    $query .= ' AND account_state = :status';
    $params['status'] = $status;
}
$query .= ' ORDER BY id DESC LIMIT 200';
$stmt = $conn->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>
<h1>Accounts</h1>
<div class="card">
    <form method="GET">
        <input name="search" placeholder="Search name, phone, email, id" value="<?= htmlspecialchars($search) ?>">
        <select name="status">
            <option value="">All</option>
            <?php foreach (['pending_verification','verified','identity_review_required','suspended','archived'] as $opt): ?>
                <option value="<?= $opt ?>" <?= $status === $opt ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Filter</button>
    </form>
</div>
<table>
<tr><th>ID</th><th>Name</th><th>Phone</th><th>Email</th><th>Status</th><th>Trust</th><th>Actions</th></tr>
<?php foreach ($users as $user): ?>
<tr>
    <td><?= (int)$user['id'] ?></td>
    <td><?= htmlspecialchars($user['full_name'] ?? '') ?></td>
    <td><?= htmlspecialchars($user['whatsapp_number'] ?? '') ?></td>
    <td><?= htmlspecialchars($user['email'] ?? '') ?></td>
    <td><?= htmlspecialchars($user['account_state'] ?? '') ?></td>
    <td><?= (int)($user['trust_score'] ?? 50) ?></td>
    <td>
        <a class="btn" href="view_user.php?id=<?= (int)$user['id'] ?>">View</a>
        <a class="btn" href="verify_account.php?id=<?= (int)$user['id'] ?>">Identity Review</a>
    </td>
</tr>
<?php endforeach; ?>
</table>
<?php include 'partials/footer.php'; 