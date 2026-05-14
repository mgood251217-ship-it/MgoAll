<?php
require_once '../connect.php';
require_once 'functions.php';
require_once BASE_PATH . '/session.php';
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$order_id   = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$diskon     = isset($_POST['diskon']) ? (int)$_POST['diskon'] : 0;
$diskon     = handleDiskonOrderItem($koneksi, $order_id, $product_id, $diskon);
$judul      = isset($_POST['judul']) ? trim($_POST['judul']) : '';
$size       = isset($_POST['size']) ? trim($_POST['size']) : '-';
$quantity   = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
$finishing  = isset($_POST['finishing']) ? trim($_POST['finishing']) : '-';
$panjang    = isset($_POST['panjang']) ? (float)$_POST['panjang'] : 0;
$lebar      = isset($_POST['lebar']) ? (float)$_POST['lebar'] : 0;
$waktu      = isset($_POST['waktu']) ? (float)$_POST['waktu'] : 0;
$finishing_cut = isset($_POST['finishing_cut']) && $_POST['finishing_cut'] == '1';
$finishing_die = isset($_POST['finishing_die']) && $_POST['finishing_die'] == '1';
$finishing_jersey = $_POST['finishing_jersey'] ?? [];
$kiloan    = isset($_POST['kiloan']) ? (float)$_POST['kiloan'] : 0;



if ($panjang > 0 && $lebar > 0) {
    $size = "{$panjang}x{$lebar}";
}

$info = cekStokProdukUtama($koneksi, $product_id, $store_id, $lebar, $panjang, $quantity);

$product_type = $info['type'];
$unit_type    = $info['unit_type'];
$stok_butuh   = $info['stok_butuh'];
$product_name = $info['name'];

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



if ($unit_type === 'M2') {
    if ($product_type === 'DTF') {
        $unit *= $panjang;
    } else {
        $unit *= $panjang * $lebar;
    }
} elseif ($unit_type === 'CM2') {
    $unit *= $panjang * $lebar;
} elseif (str_contains($product_name, 'BAHAN') && $unit_type == 'PCS' && $kiloan != 0) {
    $unit *= $kiloan;
    $size = strval($kiloan) . ' KG';
} elseif ($product_name == 'SETTING' && $product_type == 'JASA'){
    if ($waktu < 15) {
        $waktu = 15;
    } 
    if ($waktu >= 60) {
        $jam = floor($waktu / 60);
        $sisa_menit = $waktu % 60;
        $size = strval($jam) . ' Jam ' . strval($sisa_menit) . ' Menit';
    }else {
        $size = strval($waktu) . ' Menit';
    }
    $unit *= $waktu / 60;

} elseif ($product_name == 'POTONG AKRILIK' && $product_type == 'JASA'){
    $unit *= $waktu;
    $size = strval($waktu) . ' MENIT';
}

if ($finishing !== '-' && is_numeric($finishing)) {
    $finishing_ids[] = (int)$finishing;
}

// Tambahkan harga finishing tambahan (CUT, DIE)
$unit += $finishing_additional_price;

// Cek jika type adalah JERSEY
if ($product_type === 'JERSEY') {
    if ($size === '5XL') {
        $unit += 50000;
    } elseif ($size === '4XL') {
        $unit += 40000;
    } elseif ($size === '3XL') {
        $unit += 30000;
    } elseif ($size === '2XL') {
        $unit += 20000;
    } elseif ($size === 'XL') {
        $unit += 10000;
    }
    $unit = $unit += getJerseyFinishingPrice($finishing_jersey, $store_id, $koneksi);
        
    $finishing_ids = array_unique(
        array_merge($finishing_ids, $finishing_jersey)
    );

}

$amount = $unit * $quantity;
if ($product_name == 'PRINT UV' && $product_type == 'AKRILIK') {
    if ($amount < 7500) {
        $amount = 7500;
    }
}
$finishing_str = count($finishing_ids) ? implode(',', $finishing_ids) : '-';

// Cek apakah sudah ada order_items dengan judul, finishing, size sama di order_id & store_id yang sama
$stmtCheck = $koneksi->prepare("SELECT order_item_id, quantity, unit, amount FROM order_items WHERE store_id = ? AND order_id = ? AND judul = ? AND finishing = ? AND size = ?");
$stmtCheck->bind_param("iisss", $store_id, $order_id, $judul, $finishing_str, $size);
$stmtCheck->execute();
$resultCheck = $stmtCheck->get_result();

if ($rowExist = $resultCheck->fetch_assoc()) {
    // Jika ada, update quantity, unit, dan amount
    $new_quantity = $rowExist['quantity'] + $quantity;
    $new_amount = $unit * $new_quantity;

    $stmtUpdate = $koneksi->prepare("UPDATE order_items SET quantity = ?, unit = ?, amount = ? WHERE order_item_id = ?");
    $stmtUpdate->bind_param("iddi", $new_quantity, $unit, $new_amount, $rowExist['order_item_id']);
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
    $stmtInsert = $koneksi->prepare("INSERT INTO order_items 
        (store_id, order_id, product_id, judul, size, quantity, unit, amount, finishing) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmtInsert) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal mempersiapkan statement insert: ' . $koneksi->error]);
        exit;
    }

    $stmtInsert->bind_param("iisssiiis",
        $store_id, $order_id, $product_id, $judul, $size, $quantity, $unit, $amount, $finishing_str);

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

updateOrderTotal($order_id, $koneksi);
updateStatusPembayaranTerbaru($koneksi, $order_id);

?>
