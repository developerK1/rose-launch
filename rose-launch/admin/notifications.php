<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/auth.php';
require_once '../core/middleware.php';
require_once '../core/csrf.php';
require_once '../app/governance/notification_helper.php';

require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_SUPPORT]);

$db = new Database();
$conn = $db->connect();
$userId = Auth::user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_post();
    $notificationId = (int)($_POST['notification_id'] ?? 0);
    if ($notificationId > 0) {
        pick_mark_notification_read($conn, $notificationId, $userId);
    }
}

$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = :id ORDER BY created_at DESC");
$stmt->execute(['id' => $userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>
<h1>Notifications</h1>
<?php foreach ($notifications as $note): ?>
    <div class="card">
        <strong><?= htmlspecialchars($note['title']) ?></strong>
        <p><?= htmlspecialchars($note['message']) ?></p>
        <small><?= htmlspecialchars((string)$note['created_at']) ?> | <?= $note['is_read'] ? 'Read' : 'Unread' ?></small>
        <?php if (!(int)$note['is_read']): ?>
        <form method="POST">
            <?= csrf_input() ?>
            <input type="hidden" name="notification_id" value="<?= (int)$note['id'] ?>">
            <button class="btn" type="submit">Mark read</button>
        </form>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php include 'partials/footer.php'; 