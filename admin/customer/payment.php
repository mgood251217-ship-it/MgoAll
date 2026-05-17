<?php
require_once '../connect.php';
require_once 'functions.php';
require_once '../global_functions.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/models/Order.php';
require_once BASE_PATH . '/models/Payment.php';
require_once BASE_PATH . '/models/Project.php';

$orderModel = new Order($koneksi);
$paymentModel = new Payment($koneksi);
$projectModel = new Project($koneksi);

header('Content-Type: application/json');

$isLunas = isset($_POST['lunas_method']);
$data = new stdClass();
$data->order_id = $_POST['order_id'];
$data->store_id = $store_id;

$total = $orderModel->getTotalById($data->order_id);
$paid = $paymentModel->getPaidByOrderId($data->order_id);

if ($isLunas) {
    $nominal = $total - $paid;
    if ($nominal <= 0) {
        echo json_encode(['success' => false, 'message' => 'Sudah Lunas']);
        exit;
    }
} else {
    $nominal = isset($_POST['nominal']) ? (int)$_POST['nominal'] : 0;
    if ($nominal <= 0) {
        echo json_encode(['success' => false, 'message' => 'Nominal Invalid']);
        exit;
    }
}

$total_paid = $paid + $nominal;

$data->nominal = $nominal;
$data->payment_method = $isLunas ? $_POST['lunas_method'] : ($_POST['payment_method'] ?? '');
$data->status = ($total_paid >= $total) ? 'LUNAS' : 'DP';
$data->date = date('Y-m-d H:i:s');

$paymentModel->createPayment($data);
$tanggalAja = date('Y-m-d');
refreshFinance($store_id, $tanggalAja);

$lastProcess = $projectModel->getLastProjectProcessByOrderId($data->order_id);

$newProcess = ($lastProcess === 'PEMBAYARAN') ? 'BELUM DIPROSES' : ($lastProcess ?: 'BELUM DIPROSES');

$data->process = $newProcess;
$projectModel->updateProject($data);

$stmtPay = $koneksi->prepare("
    SELECT status, SUM(nominal) as total, payment_method
    FROM payment 
    WHERE order_id = ? 
    GROUP BY status
");
$stmtPay->bind_param("i", $data->order_id);
$stmtPay->execute();
$resultPay = $stmtPay->get_result();

$totalDP = 0;
$isLunasStatus = false;
$lunas_method = '';

while ($row = $resultPay->fetch_assoc()) {
    if ($row['status'] === 'DP') {
        $totalDP = (int)$row['total'];
    } elseif ($row['status'] === 'LUNAS') {
        $isLunasStatus = true;
        $lunas_method = $row['payment_method'];
    }
}
$stmtPay->close();

$lastStatus = $projectModel->getLastProjectStatusByOrderId($data->order_id);

if ($isLunasStatus) {
    $keteranganBaru = $lastProcess;
} elseif ($totalDP > 0) {
    $keteranganBaru = $lastProcess;
} elseif (!empty($lastStatus)) {
    $keteranganBaru = $lastStatus;
} else {
    $keteranganBaru = '-';
}

if ($isLunasStatus) {
    $totalBayar = "LUNAS " . $lunas_method;
} elseif ($totalDP > 0) {
    $totalBayar = "<div style='font-size: 12px; line-height: 12px;'>" . "DP: " . number_format($totalDP, 0, ',', '.') . " | Sisa : " . number_format($total - $totalDP, 0, ',', '.') . "</div>";
} elseif (!empty($lastStatus)) {
    $totalBayar = htmlspecialchars($lastStatus);
} else {
    echo '-';
}

echo json_encode([
    'success' => true,
    'message' => 'Pembayaran berhasil',
    'status' => $data->status,
    'bayar' => $totalBayar,
    'keterangan' => $keteranganBaru,
    'isLunas' => $isLunasStatus,
]);

exit;

