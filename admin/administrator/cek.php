<?php

define('BASE_PATH', '/home/u130468871/domains/mgood.my.id/public_html/admin/');
include BASE_PATH . 'config.php';
include BASE_PATH . 'connect.php';

date_default_timezone_set('Asia/Jakarta');

function sendEmailSimple($to, $subject, $message, $fromEmail, $fromName = '') {
    $headers  = "From: " . ($fromName ? "$fromName <$fromEmail>" : $fromEmail) . "\r\n";
    $headers .= "Reply-To: " . $fromEmail . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    return mail($to, $subject, $message, $headers);
}

$tanggal = date("d-m-Y");
$eventKeyStok = "spanduk-stok-warning-" . $tanggal;
$eventKeyOrder = "order-belum-diambil-" . $tanggal;
$eventKeyHutang = "order-belum-lunas-" . $tanggal;

$checkNotif = $koneksi->prepare("SELECT event_key FROM notifications WHERE event_key IN (?, ?, ?)");
$checkNotif->bind_param("sss", $eventKeyStok, $eventKeyOrder, $eventKeyHutang);
$checkNotif->execute();
$resultCheck = $checkNotif->get_result();
$existingKeys = [];
while ($row = $resultCheck->fetch_assoc()) {
    $existingKeys[] = $row['event_key'];
}
$checkNotif->close();

$type = 'OUTDOOR';
$storeResult = $koneksi->query("SELECT DISTINCT store_id FROM products WHERE type = '$type'");

while ($storeRow = $storeResult->fetch_assoc()) {
    $store_id = $storeRow['store_id'];

    $stmt = $koneksi->prepare("SELECT product_id, name FROM products WHERE type = ? AND store_id = ?");
    $stmt->bind_param("si", $type, $store_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $produkKurang = [];
    while ($row = $result->fetch_assoc()) {
        $stockStmt = $koneksi->prepare("SELECT quantity FROM stock WHERE product_id = ?");
        $stockStmt->bind_param("s", $row['product_id']);
        $stockStmt->execute();
        $stockResult = $stockStmt->get_result();
        $stockRow = $stockResult->fetch_assoc();
        $stockStmt->close();

        if ($stockRow && $stockRow['quantity'] < 300) {
            $produkKurang[] = $row['name'];
        }
    }
    $stmt->close();

    $storeDataStmt = $koneksi->prepare("SELECT name, email FROM stores WHERE store_id = ?");
    $storeDataStmt->bind_param("i", $store_id);
    $storeDataStmt->execute();
    $storeData = $storeDataStmt->get_result()->fetch_assoc();
    $storeDataStmt->close();

    $fromEmail = "noreply@mgood.my.id";
    $fromName = "Maxprint";
    $logoUrl = "https://mgood.my.id/admin/assets/img/logo.png";
    $createdAt = date("Y-m-d H:i:s");
    $isReadValue = 0;

    if (!empty($produkKurang) && !in_array($eventKeyStok, $existingKeys)) {
        $judul = "PERINGATAN BAHAN OUTDOOR";
        $isiPesan = "Stok bahan berikut kurang dari 300M: " . implode(", ", $produkKurang) . ". Tolong tambahkan stok bahan.";

        $insertNotif = $koneksi->prepare("INSERT INTO notifications (store_id, message, message_content, event_key, created_at, is_read) VALUES (?, ?, ?, ?, ?, ?)");
        $insertNotif->bind_param("issssi", $store_id, $judul, $isiPesan, $eventKeyStok, $createdAt, $isReadValue);
        $insertNotif->execute();
        $insertNotif->close();

        if ($storeData) {
            $emailSubject = "Peringatan Stok Spanduk Kurang dari MaxPrint";
            $emailMessage = "<html><head><title>$emailSubject</title><style>body{font-family:Arial,sans-serif;color:#333}.header img{max-width:150px}.highlight{font-weight:bold;color:#d9534f}</style></head><body><div class='header'><img src='$logoUrl' alt='Maxprint Logo'></div><p>Halo {$storeData['name']},</p><p>Stok bahan spanduk berikut kurang dari 300 meter:</p><p class='highlight'>" . implode(', ', $produkKurang) . "</p><p>Mohon segera lakukan penambahan stok bahan.</p><p>Terima kasih.</p></body></html>";
            sendEmailSimple($storeData['email'], $emailSubject, $emailMessage, $fromEmail, $fromName);
        }
    }

    if (!in_array($eventKeyOrder, $existingKeys)) {
        $belumDiambil = [];
        $orderStmt = $koneksi->prepare("SELECT order_id, nomorator, customer_name, date FROM orders WHERE store_id = ? AND DATEDIFF(NOW(), DATE(date)) > 3");
        $orderStmt->bind_param("i", $store_id);
        $orderStmt->execute();
        $orders = $orderStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $orderStmt->close();

        foreach ($orders as $order) {
            $projStmt = $koneksi->prepare("SELECT process FROM projects WHERE order_id = ?");
            $projStmt->bind_param("i", $order['order_id']);
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

            if (!$sudahDiambil) {
                $belumDiambil[] = [
                    'nomorator' => $order['nomorator'],
                    'date' => date('Y-m-d', strtotime($order['date'])),
                    'customer' => $order['customer_name']
                ];
            }
        }

        if (!empty($belumDiambil)) {
            $judul = "PESANAN BELUM DIAMBIL";
            $isiPesan = "Terdapat pesanan yang belum diambil (lebih dari 3 hari):\n" .
                implode("\n", array_map(fn($x) => "- {$x['nomorator']} (tgl {$x['date']}) - {$x['customer']}", $belumDiambil)) .
                "\nMohon ditindaklanjuti segera.";

            $insertNotif = $koneksi->prepare("INSERT INTO notifications (store_id, message, message_content, event_key, created_at, is_read) VALUES (?, ?, ?, ?, ?, ?)");
            $insertNotif->bind_param("issssi", $store_id, $judul, $isiPesan, $eventKeyOrder, $createdAt, $isReadValue);
            $insertNotif->execute();
            $insertNotif->close();

            if ($storeData) {
                $emailSubject = "Pemberitahuan Pesanan Belum Diambil - MaxPrint";
                $emailMessage = "<html><head><title>$emailSubject</title><style>body{font-family:Arial,sans-serif;color:#333}.header img{max-width:150px}.highlight{font-weight:bold;color:#d9534f}ul{padding-left:20px}</style></head><body><div class='header'><img src='$logoUrl' alt='Maxprint Logo'></div><p>Halo {$storeData['name']},</p><p>Pesanan berikut belum diambil lebih dari 3 hari:</p><ul>" .
                    implode('', array_map(fn($x) => "<li><strong>{$x['nomorator']}</strong> (tgl {$x['date']}) - {$x['customer']}</li>", $belumDiambil)) .
                    "</ul><p>Mohon segera ditindaklanjuti agar tidak menumpuk di area produksi.</p><p>Terima kasih.</p></body></html>";
                sendEmailSimple($storeData['email'], $emailSubject, $emailMessage, $fromEmail, $fromName);
            }
        }
    }

    if (!in_array($eventKeyHutang, $existingKeys)) {
        $belumLunas = [];

        $orderHutangStmt = $koneksi->prepare("SELECT order_id, nomorator, customer_name, date, total FROM orders WHERE store_id = ?");
        $orderHutangStmt->bind_param("i", $store_id);
        $orderHutangStmt->execute();
        $hutangOrders = $orderHutangStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $orderHutangStmt->close();

        foreach ($hutangOrders as $order) {
            $payStmt = $koneksi->prepare("SELECT SUM(nominal) AS total_bayar FROM payment WHERE order_id = ?");
            $payStmt->bind_param("i", $order['order_id']);
            $payStmt->execute();
            $payResult = $payStmt->get_result()->fetch_assoc();
            $payStmt->close();

            $dibayar = (int) $payResult['total_bayar'];
            $total = (int) $order['total'];

            if ($dibayar < $total) {
                $belumLunas[] = [
                    'nomorator' => $order['nomorator'],
                    'customer' => $order['customer_name'],
                    'kurang' => $total - $dibayar,
                    'date' => date('Y-m-d', strtotime($order['date']))
                ];
            }
        }

        if (!empty($belumLunas)) {
            $judul = "PESANAN BELUM LUNAS";
            $isiPesan = "Terdapat pesanan yang belum lunas:\n" .
                implode("\n", array_map(fn($x) => "- {$x['nomorator']} (tgl {$x['date']}) - {$x['customer']} kekurangan Rp " . number_format($x['kurang'], 0, ',', '.'), $belumLunas)) .
                "\nMohon ditagih atau dikonfirmasi.";

            $insertNotif = $koneksi->prepare("INSERT INTO notifications (store_id, message, message_content, event_key, created_at, is_read) VALUES (?, ?, ?, ?, ?, ?)");
            $insertNotif->bind_param("issssi", $store_id, $judul, $isiPesan, $eventKeyHutang, $createdAt, $isReadValue);
            $insertNotif->execute();
            $insertNotif->close();

            if ($storeData) {
                $emailSubject = "Pemberitahuan Pesanan Belum Lunas - MaxPrint";
                $emailMessage = "<html><head><title>$emailSubject</title><style>body{font-family:Arial,sans-serif;color:#333}.header img{max-width:150px}.highlight{font-weight:bold;color:#d9534f}ul{padding-left:20px}</style></head><body><div class='header'><img src='$logoUrl' alt='Maxprint Logo'></div><p>Halo {$storeData['name']},</p><p>Pesanan berikut belum lunas:</p><ul>" .
                    implode('', array_map(fn($x) => "<li><strong>{$x['nomorator']}</strong> (tgl {$x['date']}) - {$x['customer']} kekurangan Rp " . number_format($x['kurang'], 0, ',', '.') . "</li>", $belumLunas)) .
                    "</ul><p>Mohon segera ditindaklanjuti atau dikonfirmasi ke customer.</p><p>Terima kasih.</p></body></html>";
                sendEmailSimple($storeData['email'], $emailSubject, $emailMessage, $fromEmail, $fromName);
            }
        }
    }
}



// Fungsi kirim notifikasi ke FCM
function sendFCM($token, $title, $body) {
    $serviceAccount = json_decode(file_get_contents(__DIR__ . '/../service-account.json'), true);

    $jwtHeader = ['alg' => 'RS256', 'typ' => 'JWT'];
    $now = time();
    $jwtClaim = [
        'iss' => $serviceAccount['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ];

    function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    $jwtBase = base64UrlEncode(json_encode($jwtHeader)) . '.' . base64UrlEncode(json_encode($jwtClaim));
    openssl_sign($jwtBase, $signature, $serviceAccount['private_key'], 'sha256WithRSAEncryption');
    $jwt = $jwtBase . '.' . base64UrlEncode($signature);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]));
    $res = curl_exec($ch);
    $tokenData = json_decode($res, true);
    curl_close($ch);

    if (!isset($tokenData['access_token'])) {
        return 'Failed to get access token';
    }

    $accessToken = $tokenData['access_token'];

    $payload = [
        'message' => [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body
            ],
            'android' => [
                'notification' => [
                    'icon' => 'stock_ticker_update',
                    'color' => '#f45342',
                    'click_action' => 'https://mgood.my.id'
                ]
            ]
        ]
    ];

    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    $projectId = $serviceAccount['project_id'];
    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}
?>
