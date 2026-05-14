<?php

require_once '../connect.php';
require_once BASE_PATH . '/functions/functions.php';
session_start();
header('Content-Type: application/json');
$data = json_decode(file_get_contents("php://input"), true);
$expenditure_id = $data['expenditure_id'];

$stmtDelete = $koneksi->prepare("DELETE FROM expenditure WHERE expenditure_id = ?");
$stmtDelete->bind_param("i", $expenditure_id);
if ($stmtDelete->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Sukses hapus Pengeluaran.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal hapus Pengeluaran.']);
}

?>