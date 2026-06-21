<?php
require_once '../../connect.php';
session_start();
date_default_timezone_set('Asia/Jakarta');

// Validasi input
if (!isset($_POST['message'], $_POST['message_content'], $_POST['selected_stores'])) {
  die("Data tidak lengkap.");
}

$message = trim($_POST['message']);
$content = trim($_POST['message_content']);
$storeIds = explode(',', $_POST['selected_stores']);

$eventKey = 'information-' . date('Y-m-d');
$isRead = "0"; // string karena VARCHAR

// Siapkan statement
$stmt = $koneksi->prepare("
  INSERT IGNORE INTO notifications (store_id, message, message_content, is_read, event_key, created_at)
  VALUES (?, ?, ?, ?, ?, NOW())
");

$stmt->bind_param("issss", $store_id, $message, $content, $isRead, $eventKey);

// Eksekusi untuk setiap cabang
foreach ($storeIds as $id) {
  $store_id = (int)$id;
  if ($store_id > 0) {
    try {
      $stmt->execute();
    } catch (mysqli_sql_exception $e) {
      // Tampilkan error jelas ke layar saat debug
      echo "Error insert untuk store_id $store_id: " . $e->getMessage();
      exit;
    }
  }
}

$stmt->close();
header("Location: komunikasi.php?success=1");
exit;
