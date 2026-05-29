<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
$limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 0;

$stmt = $koneksi->prepare("SELECT 1 FROM user_setting WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $stmt = $koneksi->prepare("UPDATE user_setting SET customer_limit = ? WHERE user_id = ?");
    $stmt->bind_param("ii", $limit, $user_id);
    $stmt->execute();
} else {
    $stmt = $koneksi->prepare("INSERT INTO user_setting (user_id, customer_limit) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
}

header("Location: index");
exit;


?>