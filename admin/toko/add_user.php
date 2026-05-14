<?php
// ================= FILE: add_user.php =================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
$errors = [];

$name = strtoupper(trim($_POST['name'] ?? ''));
$username = strtolower(trim($_POST['username'] ?? ''));
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? '';
$initial = strtoupper(trim($_POST['initial'] ?? ''));
$pictureName = '';

if (!$name || !$username || !$password || !$role || !$initial) {
    $errors[] = "Semua field harus diisi.";
}

$stmt = $koneksi->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($userCount);
$stmt->fetch();
$stmt->close();

if ($userCount > 0) {
    $errors[] = "Username sudah digunakan.";
}

    // Handle upload gambar
    $pictureName = '';
    if (!empty($_FILES['picture']['name']) && $_FILES['picture']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed)) {
            $pictureName = uniqid('user_', true) . '.' . $ext;
            $uploadPath = BASE_PATH . "/assets/img/user/" . $pictureName;

            list($width, $height) = getimagesize($_FILES['picture']['tmp_name']);
            $maxWidth = 500;
            $maxHeight = 500;
            $scale = min($maxWidth / $width, $maxHeight / $height);
            $newWidth = (int)($width * $scale);
            $newHeight = (int)($height * $scale);

            switch ($ext) {
                case 'jpg':
                case 'jpeg':
                    $src = imagecreatefromjpeg($_FILES['picture']['tmp_name']);
                    break;
                case 'png':
                    $src = imagecreatefrompng($_FILES['picture']['tmp_name']);
                    break;
                case 'gif':
                    $src = imagecreatefromgif($_FILES['picture']['tmp_name']);
                    break;
                default:
                    $src = false;
            }

        if ($src) {
            $dst = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            $maxSize = 50 * 1024; // 50KB
            $imgData = '';
            $imgSize = 0;

            if (in_array($ext, ['jpg', 'jpeg'])) {
                $quality = 90;
                do {
                    ob_start();
                    imagejpeg($dst, null, $quality);
                    $imgData = ob_get_clean();
                    $imgSize = strlen($imgData);
                    $quality -= 5; // turunkan kualitas bertahap
                } while ($imgSize > $maxSize && $quality > 10);
            } elseif ($ext === 'png') {
                // Kompres PNG (0 = tanpa kompres, 9 = kompres maksimal)
                $compression = 9;
                do {
                    ob_start();
                    imagepng($dst, null, $compression);
                    $imgData = ob_get_clean();
                    $imgSize = strlen($imgData);
                    $compression = min($compression + 1, 9); // tingkatkan kompresi
                } while ($imgSize > $maxSize && $compression < 9);
            } elseif ($ext === 'gif') {
                // GIF tidak punya parameter kualitas signifikan
                ob_start();
                imagegif($dst);
                $imgData = ob_get_clean();
                $imgSize = strlen($imgData);
            }

            if ($imgSize <= $maxSize) {
                file_put_contents($uploadPath, $imgData);
            } else {
                $errors[] = "Gagal mengompres gambar ke ukuran di bawah 50KB.";
            }

            imagedestroy($src);
            imagedestroy($dst);
        }

        } else {
            $errors[] = "Format file tidak valid.";
        }
    }



if ($errors) {
    $_SESSION['flash_error'] = implode('<br>', $errors);
    header("Location: toko.php");
    exit;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $koneksi->prepare("INSERT INTO users (name, username, password, role, initial, picture, store_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssi", $name, $username, $passwordHash, $role, $initial, $pictureName, $store_id);
if ($stmt->execute()) {
    $_SESSION['flash_success'] = "User berhasil ditambahkan.";
} else {
    $_SESSION['flash_error'] = "Gagal menambahkan user.";
}
$stmt->close();
header("Location: index");
exit;
