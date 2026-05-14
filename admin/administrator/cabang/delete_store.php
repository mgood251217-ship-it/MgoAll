<?php
require_once '../../connect.php';
require_once BASE_PATH . '/global_functions.php';

if (!empty($_POST['store_id'])) {
  $store_id = (int) $_POST['store_id'];

  // Validasi: apakah store sedang digunakan oleh user lain
  $check = $koneksi->prepare("SELECT 1 FROM users WHERE store_id = ?");
  if ($check) {
    $check->bind_param("i", $store_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
      $_SESSION['swal_error'] = "Cabang tidak bisa dihapus karena masih digunakan oleh user.";
      $check->close();
      header("Location: cabang.php");
      exit;
    }

    $check->close();
  } else {
    $_SESSION['swal_error'] = "Gagal menyiapkan validasi pengguna.";
    header("Location: cabang.php");
    exit;
  } 

  // Lanjut hapus cabang
  $delete = $koneksi->prepare("DELETE FROM stores WHERE store_id = ?");
  $delete->bind_param('i', $store_id);
  $delete->execute();
  $delete->close();
  $_SESSION['swal_success'] = "Sukses menghapus cabang";
  
} else {
  $_SESSION['swal_error'] = "ID cabang tidak ditemukan.";
}

header("Location: cabang.php");
exit;
