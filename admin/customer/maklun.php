<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/models/Order.php';

$orderModel = new Order($koneksi);

$data = new stdClass();
$data->store_id_maklun = $_POST['store_id_maklun'];
$data->order_item_id = $_POST['order_item_id'];
$update = $orderModel->updateMaklun($data);

if ($update) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}

?>  