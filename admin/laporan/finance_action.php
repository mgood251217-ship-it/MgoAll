<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/models/Finance.php';
require_once BASE_PATH . '/controllers/FinanceController.php';
require_once BASE_PATH . '/models/Payment.php';
require_once BASE_PATH . '/controllers/PaymentController.php';
require_once BASE_PATH . '/models/Activity.php';
require_once BASE_PATH . '/controllers/OrderController.php';
require_once BASE_PATH . '/controllers/ReportController.php';

$activityModel = new Activity($koneksi);
$financeModel = new Finance($koneksi);
$financeController = new FinanceController($koneksi);
$paymentModel = new Payment($koneksi);
$reportController = new ReportController($koneksi);
$paymentController = new PaymentController($koneksi);
$orderController = new OrderController($koneksi);

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'create_tf':
        $financeController->createTf();
        break;
    case 'delete_tf':
        $financeController->deleteTf();
        break;
    case 'update_payment':
        $paymentController->updatePayment();
        break;
    case 'delete_payment':
        $paymentController->delete();
        break;
    case 'update_activity':
        $data = (object)[
            'id' => $_POST['activity_id'] ?? 0,
            'done' => $_POST['done'] ?? 0
        ];

        if ($activityModel->updateActivity($data)) {
            echo json_encode(['success' => $success]);
        }
        break;
    case 'create_expenditure':
        $financeController->createExpenditure();
        break;
    case 'create_income':
        $financeController->createIncome();
        break;
    case 'sync_finance_by_interval_date':
        $financeController->syncFinanceInterval();
        break;
    case 'update_expenditure':
        $financeController->updateExpenditure();
        break;
    case 'update_income':
        $financeController->updateIncome();
        break;
    case 'delete_expenditure':
        $financeController->deleteExpenditure();
        break;
    case 'delete_income':
        $financeController->deleteIncome();
        break;
    case 'create_note_detail':
        $orderController->createNoteDetail();
        break;
    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'errors' => ["Aksi tidak valid."]]);
        exit;
}

