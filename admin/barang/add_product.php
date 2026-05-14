<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

$type = $_POST['type'];
$nameProduct = $_POST['name'];
$price = $_POST['price'];
$unit = $_POST['unit_type'];

$stmt = $koneksi->prepare("INSERT INTO products (store_id, type, name, price, unit_type) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("issss", $store_id, $type, $nameProduct, $price, $unit);

if ($stmt->execute()) {
    header("Location: index");
    exit;
} else {
    echo "Gagal tambah data: " . $stmt->error;
}
?>
