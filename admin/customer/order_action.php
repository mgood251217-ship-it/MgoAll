<?php
require_once '../connect.php';
require_once '../global_functions.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/controllers/OrderController.php';
require_once BASE_PATH . '/controllers/SettingController.php';
require_once BASE_PATH . '/controllers/PaymentController.php';
require_once BASE_PATH . '/models/Order.php';
require_once BASE_PATH . '/models/Product.php';
require_once BASE_PATH . '/functions/helpers.php';

$productModel = new Product($koneksi);
$orderModel = new Order($koneksi);
$orderController = new OrderController($koneksi);
$settingController = new SettingController($koneksi);
$paymentController = new PaymentController($koneksi);

$order = $_GET['order'] ?? '';

switch ($order) {
    case 'save_note':
        $orderController->saveNote('CTM');
        break;
    case 'create':
        $orderController->create();
        break;
    case 'update':
        $orderController->update();
        break;
    case 'delete':
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
    case 'get_product':
        $type = $_GET['type'] ?? '';
        $data = (object)['type' => $type, 'store_id' => $store_id];
        $products = $productModel->getProductByTypeAndStoreId($data);
        send_json_response(true, 'Products retrieved successfully.', $products);
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
        header("Location: index");
        exit;
        break;
    case 'get_order_items':
        $order_id = (int)($_GET['order_id'] ?? 0);
        $orderController->get_order_items($order_id);
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
    default:
        send_json_response(false, 'Invalid action.');
}