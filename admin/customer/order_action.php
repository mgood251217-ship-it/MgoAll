<?php
require_once '../connect.php';
require_once '../global_functions.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/controllers/OrderController.php';
require_once BASE_PATH . '/controllers/SettingController.php';
require_once BASE_PATH . '/controllers/PaymentController.php';
require_once BASE_PATH . '/models/Order.php';
require_once BASE_PATH . '/models/Product.php'; 

$productModel = new Product($koneksi);
$orderModel = new Order($koneksi);
$orderController = new OrderController($koneksi);
$settingController = new SettingController($koneksi);
$paymentController = new PaymentController($koneksi);

$order = $_GET['order'] ?? '';

if ($order == 'save_note'){
    $orderController->saveNote();
}else if ($order == 'create'){
    $orderController->create();
}else if ($order == 'update'){
    $orderController->update();
}else if ($order == 'delete'){
    $orderController->delete();
}else if ($order == 'create_item'){
    $orderController->createItem();
}else if ($order == 'delete_item'){
    $orderController->deleteItem();
}else if ($order == 'get_order'){
    $id = (int)($_GET['order_id'] ?? 0);
    $order = $orderModel->getOrderById($id);
    echo json_encode($order);
}else if ($order == 'get_product'){
    $type = $_GET['type'] ?? '';
    $data = (object)['type' => $type, 'store_id' => $store_id];
    $products = $productModel->getProductByTypeAndStoreId($data);

    echo json_encode($products);
}else if ($order == 'get_note'){
    $order_id = $_GET['order_id'] ?? 0;
    $data = (object)['order_id' => $_GET['order_id'] ?? 0, 'note_for' => 'CTM'];
    $noted = $orderModel->getNoteOrder($data); 

    if ($noted && !empty($noted['note'])) {
        echo htmlspecialchars($noted['note']);
    }
}else if ($order == 'get_history'){
    $data = (object)['name' => $_GET['name'] ?? '', 'store_id' => $store_id];
    $history = $orderModel->getHistoryNameAndNomor($data) ?? [];

    echo json_encode($history);
}else if ($order == 'maklun'){
    $data = (object) ['store_id_maklun' => $_POST['store_id_maklun'] ?? 0, 'order_item_id' => $_POST['order_item_id'] ?? 0];
    $update = $orderModel->updateMaklun($data);

    if ($update) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
}else if ($order == 'limit'){
    $settingController->limit();
    header("Location: index");
    exit;
}else if ($order == 'get_order_items'){
    $order_id = (int)$_GET['order_id'];
    $orderController->get_order_items($order_id);

}else if ($order == 'preview_print'){
    require_once BASE_PATH . '/models/Setting.php';

    $settingModel = new Setting($koneksi);

    if ($settingModel->cekUserSetting($user_id)) {
        $old_preview_print = (int)$settingModel->getOneValue($id, 'preview_print');
        $new_preview_print = $old_preview_print === 1 ? 0 : 1;

        $settingModel->updateOneValue($user_id, 'preview_print', $new_preview_print);
    } else {
        $data = new stdClass();
        $data->user_id = $user_id;
        $data->preview_print = 1;
        $settingModel->create($data);
    }

    $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index';
    header("Location: $redirect_url");
    exit;
}else if ($order == 'process'){
    require_once BASE_PATH . '/models/Project.php';
    date_default_timezone_set('Asia/Jakarta');

    $projectModel = new Project($koneksi);
    $order_ids = $_POST['order_ids'] ?? '';

    if ($order_ids) {
        if (!is_array($order_ids)) {
            $order_ids = explode(',', $order_ids);
        }

        foreach ($order_ids as $order_id) {
            $data = new stdClass();
            $data->id = $order_id;

            $status_terakhir = $projectModel->getLastProjectStatusByOrderId($data->id);
            $data->process = $_POST['status'] ?? '';
            $data->status = $status_terakhir;
            $data->user_id = $_POST['user_id'] ?? 0;
            $data->order_id = $order_id;
            $data->date = date('Y-m-d H:i:s');
            $projectModel->updateProject($data);
        }
    }

    header("Location: index");
    exit;
}else if ($order == 'payment'){
    $paymentController->create();
}else if ($order == 'price'){
    $orderController->fullPrice();
}