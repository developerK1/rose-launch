<?php
require_once '../config/database.php';
require_once '../app/governance/location_helper.php';

$db = new Database();
$conn = $db->connect();

$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$rows = pick_location_alias_search($conn, $query);

header('Content-Type: application/json');
echo json_encode($rows);
