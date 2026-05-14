<?php
header('Content-Type: application/json');
require_once 'models/Order.php';

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$orderModel = new Order($pdo);
$items = $orderModel->getOrderItems($order_id);

echo json_encode($items);
?>