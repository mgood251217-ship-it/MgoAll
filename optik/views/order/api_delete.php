<?php
header('Content-Type: application/json');
require_once 'models/Order.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$orderModel = new Order($pdo);
$delete = $orderModel->deleteOrder($id);

echo json_encode([
    'success' => $delete
]);
?>