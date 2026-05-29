<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/global_functions.php';
require_once BASE_PATH . '/controllers/UserController.php';
require_once BASE_PATH . '/controllers/LocationController.php';

$store = $_POST['store'] ?? '';

if ($store === 'update_user') {
    $userController = new UserController($koneksi);
    $userController->updateUser();
} elseif ($store === 'add_user') {
    $userController = new UserController($koneksi);
    $userController->addUser();
} elseif ($store === 'delete_user') {
    $userController = new UserController($koneksi);
    $userController->deleteUser();
} elseif ($store === 'set_location') {
    $locationController = new LocationController($koneksi);
    $locationController->setLocation();
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errors' => ["Aksi tidak valid."]]);
    exit;
}
?>