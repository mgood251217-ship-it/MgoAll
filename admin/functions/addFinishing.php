<?php

function addFinishing($name, $store_id, $finishing_type, &$koneksi, &$finishing_ids, &$finishing_additional_price, $panjang = 0, $lebar = 0, $product_type = '') {
    $stmt = $koneksi->prepare("SELECT product_id, price FROM products WHERE type = ? AND name = ? AND store_id = ?");
    $stmt->bind_param("ssi", $finishing_type, $name, $store_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        $finishing_ids[] = $result['product_id'];
        $price = (float)$result['price'];
        // Khusus untuk INDOOR dan finishing CUT, harga dikali luas
        if ($product_type === 'INDOOR' && $name === 'KISS CUT') {
            $price *= $panjang * $lebar;
        }
        $finishing_additional_price += $price;
    }
    $stmt->close();
}

?>