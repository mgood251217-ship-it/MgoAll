<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/controllers/StockController.php';

$stockController = new StockController($koneksi);
$action = $_POST['stock'] ?? '';

switch ($action) {
    case 'add_stock':
        $stockController->addStock();
        break;
    case 'update_stock':
        $stockController->updateStock();
        break;
    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'errors' => ['Aksi tidak valid.']]);
        exit;
}