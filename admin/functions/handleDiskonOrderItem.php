<?php

function handleDiskonOrderItem(mysqli $koneksi, int $order_id, int $product_id, int $diskonInput): int {
    if ($diskonInput > 0) {
        // Cek apakah sudah ada record
        $cekSql = "SELECT 1 FROM diskon_order_items WHERE order_id = ? AND product_id = ?";
        $cekStmt = $koneksi->prepare($cekSql);
        $cekStmt->bind_param("ii", $order_id, $product_id);
        $cekStmt->execute();
        $cekStmt->store_result();

        if ($cekStmt->num_rows > 0) {
            // Update
            $updateSql = "UPDATE diskon_order_items SET diskon = ? WHERE order_id = ? AND product_id = ?";
            $updateStmt = $koneksi->prepare($updateSql);
            $updateStmt->bind_param("iii", $diskonInput, $order_id, $product_id);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            // Insert
            $insertSql = "INSERT INTO diskon_order_items (order_id, product_id, diskon) VALUES (?, ?, ?)";
            $insertStmt = $koneksi->prepare($insertSql);
            $insertStmt->bind_param("iii", $order_id, $product_id, $diskonInput);
            $insertStmt->execute();
            $insertStmt->close();
        }

        $cekStmt->close();
    }

    // Ambil kembali diskon dari database (jika ada)
    $cekDiskonSql = "SELECT diskon FROM diskon_order_items WHERE order_id = ? AND product_id = ?";
    $cekDiskonStmt = $koneksi->prepare($cekDiskonSql);
    $cekDiskonStmt->bind_param("ii", $order_id, $product_id);
    $cekDiskonStmt->execute();
    $cekDiskonStmt->bind_result($diskonDb);
    if ($cekDiskonStmt->fetch()) {
        $diskonInput = (int)$diskonDb;
    }
    $cekDiskonStmt->close();

    return $diskonInput;
}

?>