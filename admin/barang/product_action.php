<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/models/Product.php';

$productModel = new Product($koneksi);
$action = $_POST['product'] ?? '';

$methods = [
    'add_product'    => 'addProduct',
    'update_product' => 'updateProduct',
    'delete_product' => 'deleteProductByIdAndStoreId'
];

if (array_key_exists($action, $methods)) {
    $data = new stdClass();
    $data->id               = $_POST['product_id'] ?? 0;
    $data->store_id         = $store_id ?? 0;
    $data->type             = $_POST['type'] ?? '';
    $data->name             = $_POST['name'] ?? '';
    $data->price            = $_POST['price'] ?? '';
    $data->unit             = $_POST['unit_type'] ?? '';
    $data->reasonable_price = $_POST['reasonable_price'] ?? '';
    $data->failed_price     = $_POST['failed_price'] ?? '';

    $method = $methods[$action];
    
    if ($productModel->$method($data)) {
        header("Location: index");
        exit;
    }

    echo "Gagal melakukan aksi: " . $action;
}
