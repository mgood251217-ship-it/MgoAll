<?php
require_once '../connect.php';
require_once 'functions.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once BASE_PATH . '/session.php';

$user_id_fail  = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$order_id   = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$diskon     = isset($_POST['diskon']) ? (int)$_POST['diskon'] : 0;
$diskon     = 0;
$judul      = isset($_POST['judul']) ? trim($_POST['judul']) : '';
$size       = isset($_POST['size']) ? trim($_POST['size']) : '-';
$quantity   = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
$finishing  = isset($_POST['finishing']) ? trim($_POST['finishing']) : '-';
$panjang    = isset($_POST['panjang']) ? (float)$_POST['panjang'] : 0;
$lebar      = isset($_POST['lebar']) ? (float)$_POST['lebar'] : 0;
$finishing_cut = isset($_POST['finishing_cut']) && $_POST['finishing_cut'] == '1';
$finishing_die = isset($_POST['finishing_die']) && $_POST['finishing_die'] == '1';
$finishing_jersey = $_POST['finishing_jersey'] ?? [];
$kiloan    = isset($_POST['kiloan']) ? (float)$_POST['kiloan'] : 0;

// Set timezone & waktu sekarang
date_default_timezone_set('Asia/Jakarta');
$tanggalSekarang = date('Y-m-d H:i:s');

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

$finishing_ids = [];
$result = cekDanKurangiStokFinishing($koneksi, $store_id, $quantity, $panjang, $lebar, $finishing);
if (!$result) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Stok finishing tidak mencukupi.']);
    exit;
}

if ($finishing_cut) {
    $finishing_type = ($product_type === 'INDOOR') ? 'FINISHING INDOOR' : 'FINISHING LASER A3';
    addFinishing('KISS CUT', $store_id, $finishing_type, $koneksi, $finishing_ids, $finishing_additional_price, $panjang, $lebar, $product_type);
}

if ($finishing_die) {
    $finishing_type = ($product_type === 'INDOOR') ? 'FINISHING INDOOR' : 'FINISHING LASER A3';
    addFinishing('DIE CUT', $store_id, $finishing_type, $koneksi, $finishing_ids, $finishing_additional_price, $panjang, $lebar, $product_type);
}
if (str_contains($product_name, 'BAHAN') && $unit_type == 'PCS' && $kiloan != 0) {
    $size = strval($kiloan) . ' KG';
}elseif($product_type == 'JERSEY'){
    $finishing_ids = array_unique(
        array_merge($finishing_ids, $finishing_jersey)
    );
}
if ($finishing !== '-' && is_numeric($finishing)) {
    $finishing_ids[] = (int)$finishing;
}
$finishing_str = count($finishing_ids) ? implode(',', $finishing_ids) : '-';



// Insert baru
$stmtInsert = $koneksi->prepare("INSERT INTO failure
    (user_id, store_id, product_id, judul, size, quantity, finishing, date) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

if (!$stmtInsert) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal mempersiapkan statement insert: ' . $koneksi->error]);
    exit;
}

$stmtInsert->bind_param("iiississ",
    $user_id_fail, $store_id, $product_id, $judul, $size, $quantity, $finishing_str, $tanggalSekarang);

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

?>
