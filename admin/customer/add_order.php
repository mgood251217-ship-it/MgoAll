<?php
require_once '../connect.php';
require_once 'functions.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/controllers/OrderController.php';

$orderController = new OrderController($koneksi);
$orderController->create();