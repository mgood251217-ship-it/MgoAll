<?php
require_once '../connect.php';

header('Content-Type: application/json');
require_once BASE_PATH . '/session.php';

$id   = isset($_GET['id']) ? $_GET['id'] : '';

$stmtCalculator = $koneksi->prepare("DELETE FROM failure WHERE failure_id = ?");
$stmtCalculator->bind_param("i", $id);
$stmtCalculator->execute();

header("Location: index");
?>