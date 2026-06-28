<?php
require_once '../connect.php';
require_once BASE_PATH . '/functions/user_validation.php';

header('Content-Type: application/json');

$user_id = $_SESSION['shopee_users']['user_id'] ?? 0;
$data = json_decode(file_get_contents('php://input'), true);

if (!$user_id || !$data) {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
    exit;
}

$order_id = (int)($data['order_id'] ?? 0);
$order_no = trim($data['order_no'] ?? '');
$name = trim($data['name'] ?? '');
$info = trim($data['info'] ?? '');

if (!$order_id || !$order_no || !$name) {
    echo json_encode(['success' => false, 'message' => 'Order ID, Order No, dan Nama wajib diisi']);
    exit;
}

$stmtCheck = $koneksi->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
$stmtCheck->bind_param("ii", $order_id, $user_id);
$stmtCheck->execute();
if (!$stmtCheck->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'Order tidak ditemukan']);
    exit;
}

// Update order
$stmtUpdate = $koneksi->prepare("UPDATE orders SET order_no = ?, name = ?, info = ? WHERE id = ? AND user_id = ?");
$stmtUpdate->bind_param("sssii", $order_no, $name, $info, $order_id, $user_id);

if ($stmtUpdate->execute()) {
    echo json_encode(['success' => true, 'message' => 'Order berhasil diupdate']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal mengupdate order']);
}
?>
