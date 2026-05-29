<?php
require_once '../config/database.php';
require_once '../core/session.php';
require_once '../core/auth.php';
require_once '../core/middleware.php';
require_once '../core/csrf.php';
require_once '../app/governance/location_helper.php';
require_once '../app/governance/media_helper.php';
require_once '../app/governance/notification_helper.php';
require_once '../app/governance/trust_helper.php';

require_role(ROLE_LANDLORD);

$db = new Database();
$conn = $db->connect();
$user_id = Auth::user();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_post();

    $province_name = trim($_POST['province_name'] ?? '');
    $town_name = trim($_POST['town_name'] ?? '');
    $area = trim($_POST['area'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $contact = trim($_POST['contact'] ?? '');
    $property_type = trim($_POST['property_type'] ?? 'other');

    if ($province_name === '' || $town_name === '' || $area === '' || $title === '' || $description === '' || $price <= 0 || $contact === '') {
        $error = 'Please complete all fields.';
    } else {
        $province_id = pick_ensure_province_id($conn, $province_name);
        $town_id = pick_ensure_town_id($conn, $town_name, $province_id);

        $stmt = $conn->prepare(<<<SQL
            INSERT INTO listings (
                user_id,
                province_id,
                town_id,
                area,
                title,
                description,
                price,
                contact_number,
                property_type,
                moderation_status,
                listing_status,
                verification_status,
                trust_score,
                expires_at,
                last_confirmed_at,
                created_at,
                updated_at
            ) VALUES (
                :user_id,
                :province_id,
                :town_id,
                :area,
                :title,
                :description,
                :price,
                :contact_number,
                :property_type,
                'pending',
                'inactive',
                'unverified',
                50,
                DATE_ADD(NOW(), INTERVAL 30 DAY),
                NOW(),
                NOW(),
                NOW()
            )
        SQL);
        $stmt->execute([
            'user_id' => $user_id,
            'province_id' => $province_id,
            'town_id' => $town_id,
            'area' => $area,
            'title' => $title,
            'description' => $description,
            'price' => $price,
            'contact_number' => $contact,
            'property_type' => $property_type,
        ]);

        $listingId = (int)$conn->lastInsertId();
        if (!empty($_FILES['images']['name'][0])) {
            store_listing_images($conn, $listingId, $_FILES['images'], pick_storage_root());
        }

        pick_notify_user(
            $conn,
            $user_id,
            'listing_created',
            'Listing submitted',
            'Your listing was submitted and is waiting for review.',
            'listing',
            $listingId,
            2
        );

        $message = 'Listing submitted for review.';
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Create Listing</title></head>
<body>
<h2>Create Listing</h2>
<?php if ($error !== ''): ?><p style="color:red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($message !== ''): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>
<form method="POST" enctype="multipart/form-data">
    <?= csrf_input() ?>
    <input name="province_name" placeholder="Province" required>
    <input name="town_name" placeholder="Town / City" required>
    <input name="area" placeholder="Area / section / suburb" required>
    <input name="title" placeholder="Title" required>
    <select name="property_type" required>
        <option value="other">Other</option>
        <option value="backroom">Backroom</option>
        <option value="student_room">Student room</option>
        <option value="cottage">Cottage</option>
        <option value="house_room">House room</option>
        <option value="shared_room">Shared room</option>
        <option value="bachelor">Bachelor</option>
        <option value="guest_house">Guest house</option>
    </select>
    <textarea name="description" placeholder="Description" required></textarea>
    <input name="price" type="number" step="0.01" placeholder="Price" required>
    <input name="contact" placeholder="Contact Number" required>
    <input type="file" name="images[]" multiple accept="image/*">
    <button type="submit">Submit Listing</button>
</form>
<p><a href="index.php">Back</a></p>
</body>
</html>
