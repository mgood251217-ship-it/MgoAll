<?php
require_once '../connect.php';
require_once BASE_PATH . '/functions/functions.php';

session_start();
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$user_id = $_SESSION['shopee_users']['user_id'] ?? 0;

$title = $data['title'];
$nominal = $data['nominal'];
$date = $data['date'];
$month = date('Y-m', strtotime($date)) . '-01';

$stmtInsert = $koneksi->prepare("INSERT INTO expenditure (user_id, title, nominal, date) VALUES (?, ?, ?, ?)");
$stmtInsert->bind_param("isds", $user_id, $title, $nominal, $date);
if ($stmtInsert->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Sukses tambah Pengeluaran.']);
    refreshFinanceOmset($user_id, $month);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal Cokk.']);
}

$stmtInsert->close();

?>