<?php

function cekStokProdukUtama(mysqli $koneksi, int $product_id, int $store_id, float $lebar, float $panjang, int $quantity): array {
    // Ambil type dan unit_type produk
    $stmt = $koneksi->prepare("SELECT type, unit_type, name FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $stmt->bind_result($product_type, $unit_type, $name);
    $stmt->fetch();
    $stmt->close();

    // Hitung stok yang dibutuhkan
    $stok_butuh = $quantity;
    if ($unit_type === 'M2') {
        $stok_butuh = $panjang * $lebar * $quantity;
    } elseif ($unit_type === 'CM2') {
        $stok_butuh = round(($panjang / 100) * ($lebar / 100) * $quantity, 4);
    }

    // Cek stok jika unit_type bukan '~'
    if ($unit_type !== '~') {
        $sqlStok = $koneksi->prepare("SELECT quantity FROM stock WHERE product_id = ? AND store_id = ?");
        $sqlStok->bind_param("ii", $product_id, $store_id);
        $sqlStok->execute();
        $sqlStok->bind_result($stok_tersedia);
        $stok_ada = $sqlStok->fetch();
        $sqlStok->close();

        if (!$stok_ada || $stok_tersedia === null || $stok_tersedia < $stok_butuh) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Stok produk tidak mencukupi atau belum tersedia.']);
            exit;
        }
    }

    // Return semua informasi
    return [
        'type' => $product_type,
        'unit_type' => $unit_type,
        'stok_butuh' => $stok_butuh,
        'name' => $name
    ];
}

?>