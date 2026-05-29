<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/auth.php';
require_once '../core/middleware.php';

require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

$db = new Database();
$conn = $db->connect();

$action = trim($_GET['action_type'] ?? '');
$entityType = trim($_GET['entity_type'] ?? '');
$adminId = trim($_GET['admin_id'] ?? '');

$query = <<<SQL
    SELECT l.*, u.full_name AS admin_name
    FROM admin_logs l
    LEFT JOIN users u ON l.admin_id = u.id
    WHERE 1=1
SQL;
$params = [];
if ($action !== '') {
    $query .= ' AND l.action_type = :action_type';
    $params['action_type'] = $action;
}
if ($entityType !== '') {
    $query .= ' AND l.entity_type = :entity_type';
    $params['entity_type'] = $entityType;
}
if ($adminId !== '') {
    $query .= ' AND l.admin_id = :admin_id';
    $params['admin_id'] = (int)$adminId;
}
$query .= ' ORDER BY l.id DESC LIMIT 200';
$stmt = $conn->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>
<h1>Admin Logs</h1>
<form method="GET" class="card">
    <input name="action_type" placeholder="Action type" value="<?= htmlspecialchars($action) ?>">
    <input name="entity_type" placeholder="Entity type" value="<?= htmlspecialchars($entityType) ?>">
    <input name="admin_id" placeholder="Admin ID" value="<?= htmlspecialchars($adminId) ?>">
    <button class="btn" type="submit">Filter</button>
</form>
<table>
<tr><th>ID</th><th>Admin</th><th>Action</th><th>Entity</th><th>Old</th><th>New</th><th>Note</th><th>Date</th></tr>
<?php foreach ($logs as $log): ?>
<tr>
    <td><?= (int)$log['id'] ?></td>
    <td><?= htmlspecialchars($log['admin_name'] ?? ('Admin #' . $log['admin_id'])) ?></td>
    <td><?= htmlspecialchars($log['action_type'] ?? '') ?></td>
    <td><?= htmlspecialchars(($log['entity_type'] ?? '') . ' #' . ($log['entity_id'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string)($log['old_value'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string)($log['new_value'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string)($log['note'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string)($log['created_at'] ?? '')) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php include 'partials/footer.php'; 