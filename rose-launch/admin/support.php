<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/auth.php';
require_once '../core/middleware.php';
require_once '../core/csrf.php';
require_once '../app/governance/support_helper.php';
require_once '../app/governance/notification_helper.php';

require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_SUPPORT]);

$db = new Database();
$conn = $db->connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_post();
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'under_review');
    $message = trim($_POST['message'] ?? '');
    if ($ticketId > 0) {
        $stmt = $conn->prepare("SELECT * FROM support_tickets WHERE id = :id");
        $stmt->execute(['id' => $ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if ($message !== '') {
            pick_add_support_message($conn, $ticketId, pick_role_name((int)Auth::role()), Auth::user(), $message);
        }
        pick_update_support_ticket($conn, $ticketId, $status, Auth::user(), $message !== '' ? $message : null);
        if (!empty($ticket['user_id'])) {
            pick_notify_user($conn, (int)$ticket['user_id'], 'support_ticket_updated', 'Support ticket updated', 'Your support ticket status changed to ' . $status . '.', 'support_ticket', $ticketId, 1);
        }
    }
}

$status = trim($_GET['status'] ?? '');
$category = trim($_GET['category'] ?? '');

$query = <<<SQL
    SELECT t.*, u.full_name, u.whatsapp_number, l.title AS listing_title
    FROM support_tickets t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN listings l ON t.listing_id = l.id
    WHERE 1=1
SQL;
$params = [];
if ($status !== '') {
    $query .= ' AND t.status = :status';
    $params['status'] = $status;
}
if ($category !== '') {
    $query .= ' AND t.category = :category';
    $params['category'] = $category;
}
$query .= ' ORDER BY t.id DESC LIMIT 200';
$stmt = $conn->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head><title>Support</title></head>
<body>
<h2>Support / Appeals</h2>
<form method="GET">
    <input name="category" placeholder="Category" value="<?= htmlspecialchars($category) ?>">
    <select name="status">
        <option value="">All statuses</option>
        <?php foreach (['open','under_review','awaiting_landlord','awaiting_admin','escalated','resolved','closed'] as $opt): ?>
            <option value="<?= $opt ?>" <?= $status === $opt ? 'selected' : '' ?>><?= $opt ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Filter</button>
</form>
<table>
<tr><th>ID</th><th>User</th><th>Category</th><th>Status</th><th>Subject</th><th>Action</th></tr>
<?php foreach ($tickets as $ticket): ?>
<tr>
    <td><?= (int)$ticket['id'] ?></td>
    <td><?= htmlspecialchars(($ticket['full_name'] ?? '') . ' / ' . ($ticket['whatsapp_number'] ?? '')) ?></td>
    <td><?= htmlspecialchars($ticket['category']) ?></td>
    <td><?= htmlspecialchars($ticket['status']) ?></td>
    <td><?= htmlspecialchars($ticket['subject']) ?></td>
    <td>
        <form method="POST">
            <?= csrf_input() ?>
            <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
            <select name="status">
                <?php foreach (['under_review','awaiting_landlord','awaiting_admin','escalated','resolved','closed'] as $opt): ?>
                    <option value="<?= $opt ?>"><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
            <input name="message" placeholder="Admin note / reply">
            <button type="submit">Update</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</table>
</body>
</html>
