<?php
require_once '../../connect.php';
require_once BASE_PATH . '/global_functions.php';

// Ambil input
$name     = $_POST['name'] ?? '';
$address  = $_POST['address'] ?? '';
$nomor    = $_POST['nomor'] ?? '';
$branch   = $_POST['branch'] ?? '';
$owner_id = $_POST['owner_id'] ?? null;
$email    = $_POST['email'] ?? '';

// === Tangani Upload Logo (dengan default)
$logoName = 'logo.png';
if (!empty($_FILES['logo']['name'])) {
    $targetDir = BASE_PATH . "/assets/img/store/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    $logoName = uniqid("logo_", true) . '.' . $ext;
    $targetPath = $targetDir . $logoName;

    $maxWidth = 800;
    $maxHeight = 800;
    $maxSize = 200 * 1024;

    list($origWidth, $origHeight) = getimagesize($_FILES['logo']['tmp_name']);
    $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
    $newWidth = (int)($origWidth * $ratio);
    $newHeight = (int)($origHeight * $ratio);

    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $srcImage = imagecreatefromjpeg($_FILES['logo']['tmp_name']);
            break;
        case 'png':
            $srcImage = imagecreatefrompng($_FILES['logo']['tmp_name']);
            break;
        case 'gif':
            $srcImage = imagecreatefromgif($_FILES['logo']['tmp_name']);
            break;
        default:
            $srcImage = false;
    }

    if ($srcImage) {
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resizedImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        if ($ext === 'jpg' || $ext === 'jpeg') {
            $quality = 90;
            do {
                ob_start();
                imagejpeg($resizedImage, null, $quality);
                $imageData = ob_get_clean();
                $fileSize = strlen($imageData);
                $quality -= 5;
            } while ($fileSize > $maxSize && $quality >= 10);
            file_put_contents($targetPath, $imageData);
        } elseif ($ext === 'png') {
            ob_start();
            imagepng($resizedImage, null, 9);
            $imageData = ob_get_clean();

            if (strlen($imageData) > $maxSize) {
                $scale = sqrt($maxSize / strlen($imageData));
                $smallerWidth = max(50, (int)($newWidth * $scale));
                $smallerHeight = max(50, (int)($newHeight * $scale));
                $finalImage = imagecreatetruecolor($smallerWidth, $smallerHeight);
                imagecopyresampled($finalImage, $resizedImage, 0, 0, 0, 0, $smallerWidth, $smallerHeight, $newWidth, $newHeight);
                imagepng($finalImage, $targetPath, 9);
                imagedestroy($finalImage);
            } else {
                file_put_contents($targetPath, $imageData);
            }
        } elseif ($ext === 'gif') {
            imagegif($resizedImage, $targetPath);
        }

        imagedestroy($srcImage);
        imagedestroy($resizedImage);
    } else {
        $logoName = 'logo.png'; // fallback jika tipe tidak didukung
    }
}

// === Validasi dasar
if ($name && $address && $branch && $email && $owner_id) {
    // Insert toko ke database
    $stmt = $koneksi->prepare("INSERT INTO stores (name, address, nomor, branch, owner_id, email, logo) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssiss", $name, $address, $nomor, $branch, $owner_id, $email, $logoName);

    if ($stmt->execute()) {
        $store_id_baru = $koneksi->insert_id;

        // Update user jadi manager toko
        $updateStmt = $koneksi->prepare("UPDATE users SET store_id = ?, role = 'MANAGER' WHERE user_id = ?");
        $updateStmt->bind_param("ii", $store_id_baru, $owner_id);
        $updateStmt->execute();
        $updateStmt->close();

        header("Location: cabang.php?success=1");
        exit;
    } else {
        echo "Gagal menyimpan data: " . $stmt->error;
    }

    $stmt->close();
} else {
    echo "Field wajib diisi: nama, alamat, cabang, email, dan owner.";
}

$koneksi->close();
?>
