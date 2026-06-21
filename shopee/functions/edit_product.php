<?php

require_once '../connect.php';
require_once BASE_PATH . '/functions/user_validation.php';

$user_id = $_SESSION['shopee_users']['user_id'] ?? 0;

$product_id        = trim($_POST['product_id'] ?? 0);
$product_name      = trim($_POST['product_name'] ?? '');
$product_type      = trim($_POST['product_type'] ?? '');
$product_unit_type = trim($_POST['product_unit_type'] ?? '');

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => false, 'message' => 'Invalid request']);
    exit;
}
if ($product_id == 0 || $product_name === '' || $product_type === '' || $product_unit_type === '') {
    echo json_encode(['status' => false, 'message' => 'Data produk belum lengkap']);
    exit;
}
$koneksi->begin_transaction();

$stmtUpdate = $koneksi->prepare("
    UPDATE products
    SET name = ?, type = ?, unit_type = ?
    WHERE product_id = ? AND user_id = ?");
$stmtUpdate->bind_param("sssii", $product_name, $product_type, $product_unit_type, $product_id, $user_id);
try {
    $stmtUpdate->execute();
    $stmtUpdate->close();

    $koneksi->commit();

    echo json_encode([
        'status' => true,
        'message' => 'Produk berhasil diperbarui'
    ]);

} catch (Exception $e) {

    $koneksi->rollback();

    echo json_encode([
        'status' => false,
        'message' => 'Gagal memperbarui produk',
        'error' => $e->getMessage()
    ]);
}

?>