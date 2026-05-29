<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/auth.php';
require_once '../core/middleware.php';
require_once '../core/csrf.php';
require_once '../app/governance/support_helper.php';
require_once '../app/governance/notification_helper.php';

require_role(ROLE_LANDLORD);

$db = new Database();
$conn = $db->connect();
$userId = Auth::user();
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_post();
    $category = trim($_POST['category'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['message'] ?? '');
    $listingId = (int)($_POST['listing_id'] ?? 0) ?: null;

    if ($category === '' || $subject === '' || $body === '') {
        $error = 'Complete the support form.';
    } else {
        $ticketId = pick_create_support_ticket($conn, $userId, $listingId, $category, $subject, $body);
        pick_notify_user($conn, $userId, 'support_ticket_opened', 'Support ticket opened', 'Your support request has been received.', 'support_ticket', $ticketId, 2);
        pick_notify_role($conn, [ROLE_ADMIN, ROLE_SUPPORT], 'support_ticket_opened', 'New support ticket', 'A new support ticket requires review.', 'support_ticket', $ticketId, 2);
        $message = 'Support ticket created.';
    }
}

$stmt = $conn->prepare("SELECT * FROM support_tickets WHERE user_id = :id ORDER BY created_at DESC");
$stmt->execute(['id' => $userId]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selectedTicketId = (int)($_GET['ticket_id'] ?? ($_POST['ticket_id'] ?? 0));
$messages = [];
if ($selectedTicketId > 0) {
    $msgStmt = $conn->prepare("SELECT * FROM support_ticket_messages WHERE ticket_id = :id ORDER BY created_at ASC");
    $msgStmt->execute(['id' => $selectedTicketId]);
    $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head><title>Support</title></head>
<body>
<h2>Support / Appeals</h2>
<p><a href="index.php">Back</a></p>
<?php if ($error !== ''): ?><p style="color:red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($message !== ''): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>
<form method="POST">
    <?= csrf_input() ?>
    <input name="listing_id" type="number" placeholder="Listing ID (optional)">
    <select name="category" required>
        <option value="">Select category</option>
        <option value="appeal">Appeal</option>
        <option value="listing_issue">Listing issue</option>
        <option value="verification_issue">Verification issue</option>
        <option value="identity_review_issue">Identity review issue</option>
        <option value="expiry_issue">Expiry issue</option>
        <option value="technical_issue">Technical issue</option>
        <option value="support_request">General support</option>
    </select>
    <input name="subject" placeholder="Subject" required>
    <textarea name="message" placeholder="Explain your issue" required></textarea>
    <button type="submit">Open ticket</button>
</form>

<h3>Your tickets</h3>
<?php foreach ($tickets as $ticket): ?>
    <div style="border:1px solid #ddd;margin:10px 0;padding:10px;">
        <strong>#<?= (int)$ticket['id'] ?> <?= htmlspecialchars($ticket['subject']) ?></strong>
        <p>Status: <?= htmlspecialchars($ticket['status']) ?> | Category: <?= htmlspecialchars($ticket['category']) ?> | Severity: <?= htmlspecialchars($ticket['severity']) ?></p>
        <p><?= htmlspecialchars($ticket['resolution_note'] ?? '') ?></p>
    </div>
<?php endforeach; ?>

<h3>Conversation</h3>
<?php if (!empty($messages)): ?>
    <?php foreach ($messages as $msg): ?>
        <div class="card">
            <strong><?= htmlspecialchars($msg['sender_role'] ?? '') ?></strong>
            <p><?= htmlspecialchars($msg['message'] ?? '') ?></p>
            <small><?= htmlspecialchars((string)($msg['created_at'] ?? '')) ?></small>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
