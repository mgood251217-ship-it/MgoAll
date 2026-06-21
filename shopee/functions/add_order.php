<?php
require_once '../connect.php';
session_start();
header('Content-Type: application/json');

date_default_timezone_set('Asia/Jakarta');

$shopee_user_id = $_SESSION['shopee_users']['shopee_user_id'] ?? 0;

$order_no = trim($_POST['order_no'] ?? '');
$name     = trim($_POST['name'] ?? '');
$items    = $_POST['items'] ?? [];

if ($order_no === '' || $name === '' || empty($items)) {
    echo json_encode([
        'success' => false,
        'message' => 'Data order tidak lengkap'
    ]);
    exit;
}

$koneksi->begin_transaction();

try {

    $stmt = $koneksi->prepare("
        INSERT INTO shopee_orders
        (shopee_user_id, order_no, name, created_at)
        VALUES (?, ?, ?, ?)
    ");

    $created_at = date('Y-m-d H:i:s');

    $stmt->bind_param(
        "isss",
        $shopee_user_id,
        $order_no,
        $name,
        $created_at
    );
    $stmt->execute();

    $shopee_order_id = $stmt->insert_id;

    $stmtItem = $koneksi->prepare("
        INSERT INTO shopee_order_items
        (shopee_order_id, product_id, first_variant, last_variant, qty)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($items as $i => $item) {

        $product_id    = (int)($item['product_id'] ?? 0);
        $first_variant = (int)($item['first_variant'] ?? 0);
        $last_variant  = (int)($item['last_variant'] ?? 0);
        $qty           = (int)($item['qty'] ?? 0);

        if (!$product_id || $qty <= 0) {
            throw new Exception("Item ke-$i tidak valid");
        }

        $q = $koneksi->prepare("
            SELECT product_id
            FROM shopee_products
            WHERE product_id = ? AND shopee_user_id = ?
        ");
        $q->bind_param("ii", $product_id, $shopee_user_id);
        $q->execute();

        if ($q->get_result()->num_rows === 0) {
            throw new Exception("Produk tidak ditemukan / bukan milik user");
        }

        $stmtItem->bind_param(
            "iiiii",
            $shopee_order_id,
            $product_id,
            $first_variant,
            $last_variant,
            $qty
        );
        $stmtItem->execute();
    }

    $koneksi->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order berhasil disimpan'
    ]);

} catch (Exception $e) {

    $koneksi->rollback();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
