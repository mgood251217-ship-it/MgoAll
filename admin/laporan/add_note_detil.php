<?php
require_once '../connect.php';
require_once BASE_PATH . '/models/Order.php';

$orderModel = new Order($koneksi);

$order_id = (int)($_POST['order_id'] ?? 0);
$note = trim($_POST['note'] ?? '');
$access = trim($_POST['access'] ?? '');

$response = ['success' => false];

if ($order_id && $note !== '') {
  $data = (object)[
    'order_id'=> $order_id,
    'note_for' => 'OP'
  ];
  $existing = $orderModel->getNoteOrder($data);

  if ($existing) {
    $note_order_id = (int)$existing['note_order_id'];
    $note_session = (int)$existing['session'];
    if ($note_session <= 0 || $access == 'all') {
        $note_session_set = $note_session + 1;
        if ($orderModel->updateNoteAndSession($note_order_id, $note_session_set, $note)) {
          $response = ['success' => true];
        }
    }else {
        $response = ['success' => false, 'message' => 'access'];
    }

  } else {
    $orderModel->createNote($order_id, $note, 'OP');
    $response = ['success' => true];
  }
}
header('Content-Type: application/json');
echo json_encode($response);
exit;