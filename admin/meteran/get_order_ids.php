<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';



// Gunakan GET untuk filter
$start_date_full = isset($_GET['start_date']) ? $_GET['start_date'] . " 00:00:00" : date('Y-m-d 00:00:00');
$end_date_full   = isset($_GET['end_date']) ? $_GET['end_date'] . " 23:59:59" : date('Y-m-d 23:59:59');

$start_date_only = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date_only   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Ambil order_id dari orders
$order_ids = [];
$stmtOrder = $koneksi->prepare("SELECT order_id FROM orders WHERE store_id = ? AND date BETWEEN ? AND ?");
$stmtOrder->bind_param("iss", $store_id, $start_date_full, $end_date_full);
$stmtOrder->execute();
$resOrder = $stmtOrder->get_result();
while ($row = $resOrder->fetch_assoc()) {
    $order_ids[] = $row['order_id'];
}
$stmtOrder->close();

// Ambil nama dan alamat toko dari tabel stores
$stmtStore = $koneksi->prepare("SELECT name, address FROM stores WHERE store_id = ?");
$stmtStore->bind_param("i", $storeIdTransaksi);
$stmtStore->execute();
$resultStore = $stmtStore->get_result();
$store = $resultStore->fetch_assoc();
$storeName = $store['name'] ?? 'Nama Toko';
$storeAddress = $store['address'] ?? 'Alamat belum tersedia';

?>