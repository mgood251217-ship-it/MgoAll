<?php
require_once '../../connect.php';
require_once BASE_PATH . '/global_functions.php';

// Ambil input dari form
$store_id = $_POST['store_id'] ?? null;
$name     = $_POST['name'] ?? '';
$address  = $_POST['address'] ?? '';
$nomor    = $_POST['nomor'] ?? '';
$branch   = $_POST['branch'] ?? '';
$owner_id = $_POST['owner_id'] ?? null;
$email    = $_POST['email'] ?? '';

// Validasi dasar
if ($store_id && $name && $address && $branch && $email) {
  $store_id = (int)$store_id;
  $owner_id = $owner_id ? (int)$owner_id : null;

  // Update data store
  $stmt = $koneksi->prepare("UPDATE stores SET name = ?, address = ?, nomor = ?, branch = ?, owner_id = ?, email = ? WHERE store_id = ?");
  $stmt->bind_param("ssssisi", $name, $address, $nomor, $branch, $owner_id, $email, $store_id);

  if ($stmt->execute()) {
    // Hanya update users jika owner_id tidak kosong
    if ($owner_id) {
      $updateStmt = $koneksi->prepare("UPDATE users SET store_id = ?, role = 'MANAGER' WHERE user_id = ?");
      $updateStmt->bind_param("ii", $store_id, $owner_id);
      $updateStmt->execute();
      $updateStmt->close();
    }

    $_SESSION['swal_success'] = "Cabang berhasil diperbarui.";
  } else {
    $_SESSION['swal_error'] = "Gagal memperbarui cabang: " . $stmt->error;
  }

  $stmt->close();
} else {
  $_SESSION['swal_error'] = "Field wajib diisi: nama, alamat, cabang, email harus lengkap.";
}

$koneksi->close();
header("Location: cabang.php");
exit;
