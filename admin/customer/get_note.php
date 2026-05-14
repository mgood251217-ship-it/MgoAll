<?php
require_once '../connect.php';

$order_id = (int)($_GET['order_id'] ?? 0);

$stmt = $koneksi->prepare("SELECT note FROM note_orders WHERE order_id = ? AND note_for = 'CTM' ORDER BY note_order_id DESC LIMIT 1");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();
$noted = $result->fetch_assoc();

if ($noted && !empty($noted['note'])) {
  echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($noted['note']) . '</div>';
}
