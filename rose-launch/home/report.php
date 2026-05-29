<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/csrf.php';
require_once '../app/governance/notification_helper.php';

$db = new Database();
$conn = $db->connect();

$listing_id = (int)($_GET['id'] ?? $_POST['listing_id'] ?? 0);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_post();

    $report_type = $_POST['report_type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $reporter_phone = trim($_POST['reporter_phone'] ?? '');
    $reporter_ip = $_SERVER['REMOTE_ADDR'] ?? null;

    if ($listing_id <= 0) {
        $error = 'Invalid listing.';
    } elseif (!in_array($report_type, ['fake_listing', 'unavailable_room', 'suspicious_behavior', 'wrong_details'], true)) {
        $error = 'Invalid report type.';
    } else {
        $rateLimitStmt = $conn->prepare(<<<SQL
            SELECT COUNT(*)
            FROM reports
            WHERE (reporter_phone = :phone OR reporter_ip = :ip)
              AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        SQL);
        $rateLimitStmt->execute(['phone' => $reporter_phone ?: '', 'ip' => $reporter_ip]);
        if ((int)$rateLimitStmt->fetchColumn() >= 5) {
            $error = 'Too many reports submitted. Please try later.';
        } else {
            $stmt = $conn->prepare(<<<SQL
                INSERT INTO reports (
                    listing_id,
                    reporter_phone,
                    reporter_ip,
                    report_type,
                    description,
                    report_status,
                    severity,
                    created_at
                ) VALUES (
                    :listing_id,
                    :reporter_phone,
                    :reporter_ip,
                    :report_type,
                    :description,
                    'pending',
                    CASE WHEN :report_type IN ('fake_listing','suspicious_behavior') THEN 'critical' ELSE 'medium' END,
                    NOW()
                )
            SQL);
            $stmt->execute([
                'listing_id' => $listing_id,
                'reporter_phone' => $reporter_phone ?: null,
                'reporter_ip' => $reporter_ip,
                'report_type' => $report_type,
                'description' => $description,
            ]);

            $conn->prepare("INSERT INTO listing_analytics (listing_id, metric_type, created_at) VALUES (:id, 'report', NOW())")
                ->execute(['id' => $listing_id]);

            pick_notify_role($conn, [ROLE_ADMIN, ROLE_SUPPORT], 'new_report', 'New listing report', 'A public user reported a listing for review.', 'report', $listing_id, 3);

            $message = 'Thank you. The report was submitted for review.';
        }
    }
}

if ($message !== '') {
    echo '<p>' . htmlspecialchars($message) . '</p>';
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Report listing</title></head>
<body>
<form method="POST">
    <?= csrf_input() ?>
    <input type="hidden" name="listing_id" value="<?= (int)$listing_id ?>">
    <input type="text" name="reporter_phone" placeholder="Your phone number (optional)">
    <select name="report_type" required>
        <option value="">Select reason</option>
        <option value="fake_listing">Fake listing</option>
        <option value="unavailable_room">Unavailable room</option>
        <option value="suspicious_behavior">Suspicious behavior</option>
        <option value="wrong_details">Wrong details</option>
    </select>
    <textarea name="description" placeholder="Tell us what is wrong" required></textarea>
    <button type="submit">Submit report</button>
</form>
</body>
</html>
