<?php

require_once '../connect.php';
header('Content-Type: application/json');

$name     = trim($_POST['name'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($name === '' || $username === '' || $password === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Semua field wajib diisi'
    ]);
    exit;
}

/* CEK USERNAME */
$stmt = $koneksi->prepare("
    SELECT user_id 
    FROM users 
    WHERE username = ?
");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Username sudah digunakan'
    ]);
    exit;
}

/* HANDLE FOTO */
$uploadDir = BASE_PATH . '/assets/img/users/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$fotoName = 'default.png';

if (!empty($_FILES['foto']['name'])) {

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    $fileName  = $_FILES['foto']['name'];
    $fileTmp   = $_FILES['foto']['tmp_name'];
    $fileSize  = $_FILES['foto']['size'];

    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // ❌ ekstensi tidak diizinkan
    if (!in_array($ext, $allowedExt)) {
        echo json_encode([
            'success' => false,
            'message' => 'Format foto harus JPG, JPEG, PNG, atau WEBP'
        ]);
        exit;
    }

    // ❌ ukuran terlalu besar (2MB)
    if ($fileSize > 2 * 1024 * 1024) {
        echo json_encode([
            'success' => false,
            'message' => 'Ukuran foto maksimal 2MB'
        ]);
        exit;
    }

    // ❌ pastikan benar-benar gambar
    if (!getimagesize($fileTmp)) {
        echo json_encode([
            'success' => false,
            'message' => 'File bukan gambar valid'
        ]);
        exit;
    }

    // ✅ upload
    $fotoName = uniqid('user_') . '.' . $ext;
    move_uploaded_file($fileTmp, $uploadDir . $fotoName);
}


/* HASH PASSWORD */
$hash = password_hash($password, PASSWORD_DEFAULT);

/* INSERT USER */
$stmt = $koneksi->prepare("
    INSERT INTO users (username, password, name, foto)
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param("ssss", $username, $hash, $name, $fotoName);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal mendaftar'
    ]);
}
