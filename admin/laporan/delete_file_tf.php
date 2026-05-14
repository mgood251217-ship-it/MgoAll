<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

if (!isset($_POST['transfer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$transfer_id = (int)$_POST['transfer_id'];

// Ambil nama dan alamat toko dari tabel stores
$stmtStore = $koneksi->prepare("SELECT name, address FROM stores WHERE store_id = ?");
$stmtStore->bind_param("i", $store_id);
$stmtStore->execute();
$resultStore = $stmtStore->get_result();
$store = $resultStore->fetch_assoc();
$storeName = $store['name'] ?? 'Nama Toko';
$storeAddress = $store['address'] ?? 'Alamat belum tersedia';

// Ambil nama file dulu
$stmt = $koneksi->prepare("SELECT img, order_id FROM transfers WHERE transfer_id = ?");
$stmt->bind_param("i", $transfer_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
    exit;
}
$storeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $storeName ?? 'Toko');
$row = $result->fetch_assoc();
$imgPath = BASE_PATH . '/assets/img/buktitf/' . $storeName. "/" . $row['img'];

// Hapus dari DB
$delete = $koneksi->prepare("DELETE FROM transfers WHERE transfer_id = ?");
$delete->bind_param("i", $transfer_id);
$delete->execute();

// Hapus file fisik jika ada
if (file_exists($imgPath)) unlink($imgPath);

echo json_encode(['success' => true]);
