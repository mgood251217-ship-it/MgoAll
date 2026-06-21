<?php

function dataStore($storeId)
{
    global $koneksi;

    $stmt = $koneksi->prepare("SELECT name, logo, email, address FROM stores WHERE store_id = ?");
    $stmt->bind_param("i", $storeId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $data = $result->fetch_assoc()) {
        return [
            'name' => $data['name'],
            'logo' => $data['logo'],
            'address' => $data['address'],
            'email' => $data['email']
        ];
    }

    return null;
}

function getUserByUsername($username)
{
    global $koneksi;

    $stmt = $koneksi->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $user = $result->fetch_assoc()) {
        return $user;
    }

    return null;
}

function setInfo($user, $dataStore)
{
    global $koneksi;
    $stmt = $koneksi->prepare("SELECT mode FROM user_setting WHERE user_id = ?");
    $stmt->bind_param("i", $user['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $mode = (int)$row['mode'];
    }
    $stmt->close();
    setUserSession($user, $dataStore['name'], $dataStore['address'], $dataStore['logo'], $mode);
    setUserCookie($user, $dataStore['name'], $dataStore['address'], $dataStore['logo'], $mode);
}

function insertActivity($userId, $address, $date)
{
    global $koneksi;
    $insert = $koneksi->prepare("
        INSERT INTO login_activity (user_id, address, date)
        VALUES (?, ?, ?)
    ");
    $insert->bind_param("iss", $userId, $address, $date);
    $insert->execute();
    $insert->close();
}

function setUserSession($user, $storeName, $storeAddress, $storeLogo, $mode)
{
    $_SESSION['user'] = [
        'user_id'    => startEnk('enk', $user['user_id']),
        'username'   => startEnk('enk', $user['username']),
        'name'       => startEnk('enk', $user['name']),
        'initial'    => startEnk('enk', $user['initial']),
        'store_id'   => startEnk('enk', $user['store_id']),
        'role'       => startEnk('enk', $user['role']),
        'foto'       => startEnk('enk', $user['picture']),
        'store_name' => startEnk('enk', $storeName),
        'store_address' => startEnk('enk', $storeAddress),
        'store_logo' => startEnk('enk', $storeLogo),
        'mode'       => startEnk('enk', $mode)
    ];
}

function setUserCookie($user, $storeName, $storeAddress, $storeLogo, $mode)
{
    $expire = time() + (1 * 24 * 60 * 60);
    $path   = '/';

    setcookie('user_user_id', startEnk('enk', $user['user_id']), $expire, $path, "", true, true);
    setcookie('user_username', startEnk('enk', $user['username']), $expire, $path, "", true, true);
    setcookie('user_name', startEnk('enk', $user['name']), $expire, $path, "", true, true);
    setcookie('user_initial', startEnk('enk', $user['initial']), $expire, $path, "", true, true);
    setcookie('user_store_id', startEnk('enk', $user['store_id']), $expire, $path, "", true, true);
    setcookie('user_role', startEnk('enk', $user['role']), $expire, $path, "", true, true);
    setcookie('user_foto', startEnk('enk', $user['picture']), $expire, $path, "", true, true);

    setcookie('store_name', startEnk('enk', $storeName), $expire, $path, "", true, true);
    setcookie('store_address', startEnk('enk', $storeAddress), $expire, $path, "", true, true);
    setcookie('store_logo', startEnk('enk', $storeLogo), $expire, $path, "", true, true);
    setcookie('user_mode', startEnk('enk', $mode), $expire, $path, "", true, true);
}