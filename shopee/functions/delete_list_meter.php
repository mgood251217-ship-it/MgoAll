<?php
require_once '../connect.php';
require_once BASE_PATH . '/functions/functions.php';

session_start();
header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) $data = $_POST;

    $list_meter_id = (int)($data['list_meter_id'] ?? 0);

    if ($list_meter_id <= 0) {
        throw new Exception('List Meter ID tidak valid');
    }

    // ✅ START TRANSACTION
    $koneksi->begin_transaction();

    // ✅ Ambil meter_id
    $stmtMeterId = $koneksi->prepare("
        SELECT meter_id 
        FROM list_meters 
        WHERE list_meter_id = ?
    ");
    $stmtMeterId->bind_param("i", $list_meter_id);
    $stmtMeterId->execute();
    $resultMeterId = $stmtMeterId->get_result()->fetch_assoc();
    $stmtMeterId->close();

    if (!$resultMeterId) {
        throw new Exception('Data list meter tidak ditemukan');
    }

    $meter_id = $resultMeterId['meter_id'];

    // ✅ Ambil product_id & date
    $stmtProduk = $koneksi->prepare("
        SELECT product_id, date 
        FROM meters 
        WHERE meter_id = ?
    ");
    $stmtProduk->bind_param("i", $meter_id);
    $stmtProduk->execute();
    $resultProduk = $stmtProduk->get_result()->fetch_assoc();
    $stmtProduk->close();

    if (!$resultProduk) {
        throw new Exception('Data meter tidak ditemukan');
    }

    // ✅ Delete
    $stmtDelete = $koneksi->prepare("
        DELETE FROM list_meters 
        WHERE list_meter_id = ?
    ");
    $stmtDelete->bind_param("i", $list_meter_id);
    $stmtDelete->execute();

    if ($stmtDelete->affected_rows === 0) {
        throw new Exception('Gagal menghapus data');
    }

    $stmtDelete->close();

    // ✅ Update total
    updateTotalMeteran($resultProduk['product_id'], $resultProduk['date']);

    // ✅ Ambil total terbaru
    $stmtGetTotal = $koneksi->prepare("
        SELECT total 
        FROM meters 
        WHERE product_id = ? AND date = ?
    ");
    $stmtGetTotal->bind_param("is", $resultProduk['product_id'], $resultProduk['date']);
    $stmtGetTotal->execute();
    $resultTotal = $stmtGetTotal->get_result()->fetch_assoc();
    $updatedTotal = $resultTotal['total'] ?? 0;
    $stmtGetTotal->close();

    // ✅ COMMIT
    $koneksi->commit();

    echo json_encode([
        'success' => true,
        'message' => 'List Meter berhasil dihapus',
        'updated_total' => $updatedTotal
    ]);

} catch (Exception $e) {

    // ❌ ROLLBACK kalau error
    if ($koneksi->errno === 0) {
        $koneksi->rollback();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}