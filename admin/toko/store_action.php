<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/global_functions.php';
require_once BASE_PATH . '/models/User.php';

$userModel = new User($koneksi);
$errors = [];

$store = $_POST['store'] ?? '';
$old_picture = $_POST['old_picture'] ?? '';
$uploadDir = BASE_PATH . "/assets/img/user/";

$data = new stdClass();
$data->id = (int)($_POST['user_id'] ?? 0);
$data->name = strtoupper(trim($_POST['name'] ?? ''));
$data->username = strtolower(trim($_POST['username'] ?? ''));
$data->password = $_POST['password'] ?? '';
$data->initial = strtoupper(trim($_POST['initial'] ?? ''));
$data->role = strtoupper(trim($_POST['role'] ?? ''));
$data->store_id = $store_id;
$data->picture = $old_picture;

if ($store === 'update_user') {
    if (!empty($_FILES['picture']['name']) && $_FILES['picture']['error'] === 0) {
        $result = compress($_FILES['picture'], $uploadDir);
        
        if (!$result['success']) {
            $errors[] = $result['error'];
        } else {
            $data->picture = $result['file'];
            // Hapus gambar lama jika gambar baru berhasil di-upload
            if (!empty($old_picture) && file_exists($uploadDir . $old_picture)) {
                unlink($uploadDir . $old_picture);
            }
        }
    }

    if ($userModel->checkDuplicateUser($data)) {
        $errors[] = "Username sudah digunakan oleh user lain.";
    }

    if (!empty($errors)) {
        $_SESSION['flash_errors'] = $errors;
    } else {
        if ($userModel->updateUser($data)) {
            $_SESSION['flash_success'] = "User berhasil diperbarui.";
        } else {
            $_SESSION['flash_errors'] = ["Gagal memperbarui user."];
        }
    }

} elseif ($store === 'add_user') {
    $data->picture = ''; 
    if (!empty($_FILES['picture']['name']) && $_FILES['picture']['error'] === 0) {
        $result = compress($_FILES['picture'], $uploadDir);
        
        if (!$result['success']) {
            $errors[] = $result['error'];
        } else {
            $data->picture = $result['file'];
        }
    }

    if ($userModel->checkUser($data->username)) {
        $errors[] = "Username sudah terdaftar.";
    }

    if (!empty($errors)) {
        $_SESSION['flash_errors'] = $errors;
    } else {
        if ($userModel->addUser($data)) {
            $_SESSION['flash_success'] = "User berhasil ditambahkan.";
        } else {
            $_SESSION['flash_errors'] = ["Gagal menambahkan user."];
        }
    }
}elseif ($store === 'delete_user'){
    if ($userModel->checkUserStore($store_id) == 1 || $data->role != 'MANAGER') {
        $_SESSION['flash_error'] = "Tidak bisa menghapus user terakhir.";
    } else {
        if ($userModel->deleteUserById($data->id)) {
            if (!empty($picture)) {
                 $filePath = BASE_PATH . "/assets/img/" . $picture;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            $_SESSION['flash_success'] = "User berhasil dihapus.";
        } else {
            $_SESSION['flash_error'] = "Gagal menghapus user.";
        }
    }
}

header("Location: index");
exit;