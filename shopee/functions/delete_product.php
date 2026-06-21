<?php
require_once '../connect.php';
session_start();
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) $data = $_POST;

$product_id = (int)($data['product_id'] ?? 0);

if ($product_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Product ID tidak valid'
    ]);
    exit;
}

$koneksi->begin_transaction();

try {

    
    // Hapus produk
    $stmt = $koneksi->prepare("
        DELETE FROM products
        WHERE product_id = ?
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();

    $koneksi->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Produk berhasil dihapus'
    ]);

} catch (Exception $e) {
    $koneksi->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

