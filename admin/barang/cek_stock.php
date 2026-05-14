<?php
// === CEK STOK SPANDUK ===
$query = "SELECT product_id, name FROM products WHERE type = ?";
$stmt = $koneksi->prepare($query);
$type = 'SPANDUK';
$stmt->bind_param("s", $type);
$stmt->execute();
$result = $stmt->get_result();

$produkKurang = [];

while ($row = $result->fetch_assoc()) {
    $productId = $row['product_id'];
    $productName = $row['name'];

    $stockQuery = "SELECT quantity FROM stock WHERE product_id = ?";
    $stockStmt = $koneksi->prepare($stockQuery);
    $stockStmt->bind_param("s", $productId);
    $stockStmt->execute();
    $stockResult = $stockStmt->get_result();
    $stockRow = $stockResult->fetch_assoc();

    if ($stockRow && $stockRow['quantity'] < 300) {
        $produkKurang[] = $productName;
    }
    $stockStmt->close();
}
$stmt->close();

if (!empty($produkKurang)) {
    $judul = "SPANDUK";
    $isiPesan = "Stok bahan berikut kurang dari 300M: " . implode(", ", $produkKurang) . ". Tolong tambahkan stok bahan.";
    $eventKey = "spanduk-stok-warning";

    $checkNotif = "SELECT COUNT(*) as total FROM notifications WHERE event_key = ? AND is_read = 0";
    $checkStmt = $koneksi->prepare($checkNotif);
    $checkStmt->bind_param("s", $eventKey);
    $checkStmt->execute();
    $resultCheck = $checkStmt->get_result();
    $dataCheck = $resultCheck->fetch_assoc();
    $totalNotif = $dataCheck['total'];
    $checkStmt->close();

    if ($totalNotif == 0) {
        $isReadValue = 0;
        $insertNotif = "INSERT INTO notifications (store_id, message, message_content, event_key, created_at, is_read) VALUES (?, ?, ?, ?, NOW(), ?)";
        $insertStmt = $koneksi->prepare($insertNotif);
        $insertStmt->bind_param("isssi", $store_id, $judul, $isiPesan, $eventKey, $isReadValue);

        if (!$insertStmt->execute()) {
            error_log("Insert Error: " . $insertStmt->error);
            die("Insert Error: " . $insertStmt->error);
        }
        $insertStmt->close();
    }
}