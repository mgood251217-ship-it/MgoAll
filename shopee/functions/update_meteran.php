<?php
session_start();
require_once '../connect.php';
require_once BASE_PATH . '/functions/functions.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) $data = $_POST;

    $product_id = (int)($data['product_id'] ?? 0);
    $value      = (float)($data['value'] ?? 0);
    $date       = $data['date'] ?? '';

    $user_id    = $_SESSION['shopee_users']['user_id'] ?? 0;

    if ($product_id == 0 || $value <= 0 || empty($date)) {
        throw new Exception('Data tidak valid');
    }

    // ✅ CEK DATA METER
    $stmtCek = $koneksi->prepare("
        SELECT COUNT(*) as count
        FROM meters
        WHERE product_id = ? AND date = ?
    ");
    $stmtCek->bind_param("is", $product_id, $date);
    $stmtCek->execute();
    $resultCek = $stmtCek->get_result()->fetch_assoc();
    $stmtCek->close();

    $newListId = 0;
    $updatedTotal = 0;

    if (!empty($resultCek) && $resultCek['count'] > 0) {

        // ✅ Ambil meter_id
        $stmtGetMeterId = $koneksi->prepare("
            SELECT meter_id
            FROM meters
            WHERE product_id = ? AND date = ?
        ");
        $stmtGetMeterId->bind_param("is", $product_id, $date);
        $stmtGetMeterId->execute();
        $resultMeterId = $stmtGetMeterId->get_result()->fetch_assoc();
        $meter_id = $resultMeterId['meter_id'];
        $stmtGetMeterId->close();

    } else {

        // ✅ Insert meter baru
        $stmtInsert = $koneksi->prepare("
            INSERT INTO meters (product_id, total, date, user_id)
            VALUES (?, 0, ?, ?)
        ");
        $stmtInsert->bind_param("isi", $product_id, $date, $user_id);
        $stmtInsert->execute();
        $meter_id = $koneksi->insert_id;
        $stmtInsert->close();
    }

    // ✅ Insert ke list_meters
    $stmtInsertList = $koneksi->prepare("
        INSERT INTO list_meters (meter_id, product_id, user_id, value)
        VALUES (?, ?, ?, ?)
    ");
    $stmtInsertList->bind_param("iiid", $meter_id, $product_id, $user_id, $value);
    $stmtInsertList->execute();
    $newListId = $koneksi->insert_id;
    $stmtInsertList->close();

    // ✅ Update total
    updateTotalMeteran($product_id, $date);

    // ✅ Ambil total terbaru
    $stmtGetTotal = $koneksi->prepare("
        SELECT total
        FROM meters
        WHERE product_id = ? AND date = ?
    ");
    $stmtGetTotal->bind_param("is", $product_id, $date);
    $stmtGetTotal->execute();
    $resultTotal = $stmtGetTotal->get_result()->fetch_assoc();
    $updatedTotal = $resultTotal['total'] ?? 0;
    $stmtGetTotal->close();

    echo json_encode([
        'success' => true,
        'message' => 'Meteran berhasil diperbarui',
        'new_list_id' => $newListId,
        'value' => $value,
        'updated_total' => $updatedTotal
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}