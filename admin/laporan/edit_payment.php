<?php
require_once '../connect.php';
require_once '../global_functions.php';
require_once BASE_PATH . '/session.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Kesalahan Login Administrator']);
    exit;
}

$administrator_id = startEnk('dek', $_SESSION['admin_logged_in']['administrator_id']);


$date = date("Y-m-d H:i:s");

$payment_id = isset($_POST['payment_id']) ? (int)$_POST['payment_id'] : 0;
$order_id   = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$nominal    = isset($_POST['nominal']) ? (int)$_POST['nominal'] : 0;
$method     = isset($_POST['payment_method']) ? strtoupper(trim($_POST['payment_method'])) : '';
$tanggal    = isset($_POST['tanggal']) ? trim($_POST['tanggal']) : '';
$keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : '';
$tanggalOld = strtotime($tanggal);
$tanggalcek = date('Y-m-d', $tanggalOld);

if ($payment_id <= 0 || $order_id <= 0 || $nominal <= 0 || empty($method) || empty($tanggal)) {
    die('Data tidak lengkap atau tidak valid.');
}

$tanggal = str_replace('T', ' ', $tanggal) . ':00';

// ✅ Insert activity
$title = "UBAH PEMBAYARAN";
$message = "";
$done = 0;

$stmtOrder = $koneksi->prepare("SELECT customer_name, nomorator FROM orders WHERE order_id = ?");
$stmtOrder->bind_param("i", $order_id);
$stmtOrder->execute();
$resultOrder = $stmtOrder->get_result();
$order = $resultOrder->fetch_assoc();
$orderName = $order['customer_name'];
$orderNomorator = $order['nomorator'];

$stmtPayment = $koneksi->prepare("SELECT nominal, payment_method, date FROM payment WHERE order_id = ?");
$stmtPayment->bind_param("i", $order_id);
$stmtPayment->execute();
$resultPayment = $stmtPayment->get_result();
$payment = $resultPayment->fetch_assoc();
$paymentNominal = $payment['nominal'] ?? '';
$paymentPaymentmethod = $payment['payment_method'] ?? '';
$paymentDateOld = strtotime($payment['date']);
$paymentDate = date('Y-m-d', $paymentDateOld);

if ($method != $paymentPaymentmethod && $paymentNominal != $nominal && $paymentDate != $tanggalcek) {
    $message = "UBAH METODE PEMBAYARAN, NOMINAL, DAN TANGGAL BAYAR DARI: \n"
                . $paymentNominal . " => ". $nominal . "\n"
                . $paymentPaymentmethod . " => ". $method . "\n"
                . $paymentDate . " => ". $tanggalcek . "\n"
                . "NAMA ". $orderName . " NOMORATOR " . $orderNomorator
                ;
}elseif ($method != $paymentPaymentmethod && $paymentNominal != $nominal) {
    $message = "UBAH METODE PEMBAYARAN DAN NOMINAL DARI: \n"
                . $paymentNominal . " => ". $nominal . "\n"
                . $paymentPaymentmethod . " => ". $method . "\n"
                . "NAMA ". $orderName . " NOMORATOR " . $orderNomorator
                ;
}elseif ($paymentNominal != $nominal && $paymentDate != $tanggalcek) {
    $message = "UBAH NOMINAL, DAN TANGGAL BAYAR DARI: \n"
                . $paymentNominal . " => ". $nominal . "\n"
                . $paymentDate . " => ". $tanggalcek . "\n"
                . "NAMA ". $orderName . " NOMORATOR " . $orderNomorator
                ;
}elseif ($method != $paymentPaymentmethod && $paymentDate != $tanggalcek) {
    $message = "UBAH METODE PEMBAYARAN, DAN TANGGAL BAYAR DARI: \n"
                . $paymentPaymentmethod . " => ". $method . "\n"
                . $paymentDate . " => ". $tanggalcek . "\n"
                . "NAMA ". $orderName . " NOMORATOR " . $orderNomorator
                ;
}elseif ($method != $paymentPaymentmethod) {
    $message = "UBAH METODE PEMBAYARAN DARI: \n"
                . $paymentPaymentmethod . " => ". $method . "\n"
                . "NAMA ". $orderName . " NOMORATOR " . $orderNomorator
                ;
}elseif ($paymentNominal != $nominal) {
    $message = "UBAH NOMINAL DARI: \n"
                . $paymentNominal . " => ". $nominal . "\n"
                . "NAMA ". $orderName . " NOMORATOR " . $orderNomorator
                ;
}elseif ($paymentDate != $tanggalcek) {
    $message = "UBAH NOMINAL, DAN TANGGAL BAYAR DARI: \n"
                . $paymentDate . " => ". $tanggalcek . "\n"
                . "NAMA ". $orderName . " NOMORATOR " . $orderNomorator
                ;
}else {
    $message = "";
}

if ($message != "") {
    $insert = $koneksi->prepare("
                        INSERT INTO activity
                        (store_id, title, message, information, date, order_id, done, administrator_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $insert->bind_param("issssiii", $store_id, $title, $message, $keterangan, $date, $order_id, $done, $administrator_id);
    $insert->execute();
    $insert->close();
}


// ✅ Update payment yang dimaksud
$update = $koneksi->prepare("UPDATE payment SET nominal = ?, payment_method = ?, date = ?, status = 'DP' WHERE payment_id = ?");
$update->bind_param("issi", $nominal, $method, $tanggal, $payment_id);
$update->execute();
$update->close();

// 💰 Hitung total semua pembayaran untuk order ini
$totalPembayaran = 0;
$payments = $koneksi->query("SELECT payment_id, nominal FROM payment WHERE order_id = $order_id");
$paymentIds = [];

while ($row = $payments->fetch_assoc()) {
    $totalPembayaran += (int)$row['nominal'];
    $paymentIds[] = (int)$row['payment_id'];
}

// 🎯 Ambil total dari order
$orderResult = $koneksi->query("SELECT total FROM orders WHERE order_id = $order_id LIMIT 1");
$orderTotal = 0;
if ($orderRow = $orderResult->fetch_assoc()) {
    $orderTotal = (int)$orderRow['total'];
}

// 🔄 Update status pembayaran
if ($totalPembayaran < $orderTotal) {
    // Semua payment status = DP
    $koneksi->query("UPDATE payment SET status = 'DP' WHERE order_id = $order_id");
} else {
    // Semua DP dulu
    $koneksi->query("UPDATE payment SET status = 'DP' WHERE order_id = $order_id");

    // Ambil payment_id terbesar (terbaru) untuk order ini
    $last = $koneksi->query("SELECT payment_id FROM payment WHERE order_id = $order_id ORDER BY payment_id DESC LIMIT 1");
    if ($lastRow = $last->fetch_assoc()) {
        $lastId = (int)$lastRow['payment_id'];
        $koneksi->query("UPDATE payment SET status = 'LUNAS' WHERE payment_id = $lastId");
        $tanggalAja = date("Y-m-d");
        refreshFinance($store_id, $tanggalAja);
    }
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
