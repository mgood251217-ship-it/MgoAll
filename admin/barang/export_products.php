<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

$barangIds = $_POST['barangIds'] ?? [];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="products_export_' . date('Y-m-d-His') . '.csv"');

$output = fopen('php://output', 'w');
fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
fputcsv($output, ['Jenis', 'Nama Barang', 'Harga', 'Harga Maklun', 'Harga Kegagalan', 'Satuan']);

$placeholders = implode(',', array_fill(0, count($barangIds), '?'));

$stmt = $koneksi->prepare("
SELECT type, name, price, reasonable_price, failed_price, unit_type
FROM products
WHERE store_id = ?
AND product_id IN ($placeholders)
ORDER BY type, name
");

$types  = str_repeat('i', count($barangIds) + 1);
$params = array_merge([$store_id], $barangIds);

$stmt->bind_param($types, ...$params);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    fputcsv($output, ['Tidak ada data produk yang ditemukan']);
    fclose($output);
    exit;
}

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['type'],
        $row['name'],
        $row['price'],
        $row['reasonable_price'] ?? '',
        $row['failed_price'] ?? '',
        $row['unit_type']
    ]);
}

fclose($output);
$stmt->close();
exit;
