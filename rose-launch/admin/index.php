<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/auth.php';
require_once '../core/middleware.php';

require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_SUPPORT]);

$db = new Database();
$conn = $db->connect();

$active = (int)$conn->query("SELECT COUNT(*) FROM listings WHERE moderation_status='approved' AND listing_status IN ('active','grace_period')")->fetchColumn();
$pending = (int)$conn->query("SELECT COUNT(*) FROM listings WHERE moderation_status='pending'")->fetchColumn();
$expired = (int)$conn->query("SELECT COUNT(*) FROM listings WHERE listing_status='expired'")->fetchColumn();
$grace = (int)$conn->query("SELECT COUNT(*) FROM listings WHERE listing_status='grace_period'")->fetchColumn();
$flagged = (int)$conn->query("SELECT COUNT(*) FROM reports WHERE report_status='pending'")->fetchColumn();
$reviewUsers = (int)$conn->query("SELECT COUNT(*) FROM users WHERE account_state IN ('pending_verification','identity_review_required')")->fetchColumn();
$supportOpen = (int)$conn->query("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','under_review','escalated')")->fetchColumn();
$incidentsOpen = (int)$conn->query("SELECT COUNT(*) FROM incident_cases WHERE status IN ('open','under_review','monitor')")->fetchColumn();
$expiringSoon = (int)$conn->query("SELECT COUNT(*) FROM listings WHERE listing_status='active' AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 5 DAY)")->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head><title>Admin Dashboard</title></head>
<body>
<h2>Governance Dashboard</h2>
<ul>
    <li><a href="reviews.php">Identity Reviews</a></li>
    <li><a href="accounts.php">Accounts</a></li>
    <li><a href="listings.php">Listings</a></li>
    <li><a href="reports.php">Reports</a></li>
    <li><a href="support.php">Support</a></li>
    <li><a href="incidents.php">Incidents</a></li>
    <li><a href="logs.php">Admin Logs</a></li>
    <li><a href="notifications.php">Notifications</a></li>
</ul>
<div class="stats">
    <div class="stat">Active public listings: <strong><?= $active ?></strong></div>
    <div class="stat">Pending review: <strong><?= $pending ?></strong></div>
    <div class="stat">Grace period: <strong><?= $grace ?></strong></div>
    <div class="stat">Expiring soon: <strong><?= $expiringSoon ?></strong></div>
    <div class="stat">Expired: <strong><?= $expired ?></strong></div>
    <div class="stat">Pending reports: <strong><?= $flagged ?></strong></div>
    <div class="stat">Identity queue: <strong><?= $reviewUsers ?></strong></div>
    <div class="stat">Open support: <strong><?= $supportOpen ?></strong></div>
    <div class="stat">Open incidents: <strong><?= $incidentsOpen ?></strong></div>
</div>
</body>
</html>
