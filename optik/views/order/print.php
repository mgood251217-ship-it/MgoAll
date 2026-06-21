<?php
// views/order/print.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$id = $_GET['id'] ?? 0;
$store_id = $_SESSION['store_id'];

$stmtOrder = $pdo->prepare("SELECT * FROM orders WHERE id = :id AND store_id = :store_id");
$stmtOrder->execute(['id' => $id, 'store_id' => $store_id]);
$order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo json_encode(['status' => 'error', 'message' => 'Pesanan tidak ditemukan.']);
    exit();
}

$stmtItems = $pdo->prepare("SELECT * FROM order_items JOIN products ON order_items.product_id = products.id WHERE order_id = :id");
$stmtItems->execute(['id' => $id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

$stmtPay = $pdo->prepare("SELECT SUM(nominal) FROM payments WHERE order_id = :id");
$stmtPay->execute(['id' => $id]);
$total_dibayar = (float) $stmtPay->fetchColumn();

echo json_encode([
    'status' => 'success',
    'order' => $order,
    'items' => $items,
    'payment' => [
        'total_dibayar' => $total_dibayar,
        'sisa' => $order['total'] - $total_dibayar
    ],
    'store' => [
        'name' => $_SESSION['store_name'] ?? 'OPTIK KITA',
        'img'  => $_SESSION['store_img'] ?? '',
        'address' => $_SESSION['store_address'] ?? ''
    ]
]);
exit();
?>