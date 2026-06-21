<?php

require_once '../connect.php';
require_once BASE_PATH . '/functions/user_validation.php';

$product_id = $_GET['product_id'] ?? 0;
$user_id = $_SESSION['shopee_users']['user_id'] ?? 0;

$stmtProduct = $koneksi->prepare("
    SELECT product_id, name, type, unit_type
    FROM products
    WHERE user_id = ? AND product_id = ?");
$stmtProduct->bind_param("ii", $user_id, $product_id);
$stmtProduct->execute();
$resultProduct = $stmtProduct->get_result();

echo json_encode($resultProduct->fetch_assoc());;
$stmtProduct->close();

?>