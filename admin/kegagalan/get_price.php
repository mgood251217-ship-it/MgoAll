<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../connect.php';
require_once 'functions.php';
require_once BASE_PATH . '/session.php';

$order_id   = (int)($_POST['order_id'] ?? 0);
$product_id = (int)($_POST['product_id'] ?? 0);
$diskon     = isset($_POST['diskon']) ? (int)$_POST['diskon'] : 0;
$diskon     = handleDiskonOrderItem($koneksi, $order_id, $product_id, $diskon);
$judul      = trim($_POST['judul'] ?? '');
$size       = trim($_POST['size'] ?? '-');
$quantity   = (int)($_POST['quantity'] ?? 1);
$finishing  = trim($_POST['finishing'] ?? '-');
$finishing_tambahan = trim($_POST['finishing_tambahan'] ?? '-');
$panjang    = (float)($_POST['panjang'] ?? 0);
$lebar      = (float)($_POST['lebar'] ?? 0);

if (!$store_id || !$product_id || $quantity < 1) {
    echo json_encode(['success' => false, 'message' => 'Data input tidak lengkap']);
    exit;
}

$info = cekStokProdukUtama($koneksi, $product_id, $store_id, $lebar, $panjang, $quantity);
$product_type = $info['type'] ?? '';
$unit_type    = $info['unit_type'] ?? '';
$product_price = getProductPrice($product_id, $store_id, $koneksi);

$finishing_price = 0;
$finishing_ids = [];

function addUniqueFinishing($name, $store_id, $product_type, $koneksi, &$finishing_ids, &$finishing_price, $panjang, $lebar) {
    $finishing_type = ($product_type === 'INDOOR') ? 'FINISHING INDOOR' : 'FINISHING LASER A3';
    $extra_price = 0;
    $before_count = count($finishing_ids);
    addFinishing(strtoupper(str_replace('_', ' ', $name)), $store_id, $finishing_type, $koneksi, $finishing_ids, $extra_price, $panjang, $lebar, $product_type);
    $after_count = count($finishing_ids);

    if ($after_count > $before_count) {
        $finishing_price += $extra_price;
    }
}

// Proses finishing dari select input (id atau nama)
if ($finishing !== '-' && !empty($finishing)) {
    $parts = array_map('trim', explode(',', $finishing));
    foreach ($parts as $part) {
        if (is_numeric($part)) {
            if (!in_array((int)$part, $finishing_ids, true)) {
                $finishing_price += getProductPrice((int)$part, $store_id, $koneksi);
                $finishing_ids[] = (int)$part;
            }
        } else {
            addUniqueFinishing($part, $store_id, $product_type, $koneksi, $finishing_ids, $finishing_price, $panjang, $lebar);
        }
    }
}

// Proses finishing tambahan khusus (kiss_cut atau die_cut)
if ($finishing_tambahan !== '-' && !empty($finishing_tambahan)) {
    if (in_array($finishing_tambahan, ['kiss_cut', 'die_cut'])) {
        addUniqueFinishing($finishing_tambahan, $store_id, $product_type, $koneksi, $finishing_ids, $finishing_price, $panjang, $lebar);
    }
}

// Bersihkan finishing_ids agar unik dan numeric
$finishing_ids = array_values(array_unique(array_filter($finishing_ids, 'is_numeric')));

// Hitung harga satuan produk + finishing
$unit_price = $product_price - $diskon + $finishing_price;

// Hitung berdasarkan satuan
if ($unit_type === 'M2') {
    $unit_price *= ($product_type === 'DTF') ? $panjang : $panjang * $lebar;
} elseif ($unit_type === 'CM2') {
    $unit_price *= $panjang * $lebar;
}

// Hitung total harga akhir
$total_price = $unit_price * $quantity;

echo json_encode([
    'success' => true,
    'product_price' => $product_price,
    'unit_type' => $unit_type,
    'finishing_price' => $finishing_price,
    'finishing_ids' => $finishing_ids,
    'diskon' => $diskon,
    'unit_price' => $unit_price,
    'quantity' => $quantity,
    'total_price' => $total_price
]);
exit;
