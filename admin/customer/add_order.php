<?php
require_once '../connect.php';
require_once 'functions.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/models/Order.php';
require_once BASE_PATH . '/models/User.php';
require_once BASE_PATH . '/models/Project.php';

header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

$orderModel = new Order($koneksi);
$userModel = new User($koneksi);
$projectModel = new Project($koneksi);

$deadline_input = $_POST['deadline'] ?? '';

$data = new stdClass();
$data->store_id = $store_id;
$data->user_id = $_POST['user_id'] ?? 0;
$data->system = ($userModel->getRoleById($data->user_id) === 'ONLINE') ? 'ONLINE' : 'OFFLINE';
$data->nomorator = generateNomorator($koneksi, $data->store_id, $data->system);
$data->customer_name = $_POST['customer_name'] ?? '';
$data->nomor = $_POST['nomor'] ?? '';
$data->total = 0;
$data->deadline = date('Y-m-d H:i:s', strtotime($deadline_input));
$data->date = date('Y-m-d H:i:s');

$deadline_check = date('Y-m-d', strtotime($deadline_input));
$today_check = date('Y-m-d');

if (($deadline_check < $today_check) || $data->customer_name == '' || $data->user_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Validasi gagal. Data tidak lengkap atau deadline tidak valid.']);
    exit;
}

$insert = $orderModel->createOrder($data);

if ($insert) {
    $order_id = $koneksi->insert_id;
    $data->order_id = $order_id;
    $projectModel->createProject($data);
    
    echo json_encode(['status' => 'success', 'order_id' => $order_id]);
    exit;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal menambahkan order']);
    exit;
}
?>