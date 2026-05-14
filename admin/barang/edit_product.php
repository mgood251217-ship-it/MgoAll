<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['product_id'];
    $type = $_POST['type'];
    $nameProduct = $_POST['name'];
    $price = (int)$_POST['price'];
    $unit_type = $_POST['unit_type'];
    $reasonable_price = $_POST['reasonable_price'];
    $failed_price = $_POST['failed_price'];

    $stmt = $koneksi->prepare("UPDATE products SET type = ?, name = ?, price = ?, unit_type = ?, reasonable_price = ?, failed_price =? WHERE product_id = ? AND store_id = ?");
    $stmt->bind_param("ssssssii", $type, $nameProduct, $price, $unit_type, $reasonable_price, $failed_price, $id, $store_id);
    $stmt->execute();
    $stmt->close();

    header("Location: index");
    exit;
}
