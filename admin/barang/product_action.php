<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/controllers/ProductController.php';

$productController = new ProductController($koneksi);
$action = $_POST['product'] ?? '';

switch ($action) {
    case 'create_product':
        $productController->createProduct();
        break;
    case 'update_product':
        $productController->updateProduct();
        break;
    case 'delete_product':
        $productController->deleteProduct();
        break;
    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'errors' => ['Aksi tidak valid.']]);
        exit;
}
?>