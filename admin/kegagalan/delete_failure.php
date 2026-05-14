<?php
require_once '../connect.php';
require_once 'functions.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once BASE_PATH . '/session.php';

$id   = isset($_GET['id']) ? $_GET['id'] : '';

$stmtCalculator = $koneksi->prepare("DELETE FROM failure WHERE failure_id = ?");
$stmtCalculator->bind_param("i", $id);
$stmtCalculator->execute();

header("Location: kegagalan.php");
?>