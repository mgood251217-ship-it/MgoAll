<?php
session_start();
require_once '../connect.php';
require_once BASE_PATH . '/functions/user_validation.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$user_id = $_SESSION['shopee_users']['user_id'] ?? 0;

$month = trim($data['month'] ?? '');
$omset = (float)($data['omset'] ?? 0);

if ($month === '' || $omset < 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Data tidak lengkap atau tidak valid'
    ]);
    exit;
}

try {
    // Cek apakah ada record finance untuk bulan ini
    $stmtCheck = $koneksi->prepare("SELECT finance_id FROM finance WHERE user_id = ? AND month = ?");
    $stmtCheck->bind_param("is", $user_id, $month);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();

    if ($resultCheck->num_rows > 0) {
        // Update omset
        $stmtUpdate = $koneksi->prepare("UPDATE finance SET omset = ? WHERE user_id = ? AND month = ?");
        $stmtUpdate->bind_param("dis", $omset, $user_id, $month);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    } else {
        // Insert baru
        $stmtInsert = $koneksi->prepare("INSERT INTO finance (user_id, omset, month) VALUES (?, ?, ?)");
        $stmtInsert->bind_param("ids", $user_id, $omset, $month);
        $stmtInsert->execute();
        $stmtInsert->close();
    }

    $stmtCheck->close();

    echo json_encode([
        'status' => 'success',
        'message' => 'Omset berhasil diupdate'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal update omset: ' . $e->getMessage()
    ]);
}
?>
