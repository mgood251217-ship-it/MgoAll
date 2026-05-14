<?php
require_once '../connect.php';
require_once 'functions.php'; // pastikan fungsi updateOrderTotal ada di sini
header('Content-Type: application/json');
require_once BASE_PATH . '/session.php';

if (empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID item tidak ditemukan']);
    exit;
}

$item_id = (int)$_POST['id'];

// 1️⃣ Ambil calculator_id dari item yang mau dihapus
$stmt = $koneksi->prepare("
    SELECT calculator_id 
    FROM calculator_items 
    WHERE calculator_items_id = ? AND store_id = ?
");
$stmt->bind_param("ii", $item_id, $store_id);
$stmt->execute();
$res = $stmt->get_result();
$item = $res->fetch_assoc();
$stmt->close();

if (!$item) {
    echo json_encode(['success' => false, 'message' => 'Item tidak ditemukan']);
    exit;
}

$calculator_id = (int)$item['calculator_id'];

// 2️⃣ Hapus item
$stmt = $koneksi->prepare("
    DELETE FROM calculator_items 
    WHERE calculator_items_id = ? AND store_id = ?
");
$stmt->bind_param("ii", $item_id, $store_id);
$stmt->execute();
$deleted = $stmt->affected_rows > 0;
$stmt->close();

if (!$deleted) {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus item']);
    exit;
}

// 3️⃣ Panggil fungsi update total
updateOrderTotal($calculator_id, $koneksi);

echo json_encode([
    'success' => true,
    'calculator_id' => $calculator_id,
    'message' => 'Item berhasil dihapus dan total diperbarui'
]);
