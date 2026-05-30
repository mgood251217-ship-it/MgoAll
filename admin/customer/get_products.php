<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/models/Product.php'; 

$productModel = new Product($koneksi);

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';

if (!$store_id || !$type) {
    echo json_encode([]);
    exit;
}

$data = new stdClass();
$data->type = $type;
$data->store_id = $store_id;

$products = $productModel->getProductByTypeAndStoreId($data);

echo json_encode($products);