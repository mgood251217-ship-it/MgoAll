<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

header('Content-Type: application/json');

$name = trim($_POST['name'] ?? '');
$type = trim($_POST['type'] ?? '');

$stmt = $koneksi->prepare("INSERT INTO machine (store_id, name, type) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $store_id, $name, $type);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Mesin baru berhasil ditambahkan.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data ke database: ' . $stmt->error]);
}

$stmt->close();
?>