<?php
date_default_timezone_set('Asia/Jakarta');

function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

function sendOtpEmail($storeEmail, $otp) {
    require BASE_PATH . '/vendor/autoload.php';

    // 🔁 EMAIL HOSTINGER
    $emails = [
        ['email' => 'noreply1@mgood.my.id', 'pass' => 'Mgo221###'],
        ['email' => 'noreply2@mgood.my.id', 'pass' => 'Mgo221###'],
        ['email' => 'noreply3@mgood.my.id', 'pass' => 'Mgo221###'],
        ['email' => 'noreply4@mgood.my.id', 'pass' => 'Mgo221###'],
        ['email' => 'noreply5@mgood.my.id', 'pass' => 'Mgo221###'],
    ];

    // 🔀 random biar merata
    shuffle($emails);

    foreach ($emails as $account) {

        // 🔒 GLOBAL RATE LIMIT
        if (!canSendEmail()) {
            return false;
        }

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            // 🔧 SMTP HOSTINGER
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $account['email'];
            $mail->Password   = $account['pass'];
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            // ⏱️ DELAY biar gak burst
            usleep(500000); // 0.5 detik

            // 📩 EMAIL
            $mail->setFrom($account['email'], 'MGood Security');
            $mail->addAddress($storeEmail);
            $mail->isHTML(true);
            $mail->Subject = 'Kode OTP Login Anda';

            // 🎨 HTML
            $mail->Body = '
            <div style="font-family: Arial, sans-serif; background-color: #f4f6f8; padding: 20px;">
                <div style="max-width: 500px; margin: auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    
                    <div style="background: linear-gradient(135deg, #1845ad, #23a2f6); padding: 20px; text-align: center;">
                        <h2 style="color: #ffffff; margin: 0;">MGood Security</h2>
                    </div>

                    <div style="padding: 30px; text-align: center;">
                        <h3 style="margin-bottom: 10px; color: #333;">Verifikasi Login Anda</h3>
                        <p style="color: #666; font-size: 14px;">
                            Gunakan kode OTP berikut:
                        </p>

                        <div style="
                            margin: 25px 0;
                            font-size: 32px;
                            font-weight: bold;
                            letter-spacing: 8px;
                            color: #1845ad;
                            background: #f1f5ff;
                            padding: 15px 25px;
                            border-radius: 8px;
                            display: inline-block;
                        ">
                            '.$otp.'
                        </div>

                        <p style="color: #888; font-size: 13px;">
                            Berlaku 10 menit.
                        </p>
                    </div>

                    <div style="background: #f4f6f8; padding: 15px; text-align: center; font-size: 12px; color: #aaa;">
                        © '.date('Y').' MGood
                    </div>
                </div>
            </div>
            ';

            $mail->AltBody = "OTP: $otp";

            $mail->send();
            return true;

        } catch (Exception $e) {
            // coba email berikutnya
            continue;
        }
    }

    return false;
}

function canSendEmail() {
    $file = __DIR__ . '/email_rate.json';

    $now = time();
    $limit = 10; // max 10 email
    $window = 10; // per 10 detik

    $data = [];

    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
    }

    $data = array_filter($data, function($t) use ($now, $window) {
        return ($now - $t) < $window;
    });

    if (count($data) >= $limit) {
        return false;
    }

    $data[] = $now;
    file_put_contents($file, json_encode($data));

    return true;
}

function setOtp($username_input, $address, $storeEmail) {

    global $koneksi;

    $username_input = strtolower($username_input);

    $otp    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expire = date("Y-m-d H:i:s", time() + 600);
    $date   = date("Y-m-d H:i:s");

    $sendOtp = false;

    $stmt = $koneksi->prepare("
        SELECT otp_verification_id, last_used, status 
        FROM otp_verifications 
        WHERE LOWER(username) = ? AND address = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $username_input, $address);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $data = $result->fetch_assoc();
        $last_used = strtotime($data['last_used']);
        $now = date("Y-m-d H:i:s");

        // 👉 status 0 → redirect saja
        if ($data['status'] == 0) {
            header("Location: " . BASE_URL . "/auth?us=" . urlencode(startEnk('enk', $username_input)));
            exit;
        }

        // 👉 status 1 & >5 hari → update + kirim OTP
        if ($data['status'] == 1 && $now > ($last_used + (5 * 24 * 60 * 60))) {

            $stmt = $koneksi->prepare("
                UPDATE otp_verifications 
                SET otp = ?, expire = ?, last_used = ? 
                WHERE otp_verification_id = ?
            ");
            $stmt->bind_param("sssi", $otp, $expire, $date, $data['otp_verification_id']);
            $stmt->execute();

            $sendOtp = true;
        }

        // 👉 status 1 & <5 hari → tidak ngapa-ngapain
        if ($data['status'] == 1 && $now <= ($last_used + (5 * 24 * 60 * 60))) {
            require_once BASE_PATH . '/functions/setInfo.php';
            setInfo(getUserByUsername($username_input), dataStore(getUserByUsername($username_input)['store_id']));
            header("Location: " . BASE_URL . "customer");
            exit;
        }
    } else {

        // 👉 username + address belum ada → insert
        $stmt = $koneksi->prepare("
            INSERT INTO otp_verifications (username, address, otp, expire, last_used, status) 
            VALUES (?, ?, ?, ?, ?, 0)
        ");
        $stmt->bind_param("sssss", $username_input, $address, $otp, $expire, $date);
        $stmt->execute();

        $sendOtp = true;
    }

    // 🔵 KIRIM OTP (kalau perlu)
    if ($sendOtp) {
        sendOtpEmail($storeEmail, $otp);
    }

    // 🔵 redirect terakhir
    header("Location: " . BASE_URL . "/auth?us=" . urlencode(startEnk('enk', $username_input)));
    exit;
}

function getOtp($koneksi, $username, $address = null) {
    $username = strtolower($username);

    $otp    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expire = date("Y-m-d H:i:s", time() + 600);

    // cek apakah sudah ada
    $stmt = $koneksi->prepare("
        SELECT otp_verification_id 
        FROM otp_verifications 
        WHERE LOWER(username) = ?
        AND address = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $username, $address);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // update
        $stmt = $koneksi->prepare("
            UPDATE otp_verifications 
            SET otp = ?, expire = ?, address = ?
            WHERE LOWER(username) = ?
        ");
        $stmt->bind_param("ssss", $otp, $expire, $address, $username);
        $stmt->execute();
    } else {
        // insert
        $stmt = $koneksi->prepare("
            INSERT INTO otp_verifications (username, address, otp, expire)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("ssss", $username, $address, $otp, $expire);
        $stmt->execute();
    }

    return [
        'otp' => $otp,
        'expire' => $expire
    ];
}

function otpVerification($koneksi, $username, $otp_input) {

    $username = strtolower($username);
    $date = date("Y-m-d H:i:s");

    // ambil OTP
    $stmt = $koneksi->prepare("
        SELECT otp, expire, failed_attempt
        FROM otp_verifications 
        WHERE LOWER(username) = ? 
        LIMIT 1
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return [
            'status' => false,
            'message' => 'OTP tidak ditemukan.'
        ];
    }

    $data = $result->fetch_assoc();

    // cek OTP salah
    if ($data['otp'] !== $otp_input) {

        // increment failed_attempt
        $stmt = $koneksi->prepare("
            UPDATE otp_verifications 
            SET failed_attempt = failed_attempt + 1 
            WHERE LOWER(username) = ?
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();

        return [
            'status' => false,
            'message' => 'Kode OTP salah.'
        ];
    }

    // cek expired
    if ($date > date("Y-m-d H:i:s", strtotime($data['expire']))) {

        // increment failed_attempt
        $stmt = $koneksi->prepare("
            UPDATE otp_verifications 
            SET failed_attempt = failed_attempt + 1 
            WHERE LOWER(username) = ?
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();

        return [
            'status' => false,
            'message' => 'Kode OTP sudah kedaluwarsa.'
        ];
    }

    // ambil user
    $stmt = $koneksi->prepare("
        SELECT * FROM users 
        WHERE LOWER(username) = ? 
        LIMIT 1
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        return [
            'status' => false,
            'message' => 'User tidak ditemukan.'
        ];
    }

    // login
    $dataStore = dataStore($user['store_id']);
    setInfo($user, $dataStore);

    // reset failed_attempt + set used
    $stmt = $koneksi->prepare("
        UPDATE otp_verifications 
        SET 
            status = 1, 
            last_used = NOW(),
            failed_attempt = 0
        WHERE LOWER(username) = ?
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();

    return [
        'status' => true,
        'message' => 'Login berhasil.',
        'user' => $user
    ];
}

?>