<?php
// views/product/store_action.php

$type = $url_parts[1]; 
$store_id = $_SESSION['store_id'];

function compressAndResizeImage($source_path, $target_path, $max_size = 800, $quality = 80) {
    $info = getimagesize($source_path);
    if (!$info) return false;

    $width = $info[0]; $height = $info[1]; $mime = $info['mime'];

    if ($width > $max_size || $height > $max_size) {
        if ($width > $height) {
            $new_width = $max_size; $new_height = floor($height * ($max_size / $width));
        } else {
            $new_height = $max_size; $new_width = floor($width * ($max_size / $height));
        }
    } else {
        $new_width = $width; $new_height = $height;
    }

    $image_p = imagecreatetruecolor($new_width, $new_height);

    switch ($mime) {
        case 'image/jpeg': case 'image/jpg':
            $image = imagecreatefromjpeg($source_path);
            imagecopyresampled($image_p, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            imagejpeg($image_p, $target_path, $quality); 
            break;
        case 'image/png':
            $image = imagecreatefrompng($source_path);
            imagealphablending($image_p, false); imagesavealpha($image_p, true);
            $transparent = imagecolorallocatealpha($image_p, 255, 255, 255, 127);
            imagefilledrectangle($image_p, 0, 0, $new_width, $new_height, $transparent);
            imagecopyresampled($image_p, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            $png_quality = 9 - round(($quality / 100) * 9);
            imagepng($image_p, $target_path, $png_quality);
            break;
        default:
            move_uploaded_file($source_path, $target_path);
            return true;
    }
    imagedestroy($image_p); imagedestroy($image);
    return true;
}

if ($type == 'api_detail') {
    header('Content-Type: application/json');
    $id = $_GET['id'] ?? 0;
    
    // Ubah ? menjadi :id dan :store_id
    $stmt = $pdo->prepare("
        SELECT p.*, b.brand_code 
        FROM products p 
        LEFT JOIN brands b ON p.brand_id = b.id 
        WHERE p.id = :id AND p.store_id = :store_id
    ");
    
    $stmt->execute(['id' => $id, 'store_id' => $store_id]);
    
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    exit();
}

if ($type == 'delete_product') {
    $product_id = $_GET['id'] ?? 0;
    
    $stmtInfo = $pdo->prepare("SELECT img FROM products WHERE id = :id AND store_id = :store_id");
    $stmtInfo->execute(['id' => $product_id, 'store_id' => $store_id]);
    $product = $stmtInfo->fetch();

    if ($product) {
        if ($product['img'] && $product['img'] !== 'no-image.png' && file_exists('assets/products/' . $product['img'])) {
            unlink('assets/products/' . $product['img']);
        }
        $stmtDel = $pdo->prepare("DELETE FROM products WHERE id = :id AND store_id = :store_id");
        $stmtDel->execute(['id' => $product_id, 'store_id' => $store_id]);
    }
    header("Location: /product");
    exit();
}

if ($type == 'print_label') {
    $id = $_GET['id'] ?? 0;
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id AND store_id = :store_id");
    $stmt->execute(['id' => $id, 'store_id' => $store_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) die("Data produk tidak ditemukan.");
    ?>

    <?php
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if ($type == 'store_cat') {
        $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (:name)");
        $stmt->execute(['name' => $_POST['name']]);
    }

    if ($type == 'store_brand') {
        $img_name = 'default.png';
        if (!empty($_FILES['img']['name'])) {
            $img_name = time() . '_' . $_FILES['img']['name'];
            compressAndResizeImage($_FILES['img']['tmp_name'], 'assets/brands/' . $img_name, 400, 80);
        }
        
        // Memasukkan input brand_code ke tabel brands
        $stmt = $pdo->prepare("INSERT INTO brands (store_id, brand_code, name, img) VALUES (?, ?, ?, ?)");
        $stmt->execute([$store_id, $_POST['brand_code'], $_POST['name'], $img_name]);
    }

    if ($type == 'store_product') {
        require_once 'models/Product.php';
        $productModel = new Product($pdo);
        do {
            $new_code = (string) mt_rand(100000000, 999999999);
            $is_exists = $productModel->checkProductCodeExists($new_code, $store_id);
        } while ($is_exists == true);

        $img_name = 'no-image.png';
        if (!empty($_FILES['img']['name'])) {
            $img_name = time() . '_' . $_FILES['img']['name'];
            compressAndResizeImage($_FILES['img']['tmp_name'], 'assets/products/' . $img_name, 800, 80);
        }

        // Memasukkan input year ke tabel products
        $sql = "INSERT INTO products (product_code, name, info, year, brand_id, category_id, color, stock, price, img, store_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$new_code, $_POST['name'], $_POST['info'], $_POST['year'], $_POST['brand_id'], $_POST['category_id'], $_POST['color'], $_POST['stock'], $_POST['price'], $img_name, $store_id]);
    }

    if ($type == 'edit_stock') {
        // Menggunakan UPDATE murni tanpa operator '+', sehingga menimpa data lama
        $stmt = $pdo->prepare("UPDATE products SET stock = :qty WHERE id = :id AND store_id = :store_id");
        $stmt->execute([
            'qty' => (int)$_POST['new_stock'], 
            'id' => $_POST['product_id'], 
            'store_id' => $store_id
        ]);
    }

    if ($type == 'update_product') {
        $id = $_POST['product_id'];
        
        // Mengupdate input year pada tabel products (Tanpa mengubah foto)
        $sql = "UPDATE products SET product_code=?, name=?, info=?, year=?, brand_id=?, category_id=?, color=?, price=? WHERE id=? AND store_id=?";
        $params = [$_POST['product_code'], $_POST['name'], $_POST['info'], $_POST['year'], $_POST['brand_id'], $_POST['category_id'], $_POST['color'], $_POST['price'], $id, $store_id];

        if (!empty($_FILES['img']['name'])) {
            $img_name = time() . '_' . $_FILES['img']['name'];
            compressAndResizeImage($_FILES['img']['tmp_name'], 'assets/products/' . $img_name, 800, 80);
            
            // Mengupdate input year pada tabel products (Dengan mengubah foto)
            $sql = "UPDATE products SET product_code=?, name=?, info=?, year=?, brand_id=?, category_id=?, color=?, price=?, img=? WHERE id=? AND store_id=?";
            $params = [$_POST['product_code'], $_POST['name'], $_POST['info'], $_POST['year'], $_POST['brand_id'], $_POST['category_id'], $_POST['color'], $_POST['price'], $img_name, $id, $store_id];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    // ========================================================
    // TAMBAHAN: AKSI UPDATE BRAND (Termasuk Kode Brand & Logo)
    // ========================================================
    if ($type == 'update_brand') {
        $brand_id = $_POST['brand_id'];
        
        // Update data text dasar
        $sql = "UPDATE brands SET brand_code=?, name=? WHERE id=? AND store_id=?";
        $params = [$_POST['brand_code'], $_POST['name'], $brand_id, $store_id];

        // Jika user mengupload gambar logo baru
        if (!empty($_FILES['img']['name'])) {
            $img_name = time() . '_' . $_FILES['img']['name'];
            compressAndResizeImage($_FILES['img']['tmp_name'], 'assets/brands/' . $img_name, 400, 80);
            
            $sql = "UPDATE brands SET brand_code=?, name=?, img=? WHERE id=? AND store_id=?";
            $params = [$_POST['brand_code'], $_POST['name'], $img_name, $brand_id, $store_id];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    header("Location: /product");
    exit();
}

// ==========================================================
// BLOK GET: AKSI DELETE BRAND (Hapus via link URL)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] == 'GET' && $type == 'delete_brand') {
    $id = $_GET['id'] ?? 0;
    
    try {
        // Pengamanan: Lepaskan relasi brand dari produk (ubah brand_id jadi 0) agar produk tidak error saat brand dihapus
        $stmtUpdate = $pdo->prepare("UPDATE products SET brand_id = 0 WHERE brand_id = ? AND store_id = ?");
        $stmtUpdate->execute([$id, $store_id]);

        // Hapus brand dari database
        $stmt = $pdo->prepare("DELETE FROM brands WHERE id = ? AND store_id = ?");
        $stmt->execute([$id, $store_id]);
        
    } catch (PDOException $e) {
        die("Error Delete Brand: " . $e->getMessage());
    }
    
    header("Location: /product");
    exit();
}
?>