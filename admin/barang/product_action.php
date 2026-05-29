<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/controllers/ProductController.php';

$productController = new ProductController($koneksi);
$action = $_POST['product'] ?? '';

if ($action === 'create_product') {
    $productController->createProduct();
} elseif ($action === 'update_product') {
    $productController->updateProduct();
} elseif ($action === 'delete_product') {
    $productController->deleteProduct();
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errors' => ['Aksi tidak valid.']]);
    exit;
}
?>