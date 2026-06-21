<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

$start_date_full = isset($_GET['start_date']) ? $_GET['start_date'] . " 00:00:00" : date('Y-m-d 00:00:00');
$end_date_full   = isset($_GET['end_date']) ? $_GET['end_date'] . " 23:59:59" : date('Y-m-d 23:59:59');

$stmtOrder = $koneksi->prepare("SELECT order_id FROM orders WHERE store_id = ? AND date BETWEEN ? AND ?");
$stmtOrder->bind_param("iss", $store_id, $start_date_full, $end_date_full);
$stmtOrder->execute();
$resOrder = $stmtOrder->get_result();
$rows = $resOrder->fetch_all(MYSQLI_ASSOC);
$order_ids = array_column($rows, 'order_id');

$stmtOrder->close();

?>