<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
$product_id = (int)($_POST['product_id'] ?? 0);
$new_quantity = floatval($_POST['new_quantity'] ?? -1);

if ($product_id > 0 && $new_quantity >= 0) {
    // Cek apakah sudah ada stok untuk product_id ini
    $check = $koneksi->prepare("SELECT stock_id FROM stock WHERE store_id = ? AND product_id = ?");
    $check->bind_param("ii", $store_id, $product_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        // Update existing stock
        $update = $koneksi->prepare("UPDATE stock SET quantity = ? WHERE store_id = ? AND product_id = ?");
        $update->bind_param("dii", $new_quantity, $store_id, $product_id);
        $update->execute();
        $update->close();
    } else {
        // Insert new stock
        $insert = $koneksi->prepare("INSERT INTO stock (store_id, product_id, quantity) VALUES (?, ?, ?)");
        $insert->bind_param("iid", $store_id, $product_id, $new_quantity);
        $insert->execute();
        $insert->close();
    }

    $check->close();
}

header("Location: stock");
exit;
?>
