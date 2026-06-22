<?php
require_once '../connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}
$input = $input ?? [];

$orderNo = trim($input['order_no'] ?? '');
$name = trim($input['name'] ?? '');
$date = trim($input['date'] ?? date('Y-m-d'));
$userId = $_SESSION['shopee_users']['user_id'] ?? 0;

if (!$orderNo || !$name) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order No dan Nama harus diisi']);
    exit;
}

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User tidak terautentikasi']);
    exit;
}

$stmtCheck = $koneksi->prepare("
    SELECT id FROM orders 
    WHERE order_no = ? AND user_id = ? AND date = ?");
$stmtCheck->bind_param("sis", $orderNo, $userId, $date);
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
