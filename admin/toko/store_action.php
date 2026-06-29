<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/global_functions.php';
require_once BASE_PATH . '/controllers/UserController.php';
require_once BASE_PATH . '/controllers/LocationController.php';
require_once BASE_PATH . '/controllers/StoreController.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'update_user':
        $userController = new UserController($koneksi);
        $userController->updateUser();
        break;
    case 'create_user':
        $userController = new UserController($koneksi);
        $userController->addUser();
        break;
    case 'delete_user':
        $userController = new UserController($koneksi);
        $userController->deleteUser();
        break;
    case 'set_location':
        $locationController = new LocationController($koneksi);
        $locationController->setLocation();
        break;
    case 'create_machine':
        $storeController = new StoreController($koneksi);
        $storeController->createMachine();
        break;
    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'errors' => ["Aksi tidak valid."]]);
        exit;
}
?>