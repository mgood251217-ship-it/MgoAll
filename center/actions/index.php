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

    case 'add_user':
    case 'edit_user':
    case 'delete_user':
    case 'restore_user':
    case 'change_user_store':
        require_once __DIR__ . '/../controllers/UserController.php';
        $userController = new UserController($koneksi);
        if ($action === 'add_user') $userController->addUser();
        if ($action === 'edit_user') $userController->editUser();
        if ($action === 'delete_user') $userController->deleteUser();
        if ($action === 'restore_user') $userController->restoreUser();
        if ($action === 'change_user_store') $userController->changeStore();
        break;

    case 'add_product':
    case 'edit_product':
    case 'delete_product':
    case 'add_finishing':
    case 'edit_finishing':
    case 'delete_finishing':
        require_once __DIR__ . '/../controllers/ProductController.php';
        $productController = new ProductController($koneksi);
        if ($action === 'add_product') $productController->addProduct();
        if ($action === 'edit_product') $productController->editProduct();
        if ($action === 'delete_product') $productController->deleteProduct();
        if ($action === 'add_finishing') $productController->addFinishing();
        if ($action === 'edit_finishing') $productController->editFinishing();
        if ($action === 'delete_finishing') $productController->deleteFinishing();
        break;

    case 'delete_order':
    case 'clear_order_items':
        require_once __DIR__ . '/../controllers/OrderController.php';
        $orderController = new OrderController($koneksi);
        if ($action === 'delete_order') $orderController->deleteOrder();
        if ($action === 'clear_order_items') $orderController->clearOrderItems();
        break;
        
    default:
        http_response_code(404);
        echo "Action not found";
        break;
}