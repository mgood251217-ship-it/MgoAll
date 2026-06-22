<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/models/Finance.php';
require_once BASE_PATH . '/controllers/FinanceController.php';
require_once BASE_PATH . '/models/Payment.php';
require_once BASE_PATH . '/controllers/PaymentController.php';
require_once BASE_PATH . '/models/Activity.php';

$activityModel = new Activity($koneksi);
$financeModel = new Finance($koneksi);
$financeController = new FinanceController($koneksi);
$paymentModel = new Payment($koneksi);
$paymentController = new PaymentController($koneksi);

$action = $_GET['action'] ?? '';

if ($action === 'create_tf') {
    $financeController->createTf();
} else if ($action === 'delete_tf') {
    $financeController->deleteTf();
} else if ($action === 'update_activity') {
    $data = (object)[
        'id' => $_POST['activity_id'] ?? 0,
        'done' => $_POST['done'] ?? 0
    ];

    if ($activityModel->updateActivity($data)) {
        echo json_encode(['success' => $success]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errors' => ["Aksi tidak valid."]]);
    exit;
}
?>