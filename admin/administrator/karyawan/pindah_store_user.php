<?php
require_once '../../connect.php';

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$store_id = isset($_POST['store_id']) ? (int)$_POST['store_id'] : 0;

if ($user_id && $store_id) {
  $stmt = $koneksi->prepare("UPDATE users SET store_id = ? WHERE user_id = ?");
  $stmt->bind_param("ii", $store_id, $user_id);
  if ($stmt->execute()) {
    echo "OK";
  } else {
    echo "Update gagal";
  }
} else {
  echo "Data tidak valid";
}
