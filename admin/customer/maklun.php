<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

$order_item_id = (int)$_POST['order_item_id'];
$store_id_maklun = (int)$_POST['store_id_maklun'];

$stmtUpdate = $koneksi->prepare("UPDATE order_items SET maklun = ? WHERE order_item_id = ?");
$stmtUpdate->bind_param("ii", $store_id_maklun, $order_item_id);
if ($stmtUpdate->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}

?>