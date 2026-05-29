<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/auth.php';
require_once '../core/middleware.php';
require_once '../core/csrf.php';
require_once '../app/governance/admin_logger.php';
require_once '../app/governance/incident_helper.php';
require_once '../app/governance/notification_helper.php';

require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_SUPPORT]);

$db = new Database();
$conn = $db->connect();

$status = trim($_GET['status'] ?? 'pending');
$type = trim($_GET['type'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_post();
    $report_id = (int)($_POST['report_id'] ?? 0);
    $new_status = trim($_POST['report_status'] ?? 'reviewed');
    $note = trim($_POST['note'] ?? '');

    if ($report_id > 0 && in_array($new_status, ['pending', 'reviewed', 'resolved', 'dismissed'], true)) {
        $stmt = $conn->prepare('SELECT r.*, l.user_id AS landlord_id FROM reports r LEFT JOIN listings l ON r.listing_id = l.id WHERE r.id = :id');
        $stmt->execute(['id' => $report_id]);
        $before = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $severity = pick_report_severity((string)($before['report_type'] ?? ''));
        $stmt = $conn->prepare('UPDATE reports SET report_status = :status, review_note = :note, severity = :severity, reviewed_at = NOW(), reviewed_by = :admin WHERE id = :id');
        $stmt->execute([
            'status' => $new_status,
            'note' => $note,
            'severity' => $severity,
            'admin' => Auth::user(),
            'id' => $report_id,
        ]);

        if (in_array($severity, ['critical', 'high'], true) && in_array($new_status, ['reviewed', 'resolved'], true)) {
            $caseId = pick_create_incident_case_from_report($conn, $before, (int)Auth::user(), $note ?: 'Incident opened from report review');
            $conn->prepare('UPDATE reports SET incident_case_id = :case_id WHERE id = :id')->execute(['case_id' => $caseId, 'id' => $report_id]);

            if (!empty($before['landlord_id'])) {
                $action = pick_handle_repeat_offender($conn, (int)$before['landlord_id']);
                pick_notify_user($conn, (int)$before['landlord_id'], 'incident_case_opened', 'Incident review opened', 'A report related to your listings requires review.', 'incident_case', $caseId, 3);
            }
        }

        log_admin_action($conn, (int)Auth::user(), 'review_report', 'report', $report_id, $note, $before, ['report_status' => $new_status]);
        pick_notify_role($conn, [ROLE_ADMIN, ROLE_SUPPORT], 'report_reviewed', 'Report reviewed', 'A report has been processed in the moderation queue.', 'report', $report_id, 1);
    }
}

$query = <<<SQL
    SELECT r.*, l.title, l.user_id AS landlord_id, u.full_name AS landlord_name, i.id AS incident_id
    FROM reports r
    LEFT JOIN listings l ON r.listing_id = l.id
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN incident_cases i ON i.report_id = r.id
    WHERE 1=1
SQL;
$params = [];
if ($status !== '') {
    $query .= ' AND r.report_status = :status';
    $params['status'] = $status;
}
if ($type !== '') {
    $query .= ' AND r.report_type = :type';
    $params['type'] = $type;
}
$query .= ' ORDER BY r.id DESC LIMIT 200';
$stmt = $conn->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head><title>Reports</title></head>
<body>
<h2>Reports</h2>
<form method="GET">
    <select name="status">
        <option value="">All status</option>
        <?php foreach (['pending','reviewed','resolved','dismissed'] as $opt): ?>
            <option value="<?= $opt ?>" <?= $status === $opt ? 'selected' : '' ?>><?= $opt ?></option>
        <?php endforeach; ?>
    </select>
    <select name="type">
        <option value="">All types</option>
        <?php foreach (['fake_listing','unavailable_room','suspicious_behavior','wrong_details'] as $opt): ?>
            <option value="<?= $opt ?>" <?= $type === $opt ? 'selected' : '' ?>><?= $opt ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Filter</button>
</form>
<table>
<tr><th>ID</th><th>Listing</th><th>Type</th><th>Description</th><th>Status</th><th>Incident</th><th>Action</th></tr>
<?php foreach ($reports as $report): ?>
<tr>
    <td><?= (int)$report['id'] ?></td>
    <td><?= htmlspecialchars(($report['title'] ?? 'Listing #' . $report['listing_id']) . ' / ' . ($report['landlord_name'] ?? 'Unknown')) ?></td>
    <td><?= htmlspecialchars($report['report_type'] ?? '') ?></td>
    <td><?= htmlspecialchars($report['description'] ?? '') ?></td>
    <td><?= htmlspecialchars($report['report_status'] ?? '') ?></td>
    <td><?= htmlspecialchars((string)($report['incident_id'] ?? '')) ?></td>
    <td>
        <form method="POST" style="display:inline;">
            <?= csrf_input() ?>
            <input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
            <input type="hidden" name="report_status" value="reviewed">
            <input type="hidden" name="note" value="Reviewed by admin">
            <button type="submit">Review</button>
        </form>
        <form method="POST" style="display:inline;">
            <?= csrf_input() ?>
            <input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
            <input type="hidden" name="report_status" value="resolved">
            <input type="hidden" name="note" value="Resolved after moderation">
            <button type="submit">Resolve</button>
        </form>
        <form method="POST" style="display:inline;">
            <?= csrf_input() ?>
            <input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
            <input type="hidden" name="report_status" value="dismissed">
            <input type="hidden" name="note" value="Dismissed">
            <button type="submit">Dismiss</button>
        </form>
        <a href="view_listing.php?id=<?= (int)$report['listing_id'] ?>">View listing</a>
    </td>
</tr>
<?php endforeach; ?>
</table>
</body>
</html>
