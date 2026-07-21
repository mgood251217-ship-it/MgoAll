<?php
require_once '../connect.php';
require_once '../global_functions.php';
require_once BASE_PATH . '/controllers/OrderController.php';
require_once BASE_PATH . '/controllers/SettingController.php';
require_once BASE_PATH . '/controllers/PaymentController.php';
require_once BASE_PATH . '/controllers/UserController.php';
require_once BASE_PATH . '/controllers/LocationController.php';
require_once BASE_PATH . '/controllers/StoreController.php';
require_once BASE_PATH . '/models/Order.php';
require_once BASE_PATH . '/models/Product.php';
require_once BASE_PATH . '/functions/helpers.php';
require_once BASE_PATH . '/functions/Otp.php';
require_once BASE_PATH . "/functions/setInfo.php";
require_once BASE_PATH . '/controllers/ProductController.php';
require_once BASE_PATH . '/controllers/AuthController.php';
require_once BASE_PATH . '/controllers/FailureController.php';

$authController = new AuthController($koneksi);
$productController = new ProductController($koneksi);
$productModel = new Product($koneksi);
$orderModel = new Order($koneksi);
$orderController = new OrderController($koneksi);
$settingController = new SettingController($koneksi);
$paymentController = new PaymentController($koneksi);
$userController = new UserController($koneksi);
$locationController = new LocationController($koneksi);
$storeController = new StoreController($koneksi);
$failureController = new FailureController($koneksi);

$action = $_GET['action'] ?? '';
if ($action != 'login'){
    require_once BASE_PATH . '/session.php';
}

switch ($action) {
    case 'login':
        $auth = new AuthController($koneksi);
        $auth->login();
        break;
    case 'logout':
        $auth = new AuthController($koneksi);
        $auth->logout();
        break;
    case 'theme':
        $settingController = new SettingController($koneksi);
        $settingController->changeTheme();
        break;
    case 'update_user':
        $userController->update();
        break;
    case 'create_user':
        $userController->create();
        break;
    case 'delete_user':
        $userController->delete();
        break;
    case 'set_location':
        $locationController->setLocation();
        break;
    case 'create_machine':
        $storeController->createMachine();
        break;
        // product
    case 'create_product':
        $productController->createProduct();
        break;
    case 'create_finishing':
        $productController->createFinishing();
        break;
    case 'update_stock':
        $productController->updateStock();
        break;
    case 'update_stock_finishing':
        $productController->updateStockFinishing();
        break;
    case 'update_product':
        $productController->updateProduct();
        break;
    case 'update_finishing':
        $productController->updateFinishing();
        break;
    case 'delete_product':
        $productController->deleteProduct();
        break;
    case 'delete_finishing':
        $productController->deleteFinishing();
        break;
    case 'save_note':
        $orderController->createNote();
        break;
    case 'get_orders':
        $orderController->index();
        break;
    case 'create_order':
        $orderController->create();
        break;
    case 'update_order':
        $orderController->update();
        break;
    case 'delete_order':
        $orderController->delete();
        break;
    case 'create_item':
        $orderController->createItem();
        break;
    case 'delete_item':
        $orderController->deleteItem();
        break;
    case 'get_order':
        $id = (int)($_GET['order_id'] ?? 0);
        $order = $orderModel->getOrderById($id);
        send_json_response(true, 'Order retrieved successfully.', $order);
        break;
    case 'get_category':
        $categories = $productModel->getCategoryByStoreId($store_id);
        send_json_response(true, 'categories retrieved successfully.', $categories);
        break;
    case 'get_product':
        $category_id = $_GET['category_id'] ?? '';
        $products = $productModel->getProductByCategoryId($category_id);
        send_json_response(true, 'Products retrieved successfully.', $products);
        break;
    case 'get_finishing':
        $category_id = $_GET['category_id'] ?? '';
        $finishings = $productModel->getFinishingByCategoryId($category_id);
        send_json_response(true, 'finishings retrieved successfully.', $finishings);
        break;
    case 'get_note':
        $order_id = $_GET['order_id'] ?? 0;
        $data = (object)['order_id' => $_GET['order_id'] ?? 0, 'note_for' => 'CTM'];
        $noted = $orderModel->getNoteOrder($data); 

        if ($noted && !empty($noted['note'])) {
            send_json_response(true, 'Note retrieved successfully.', ['note' => $noted['note']]);
        }
        break;
    case 'get_history':
        $data = (object)['name' => $_GET['name'] ?? '', 'store_id' => $store_id];
        $history = $orderModel->getHistoryNameAndNomor($data) ?? [];
        send_json_response(true, 'History retrieved successfully.', $history);

        break;
    case 'maklun':
        $data = (object) ['store_id_maklun' => $_POST['store_id_maklun'] ?? 0, 'order_item_id' => $_POST['order_item_id'] ?? 0];

        if ($orderModel->updateMaklun($data)) {
            send_json_response(true, 'Maklun updated successfully.');
        } else {
            send_json_response(false, 'Failed to update Maklun.');
        }
        break;
    case 'limit':
        $settingController->limit();
        break;
    case 'get_order_items':
        $orderController->orderDetail();
        break;
    case 'preview_print':
        $settingController->updatePreviewPrint();
        break;
    case 'process':
        $orderController->updateProject();
        break;
    case 'payment':
        $paymentController->create();
        break;
    case 'price':
        $orderController->fullPrice();
        break;
    case 'create_failure':
        $failureController->create();
        break;
    case 'update_failure_info':
        $failureController->updateInfo();
        break;
    case 'delete_failure':
        $failureController->delete();
        break;
    default:
        send_json_response(false, 'Invalid action.');
}