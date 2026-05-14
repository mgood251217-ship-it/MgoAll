<?php
require_once '../connect.php';
require_once 'functions.php';
require_once '../global_functions.php';
require_once BASE_PATH . '/session.php';

header('Content-Type: application/json');

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

$isLunas = isset($_POST['lunas_method']);
$payment_method = $isLunas ? $_POST['lunas_method'] : ($_POST['payment_method'] ?? '');

if (!$payment_method || !$order_id) {
    echo json_encode(['success' => false, 'message' => 'Data Kosong']);
    exit;
}

// Ambil total order dan total payment yang sudah dilakukan
$total = 0;
$paid = 0;

$stmt = $koneksi->prepare("SELECT total FROM orders WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();

$stmt = $koneksi->prepare("SELECT COALESCE(SUM(nominal), 0) FROM payment WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$stmt->bind_result($paid);
$stmt->fetch();
$stmt->close();

// Hitung nominal pembayaran saat ini
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
$status = ($total_paid >= $total) ? 'LUNAS' : 'DP';


$tanggalSekarang = date('Y-m-d H:i:s');

// Simpan ke tabel payment
$stmt = $koneksi->prepare("INSERT INTO payment (order_id, store_id, nominal, payment_method, status, date) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iiisss", $order_id, $store_id, $nominal, $payment_method, $status, $tanggalSekarang);
$stmt->execute();
$stmt->close();
$tanggalAja = date('Y-m-d');
refreshFinance($store_id, $tanggalAja);

// Cek proses terakhir di projects
$stmt = $koneksi->prepare("SELECT process FROM projects WHERE order_id = ? ORDER BY date DESC LIMIT 1");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$stmt->bind_result($lastProcess);
$stmt->fetch();
$stmt->close();

$newProcess = ($lastProcess === 'PEMBAYARAN') ? 'BELUM DIPROSES' : ($lastProcess ?: 'BELUM DIPROSES');

// Tambahkan ke projects log
// $stmt = $koneksi->prepare("INSERT INTO projects (order_id, status, process, user_id, date) VALUES (?, ?, ?, 0, ?)");
// $stmt->bind_param("isss", $order_id, $status, $newProcess, $tanggalSekarang);
// $stmt->execute();
// $stmt->close();

// Tambahkan ke projects log
$stmt = $koneksi->prepare("UPDATE projects SET status = ?, process = ?, date = ? WHERE order_id = ?");
$stmt->bind_param("sssi", $status, $newProcess, $tanggalSekarang, $order_id);
$stmt->execute();
$stmt->close();

// Hitung ulang status pembayaran (untuk update tampilan)
$stmtPay = $koneksi->prepare("
    SELECT status, SUM(nominal) as total, payment_method
    FROM payment 
    WHERE order_id = ? 
    GROUP BY status
");
$stmtPay->bind_param("i", $order_id);
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

// Ambil project terakhir untuk ditampilkan
$stmtProj = $koneksi->prepare("SELECT status, process FROM projects WHERE order_id = ? ORDER BY date DESC LIMIT 1");
$stmtProj->bind_param("i", $order_id);
$stmtProj->execute();
$stmtProj->bind_result($projectStatus, $projectProcess);
$stmtProj->fetch();
$stmtProj->close();



// Tentukan keterangan untuk ditampilkan di UI
if ($isLunasStatus) {
    $keteranganBaru = $projectProcess;
} elseif ($totalDP > 0) {
    $keteranganBaru =$projectProcess;
} elseif (!empty($projectStatus)) {
    $keteranganBaru = $projectStatus;
} else {
    $keteranganBaru = '-';
}

if ($isLunasStatus) {
    $totalBayar = "LUNAS " . $lunas_method;
} elseif ($totalDP > 0) {
    $totalBayar = "<div style='font-size: 12px; line-height: 12px;'>" . "DP: " . number_format($totalDP, 0, ',', '.') . " | Sisa : " . number_format($total - $totalDP, 0, ',', '.') . "</div>";
} elseif (!empty($projectStatus)) {
    $totalBayar = htmlspecialchars($projectStatus);
} else {
    echo '-';
}

// Kirim respons ke frontend
echo json_encode([
    'success' => true,
    'message' => 'Pembayaran berhasil',
    'status' => $status,
    'bayar' => $totalBayar,
    'keterangan' => $keteranganBaru,
    'isLunas' => $isLunasStatus,
]);

exit;

