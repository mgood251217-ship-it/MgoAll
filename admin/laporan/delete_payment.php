<?php
require_once '../connect.php'; // ganti sesuai path
require_once BASE_PATH . '/session.php';

header('Content-Type: application/json');



$date = date("Y-m-d H:i:s");
$administrator_id = startEnk('dek',  $_SESSION['admin_logged_in']['administrator_id']);
$payment_id = isset($_POST['payment_id']) ? (int)$_POST['payment_id'] : 0;
$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$keterangan = isset($_POST['keterangan_hapus']) ? trim($_POST['keterangan_hapus']) : '';

if ($payment_id <= 0 || $order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
    exit;
}

$stmtOrder = $koneksi->prepare("SELECT customer_name, nomorator FROM orders WHERE order_id = ?");
$stmtOrder->bind_param("i", $order_id);
$stmtOrder->execute();
$resultOrder = $stmtOrder->get_result();
$order = $resultOrder->fetch_assoc();
$orderName = $order['customer_name'];
$orderNomorator = $order['nomorator'];

$title = "HAPUS PEMBAYARAN";
$message = "HAPUS PEMBAYARAN UNTUK ORDERAN DENGAN NAMA " . $orderName . " NOMORATOR " . $orderNomorator;
$done = 0;

if ($message != "") {
    $insert = $koneksi->prepare("
                        INSERT INTO activity
                        (store_id, title, message, information, date, order_id, done, administrator_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $insert->bind_param("issssiii", $store_id, $title, $message, $keterangan, $date, $order_id, $done, $administrator_id);
    $insert->execute();
    $insert->close();
}

// Hapus pembayaran
$stmt = $koneksi->prepare("DELETE FROM payment WHERE payment_id = ? AND order_id = ?");
$stmt->bind_param("ii", $payment_id, $order_id);
$success = $stmt->execute();
$stmt->close();
$tanggalAja = date("Y-m-d");
refreshFinance($store_id, $tanggalAja);

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus dari database.']);
}
