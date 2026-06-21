<?php
include '../config.php';
include '../connect.php';

// Fungsi kirim email simpel dengan nama pengirim
function sendEmailSimple($to, $subject, $message, $fromEmail, $fromName = '') {
    $headers  = "From: " . ($fromName ? "$fromName <$fromEmail>" : $fromEmail) . "\r\n";
    $headers .= "Reply-To: " . $fromEmail . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";

    return mail($to, $subject, $message, $headers);
}

// === CEK STOK SPANDUK ===
$query = "SELECT product_id, name FROM products WHERE type = ? AND store_id = ?";
$stmt = $koneksi->prepare($query);
$type = 'OUTDOOR';
$stmt->bind_param("si", $type, $store_id);
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
    $stockStmt->close();

    if ($stockRow && $stockRow['quantity'] < 300) {
        $produkKurang[] = $productName;
    }
}
$stmt->close();

if (!empty($produkKurang)) {
    $judul = "SPANDUK";
    $isiPesan = "Stok bahan berikut kurang dari 300M: " . implode(", ", $produkKurang) . ". Tolong tambahkan stok bahan.";
    $tanggal = date("d-m-Y");
    $eventKey = "spanduk-stok-warning-" . $tanggal;
    $isReadValue = 0;

    // Cek apakah notifikasi hari ini sudah pernah dikirim
    $checkNotif = "SELECT COUNT(*) as total FROM notifications WHERE event_key = ?";
    $checkStmt = $koneksi->prepare($checkNotif);
    $checkStmt->bind_param("s", $eventKey);
    $checkStmt->execute();
    $resultCheck = $checkStmt->get_result();
    $dataCheck = $resultCheck->fetch_assoc();
    $checkStmt->close();

    if ($dataCheck['total'] == 0) {
        // Ambil semua store_id yang punya token FCM
        $tokenQuery = "SELECT DISTINCT store_id FROM fcm_token";
        $tokenResult = $koneksi->query($tokenQuery);

        while ($store = $tokenResult->fetch_assoc()) {
            $store_id = $store['store_id'];

            
            $createdAt = date("Y-m-d H:i:s");

            $insertNotif = "INSERT INTO notifications (store_id, message, message_content, event_key, created_at, is_read) 
                            VALUES (?, ?, ?, ?, ?, ?)";

            $insertStmt = $koneksi->prepare($insertNotif);
            $insertStmt->bind_param("issssi", $store_id, $judul, $isiPesan, $eventKey, $createdAt, $isReadValue);

            $executed = $insertStmt->execute();

            if ($executed) {
                // Kirim email per store
                $toEmail = "vikixmm4@gmail.com";
                $fromEmail = "noreply@mgood.my.id";
                $fromName = "Maxprint";

                $emailSubject = "Peringatan Stok Spanduk Kurang dari MaxPrint";
                $produkListKurang = implode(", ", $produkKurang);
                $logoUrl = "https://mgood.my.id/admin/assets/img/logo.png";

                $emailMessage = "
                    <html>
                    <head>
                        <title>$emailSubject</title>
                        <style>
                            body { font-family: Arial, sans-serif; color: #333; }
                            .header { text-align: center; margin-bottom: 20px; }
                            .header img { max-width: 150px; }
                            .content { font-size: 16px; }
                            .highlight { font-weight: bold; color: #d9534f; }
                            .footer { margin-top: 30px; font-size: 14px; color: #777; }
                        </style>
                    </head>
                    <body>
                        <div class='header'>
                            <img src='$logoUrl' alt='Maxprint Logo'>
                        </div>
                        <div class='content'>
                            <p>Halo Viki,</p>
                            <p>Stok bahan spanduk berikut kurang dari 300 meter:</p>
                            <p class='highlight'>$produkListKurang</p>
                            <p>Mohon segera lakukan penambahan stok bahan.</p>
                            <p>Terima kasih.</p>
                        </div>
                        <div class='footer'>
                            <p>Maxprint - mgood.my.id</p>
                        </div>
                    </body>
                    </html>
                ";

                sendEmailSimple($toEmail, $emailSubject, $emailMessage, $fromEmail, $fromName);

                // Kirim notifikasi FCM ke setiap token store
                $tokenPerStore = $koneksi->prepare("SELECT token FROM fcm_token WHERE store_id = ?");
                $tokenPerStore->bind_param("i", $store_id);
                $tokenPerStore->execute();
                $resToken = $tokenPerStore->get_result();

                while ($rowToken = $resToken->fetch_assoc()) {
                    $token = $rowToken['token'];
                    sendFCM($token, $judul, $isiPesan);
                }
                $tokenPerStore->close();
            }
            $insertStmt->close();
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

    // Dapatkan access token
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

    // Kirim pesan
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
