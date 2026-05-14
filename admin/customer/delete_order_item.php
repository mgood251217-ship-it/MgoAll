<?php
ob_start(); // ⬅️ TAMBAHKAN INI
require_once '../connect.php';
session_start();
require_once BASE_PATH . '/session.php';
require_once 'functions.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$order_item_id = isset($_POST['order_item_id']) ? (int)$_POST['order_item_id'] : 0;

if ($order_item_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID item tidak valid.']);
    exit;
}

// Ambil data item
$sql = $koneksi->prepare("SELECT * FROM order_items WHERE order_item_id = ? AND store_id = ?");
$sql->bind_param("ii", $order_item_id, $store_id);
$sql->execute();
$result = $sql->get_result();
$item = $result->fetch_assoc();
$sql->close();

if (!$item) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Item tidak ditemukan.']);
    exit;
}

$product_id = $item['product_id'];
$quantity = $item['quantity'];
$size = $item['size'];
$finishing_ids = $item['finishing'];
$order_id = $item['order_id'];

// Ambil detail produk untuk perhitungan stok
$sqlProd = $koneksi->prepare("SELECT unit_type, type FROM products WHERE product_id = ? AND store_id = ?");
$sqlProd->bind_param("ii", $product_id, $store_id);
$sqlProd->execute();
$sqlProd->bind_result($unit_type, $type);
$sqlProd->fetch();
$sqlProd->close();

// Hitung kembali stok yang akan dikembalikan
$stok_kembali = $quantity;
$panjang = 0;
$lebar = 0;

if (preg_match('/([\d.]+)x([\d.]+)/', $size, $matches)) {
    $panjang = (float)$matches[1];
    $lebar = (float)$matches[2];
}

if ($unit_type === 'M2' || $unit_type === 'CM2') {
    $stok_kembali = round(($panjang / 100) * ($lebar / 100) * $quantity, 4);
}
if (strtoupper($type) === 'SPANDUK') {
    $stok_kembali = round((($panjang + 5) * ($lebar + 5)) / 10000 * $quantity, 4);
}

// Kembalikan stok utama
$stmtRestock = $koneksi->prepare("UPDATE stock SET quantity = quantity + ? WHERE product_id = ? AND store_id = ?");
$stmtRestock->bind_param("dii", $stok_kembali, $product_id, $store_id);
$stmtRestock->execute();
$stmtRestock->close();

// Kembalikan stok finishing jika ada
if ($finishing_ids !== '-') {
    $finishing_array = explode(',', $finishing_ids);
    foreach ($finishing_array as $fid) {
        $fid = (int)$fid;
        if ($fid === 0) continue;

        // Ambil type finishing
        $sqlFin = $koneksi->prepare("SELECT type FROM products WHERE product_id = ? AND store_id = ?");
        $sqlFin->bind_param("ii", $fid, $store_id);
        $sqlFin->execute();
        $sqlFin->bind_result($fin_type);
        $sqlFin->fetch();
        $sqlFin->close();

        $stok_kembali_fin = $quantity;
        $fin_type = strtoupper($fin_type);

        if ($fin_type === 'FINISHING STIKER A3' || $fin_type === 'FINISHING PHOTO A3') {
            $stok_kembali_fin = 0.1536 * $quantity;
        } elseif ($fin_type === 'FINISHING STIKER PERMETER' || $fin_type === 'FINISHING PHOTO PERMETER') {
            $panjang_meter = ($panjang > 20) ? $panjang / 100 : $panjang;
            $lebar_meter = ($lebar > 20) ? $lebar / 100 : $lebar;
            $stok_kembali_fin = $panjang_meter * $lebar_meter * $quantity;
        }

        $stmtFin = $koneksi->prepare("UPDATE stock SET quantity = quantity + ? WHERE product_id = ? AND store_id = ?");
        $stmtFin->bind_param("dii", $stok_kembali_fin, $fid, $store_id);
        $stmtFin->execute();
        $stmtFin->close();
    }
}

// Hapus item
$stmtDel = $koneksi->prepare("DELETE FROM order_items WHERE order_item_id = ? AND store_id = ?");
$stmtDel->bind_param("ii", $order_item_id, $store_id);
if ($stmtDel->execute()) {
    // Update total
    updateOrderTotal($order_id, $koneksi);
    ob_clean(); // 🧽 Bersihkan output buffer
    echo json_encode(['success' => true, 'message' => 'Item berhasil dihapus dan stok dikembalikan.']);
} else {
    http_response_code(500);
    ob_clean(); // 🧽
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus item.']);
}

$stmtDel->close();
