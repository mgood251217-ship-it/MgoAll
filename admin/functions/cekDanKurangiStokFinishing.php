<?php

function cekDanKurangiStokFinishing(mysqli $koneksi, int $store_id, int $quantity, float $panjang, float $lebar, $finishing): bool {
    $finishing_ids = [];
    if ($finishing !== '-' && is_numeric($finishing)) {
        $finishing_ids[] = (int)$finishing;
    }

    // Cek ketersediaan stok
    foreach ($finishing_ids as $fid) {
        $stok_finishing = 0;
        $stmtCek = $koneksi->prepare("
            SELECT p.unit_type, s.quantity 
            FROM products p 
            JOIN stock s ON p.product_id = s.product_id 
            WHERE p.product_id = ? AND p.store_id = ? AND s.store_id = ?
        ");
        $stmtCek->bind_param("iii", $fid, $store_id, $store_id);
        $stmtCek->execute();
        $stmtCek->bind_result($unit_type_finishing, $stok_finishing);

        if ($stmtCek->fetch()) {
            if ($unit_type_finishing !== '~') {
                if ($stok_finishing === null || $stok_finishing < 1) {
                    $stmtCek->close();
                    return false;
                }
            }
        }
        $stmtCek->close();
    }

    // Lakukan pengurangan stok
    foreach ($finishing_ids as $fid) {
        $stmtCekUnit = $koneksi->prepare("SELECT unit_type, name, type FROM products WHERE product_id = ? AND store_id = ?");
        $stmtCekUnit->bind_param("ii", $fid, $store_id);
        $stmtCekUnit->execute();
        $stmtCekUnit->bind_result($unit_type_finishing, $name_finishing, $product_type_finishing);
        $stmtCekUnit->fetch();
        $stmtCekUnit->close();

        if ($unit_type_finishing !== '~') {
            if (
                ($name_finishing === 'DOFF' || $name_finishing === 'GLOSSY')
                && $product_type_finishing === 'FINISHING LASER A3'
            ) {
                $qty_per_item = 0.1536;
            } elseif (
                ($name_finishing === 'DOFF' || $name_finishing === 'GLOSSY')
                && $product_type_finishing === 'FINISHING INDOOR'
            ) {
                $qty_per_item = $panjang * $lebar;
            } else {
                $qty_per_item = 1;
            }

            $qty_to_reduce = $qty_per_item * $quantity;

            $stmtFinishingStok = $koneksi->prepare("UPDATE stock SET quantity = quantity - ? WHERE product_id = ? AND store_id = ?");
            $stmtFinishingStok->bind_param("dii", $qty_to_reduce, $fid, $store_id);
            $stmtFinishingStok->execute();
            $stmtFinishingStok->close();
        }
    }

    return true;
}

?>