<?php
require_once '../connect.php';

$order_id = (int)($_POST['order_id'] ?? 0);
$note = trim($_POST['note'] ?? '');
$access = trim($_POST['access'] ?? '');

$response = ['success' => false];

if ($order_id && $note !== '') {
  // Cek apakah sudah ada note sebelumnya
  $cek = $koneksi->prepare("SELECT note_order_id, session FROM note_orders WHERE order_id = ? AND note_for = 'OP' ORDER BY note_order_id DESC LIMIT 1");
  $cek->bind_param("i", $order_id);
  $cek->execute();
  $result = $cek->get_result();
  $existing = $result->fetch_assoc();

  if ($existing) {
    // Update note terakhir
    $note_order_id = (int)$existing['note_order_id'];
    $note_session = (int)$existing['session'];
    if ($note_session <= 0 || $access == 'all') {
        $note_session_set = $note_session + 1;
        $update = $koneksi->prepare("UPDATE note_orders SET note = ?, session = ? WHERE note_order_id = ?");
        $update->bind_param("sii", $note, $note_session_set, $note_order_id);
        $update->execute();
        $response = ['success' => true];
    }else {
        $response = ['success' => false, 'message' => 'access'];
    }

  } else {
    // Insert note baru
    $insert = $koneksi->prepare("INSERT INTO note_orders (order_id, note, note_for) VALUES (?, ?, 'OP')");
    $insert->bind_param("is", $order_id, $note);
    $insert->execute();
    $response = ['success' => true];
  }
}
header('Content-Type: application/json');
echo json_encode($response);
exit;