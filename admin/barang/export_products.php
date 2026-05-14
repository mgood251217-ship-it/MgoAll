<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

if (!isset($_SESSION['user'])) {
    header("Location: " . BASE_URL . "/login");
    exit;
}


$barangIds = $_POST['barangIds'] ?? [];

if (empty($barangIds)) {
    exit('Tidak ada barang dipilih');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="products_export_' . date('Y-m-d-His') . '.csv"');

$output = fopen('php://output', 'w');

// Tulis BOM untuk UTF-8
fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Tulis header kolom
fputcsv($output, ['Jenis', 'Nama Barang', 'Harga', 'Harga Maklun', 'Harga Kegagalan', 'Satuan']);

$placeholders = implode(',', array_fill(0, count($barangIds), '?'));

$sql = "
SELECT type, name, price, reasonable_price, failed_price, unit_type
FROM products
WHERE store_id = ?
AND product_id IN ($placeholders)
ORDER BY type, name
";

$stmt = $koneksi->prepare($sql);

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
