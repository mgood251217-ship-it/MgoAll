<?php
require_once '../connect.php';

$order_id = (int)($_GET['order_id'] ?? 0);

$stmt = $koneksi->prepare("SELECT nomorator, customer_name, nomor, system, deadline, date, user_id AS userid FROM orders WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();
$noted = $result->fetch_assoc();

echo json_encode($noted);
