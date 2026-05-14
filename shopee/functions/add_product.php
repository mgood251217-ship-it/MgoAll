<?php
session_start();
require_once '../connect.php';
$user_id = $_SESSION['shopee_users']['user_id'] ?? 0;
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => false, 'message' => 'Invalid request']);
    exit;
}

$user_id = $_SESSION['shopee_users']['user_id'] ?? 0;
$product_name      = trim($_POST['product_name'] ?? '');
$product_type      = trim($_POST['product_type'] ?? '');
$product_unit_type = trim($_POST['product_unit_type'] ?? '');

if ($product_name === '' || $product_type === '' || $product_unit_type === '') {
    echo json_encode(['status' => false, 'message' => 'Data produk belum lengkap']);
    exit;
}

$koneksi->begin_transaction();

try {

    $stmt = $koneksi->prepare("
        INSERT INTO products (user_id, name, type, unit_type)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("isss", $user_id, $product_name, $product_type, $product_unit_type);
    $stmt->execute();
    $product_id = $stmt->insert_id;
    $stmt->close();

    $koneksi->commit();

    echo json_encode([
        'status' => true,
        'message' => 'Produk berhasil ditambahkan',
        'product_id' => $product_id
    ]);

} catch (Exception $e) {

    $koneksi->rollback();

    echo json_encode([
        'status' => false,
        'message' => 'Gagal menambahkan produk',
        'error' => $e->getMessage()
    ]);
}
