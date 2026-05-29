<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/auth.php';
require_once '../core/middleware.php';

require_login();

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("SELECT whatsapp_verification_token, verification_deadline, full_name, account_state FROM users WHERE id = :id");
$stmt->execute(['id' => Auth::user()]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html>
<head><title>Identity Review</title></head>
<body>
<h3>Identity Review</h3>
<p>Hello <?= htmlspecialchars($user['full_name'] ?? '') ?>.</p>
<p>Your account state: <?= htmlspecialchars($user['account_state'] ?? '') ?></p>
<p>Send this code to the official PickMzansi WhatsApp business number:</p>
<p><strong>ACTIVATE <?= htmlspecialchars($user['whatsapp_verification_token'] ?? '') ?></strong></p>
<p>Deadline: <?= htmlspecialchars($user['verification_deadline'] ?? '') ?></p>
</body>
</html>
