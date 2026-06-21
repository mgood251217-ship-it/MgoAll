<?php
require_once '../connect.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$listMeterId = (int)($input['list_meter_id'] ?? 0);

if ($listMeterId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'List Meter ID tidak valid']);
    exit;
}

$stmtDelete = $koneksi->prepare("
    DELETE FROM list_meters 
    WHERE list_meter_id = ?");
$stmtDelete->bind_param("i", $listMeterId);

if ($stmtDelete->execute()) {
    echo json_encode(['success' => true, 'message' => 'Meter berhasil dihapus']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus meter']);
}
?>