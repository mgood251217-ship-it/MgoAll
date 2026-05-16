<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/models/Stock.php';

$stockModel = new Stock($koneksi);
$data = new stdClass();
$data->id = $_POST['product_id'] ?? 0;
$data->store_id = $store_id;
$data->quantity = $_POST['quantity'] ?? 0;
$stock = $_POST['stock'] ?? '';

if ($stockModel->checkStock($data)) {
    if ($stock == 'add_stock') {
        $stockModel->addUpdateStock($data);
    }else if ($stock == 'update_stock'){
        $stockModel->updateStock($data);
    }
} else {
    $stockModel->addStock($data);
}

header("Location: index");
exit;
?>