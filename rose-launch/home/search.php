<?php
require_once '../config/database.php';
require_once '../app/governance/listing_governance.php';

$db = new Database();
$conn = $db->connect();

$town_id = isset($_GET['town_id']) ? (int)$_GET['town_id'] : 0;
$province_id = isset($_GET['province_id']) ? (int)$_GET['province_id'] : 0;
$min_price = max(0, (int)($_GET['min_price'] ?? 0));
$max_price = max($min_price, (int)($_GET['max_price'] ?? 999999));
$query = trim($_GET['q'] ?? '');
$property_type = trim($_GET['type'] ?? '');

$sql = <<<SQL
    SELECT l.*, t.name AS town_name, p.name AS province_name, u.trust_score AS landlord_trust, u.account_state
    FROM listings l
    LEFT JOIN towns t ON l.town_id = t.id
    LEFT JOIN provinces p ON l.province_id = p.id
    LEFT JOIN users u ON l.user_id = u.id
    WHERE {WHERE_CLAUSE}
      AND l.price BETWEEN :min_price AND :max_price
SQL;
$sql = str_replace('{WHERE_CLAUSE}', pick_listing_public_where('l', 'u'), $sql);

$params = [
    'min_price' => $min_price,
    'max_price' => $max_price,
];

if ($town_id > 0) {
    $sql .= ' AND l.town_id = :town_id';
    $params['town_id'] = $town_id;
}
if ($province_id > 0) {
    $sql .= ' AND l.province_id = :province_id';
    $params['province_id'] = $province_id;
}
if ($property_type !== '') {
    $sql .= ' AND l.property_type = :property_type';
    $params['property_type'] = $property_type;
}
$sql .= pick_listing_public_search_clause('l', $query, $params);
$sql .= " ORDER BY " . pick_listing_public_order_sql('l', 'u');

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head><title>Search</title></head>
<body>
<h2>Search rooms</h2>
<form method="GET">
    <input name="q" placeholder="Search" value="<?= htmlspecialchars($query) ?>">
    <input name="min_price" type="number" placeholder="Min price" value="<?= (int)$min_price ?>">
    <input name="max_price" type="number" placeholder="Max price" value="<?= (int)$max_price ?>">
    <input name="town_id" type="number" placeholder="Town ID" value="<?= (int)$town_id ?>">
    <input name="province_id" type="number" placeholder="Province ID" value="<?= (int)$province_id ?>">
    <select name="type">
        <option value="">All types</option>
        <?php foreach (['backroom','student_room','cottage','house_room','shared_room','bachelor','guest_house','other'] as $type): ?>
            <option value="<?= $type ?>" <?= $property_type === $type ? 'selected' : '' ?>><?= $type ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Search</button>
</form>
<?php foreach ($results as $listing): ?>
    <div style="margin-bottom:12px;">
        <h4><?= htmlspecialchars($listing['title']) ?> - R<?= htmlspecialchars((string)$listing['price']) ?></h4>
        <p><?= htmlspecialchars(trim(($listing['area'] ?? '') . ', ' . ($listing['town_name'] ?? ''))) ?></p>
        <p><?= pick_listing_badge_visible($listing) ? 'Identity Reviewed' : 'Reviewed by platform' ?></p>
        <a href="view.php?id=<?= (int)$listing['id'] ?>">Open listing</a>
    </div>
<?php endforeach; ?>
</body>
</html>
