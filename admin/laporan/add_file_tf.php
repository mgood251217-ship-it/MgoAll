<?php

require_once '../connect.php';
require_once BASE_PATH . '/session.php';
$order_id = $_POST['order_id'];

$storeNames = preg_replace('/[^a-zA-Z0-9_-]/', '_', $storeName ?? 'Toko');
$uploadDir = BASE_PATH . "/assets/img/buktitf/$storeNames/";

$errors = [];
$pictureName = '';
$maxFileSize = 120 * 1024; // 50KB
if (!empty($_FILES['picture']['name']) && $_FILES['picture']['error'] === 0) {
    $ext = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];

    if (!in_array($ext, $allowed)) {
        $errors[] = "Format file tidak valid.";
    } else {
        $imageInfo = @getimagesize($_FILES['picture']['tmp_name']);
        if ($imageInfo === false) {
            $errors[] = "File bukan gambar valid.";
        } else {
            list($width, $height) = $imageInfo;

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


                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $pictureName = uniqid('exp_', true) . '.' . $ext;
                $destination = $uploadDir . $pictureName;

                $scale = 1.0;
                $success = false;

                // 🔄 Resize bertahap hingga < 50KB
                do {
                    $newWidth  = (int)($width * $scale);
                    $newHeight = (int)($height * $scale);
                    $dst = imagecreatetruecolor($newWidth, $newHeight);

                    // Transparansi untuk PNG & GIF
                    if ($ext === 'png' || $ext === 'gif') {
                        imagealphablending($dst, false);
                        imagesavealpha($dst, true);
                        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                        imagefill($dst, 0, 0, $transparent);
                    }

                    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

                    ob_start();
                    if ($ext === 'jpg' || $ext === 'jpeg') {
                        imagejpeg($dst, null, 85);
                    } elseif ($ext === 'png') {
                        imagepng($dst, null, 8);
                    } elseif ($ext === 'gif') {
                        imagegif($dst);
                    }
                    $imgData = ob_get_clean();

                    if (strlen($imgData) <= $maxFileSize) {
                        file_put_contents($destination, $imgData);
                        $success = true;
                        imagedestroy($dst);
                        break;
                    }

                    imagedestroy($dst);
                    $scale -= 0.1;
                } while ($scale > 0.1);

                imagedestroy($src);

                if (!$success) {
                    $errors[] = "Gagal mengompres gambar ke ukuran di bawah 50KB.";
                    $pictureName = '';
                } else {
                    // Simpan path relatif untuk DB
                    
                }
            }
        }
    }
    $insert = $koneksi->prepare("
                        INSERT INTO transfers
                        (order_id, store_id, img, date)
                        VALUES (?, ?, ?, ?)");
    $insert->bind_param("iiss", $order_id, $store_id, $pictureName, $date);
    $insert->execute();
    $insert->close();
}
?>