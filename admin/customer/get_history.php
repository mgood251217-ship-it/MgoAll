<?php
require_once "../connect.php";
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/models/Order.php';

$orderModel = new Order($koneksi);

$data = new stdClass();
$data->name = $_GET['name'];
$data->store_id = $store_id;
$history = [];

$result = $orderModel->getHistoryNameAndNomor($data);

while ($a = $result->fetch_assoc()) {
    $history[] = $a;
}

echo json_encode($history);

?>