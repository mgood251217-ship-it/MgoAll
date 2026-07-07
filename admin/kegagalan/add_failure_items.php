<?php
// add_failure_item.php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/models/Product.php';
require_once BASE_PATH . '/models/Order.php';
require_once BASE_PATH . '/models/Failure.php';
require_once BASE_PATH . '/controllers/OrderController.php';

header('Content-Type: application/json');

$request = $_POST;
if (empty($request)) {
    $rawInput = trim(file_get_contents('php://input'));
    if ($rawInput !== '') {
        $decodedInput = json_decode($rawInput, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedInput)) {
            $request = $decodedInput;
        }
    }
}

function requestValue(array $request, string $key, $default = null) {
    return array_key_exists($key, $request) ? $request[$key] : $default;
}

function requestArray(array $request, string $key): array {
    $value = requestValue($request, $key, []);
    if (is_array($value)) {
        return $value;
    }
    if ($value === '' || $value === null) {
        return [];
    }
    return explode(',', (string) $value);
}

$orderController = new OrderController($koneksi);
$productModel = new Product($koneksi);
$failureModel = new Failure($koneksi);
$orderModel = new Order($koneksi);

global $user_id;
global $store_id;

$user_id_fail  = (int) requestValue($request, 'user_id', $user_id ?? 0);
$order_id      = (int) requestValue($request, 'order_id', 0);
$product_id    = (int) requestValue($request, 'product_id', 0);
$diskon        = 0;
$judul         = trim((string) requestValue($request, 'judul', ''));
$size          = trim((string) requestValue($request, 'size', '-'));
$quantity      = (int) requestValue($request, 'quantity', 1);
$finishing     = trim((string) requestValue($request, 'finishing', '-'));
$panjang       = (float) requestValue($request, 'panjang', 0);
$lebar         = (float) requestValue($request, 'lebar', 0);
$finishing_cut = (string) requestValue($request, 'finishing_cut', '') === '1';
$finishing_die = (string) requestValue($request, 'finishing_die', '') === '1';
$finishing_jersey = requestArray($request, 'finishing_jersey');
$kiloan        = (float) requestValue($request, 'kiloan', 0);
$nomorator     = trim((string) requestValue($request, 'nomorator', ''));
$customer_name = trim((string) requestValue($request, 'customer_name', ''));
$machine_id    = (int) requestValue($request, 'machine_id', 0);
$loss_burden   = trim((string) requestValue($request, 'loss_burden', ''));
$info          = trim((string) requestValue($request, 'info', ''));
$dateRaw       = trim((string) requestValue($request, 'date', ''));
$date          = $dateRaw !== '' ? date('Y-m-d', strtotime($dateRaw)) : date('Y-m-d');

$failure_design = implode(',', requestArray($request, 'failure_design'));
$failure_print = implode(',', requestArray($request, 'failure_print'));
$failure_finishing = implode(',', requestArray($request, 'failure_finishing'));
$failure_cause = implode(',', requestArray($request, 'failure_cause'));
$failure_cause_other = trim((string) requestValue($request, 'failure_cause_other', ''));

if ($panjang > 0 && $lebar > 0) {
    $size = "{$panjang}x{$lebar}";
}

$product = $productModel->getProductById($product_id);

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

$fData = $orderController->finishingData($finishing, $panjang, $lebar);
$finishing_ids = $fData['ids'] ?? [];
$finishing_price = $fData['price'] ?? 0;
$finishing_str = count($finishing_ids) ? implode(',', $finishing_ids) : '-';

$data = [
    'user_id_fail' => $user_id_fail,
    'store_id' => $store_id,
    'nomorator' => $nomorator,
    'customer_name' => $customer_name,
    'machine_id' => $machine_id,
    'product_id' => $product_id,
    'judul' => $judul,
    'size' => $size,
    'quantity' => $quantity,
    'finishing_str' => $finishing_str,
    'date' => $date,
    'failure_design' => $failure_design,
    'failure_print' => $failure_print,
    'failure_finishing' => $failure_finishing,
    'failure_cause' => $failure_cause,
    'failure_cause_other' => $failure_cause_other,
    'loss_burden' => $loss_burden,
    'info' => $info
];

if ($failureModel->createFailure($data)) {
    if ($unit_type !== '~') {
        $productModel->updateStock($product_id, $stok_butuh);
    }
    echo json_encode(['success' => true, 'message' => 'Item berhasil ditambahkan.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal Insert Data: ']);
}
?>