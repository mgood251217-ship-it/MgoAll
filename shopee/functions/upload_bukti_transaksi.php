<?php
require_once '../connect.php';
require_once BASE_PATH . '/functions/user_validation.php';

header('Content-Type: application/json');

$bukti_transaksi = $_FILES['bukti_transaksi'] ?? null;
$finance_id = (int)($_POST['finance_id'] ?? 0);
$user_id = $_SESSION['shopee_users']['user_id'] ?? 0;

if (!$bukti_transaksi || !$finance_id) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
    exit;
}

function uploadFotoBuktiTransaksi($file){
    $targetDir = BASE_PATH . '/assets/img/bukti_transaksi/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = uniqid() . '_' . basename($file['name']);
    $targetFilePath = $targetDir . $fileName;
    $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

    if (!in_array($fileType, $allowedTypes)) {
        return null;
    }

    return move_uploaded_file($file['tmp_name'], $targetFilePath)
        ? $fileName
        : null;
}

function deleteOldFotoBuktiTransaksi($fileName){
    $filePath = BASE_PATH . '/assets/img/bukti_transaksi/' . $fileName;
    if (file_exists($filePath)) {
        unlink($filePath);
    }
}

date_default_timezone_set('Asia/Jakarta');

$fileName = uploadFotoBuktiTransaksi($bukti_transaksi);

if (!$fileName) {
    echo json_encode(['status' => 'error', 'message' => 'Format file tidak valid']);
    exit;
}

$currentDate = date('Y-m-d H:i:s');

/* Cek apakah proof sudah ada */
$stmtCekProof = $koneksi->prepare("SELECT proof_id, foto FROM proof WHERE finance_id = ?");
$stmtCekProof->bind_param("i", $finance_id);
$stmtCekProof->execute();
$result = $stmtCekProof->get_result();

if ($result->num_rows > 0) {
    /* UPDATE */
    $stmtUpdate = $koneksi->prepare("
        UPDATE proof 
        SET foto = ?, date = ? 
        WHERE finance_id = ?
    ");
    $stmtUpdate->bind_param("ssi", $fileName, $currentDate, $finance_id);

    $success = $stmtUpdate->execute();
    $message = 'Bukti transaksi berhasil diperbarui.';

    /* Hapus file lama */
    $row = $result->fetch_assoc();
    deleteOldFotoBuktiTransaksi($row['foto']);
} else {
    /* INSERT */
    $stmtInsert = $koneksi->prepare("
        INSERT INTO proof (finance_id, foto, date, user_id)
        VALUES (?, ?, ?, ?)
    ");
    $stmtInsert->bind_param("issi", $finance_id, $fileName, $currentDate, $user_id);

    $success = $stmtInsert->execute();
    $message = 'Bukti transaksi berhasil diunggah.';
}

echo json_encode([
    'status' => $success ? 'success' : 'error',
    'message' => $success ? $message : 'Gagal menyimpan bukti transaksi'
]);
