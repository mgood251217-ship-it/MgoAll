<?php
require_once '../connect.php';
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: " . BASE_URL . "/login");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$store_id = $_SESSION['user']['store_id'];

// Pastikan barang milik toko user yang login
$stmt = $koneksi->prepare("DELETE FROM products WHERE product_id = ? AND store_id = ?");
$stmt->bind_param("ii", $id, $store_id);
$stmt->execute();
$stmt->close();

header("Location: index");
exit;
