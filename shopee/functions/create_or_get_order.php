<?php
require_once '../connect.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

date_default_timezone_set('Asia/Jakarta');
$orderNo = $input['order_no'] ?? '';
$name = $input['name'] ?? '';
$userId = (int)($input['user_id'] ?? 0);
$date = date('Y-m-d');

if (!$orderNo || !$name || $userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
    exit;
}

$stmtCheck = $koneksi->prepare("
    SELECT id FROM orders 
    WHERE order_no = ? AND user_id = ?");
$stmtCheck->bind_param("si", $orderNo, $userId);
$stmtCheck->execute();
$existingOrder = $stmtCheck->get_result()->fetch_assoc();

if ($existingOrder) {
    echo json_encode(['success' => true, 'order_id' => $existingOrder['id']]);
    exit;
}

$stmtGetMaxInv = $koneksi->prepare("
    SELECT MAX(CAST(inv AS UNSIGNED)) AS max_inv 
    FROM orders 
    WHERE user_id = ?");
$stmtGetMaxInv->bind_param("i", $userId);
$stmtGetMaxInv->execute();
$result = $stmtGetMaxInv->get_result()->fetch_assoc();
$nextInv = ($result['max_inv'] ?? 0) + 1;

$stmtInsert = $koneksi->prepare("
    INSERT INTO orders (inv, order_no, name, user_id, date) 
    VALUES (?, ?, ?, ?, ?)");
$stmtInsert->bind_param("issis", $nextInv, $orderNo, $name, $userId, $date);

if ($stmtInsert->execute()) {
    $newOrderId = $stmtInsert->insert_id;
    echo json_encode(['success' => true, 'order_id' => $newOrderId]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal membuat order']);
}
?>
