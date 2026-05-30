<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

require_once BASE_PATH . '/controllers/OrderController.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderController = new OrderController($koneksi);
    $orderController->update();
} else {
    header("Location: index");
    exit;
}
?>