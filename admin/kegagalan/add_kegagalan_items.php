<?php
require_once '../connect.php';
require_once 'functions.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once BASE_PATH . '/session.php';

$cal_id   = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$diskon     = isset($_POST['diskon']) ? (int)$_POST['diskon'] : 0;
$diskon = handleDiskonOrderItem($koneksi, $cal_id, $product_id, $diskon);
$judul      = isset($_POST['judul']) ? trim($_POST['judul']) : '';
$size       = isset($_POST['size']) ? trim($_POST['size']) : '-';
$quantity   = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
$finishing  = isset($_POST['finishing']) ? trim($_POST['finishing']) : '-';
$panjang    = isset($_POST['panjang']) ? (float)$_POST['panjang'] : 0;
$lebar      = isset($_POST['lebar']) ? (float)$_POST['lebar'] : 0;
$finishing_cut = isset($_POST['finishing_cut']) && $_POST['finishing_cut'] == '1';
$finishing_die = isset($_POST['finishing_die']) && $_POST['finishing_die'] == '1';


if ($panjang > 0 && $lebar > 0) {
    $size = "{$panjang}x{$lebar}";
}

$info = cekStokProdukUtama($koneksi, $product_id, $store_id, $lebar, $panjang, $quantity);

$product_type = $info['type'];
$unit_type    = $info['unit_type'];
$stok_butuh   = $info['stok_butuh'];

// Ambil jenis produk dulu
$jenis = strtoupper($product_type);

// Hanya cari ulang jika product_id belum valid (misalnya 0)
if ($jenis !== 'PAKET INDOOR OUTDOOR' && (int)$product_id === 0) {
    $newProductId = getAvailableProductIdByPrefix($koneksi, $store_id, $judul);
    if ($newProductId !== null) {
        $product_id = $newProductId;
    }
}

$product_price = getProductPrice($product_id, $store_id, $koneksi);
$finishing_price = getFinishingPrice($finishing, $store_id, $koneksi);

// Hitung harga satuan produk + finishing utama
$unit = $product_price - $diskon + $finishing_price;


$finishing_ids = [];
$result = cekDanKurangiStokFinishing($koneksi, $store_id, $quantity, $panjang, $lebar, $finishing);
if (!$result) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Stok finishing tidak mencukupi.']);
    exit;
}

$finishing_additional_price = 0;

if ($finishing_cut) {
    $finishing_type = ($product_type === 'INDOOR') ? 'FINISHING INDOOR' : 'FINISHING LASER A3';
    addFinishing('KISS CUT', $store_id, $finishing_type, $koneksi, $finishing_ids, $finishing_additional_price, $panjang, $lebar, $product_type);
}

if ($finishing_die) {
    $finishing_type = ($product_type === 'INDOOR') ? 'FINISHING INDOOR' : 'FINISHING LASER A3';
    addFinishing('DIE CUT', $store_id, $finishing_type, $koneksi, $finishing_ids, $finishing_additional_price, $panjang, $lebar, $product_type);
}

$finishing_price += $finishing_additional_price;



// Hitung harga akhir per unit sesuai ukuran
if ($unit_type === 'M2') {
    if ($product_type === 'DTF') {
        // DTF hitung harga hanya berdasarkan panjang, lebar diabaikan
        $unit *= $panjang;
    } else {
        $unit *= $panjang * $lebar;
    }
} elseif ($unit_type === 'CM2') {
    $unit *= $panjang * $lebar;
}

if ($finishing !== '-' && is_numeric($finishing)) {
    $finishing_ids[] = (int)$finishing;
}

// Tambahkan harga finishing tambahan (CUT, DIE)
$unit += $finishing_additional_price;

// Cek jika type adalah JERSEY
if ($product_type === 'JERSEY') {
    if ($size === 'XXXL') {
        $unit += 20000;
    } elseif ($size === 'XXL') {
        $unit += 10000;
    }
}

$amount = $unit * $quantity;
$finishing_str = count($finishing_ids) ? implode(',', $finishing_ids) : '-';

// Cek apakah sudah ada order_items dengan judul, finishing, size sama di order_id & store_id yang sama
$stmtCheck = $koneksi->prepare("SELECT calculator_id, quantity, unit, amount FROM calculator_items WHERE store_id = ? AND calculator_id = ? AND judul = ? AND finishing = ? AND size = ?");
$stmtCheck->bind_param("iisss", $store_id, $cal_id, $judul, $finishing_str, $size);
$stmtCheck->execute();
$resultCheck = $stmtCheck->get_result();

if ($rowExist = $resultCheck->fetch_assoc()) {
    // Jika ada, update quantity, unit, dan amount
    $new_quantity = $rowExist['quantity'] + $quantity;
    $new_amount = $unit * $new_quantity;

    $stmtUpdate = $koneksi->prepare("UPDATE calculator_items SET quantity = ?, unit = ?, amount = ? WHERE calculator_items_id = ?");
    $stmtUpdate->bind_param("iddi", $new_quantity, $unit, $new_amount, $rowExist['calculator_items_id']);
    $success = $stmtUpdate->execute();
    $stmtUpdate->close();

    if ($success) {
        // Kalau unit_type bukan "~", baru kurangi stok
        if ($unit_type !== '~') {
            $stok_untuk_dikurangi = $stok_butuh;
            $stmtUpdateStok = $koneksi->prepare("UPDATE stock SET quantity = quantity - ? WHERE product_id = ? AND store_id = ?");
            $stmtUpdateStok->bind_param("dii", $stok_untuk_dikurangi, $product_id, $store_id);
            $stmtUpdateStok->execute();
            $stmtUpdateStok->close();
        }

        echo json_encode(['success' => true, 'message' => 'Item berhasil diperbarui.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui item: ' . $stmtUpdate->error]);
    }

} else {
    // Insert baru
    $stmtInsert = $koneksi->prepare("INSERT INTO calculator_items
        (store_id, calculator_id, product_id, judul, size, quantity, unit, amount, finishing) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmtInsert) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal mempersiapkan statement insert: ' . $koneksi->error]);
        exit;
    }

    $stmtInsert->bind_param("iisssiiis",
        $store_id, $cal_id, $product_id, $judul, $size, $quantity, $unit, $amount, $finishing_str);

    if ($stmtInsert->execute()) {
        // Kalau unit_type bukan "~", baru kurangi stok
        if ($unit_type !== '~') {
            $stmtUpdate = $koneksi->prepare("UPDATE stock SET quantity = quantity - ? WHERE product_id = ? AND store_id = ?");
            $stmtUpdate->bind_param("dii", $stok_butuh, $product_id, $store_id);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }

        echo json_encode(['success' => true, 'message' => 'Item berhasil ditambahkan.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan item: ' . $stmtInsert->error]);
    }
    $stmtInsert->close();
}

$stmtCheck->close();

updateOrderTotal($cal_id, $koneksi);

?>
