<?php
require_once '../connect.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$orderId = (int)($input['order_id'] ?? 0);
$productId = (int)($input['product_id'] ?? 0);
$value = (float)($input['value'] ?? 0);

if ($orderId <= 0 || $productId <= 0 || $value <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parameter tidak valid']);
    exit;
}

// Insert into list_meters
$stmtInsert = $koneksi->prepare("
    INSERT INTO list_meters (order_id, product_id, value) 
    VALUES (?, ?, ?)");
$stmtInsert->bind_param("iid", $orderId, $productId, $value);

if ($stmtInsert->execute()) {
    echo json_encode(['success' => true, 'message' => 'Meter berhasil ditambahkan']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal menambah meter']);
}
?>
