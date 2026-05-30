<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

$order_id = (int)$_GET['order_id'];

$sql = "
    SELECT 
        oi.order_item_id,
        oi.product_id,
        p.type,
        p.name AS product_name,
        oi.judul,
        oi.size,
        oi.quantity,
        oi.unit,
        oi.amount,
        oi.finishing,
        oi.maklun
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ? AND oi.store_id = ?

";

$stmt = $koneksi->prepare($sql);
$stmt->bind_param("ii", $order_id, $store_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];

while ($row = $result->fetch_assoc()) {
    $finishing_names = '-';
    $finishing_utama_names = '-';
    $finishing_kissdie_names = '-';
    $finishing_ids = [];

    if (!empty($row['finishing']) && $row['finishing'] !== '-') {
        $finishing_ids = array_filter(array_map('intval', explode(',', $row['finishing'])));
        if (!empty($finishing_ids)) {
            $placeholders = implode(',', array_fill(0, count($finishing_ids), '?'));
            $types = str_repeat('i', count($finishing_ids));
            $sqlF = "SELECT product_id, name FROM products WHERE product_id IN ($placeholders)";
            $stmtF = $koneksi->prepare($sqlF);

            if ($stmtF) {
                $stmtF->bind_param($types, ...$finishing_ids);
                $stmtF->execute();
                $resF = $stmtF->get_result();
                $all_names = [];
                $utama_names = [];
                $kissdie_names = [];

                while ($rF = $resF->fetch_assoc()) {
                    $name = $rF['name'];
                    $all_names[] = $name;

                    if (stripos($name, 'KISS CUT') !== false || stripos($name, 'DIE CUT') !== false) {
                        $kissdie_names[] = $name;
                    } else {
                        $utama_names[] = $name;
                    }
                }
                $stmtF->close();

                $finishing_names = implode(', ', $all_names);
                $finishing_utama_names = implode(', ', $utama_names) ?: '-';
                $finishing_kissdie_names = implode(', ', $kissdie_names) ?: '-';
            }
        }
    }


    $items[] = [
        'order_item_id'        => $row['order_item_id'],
        'product_id'           => $row['product_id'],
        'type'                 => $row['type'] ?? '',
        'product_name'         => $row['product_name'] ?? '',
        'judul'                => $row['judul'],
        'size'                 => $row['size'],
        'quantity'             => $row['quantity'],
        'unit'                 => $row['unit'],
        'amount'               => $row['amount'],
        'finishing'            => $finishing_names,
        'finishing_id'         => $finishing_ids,
        'finishing_utama'      => $finishing_utama_names,
        'finishingkissdie'     => $finishing_kissdie_names,
        'maklun'               => $row['maklun']
    ];

}
$stmt->close();

// Ambil total order
$stmt = $koneksi->prepare("SELECT total FROM orders WHERE order_id = ? AND store_id = ?");
$stmt->bind_param("ii", $order_id, $store_id);
$stmt->execute();
$order_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Hitung diskon per judul produk
$diskon_per_produk = [];
foreach ($items as $item) {
    $diskon = 0;
    $stmtDiskon = $koneksi->prepare("SELECT diskon FROM diskon_order_items WHERE order_id = ? AND product_id = ?");
    $stmtDiskon->bind_param("ii", $order_id, $item['product_id']);
    $stmtDiskon->execute();
    $resDiskon = $stmtDiskon->get_result();
    if ($resDiskon && $diskonRow = $resDiskon->fetch_assoc()) {
        $diskon = (int)$diskonRow['diskon'];
    }
    $stmtDiskon->close();

    if ($diskon > 0) {
        if (!isset($diskon_per_produk[$item['judul']])) {
            $diskon_per_produk[$item['judul']] = 0;
        }
        $diskon_per_produk[$item['judul']] = $diskon;
    }
}

$response = [
    'items' => $items,
    'total' => (int)($order_data['total'] ?? 0),
    'diskon_per_produk' => $diskon_per_produk,
];

header('Content-Type: application/json');
echo json_encode($response);
