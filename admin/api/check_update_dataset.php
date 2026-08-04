<?php
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    "http://localhost:51730",
    "http://localhost:5173",
    "https://mgood.my.id",
];

if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header("Access-Control-Allow-Credentials: true");
    header("Vary: Origin");
}

header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Client-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../connect.php';
require_once BASE_PATH . '/middleware/init_auth.php';

header('Content-Type: application/json');

global $store_id;

if (!$store_id) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$dataset_path = BASE_PATH . '/temp/dataset/store_' . $store_id . '.json';
$order_trigger_path = BASE_PATH . '/temp/orders/store_' . $store_id . '.json';

$data = [];

if (file_exists($dataset_path)) {
    $dataset_json = file_get_contents($dataset_path);
    $dataset_arr = json_decode($dataset_json, true);
    if (is_array($dataset_arr)) {
        $data = array_merge($data, $dataset_arr);
    }
}

if (file_exists($order_trigger_path)) {
    $order_json = file_get_contents($order_trigger_path);
    $order_arr = json_decode($order_json, true);
    if (is_array($order_arr)) {
        $data['order_trigger'] = $order_arr;
    }
}

echo json_encode([
    'status' => 'success',
    'data' => $data
]);
?>