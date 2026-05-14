<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$add_quantity = isset($_POST['add_quantity']) ? (float)$_POST['add_quantity'] : 0;

if ($product_id <= 0 || $add_quantity <= 0) {
    header("Location: stock.php");
    exit;
}

// Cek apakah stok sudah ada
$cekStmt = $koneksi->prepare("SELECT quantity FROM stock WHERE product_id = ? AND store_id = ?");
$cekStmt->bind_param("ii", $product_id, $store_id);
$cekStmt->execute();
$cekStmt->store_result();

if ($cekStmt->num_rows > 0) {
    // Update stok
    $update = $koneksi->prepare("UPDATE stock SET quantity = quantity + ? WHERE product_id = ? AND store_id = ?");
    $update->bind_param("dii", $add_quantity, $product_id, $store_id);
    $update->execute();
    $update->close();
} else {
    // Insert stok baru
    $insert = $koneksi->prepare("INSERT INTO stock (product_id, store_id, quantity) VALUES (?, ?, ?)");
    $insert->bind_param("iid", $product_id, $store_id, $add_quantity);
    $insert->execute();
    $insert->close();
}

$cekStmt->close();
header("Location: stock.php");
exit;