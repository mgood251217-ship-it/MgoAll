<?php
require_once '../connect.php';

$order_id = (int)($_POST['order_id'] ?? 0);
$note = trim($_POST['note'] ?? '');

if ($order_id && $note !== '') {
  $cek = $koneksi->prepare("SELECT note_order_id FROM note_orders WHERE order_id = ? AND note_for = 'CTM' ORDER BY note_order_id DESC LIMIT 1");
  $cek->bind_param("i", $order_id);
  $cek->execute();
  $result = $cek->get_result();
  $existing = $result->fetch_assoc();

  if ($existing) {
    $note_order_id = (int)$existing['note_order_id'];
    $update = $koneksi->prepare("UPDATE note_orders SET note = ? WHERE note_order_id = ?");
    $update->bind_param("si", $note, $note_order_id);
    $update->execute();
  } else {
    $insert = $koneksi->prepare("INSERT INTO note_orders (order_id, note, note_for) VALUES (?, ?, 'CTM')");
    $insert->bind_param("is", $order_id, $note);
    $insert->execute();
  }

  echo htmlspecialchars($note);
}
