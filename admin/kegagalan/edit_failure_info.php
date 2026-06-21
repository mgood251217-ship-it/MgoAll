<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

header('Content-Type: application/json');

$failure_id = (int)($_POST['failure_id'] ?? 0);
$info = trim($_POST['info'] ?? '');

if ($failure_id === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID kegagalan tidak valid.']);
    exit;
}

$stmt = $koneksi->prepare("UPDATE failure SET info = ? WHERE failure_id = ? AND store_id = ?");
$stmt->bind_param("sii", $info, $failure_id, $store_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Keterangan berhasil diperbarui.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui database: ' . $stmt->error]);
}

$stmt->close();
?>