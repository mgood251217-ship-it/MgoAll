<?php
header('Content-Type: application/json');
require_once 'models/Order.php';

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || empty($data['items'])) {
        throw new Exception("Data keranjang kosong atau format salah.");
    }

    $inv_no = htmlspecialchars($data['inv_no']);
    $customer_name = htmlspecialchars($data['customer_name']);
    $nomor_konsumen = htmlspecialchars($data['nomor']);
    $items = $data['items'];

    $total_harga = 0;
    foreach ($items as $item) {
        $total_harga += (floatval($item['price']) * intval($item['quantity']));
    }

    $orderModel = new Order($pdo);
    
    $order_id = $orderModel->createTransaction($customer_name, $inv_no, $nomor_konsumen, $total_harga, $items);

    echo json_encode([
        'status' => 'success',
        'message' => 'Pesanan berhasil disimpan',
        'order_id' => $order_id
    ]);

    

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>