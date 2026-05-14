<?php // update_order_item.php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$qty = isset($_POST['qty']) ? (int)$_POST['qty'] : 1;

if ($id > 0 && $qty > 0) {
    $stmt = $koneksi->prepare("UPDATE order_items SET quantity = ?, amount = quantity * unit WHERE id = ?");
    $stmt->bind_param("ii", $qty, $id);
    if ($stmt->execute()) {
        echo "berhasil";
    } else {
        echo "gagal";
    }
    $stmt->close();
}
?>