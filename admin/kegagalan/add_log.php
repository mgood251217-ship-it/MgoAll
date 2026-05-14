<?php
require_once '../connect.php';
require_once 'functions.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once BASE_PATH . '/session.php';

// Set timezone & waktu sekarang
date_default_timezone_set('Asia/Jakarta');
$tanggalSekarang = date('Y-m-d H:i:s');

$user_id_fail   = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$nama   = isset($_POST['name']) ? $_POST['name'] : '';
$total = 0;

$stmtCalculator = $koneksi->prepare("INSERT INTO calculator (store_id, customer_name, total, user_id, date) VALUES (?, ?, ?, ?, ?)");
$stmtCalculator->bind_param("isiis", $store_id, $nama, $total, $user_id_fail, $tanggalSekarang);
$stmtCalculator->execute();

header("Location: kalkulator.php");
?>