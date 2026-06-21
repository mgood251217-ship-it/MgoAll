<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

$type         = $_POST['type'] ?? '';
$old_info     = trim($_POST['old_info'] ?? '');
$new_info     = strtoupper(trim($_POST['information'] ?? ''));
$new_nominal  = (int)($_POST['nominal'] ?? 0);
$start_date   = trim($_POST['start_date'] ?? '');
$end_date     = trim($_POST['end_date'] ?? '');

if (!$type || !$old_info || !$new_info || $new_nominal <= 0) {
    header("Location: keuangan.php?start_date=$start_date&end_date=$end_date");
    exit;
}
 
$table = ($type === 'income') ? 'income' : 'expenditures';

// Ambil tanggal dari data lama (untuk refreshFinance)
$stmt = $koneksi->prepare("SELECT date FROM $table WHERE store_id = ? AND information = ? LIMIT 1");
$stmt->bind_param("is", $store_id, $old_info);
$stmt->execute();
$stmt->bind_result($tanggal);
$stmt->fetch();
$stmt->close();

if (!$tanggal) {
    die("Data tidak ditemukan");
}

// Update data
$query = "
    UPDATE $table SET 
        nominal = ?, 
        information = ?
    WHERE store_id = ? 
      AND information = ?
    LIMIT 1
";
$stmt = $koneksi->prepare($query);
$stmt->bind_param("isis", $new_nominal, $new_info, $store_id, $old_info);
$stmt->execute();
$stmt->close();

// Refresh keuangan
require_once '../global_functions.php';
refreshFinance($store_id, $start_date);

header("Location: keuangan.php?start_date=$start_date&end_date=$end_date");
exit;
