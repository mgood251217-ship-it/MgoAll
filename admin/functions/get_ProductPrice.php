<?php

function getProductPrice(int $product_id, int $store_id, mysqli $koneksi): int {
    $price = 0;
    $stmt = $koneksi->prepare("SELECT price FROM products WHERE product_id = ? AND store_id = ?");
    $stmt->bind_param("ii", $product_id, $store_id);
    $stmt->execute();
    $stmt->bind_result($price);
    $stmt->fetch();
    $stmt->close();
    return $price;
}

?>