<?php

function compress($picture, $uploadDir, $targetFileSize = 120 * 1024) {
    if (!is_array($picture) || empty($picture['tmp_name']) || !is_uploaded_file($picture['tmp_name'])) {
        return ['success'=>false, 'error'=>'File upload tidak valid.'];
    }

    if (($picture['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success'=>false, 'error'=>'Gagal mengupload file.'];
    }

    if ($picture['size'] > 2 * 1024 * 1024) {
        return ['success'=>false, 'error'=>'Ukuran gambar maksimal 2 MB.'];
    }

    $ext = strtolower(pathinfo($picture['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif'])) return ['success'=>false, 'error'=>'Format file tidak valid'];

    $imageInfo = @getimagesize($picture['tmp_name']);
    if (!$imageInfo) return ['success'=>false, 'error'=>'File bukan gambar valid'];
    [$width, $height] = $imageInfo;

    $src = match($ext) {
        'jpg','jpeg' => @imagecreatefromjpeg($picture['tmp_name']),
        'png' => @imagecreatefrompng($picture['tmp_name']),
        'gif' => @imagecreatefromgif($picture['tmp_name']),
        default => false
    };
    if (!$src) return ['success'=>false, 'error'=>'File gambar rusak atau tidak valid'];
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $pictureName = uniqid('img_', true) . '.' . $ext;
    $destination = $uploadDir . $pictureName;
    $scale = min(1200 / $width, 1200 / $height, 1);
    do {
        $newWidth = max(1, (int)($width * $scale));
        $newHeight = max(1, (int)($height * $scale));
        $dst = imagecreatetruecolor($newWidth, $newHeight);
        if ($ext === 'png' || $ext === 'gif') {
            imagealphablending($dst, false); imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        ob_start();
        match($ext) {
            'jpg','jpeg' => imagejpeg($dst, null, 75),
            'png' => imagepng($dst, null, 8),
            'gif' => imagegif($dst)
        };
        $imgData = ob_get_clean();
        if (strlen($imgData) <= $targetFileSize) {
            file_put_contents($destination, $imgData);
            return ['success'=>true, 'file'=>$pictureName];
        }
        unset($dst);
        $scale -= 0.2;

    } while ($scale > 0.2);

    return ['success'=>false, 'error'=>'Gagal mengompres gambar ke target ukuran (120KB)'];
}

?>