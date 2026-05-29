<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/auth.php';
require_once '../core/middleware.php';
require_once '../core/csrf.php';

require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_SUPPORT]);

$db = new Database();
$conn = $db->connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_post();
    $caseId = (int)($_POST['case_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'under_review');
    $note = trim($_POST['note'] ?? '');
    if ($caseId > 0) {
        $stmt = $conn->prepare("UPDATE incident_cases SET status = :status, resolution_note = CASE WHEN :status = 'resolved' THEN :note ELSE resolution_note END, resolved_at = CASE WHEN :status = 'resolved' THEN NOW() ELSE resolved_at END, updated_at = NOW(), resolved_by = CASE WHEN :status = 'resolved' THEN :admin ELSE resolved_by END WHERE id = :id");
        $stmt->execute([
            'status' => $status,
            'note' => $note,
            'admin' => Auth::user(),
            'id' => $caseId,
        ]);
    }
}

$cases = $conn->query(<<<SQL
    SELECT c.*, r.report_type, r.report_status, u.full_name AS landlord_name, l.title AS listing_title
    FROM incident_cases c
    LEFT JOIN reports r ON c.report_id = r.id
    LEFT JOIN users u ON c.landlord_id = u.id
    LEFT JOIN listings l ON c.listing_id = l.id
    ORDER BY c.id DESC LIMIT 200
SQL)->fetchAll(PDO::FETCH_ASSOC);

include 'partials/header.php';
include 'partials/sidebar.php';
?>
<h1>Incident Cases</h1>
<table>
<tr><th>ID</th><th>Landlord</th><th>Listing</th><th>Severity</th><th>Status</th><th>Action</th></tr>
<?php foreach ($cases as $case): ?>
<tr>
    <td><?= (int)$case['id'] ?></td>
    <td><?= htmlspecialchars($case['landlord_name'] ?? '') ?></td>
    <td><?= htmlspecialchars($case['listing_title'] ?? '') ?></td>
    <td><?= htmlspecialchars($case['severity'] ?? '') ?></td>
    <td><?= htmlspecialchars($case['status'] ?? '') ?></td>
    <td>
        <form method="POST">
            <?= csrf_input() ?>
            <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
            <select name="status">
                <?php foreach (['under_review','monitor','resolved','closed'] as $opt): ?>
                    <option value="<?= $opt ?>"><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
            <input name="note" placeholder="Resolution note">
            <button type="submit">Update</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</table>
<?php include 'partials/footer.php'; 