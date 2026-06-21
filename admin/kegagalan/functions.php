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

function getFinishingPrice(string $finishing_ids, int $store_id, mysqli $koneksi): int {
    $total = 0;
    if ($finishing_ids === '-' || empty($finishing_ids)) return 0;

    $ids = explode(',', $finishing_ids);
    foreach ($ids as $fid) {
        $fid = trim($fid);
        if (is_numeric($fid)) {
            $total += getProductPrice((int)$fid, $store_id, $koneksi);
        }
    }
    return $total;
}

// Fungsi ambil harga finishing tambahan berdasarkan nama
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

// *** Tambahan fungsi dan pemanggilan ***
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

function handleDiskonOrderItem(mysqli $koneksi, int $order_id, int $product_id, int $diskonInput): int {
    if ($diskonInput > 0) {
        // Cek apakah sudah ada record
        $cekSql = "SELECT 1 FROM diskon_calculator_items WHERE calculator_id = ? AND product_id = ?";
        $cekStmt = $koneksi->prepare($cekSql);
        $cekStmt->bind_param("ii", $order_id, $product_id);
        $cekStmt->execute();
        $cekStmt->store_result();

        if ($cekStmt->num_rows > 0) {
            // Update
            $updateSql = "UPDATE diskon_calculator_items SET diskon = ? WHERE calculator_id = ? AND product_id = ?";
            $updateStmt = $koneksi->prepare($updateSql);
            $updateStmt->bind_param("iii", $diskonInput, $order_id, $product_id);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            // Insert
            $insertSql = "INSERT INTO diskon_calculator_items (calculator_id, product_id, diskon) VALUES (?, ?, ?)";
            $insertStmt = $koneksi->prepare($insertSql);
            $insertStmt->bind_param("iii", $order_id, $product_id, $diskonInput);
            $insertStmt->execute();
            $insertStmt->close();
        }

        $cekStmt->close();
    }

    // Ambil kembali diskon dari database (jika ada)
    $cekDiskonSql = "SELECT diskon FROM diskon_calculator_items WHERE calculator_id = ? AND product_id = ?";
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
function cekStokProdukUtama(mysqli $koneksi, int $product_id, int $store_id, float $lebar, float $panjang, int $quantity): array {
    // Ambil type dan unit_type produk
    $stmt = $koneksi->prepare("SELECT type, name, unit_type FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $stmt->bind_result($product_type, $name, $unit_type);
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
function cekDanKurangiStokFinishing(mysqli $koneksi, int $store_id, int $quantity, float $panjang, float $lebar, $finishing): bool {
    $finishing_ids = [];
    if ($finishing !== '-' && is_numeric($finishing)) {
        $finishing_ids[] = (int)$finishing;
    }

    // Cek ketersediaan stok
    foreach ($finishing_ids as $fid) {
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



function updateOrderTotal(int $order_id, mysqli $koneksi): bool {
    $sql = "
        SELECT 
            oi.product_id,
            oi.quantity,
            oi.size,
            oi.unit,
            oi.judul,
            p.price,
            UPPER(COALESCE(p.type, '')) AS type,
            COALESCE(doi.diskon, 0) AS diskon
        FROM calculator_items oi
        LEFT JOIN products p ON oi.product_id = p.product_id AND p.store_id = oi.store_id
        LEFT JOIN diskon_calculator_items doi ON doi.calculator_id = oi.calculator_id AND doi.product_id = oi.product_id
        WHERE oi.calculator_id = ?
    ";

    $stmt = $koneksi->prepare($sql);
    if (!$stmt) return false;

    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $outdoorGroups = [];
    $grand_total = 0;

    while ($row = $result->fetch_assoc()) {
        $product_id = $row['product_id'];
        $type = $row['type'];
        $quantity = (int)$row['quantity'];
        $size_str = $row['size'];
        $judul = strtoupper(trim($row['judul']));
        $unit = $row['unit'];
        $price = (int)$row['price'];
        $diskon = (int)$row['diskon'];

        $is_manual = empty($product_id);

        if ($is_manual) {
            $type = '';
            $product_id = 'manual_' . $judul;
        }

        if ($type === 'OUTDOOR') {
            if (!isset($outdoorGroups[$product_id])) {
                $outdoorGroups[$product_id] = [
                    'price' => $price,
                    'diskon' => $diskon,
                    'total_size' => 0,
                ];
            }

            if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $size_str, $matches)) {
                $panjang = floatval($matches[1]);
                $lebar = floatval($matches[2]);
                $luas = $panjang * $lebar;
                $outdoorGroups[$product_id]['total_size'] += $luas * $quantity;
            } else {
                $outdoorGroups[$product_id]['total_size'] += 0;
            }
        } else {
            // Non-OUTDOOR → ambil harga dari unit
            $harga_satuan = (int)$unit;
            $amount = $harga_satuan * $quantity;
            $grand_total += $amount;
        }
    }

    $stmt->close();

    // Hitung total untuk OUTDOOR
    foreach ($outdoorGroups as $group) {
        $harga_per_meter = max($group['price'] - $group['diskon'], 0);
        $total_size = $group['total_size'];
        $amount = ($total_size < 1) ? $harga_per_meter : ($total_size * $harga_per_meter);
        $grand_total += $amount;
    }

    // Bulatkan ke bawah kelipatan 500
    $grand_total = floor($grand_total / 500) * 500;

    $stmt = $koneksi->prepare("UPDATE calculator SET total = ? WHERE calculator_id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("ii", $grand_total, $order_id);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

?>
