<?php
// add_failure_item.php
require_once '../connect.php';
require_once 'functions.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/models/Product.php';
require_once BASE_PATH . '/models/Order.php';
require_once BASE_PATH . '/models/Stock.php';

header('Content-Type: application/json');

$productModel = new Product($koneksi);
$orderModel = new Order($koneksi);
$stockModel = new Stock($koneksi);

$user_id_fail  = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$order_id   = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$diskon     = 0;
$judul = trim($_POST['judul'] ?? '');
$size = trim($_POST['size'] ?? '-');
$quantity = (int)($_POST['quantity'] ?? 1);
$finishing = trim($_POST['finishing'] ?? '-');
$panjang = (float)($_POST['panjang'] ?? 0);
$lebar = (float)($_POST['lebar'] ?? 0);
$finishing_cut = ($_POST['finishing_cut'] ?? '') == '1';
$finishing_die = ($_POST['finishing_die'] ?? '') == '1';
$finishing_jersey = $_POST['finishing_jersey'] ?? [];
$kiloan = (float)($_POST['kiloan'] ?? 0);
$nomorator = trim($_POST['nomorator'] ?? '');
$customer_name = trim($_POST['customer_name'] ?? '');
$machine_id = (int)($_POST['machine_id'] ?? 0);
$loss_burden = trim($_POST['loss_burden'] ?? '');
$info = trim($_POST['info'] ?? '');
$date = date("Y-m-d", strtotime($_POST['date']));

$failure_design = isset($_POST['failure_design']) && is_array($_POST['failure_design']) ? implode(',', $_POST['failure_design']) : '';
$failure_print = isset($_POST['failure_print']) && is_array($_POST['failure_print']) ? implode(',', $_POST['failure_print']) : '';
$failure_finishing = isset($_POST['failure_finishing']) && is_array($_POST['failure_finishing']) ? implode(',', $_POST['failure_finishing']) : '';
$failure_cause = isset($_POST['failure_cause']) && is_array($_POST['failure_cause']) ? implode(',', $_POST['failure_cause']) : '';
$failure_cause_other = trim($_POST['failure_cause_other'] ?? '');

if ($panjang > 0 && $lebar > 0) {
    $size = "{$panjang}x{$lebar}";
}

if ($product_id === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product ID tidak valid.']);
    exit;
}

$product = $productModel->getProductById($product_id);
if (!$product) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan di database.']);
    exit;
}

$product_type = $product['type'];
$unit_type = $product['unit_type'];
$product_name = $product['name'];
$product_price = $product['price'];

$stok_butuh = 0;
if ($panjang > 0 && $lebar > 0) {
    $stok_butuh = $panjang * $lebar * $quantity;
} elseif ($kiloan > 0) {
    $stok_butuh = $kiloan * $quantity;
} else {
    $stok_butuh = $quantity;
}

$finishing_ids = [];
$finishing_additional_price = 0;

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
} elseif($product_type == 'JERSEY'){
    $finishing_ids = array_unique(
        array_merge($finishing_ids, $finishing_jersey)
    );
}

if ($finishing !== '-' && is_numeric($finishing)) {
    $finishing_ids[] = (int)$finishing;
}

$finishing_str = count($finishing_ids) ? implode(',', $finishing_ids) : '-';

$sqlInsert = "INSERT INTO failure 
    (user_id, store_id, nomorator, customer_name, machine_id, product_id, judul, size, quantity, finishing, date, failure_design, failure_print, failure_finishing, failure_cause, failure_cause_other, loss_burden, info) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmtInsert = $koneksi->prepare($sqlInsert);

if (!$stmtInsert) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Kesalahan Struktur SQL: ' . $koneksi->error]);
    exit;
}

$stmtInsert->bind_param(
    "iissiisssissssssss", 
    $user_id_fail, 
    $store_id, 
    $nomorator, 
    $customer_name, 
    $machine_id, 
    $product_id, 
    $judul, 
    $size, 
    $quantity, 
    $finishing_str, 
    $date,
    $failure_design,
    $failure_print,
    $failure_finishing,
    $failure_cause,
    $failure_cause_other,
    $loss_burden,
    $info
);

if ($stmtInsert->execute()) {
    if ($unit_type !== '~') {
        $stmtUpdate = $koneksi->prepare("UPDATE stock SET quantity = quantity - ? WHERE product_id = ? AND store_id = ?");
        $stmtUpdate->bind_param("dii", $stok_butuh, $product_id, $store_id);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    }

    echo json_encode(['success' => true, 'message' => 'Item berhasil ditambahkan.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal Insert Data: ' . $stmtInsert->error]);
}

$stmtInsert->close();
?>