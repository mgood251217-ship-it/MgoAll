<?php
require_once '../connect.php';
require_once BASE_PATH . '/models/Order.php';

$orderModel = new Order($koneksi);

$order_id = $_GET['order_id'] ?? 0;
$data = new stdClass();
$data->order_id = $_GET['order_id'] ?? 0;
$data->note_for = 'CTM';

$result = $orderModel->getNoteOrder($data);
$noted = $result->fetch_assoc();

if ($noted && !empty($noted['note'])) {
  echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($noted['note']) . '</div>';
}
