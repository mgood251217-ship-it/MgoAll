<?php
$action = isset($_GET['action']) ? $_GET['action'] : '';

if (empty($action)) {
    $parts = explode('/', $route);
    if (isset($parts[1])) {
        $action = $parts[1];
    }
}

require_once __DIR__ . '/../config/connect.php';

switch ($action) {
    case 'login':
    case 'logout':
        require_once __DIR__ . '/../controllers/AuthController.php';
        $authController = new AuthController();
        if ($action === 'login') $authController->login();
        if ($action === 'logout') $authController->logout();
        break;
        
    case 'add_store':
    case 'edit_store':
    case 'set_session':
        require_once __DIR__ . '/../controllers/StoreController.php';
        $storeController = new StoreController($koneksi);
        if ($action === 'add_store') $storeController->addStore();
        if ($action === 'edit_store') $storeController->editStore();
        if ($action === 'set_session') $storeController->setSession();
        break;
        
    default:
        http_response_code(404);
        echo "Action not found";
        break;
}