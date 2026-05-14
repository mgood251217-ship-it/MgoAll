<?php
session_start();
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

$type = $_GET['type'] ?? '';

if (!$store_id || !$type) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT product_id, name, price, unit_type FROM products WHERE store_id = ? AND type = ? AND name != 'KISS CUT' AND name != 'DIE CUT'";
$stmt = $koneksi->prepare($sql);
$stmt->bind_param("is", $store_id, $type);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
        $products[] = [
            'product_id' => $row['product_id'], // <-- ini penting!
            'name' => $row['name'],
            'price' => (float)$row['price'],
            'unit_type' => trim($row['unit_type']),
        ];
}
$stmt->close();

header('Content-Type: application/json');
echo json_encode($products);
