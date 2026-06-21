<?php
header('Content-Type: application/json');
require_once 'models/Order.php';

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || empty($data['order_id'])) throw new Exception("Data tidak valid.");

    $orderModel = new Order($pdo);
    $sukses = $orderModel->addPayment($data['order_id'], $data['nominal'], $data['payment_method'], $data['information']);

    if ($sukses) {
        require_once 'models/Finance.php';
        $financeModel = new Finance($pdo);
        $financeModel->refreshDailyFinance($_SESSION['store_id'], date('Y-m-d'));
        echo json_encode(['status' => 'success']);
    } else {
        throw new Exception("Gagal eksekusi database.");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>