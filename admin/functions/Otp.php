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



function canSendEmail() {
    $file = __DIR__ . '/email_rate.json';

    $now = time();
    $limit = 10;
    $window = 10;

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

function getOtp($koneksi, $username, $address = null) {
    $username = strtolower($username);

    $otp    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expire = date("Y-m-d H:i:s", time() + 600);

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
        $stmt = $koneksi->prepare("
            UPDATE otp_verifications 
            SET otp = ?, expire = ?, address = ?
            WHERE LOWER(username) = ?
        ");
        $stmt->bind_param("ssss", $otp, $expire, $address, $username);
        $stmt->execute();
    } else {
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

    if ($data['otp'] !== $otp_input) {

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

    if ($date > date("Y-m-d H:i:s", strtotime($data['expire']))) {

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

    $dataStore = dataStore($user['store_id']);
    setInfo($user, $dataStore);

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