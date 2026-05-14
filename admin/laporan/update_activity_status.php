<?php
require_once '../connect.php';
session_start();

if (!isset($_POST['activity_id'], $_POST['done'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$activity_id = (int)$_POST['activity_id'];
$done = (int)$_POST['done'];

$stmt = $koneksi->prepare("UPDATE activity SET done = ? WHERE activity_id = ?");
$stmt->bind_param("ii", $done, $activity_id);
$success = $stmt->execute();

echo json_encode(['success' => $success]);
