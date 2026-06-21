<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/global_functions.php';
require_once BASE_PATH . '/controllers/UserController.php';
require_once BASE_PATH . '/controllers/LocationController.php';
require_once BASE_PATH . '/controllers/StoreController.php';

$action = $_GET['action'] ?? '';

if ($action === 'update_user') {
    $userController = new UserController($koneksi);
    $userController->updateUser();
} elseif ($action === 'create_user') {
    $userController = new UserController($koneksi);
    $userController->addUser();
} elseif ($action === 'delete_user') {
    $userController = new UserController($koneksi);
    $userController->deleteUser();
} elseif ($action === 'set_location') {
    $locationController = new LocationController($koneksi);
    $locationController->setLocation();
} elseif ($action === 'create_machine') {
    $storeController = new StoreController($koneksi);
    $storeController->createMachine();
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errors' => ["Aksi tidak valid."]]);
    exit;
}
?>