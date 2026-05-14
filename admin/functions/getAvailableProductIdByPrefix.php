<?php

function getAvailableProductIdByPrefix($koneksi, $store_id, $judul) {
    $prefixes = ['XBANNER', 'ROLLUP', 'MINIBANNER', 'KN'];
    foreach ($prefixes as $prefix) {
        if (stripos($judul, $prefix) === 0) {
            $sql = $koneksi->prepare("SELECT p.product_id FROM products p JOIN stock s ON p.product_id = s.product_id WHERE p.store_id = ? AND p.name LIKE ? AND s.quantity > 0 ORDER BY s.quantity DESC LIMIT 1");
            $likeName = $prefix . '%';
            $sql->bind_param("is", $store_id, $likeName);
            $sql->execute();
            $result = $sql->get_result();
            if ($row = $result->fetch_assoc()) {
                $sql->close();
                return (int)$row['product_id'];
            }
            $sql->close();
        }
    }
    return null;
}

?>