<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../connect.php';
require_once BASE_PATH . '/session.php';

$type = $_POST['type'] ?? '';
$id   = (int)($_POST['id'] ?? 0);

if (!$type || !$id) {
    die("Data tidak lengkap");
}

$table = ($type === 'income') ? 'income' : 'expenditures';
$id_field = ($type === 'income') ? 'income_id' : 'expenditure_id';

$storeNames = preg_replace('/[^a-zA-Z0-9_-]/', '_', $storeName ?? 'Toko');

if ($type === 'expenditures') {
    $stmt = $koneksi->prepare("SELECT date, img FROM expenditures WHERE expenditure_id = ? AND store_id = ?");
    $stmt->bind_param("ii", $id, $store_id);
    $stmt->execute();
    $stmt->bind_result($date, $img);
    $stmt->fetch();
    $stmt->close();

    if (!$date) {
        die("Data tidak ditemukan");
    }

    $ambilTahun   = date('Y', strtotime($date));
    $ambilBulan   = date('m', strtotime($date));
    $ambilTanggal = date('d', strtotime($date));
    $folderDate   = "$ambilTahun/$ambilBulan/$ambilTanggal";
    $uploadDir    = BASE_PATH . "/assets/img/bukti/$storeNames/$folderDate/";

    if (!empty($img)) {
        $imgPath = $uploadDir . $img;
        if (file_exists($imgPath)) {
            unlink($imgPath);
        }
    }

} else {
    $stmt = $koneksi->prepare("SELECT date FROM income WHERE income_id = ? AND store_id = ?");
    $stmt->bind_param("ii", $id, $store_id);
    $stmt->execute();
    $stmt->bind_result($date);
    $stmt->fetch();
    $stmt->close();

    if (!$date) {
        die("Data tidak ditemukan");
    }
}

$stmt = $koneksi->prepare("DELETE FROM $table WHERE $id_field = ? AND store_id = ?");
$stmt->bind_param("ii", $id, $store_id);
$stmt->execute();
$stmt->close();



$start_date_hapus = trim($_POST['start_date_hapus'] ?? '');
$end_date_hapus   = trim($_POST['end_date_hapus'] ?? '');

require_once '../global_functions.php';
refreshFinance($store_id, $start_date_hapus);

header("Location: keuangan.php?start_date=$start_date_hapus&end_date=$end_date_hapus");
exit;
