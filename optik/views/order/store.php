<?php
header('Content-Type: application/json');
require_once 'models/Order.php';

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || empty($data['items'])) {
        throw new Exception("Data keranjang kosong atau format salah.");
    }

    $customer_name = htmlspecialchars($data['customer_name']);
    $nomor_konsumen = htmlspecialchars($data['nomor']);
    $items = $data['items'];

    $total_harga = 0;
    foreach ($items as $item) {
        $total_harga += (floatval($item['price']) * intval($item['quantity']));
    }

    $orderModel = new Order($pdo);
    
    $order = $orderModel->createTransaction($customer_name, $nomor_konsumen, $items);

    echo json_encode([
        'status' => 'success',
        'message' => 'Pesanan berhasil disimpan',
        'order_id' => $order['order_id'],
        'inv_no' => $order['inv_no']
    ]);

    

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>