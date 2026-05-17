<?php
require_once '../connect.php';
require_once BASE_PATH . '/models/Order.php';

$orderModel = new Order($koneksi);

$id = (int)($_GET['order_id'] ?? 0);

$result = $orderModel->getOrderById($id);
$order = $result->fetch_assoc();

echo json_encode($order);