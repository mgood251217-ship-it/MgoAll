<?php
define('BASE_PATH', '/home/u130468871/domains/kasirmaxprint.com/public_html/admin/');
include BASE_PATH . 'config.php';
include BASE_PATH . 'connect.php';

date_default_timezone_set('Asia/Jakarta'); // Zona waktu Jakarta

$wa_api_url = "https://75cb-180-243-190-196.ngrok-free.app/send";

// Ambil order lebih dari 3 hari lalu
$orderStmt = $koneksi->prepare("
    SELECT o.order_id, o.nomorator, o.customer_name, o.date, o.nomor, o.total, o.deadline, s.name AS store_name
    FROM orders o
    JOIN stores s ON o.store_id = s.store_id
    WHERE DATEDIFF(NOW(), DATE(o.date)) > 3
");

$orderStmt->execute();
$result = $orderStmt->get_result();
$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
$orderStmt->close();

foreach ($orders as $order) {
    $order_id = $order['order_id'];
    $nomor = trim($order['nomor']);
    if (!$nomor) continue;
    $wa_number = preg_replace('/^0/', '62', $nomor) . '@c.us';

    $projStmt = $koneksi->prepare("SELECT process FROM projects WHERE order_id = ?");
    $projStmt->bind_param("i", $order_id);
    $projStmt->execute();
    $projResult = $projStmt->get_result();

    $sudahDiambil = false;
    while ($proj = $projResult->fetch_assoc()) {
        if (stripos($proj['process'], 'diambil') !== false) {
            $sudahDiambil = true;
            break;
        }
    }
    $projStmt->close();

    // Hanya kirim jika sudah lewat deadline
    $deadline = strtotime($order['deadline']);
    $now = time();

    if (!$sudahDiambil && $deadline <= $now) {
        $eventKey = 'belum-ambil';
        $checkNotif = $koneksi->prepare("SELECT 1 FROM customer_notifications WHERE order_id = ? AND event_key = ?");
        $checkNotif->bind_param("is", $order_id, $eventKey);
        $checkNotif->execute();
        $checkNotif->store_result();

        if ($checkNotif->num_rows === 0) {
            $message = "Halo {$order['customer_name']}, pesanan nomorator {$order['nomorator']} tanggal " . date('d-m-Y', strtotime($order['date'])) . " belum diambil hingga melewati batas waktu. Mohon segera diambil ya.\n\nDari : {$order['store_name']}";
            kirimWA($wa_api_url, $wa_number, $message);

            $send_at = date("Y-m-d H:i:s");
            $insert = $koneksi->prepare("INSERT INTO customer_notifications (order_id, event_key, send_at) VALUES (?, ?, ?)");
            $insert->bind_param("iss", $order_id, $eventKey, $send_at);
            $insert->execute();
            $insert->close();
        }
        $checkNotif->close();
    }

    $eventKey = 'belum-lunas';
    $payStmt = $koneksi->prepare("SELECT SUM(nominal) AS total_bayar FROM payment WHERE order_id = ?");
    $payStmt->bind_param("i", $order_id);
    $payStmt->execute();
    $payResult = $payStmt->get_result()->fetch_assoc();
    $payStmt->close();

    $dibayar = (int) ($payResult['total_bayar'] ?? 0);
    $total = (int) $order['total'];

    if ($dibayar < $total) {
        $checkNotif = $koneksi->prepare("SELECT 1 FROM customer_notifications WHERE order_id = ? AND event_key = ?");
        $checkNotif->bind_param("is", $order_id, $eventKey);
        $checkNotif->execute();
        $checkNotif->store_result();

        if ($checkNotif->num_rows === 0) {
            $kurang = $total - $dibayar;
            $message = "Halo {$order['customer_name']}, pesanan nomorator {$order['nomorator']} tanggal " . date('d-m-Y', strtotime($order['date'])) . " belum lunas. Kekurangan pembayaran sebesar Rp " . number_format($kurang, 0, ',', '.') . ". Mohon segera lunasi.\n\nDari : {$order['store_name']}";
            kirimWA($wa_api_url, $wa_number, $message);

            $send_at = date("Y-m-d H:i:s");
            $insert = $koneksi->prepare("INSERT INTO customer_notifications (order_id, event_key, send_at) VALUES (?, ?, ?)");
            $insert->bind_param("iss", $order_id, $eventKey, $send_at);
            $insert->execute();
            $insert->close();
        }
        $checkNotif->close();
    }
}

// FUNGSI KIRIM WHATSAPP
function kirimWA($url, $number, $message) {
    $data = ['number' => $number, 'message' => $message];
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'timeout' => 10
        ]
    ];
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    echo $response === FALSE ? "Gagal kirim ke $number<br>" : "Berhasil kirim ke $number<br>";
}
?>
