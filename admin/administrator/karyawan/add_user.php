<?php
require_once '../config.php';
require_once '../../global_functions.php';


$errors = [];
$success = '';
$formData = [];

if (!empty($_POST)) {
    // Ambil data POST
    $formData['name']     = strtoupper(trim($_POST['name'] ?? ''));
    $formData['username'] = strtolower(trim($_POST['username'] ?? ''));
    $formData['password'] = $_POST['password'] ?? '';
    $formData['role']     = $_POST['role'] ?? '';
    $formData['initial']  = strtoupper(trim($_POST['initial'] ?? ''));
    $formData['store_id'] = $_POST['store_id'] ?? null;

    // Validasi input
    if (
        $formData['name'] === '' ||
        $formData['username'] === '' ||
        $formData['password'] === '' ||
        $formData['role'] === '' ||
        $formData['initial'] === ''
    ) {
        $errors[] = "Semua field wajib diisi.";
    }

    // Cek username unik
    if (empty($errors)) {
        $check = $koneksi->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $check->bind_param("s", $formData['username']);
        $check->execute();
        $check->bind_result($exists);
        $check->fetch();
        $check->close();

        if ($exists > 0) {
            $errors[] = "Username sudah digunakan.";
        }
    }

    // Handle upload gambar
    $pictureName = '';
    $maxFileSize = 50 * 1024; // 50KB

    if (!empty($_FILES['picture']['name']) && $_FILES['picture']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed)) {
            if ($_FILES['picture']['size'] > $maxFileSize) {
                $errors[] = "Ukuran file terlalu besar. Maksimal 50KB.";
            } else {
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
            }
        } else {
            $errors[] = "Format file tidak valid.";
        }
    }


    // Simpan jika validasi lolos
    if (empty($errors)) {
        $passwordHash = password_hash($formData['password'], PASSWORD_DEFAULT);
        $stmt = $koneksi->prepare("INSERT INTO users (name, username, password, role, initial, picture, store_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "ssssssi",
            $formData['name'],
            $formData['username'],
            $passwordHash,
            $formData['role'],
            $formData['initial'],
            $pictureName,
            $formData['store_id']
        );
        header('Location: karyawan.php');

        if ($stmt->execute()) {
            $success = "User berhasil ditambahkan.";
            $formData = ['name' => '', 'username' => '', 'role' => '', 'initial' => '', 'store_id' => null];
        } else {
            $errors[] = "Gagal menyimpan ke database.";
        }
        $stmt->close();
        
    }
}
?>
