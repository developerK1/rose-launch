<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/auth.php';
require_once '../core/middleware.php';
require_once '../core/csrf.php';
require_once '../app/governance/listing_governance.php';
require_once '../app/governance/location_helper.php';
require_once '../app/governance/media_helper.php';
require_once '../app/governance/notification_helper.php';

require_role(ROLE_LANDLORD);

$db = new Database();
$conn = $db->connect();
$user_id = Auth::user();
$listing_id = (int)($_GET['id'] ?? $_POST['listing_id'] ?? 0);

$stmt = $conn->prepare('SELECT * FROM listings WHERE id = :id AND user_id = :user_id LIMIT 1');
$stmt->execute(['id' => $listing_id, 'user_id' => $user_id]);
$listing = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$listing) {
    die('Listing not found.');
}

$locStmt = $conn->prepare('SELECT p.name AS province_name, t.name AS town_name FROM listings l LEFT JOIN provinces p ON l.province_id = p.id LEFT JOIN towns t ON l.town_id = t.id WHERE l.id = :id');
$locStmt->execute(['id' => $listing_id]);
$locationNames = $locStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_post();

    $new = [
        'province_name' => trim($_POST['province_name'] ?? ''),
        'town_name' => trim($_POST['town_name'] ?? ''),
        'area' => trim($_POST['area'] ?? $listing['area']),
        'title' => trim($_POST['title'] ?? $listing['title']),
        'description' => trim($_POST['description'] ?? $listing['description']),
        'price' => (float)($_POST['price'] ?? $listing['price']),
        'contact_number' => trim($_POST['contact'] ?? $listing['contact_number']),
        'property_type' => trim($_POST['property_type'] ?? $listing['property_type']),
    ];

    if ($new['province_name'] === '' || $new['town_name'] === '' || $new['area'] === '' || $new['title'] === '' || $new['description'] === '' || $new['price'] <= 0 || $new['contact_number'] === '') {
        $error = 'Please complete all fields.';
    } else {
        $province_id = pick_ensure_province_id($conn, $new['province_name']);
        $town_id = pick_ensure_town_id($conn, $new['town_name'], $province_id);

        $before = $listing;
        $after = [
            'province_id' => $province_id,
            'town_id' => $town_id,
            'area' => $new['area'],
            'title' => $new['title'],
            'description' => $new['description'],
            'price' => $new['price'],
            'contact_number' => $new['contact_number'],
            'property_type' => $new['property_type'],
        ];

        $changes = pick_listing_revision_pairs($before, $after);
        $requires_requeue = pick_listing_requires_requeue($before, $after);

        $stmt = $conn->prepare(<<<SQL
            UPDATE listings
            SET province_id = :province_id,
                town_id = :town_id,
                area = :area,
                title = :title,
                description = :description,
                price = :price,
                contact_number = :contact_number,
                property_type = :property_type,
                moderation_status = CASE WHEN :requires_requeue = 1 THEN 'pending' ELSE moderation_status END,
                listing_status = CASE WHEN :requires_requeue = 1 THEN 'inactive' ELSE listing_status END,
                verification_status = CASE WHEN :requires_requeue = 1 THEN 'reverification_required' ELSE verification_status END,
                moderation_reviewed_at = CASE WHEN :requires_requeue = 1 THEN NULL ELSE moderation_reviewed_at END,
                last_reviewed_by = CASE WHEN :requires_requeue = 1 THEN NULL ELSE last_reviewed_by END,
                updated_at = NOW()
            WHERE id = :id AND user_id = :user_id
        SQL);
        $stmt->execute([
            'province_id' => $province_id,
            'town_id' => $town_id,
            'area' => $new['area'],
            'title' => $new['title'],
            'description' => $new['description'],
            'price' => $new['price'],
            'contact_number' => $new['contact_number'],
            'property_type' => $new['property_type'],
            'requires_requeue' => $requires_requeue ? 1 : 0,
            'id' => $listing_id,
            'user_id' => $user_id,
        ]);

        foreach ($changes as $change) {
            $stmt = $conn->prepare(<<<SQL
                INSERT INTO listing_revisions (
                    listing_id,
                    field_name,
                    old_value,
                    new_value,
                    modified_by,
                    created_at
                ) VALUES (
                    :listing_id,
                    :field_name,
                    :old_value,
                    :new_value,
                    :modified_by,
                    NOW()
                )
            SQL);
            $stmt->execute([
                'listing_id' => $listing_id,
                'field_name' => $change['field_name'],
                'old_value' => $change['old_value'],
                'new_value' => $change['new_value'],
                'modified_by' => $user_id,
            ]);
        }

        if (!empty($_FILES['images']['name'][0])) {
            store_listing_images($conn, $listing_id, $_FILES['images'], pick_storage_root(), 0, true);
            $conn->prepare("UPDATE listings SET moderation_status = 'pending', listing_status = 'inactive', verification_status = 'reverification_required' WHERE id = :id")
                ->execute(['id' => $listing_id]);
        }

        pick_notify_user(
            $conn,
            $user_id,
            $requires_requeue ? 'listing_reapproval_required' : 'listing_updated',
            $requires_requeue ? 'Listing re-queued' : 'Listing updated',
            $requires_requeue ? 'Important changes require review before the listing returns to public visibility.' : 'Your listing was saved.',
            'listing',
            $listing_id,
            2
        );

        $message = $requires_requeue ? 'Important changes were saved and the listing was re-queued for review.' : 'Listing updated.';

        $stmt = $conn->prepare('SELECT * FROM listings WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['id' => $listing_id, 'user_id' => $user_id]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Edit Listing</title></head>
<body>
<h2>Edit Listing</h2>
<?php if ($error !== ''): ?><p style="color:red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($message !== ''): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>
<form method="POST" enctype="multipart/form-data">
    <?= csrf_input() ?>
    <input type="hidden" name="listing_id" value="<?= (int)$listing['id'] ?>">
    <input name="province_name" value="<?= htmlspecialchars((string)($locationNames['province_name'] ?? '')) ?>" placeholder="Province" required>
    <input name="town_name" value="<?= htmlspecialchars((string)($locationNames['town_name'] ?? '')) ?>" placeholder="Town / City" required>
    <input name="area" value="<?= htmlspecialchars($listing['area'] ?? '') ?>" required>
    <input name="title" value="<?= htmlspecialchars($listing['title'] ?? '') ?>" required>
    <select name="property_type" required>
        <?php foreach (['other','backroom','student_room','cottage','house_room','shared_room','bachelor','guest_house'] as $type): ?>
            <option value="<?= $type ?>" <?= ($listing['property_type'] ?? 'other') === $type ? 'selected' : '' ?>><?= $type ?></option>
        <?php endforeach; ?>
    </select>
    <textarea name="description" required><?= htmlspecialchars($listing['description'] ?? '') ?></textarea>
    <input name="price" type="number" step="0.01" value="<?= htmlspecialchars((string)($listing['price'] ?? '0')) ?>" required>
    <input name="contact" value="<?= htmlspecialchars($listing['contact_number'] ?? '') ?>" required>
    <input type="file" name="images[]" multiple accept="image/*">
    <button type="submit">Save changes</button>
</form>
<p><a href="index.php">Back</a></p>
</body>
</html>
