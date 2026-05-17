<?php
require_once '../connect.php';
require_once 'functions.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/models/Order.php';
require_once BASE_PATH . '/models/User.php';
require_once BASE_PATH . '/models/Project.php';

$orderModel = new Order($koneksi);
$userModel = new User($koneksi);
$projectModel = new Project($koneksi);
date_default_timezone_set('Asia/Jakarta');

$deadline_input = $_POST['deadline'] ?? '';

$data = new stdClass();
$data->store_id = $store_id;
$data->user_id = $_POST['user_id'] ?? 0;
$data->system = ($userModel->getRoleById($data->user_id) === 'ONLINE') ? 'ONLINE' : 'OFFLINE';
$data->nomorator = generateNomorator($koneksi, $store_id, $data->system);
$data->customer_name = $_POST['customer_name'] ?? '';
$data->nomor = $_POST['nomor'] ?? '';
$data->total = 0;
$data->deadline = date('Y-m-d H:i:s', strtotime($deadline_input));
$data->date = date('Y-m-d H:i:s');

if ($data->deadline < $data->date) {
    header("Location: index");
    exit;
}

$insert = $orderModel->createOrder($data);

if ($insert) {
    $order_id = $koneksi->insert_id;
    $data->order_id = $order_id;
    $projectModel->createProject($data);
    ?>
    <form id="redirectForm" action="nota" method="post">
      <input type="hidden" name="order_id" value="<?= htmlspecialchars($order_id) ?>">
    </form>
    <script>document.getElementById('redirectForm').submit();</script>
    <?php
    exit;
} else {
    echo "Gagal menambahkan order: " . $stmt->error;
}
?>