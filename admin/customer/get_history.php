<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "../connect.php";
require_once BASE_PATH . '/session.php';

$nameCustomer = $_GET['name'];

$history = [];

$stmt_history = $koneksi->prepare("SELECT DISTINCT customer_name AS name, nomor FROM orders WHERE store_id = ? AND customer_name LIKE ? LIMIT 10");
$keyword = "%" . $nameCustomer . "%";
$stmt_history->bind_param("is", $store_id, $keyword);
$stmt_history->execute();
$result = $stmt_history->get_result();

while ($a = $result->fetch_assoc()) {
    $history[] = $a;
}

echo json_encode($history);

?>