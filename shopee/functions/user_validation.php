<?php
session_start();

if (!isset($_SESSION['shopee_users'])) {
    header("Location: " . BASE_URL );
    exit;
}
?>