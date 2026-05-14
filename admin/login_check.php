<?php
require_once 'connect.php';
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: " . BASE_URL . "/login");
    exit;
}
?>