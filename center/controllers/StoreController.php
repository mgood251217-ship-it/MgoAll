<?php
class StoreController {
    private $koneksi;

    public function __construct($db) {
        $this->koneksi = $db;
    }

    public function getIndexData($access) {
        if ($access == 'ALL') {
            $query = "
                SELECT s.*, 
                       u.name AS owner_name, 
                       (SELECT COUNT(*) FROM users emp WHERE emp.store_id = s.store_id AND emp.is_deleted = 0) AS total_karyawan 
                FROM stores s 
                LEFT JOIN users u ON s.owner_id = u.user_id
            ";
            $result = $this->koneksi->query($query);
        } else {
            $query = "
                SELECT s.*, 
                       u.name AS owner_name, 
                       (SELECT COUNT(*) FROM users emp WHERE emp.store_id = s.store_id AND emp.is_deleted = 0) AS total_karyawan 
                FROM stores s 
                LEFT JOIN users u ON s.owner_id = u.user_id 
                WHERE s.administrator = ? 
            ";
            $stmtStore = $this->koneksi->prepare($query);
            $stmtStore->bind_param("s", $access);
            $stmtStore->execute();
            $result = $stmtStore->get_result();
        }

        $stores = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $stores[] = $row;
            }
        }

        $userResult = $this->koneksi->query("SELECT user_id, name, username FROM users WHERE is_deleted = 0 ORDER BY name ASC");
        $all_users = [];
        if ($userResult) {
            while ($uRow = $userResult->fetch_assoc()) {
                $all_users[] = $uRow;
            }
        }

        return [
            'stores' => $stores,
            'all_users' => $all_users
        ];
    }

    private function catalogQuery($sql, $params = []) {
        $stmt = $this->koneksi->prepare($sql);
        if (!$stmt) throw new RuntimeException('Gagal menyiapkan impor produk.');
        if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Gagal menjalankan impor produk.');
        }
        return $stmt;
    }

    public function copyCatalogData($sourceId, $targetId, $access) {
        if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
            throw new InvalidArgumentException('Pilih toko sumber dan tujuan yang berbeda.');
        }
        if (!$this->koneksi->begin_transaction()) throw new RuntimeException('Gagal memulai transaksi.');
        try {
            $stmt = $this->catalogQuery('SELECT store_id, administrator FROM stores WHERE store_id IN (?, ?) ORDER BY store_id FOR UPDATE', [$sourceId, $targetId]);
            $stores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            if (count($stores) !== 2) throw new InvalidArgumentException('Toko sumber atau tujuan tidak ditemukan.');
            foreach ($stores as $store) {
                if ($access !== 'ALL' && ($access === '' || (string)$store['administrator'] !== (string)$access)) {
                    throw new InvalidArgumentException('Anda tidak memiliki akses ke toko sumber atau tujuan.');
                }
            }
            $source = [];
            foreach (['categories', 'products', 'finishings'] as $table) {
                $stmt = $this->catalogQuery("SELECT 1 FROM $table WHERE store_id = ? LIMIT 1 FOR UPDATE", [$targetId]);
                $hasData = $stmt->get_result()->num_rows > 0;
                $stmt->close();
                if ($hasData) throw new InvalidArgumentException('Toko tujuan sudah memiliki kategori, produk, atau finishing. Impor dibatalkan agar tidak duplikat.');
                $stmt = $this->catalogQuery("SELECT * FROM $table WHERE store_id = ? FOR UPDATE", [$sourceId]);
                $source[$table] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
            }
            if (!array_filter($source)) throw new InvalidArgumentException('Toko sumber belum memiliki data untuk diimpor.');
            $categoryMap = [];
            foreach ($source['categories'] as $category) {
                $stmt = $this->catalogQuery('INSERT INTO categories (name, store_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())', [$category['name'], $targetId]);
                $categoryMap[$category['category_id']] = $this->koneksi->insert_id;
                $stmt->close();
            }
            foreach (['products', 'finishings'] as $table) {
                $columns = $table === 'products'
                    ? ['category_id', 'type', 'unit_type', 'name', 'price', 'reasonable_price', 'failed_price', 'store_id', 'stock']
                    : ['category_id', 'unit_type', 'name', 'price', 'reasonable_price', 'failed_price', 'store_id', 'stock'];
                foreach ($source[$table] as $row) {
                    $oldId = $row['category_id'];
                    if ($oldId !== null && !isset($categoryMap[$oldId])) {
                        throw new InvalidArgumentException('Kategori produk atau finishing di toko sumber tidak valid. Perbaiki relasinya terlebih dahulu.');
                    }
                    $row['category_id'] = $oldId === null ? null : $categoryMap[$oldId];
                    $row['store_id'] = $targetId;
                    $params = array_map(function ($column) use ($row) { return $row[$column]; }, $columns);
                    $names = implode(', ', $columns);
                    $values = implode(', ', array_fill(0, count($columns), '?'));
                    if ($table === 'finishings') {
                        $names .= ', created_at, updated_at';
                        $values .= ', NOW(), NOW()';
                    }
                    $stmt = $this->catalogQuery("INSERT INTO $table ($names) VALUES ($values)", $params);
                    $stmt->close();
                }
            }
            if (!$this->koneksi->commit()) throw new RuntimeException('Gagal menyimpan hasil impor.');
            return array_map('count', $source);
        } catch (Throwable $e) {
            $this->koneksi->rollback();
            throw $e;
        }
    }

    private function verifyCopyPassword($password) {
        if (!is_string($password) || $password === '') {
            throw new InvalidArgumentException('Masukkan sandi akun Anda.');
        }
        $adminId = (int)startEnk('dek', $_SESSION['admin_logged_in']['administrator_id'] ?? '');
        $stmt = $this->koneksi->prepare('SELECT password, access FROM administrator WHERE administrator_id = ?');
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$admin || !password_verify($password, $admin['password'])) {
            throw new InvalidArgumentException('Sandi salah. Data tidak diimpor.');
        }
        return $admin['access'];
    }

    public function copyCatalog() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        require_once __DIR__ . '/../functions/helpers.php';
        try {
            if (empty($_SESSION['admin_logged_in']) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new InvalidArgumentException('Silakan login kembali.');
            }
            if (!is_string($_POST['csrf'] ?? null) || empty($_SESSION['store_copy_csrf']) || !hash_equals($_SESSION['store_copy_csrf'], $_POST['csrf'])) {
                throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
            }
            if (($_POST['confirmed'] ?? '') !== '1') {
                throw new InvalidArgumentException('Konfirmasi impor terlebih dahulu.');
            }
            $access = $this->verifyCopyPassword($_POST['password'] ?? '');
            $sourceId = filter_var($_POST['source_store_id'] ?? '', FILTER_VALIDATE_INT);
            $targetId = filter_var($_POST['target_store_id'] ?? '', FILTER_VALIDATE_INT);
            $counts = $this->copyCatalogData($sourceId, $targetId, $access);
        } catch (Throwable $e) {
            $_SESSION['swal_error'] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Impor gagal. Tidak ada data yang ditambahkan; silakan coba lagi.';
            error_log('Store catalog copy failed: ' . $e->getMessage());
            header('Location: /stores');
            exit;
        }

        try {
            require_once __DIR__ . '/../functions/cacheHelper.php';
            foreach (array_keys($counts) as $module) updateStoreCache($targetId, $module);
        } catch (Throwable $e) {
            error_log('Store catalog cache refresh failed: ' . $e->getMessage());
        }
        $_SESSION['swal_success'] = "Berhasil mengimpor {$counts['categories']} kategori, {$counts['products']} produk, dan {$counts['finishings']} finishing.";
        header('Location: /stores');
        exit;
    }

    public function editStore() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        $store_id = $_POST['store_id'] ?? null;
        $name     = $_POST['name'] ?? '';
        $address  = $_POST['address'] ?? '';
        $nomor    = $_POST['nomor'] ?? '';
        $branch   = $_POST['branch'] ?? '';
        $owner_id = $_POST['owner_id'] ?? null;
        $email    = $_POST['email'] ?? '';

        if ($store_id && $name && $address && $branch && $email) {
            $store_id = (int)$store_id;
            $owner_id = $owner_id ? (int)$owner_id : null;

            $stmt = $this->koneksi->prepare("UPDATE stores SET name = ?, address = ?, nomor = ?, branch = ?, owner_id = ?, email = ? WHERE store_id = ?");
            $stmt->bind_param("ssssisi", $name, $address, $nomor, $branch, $owner_id, $email, $store_id);

            if ($stmt->execute()) {
                if ($owner_id) {
                    $updateStmt = $this->koneksi->prepare("UPDATE users SET store_id = ?, role = 'MANAGER' WHERE user_id = ?");
                    $updateStmt->bind_param("ii", $store_id, $owner_id);
                    $updateStmt->execute();
                    $updateStmt->close();
                }

                $_SESSION['swal_success'] = "Cabang berhasil diperbarui.";
            } else {
                $_SESSION['swal_error'] = "Gagal memperbarui cabang: " . $stmt->error;
            }

            $stmt->close();
        } else {
            $_SESSION['swal_error'] = "Field wajib diisi: nama, alamat, cabang, email harus lengkap.";
        }

        header("Location: /stores");
        exit;
    }

    public function addStore() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        $name     = $_POST['name'] ?? '';
        $address  = $_POST['address'] ?? '';
        $nomor    = $_POST['nomor'] ?? '';
        $branch   = $_POST['branch'] ?? '';
        $owner_id = $_POST['owner_id'] ?? null;
        $email    = $_POST['email'] ?? '';

        $logoName = 'logo.png';
        if (!empty($_FILES['logo']['name'])) {
            $targetDir = __DIR__ . "/../../../assets/img/store/";
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
                $logoName = 'logo.png';
            }
        }

        if ($name && $address && $branch && $email && $owner_id) {
            $stmt = $this->koneksi->prepare("INSERT INTO stores (name, address, nomor, branch, owner_id, email, logo) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssiss", $name, $address, $nomor, $branch, $owner_id, $email, $logoName);

            if ($stmt->execute()) {
                $store_id_baru = $this->koneksi->insert_id;

                $updateStmt = $this->koneksi->prepare("UPDATE users SET store_id = ?, role = 'MANAGER' WHERE user_id = ?");
                $updateStmt->bind_param("ii", $store_id_baru, $owner_id);
                $updateStmt->execute();
                $updateStmt->close();

                $_SESSION['swal_success'] = "Toko berhasil ditambahkan.";
            } else {
                $_SESSION['swal_error'] = "Gagal menyimpan data: " . $stmt->error;
            }

            $stmt->close();
        } else {
            $_SESSION['swal_error'] = "Field wajib diisi: nama, alamat, cabang, email, dan owner.";
        }
        
        header("Location: /stores");
        exit;
    }

    public function setSession() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        session_destroy();

        require_once __DIR__ . '/../functions/helpers.php';
        
        $domain = 'mgood.my.id';

        $clearOptions = [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => $domain,
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'None',
        ];

        $sessionName = session_name();
        setcookie($sessionName, '', $clearOptions);
        if (isset($_COOKIE[$sessionName])) {
            unset($_COOKIE[$sessionName]); 
        }

        $cookiesToClear = [
            'user_user_id', 'user_username', 'user_name', 'user_initial', 
            'user_store_id', 'user_role', 'user_foto', 'store_name', 
            'store_address', 'store_logo', 'user_mode', 'user_access'
        ];
        
        foreach ($cookiesToClear as $cookieName) {
            setcookie($cookieName, '', $clearOptions);
            if (isset($_COOKIE[$cookieName])) {
                unset($_COOKIE[$cookieName]);
            }
        }
        
        $user_id = (int)$_POST['user_id'];
        $sql = "SELECT user_id, username, name, password, store_id, initial, role, picture FROM users WHERE user_id = ?";
        $stmt = $this->koneksi->prepare($sql);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $sesi = '';
        while ($row = $result->fetch_assoc()) {
            $sesi = $row;
        }

        if ($sesi) {
            $storeName = '';
            $storeAddress = '';
            $storeLogo = '';
            $mode = 0;

            $stmt = $this->koneksi->prepare("SELECT name, logo, address FROM stores WHERE store_id = ?");
            $stmt->bind_param("i", $sesi['store_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $data = $result->fetch_assoc()) {
                $storeName = $data['name'];
                $storeAddress = $data['address'];
                $storeLogo = $data['logo'];
            }

            $stmt = $this->koneksi->prepare("SELECT mode FROM user_setting WHERE user_id = ?");
            $stmt->bind_param("i", $sesi['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $row = $result->fetch_assoc()) {
                $mode = (int)$row['mode'];
            }
            $stmt->close();

            $expire = time() + (86400 * 1); 

            $options = [
                'expires'  => $expire,
                'path'     => '/',
                'domain'   => $domain,
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'None',
            ];

            $encryptedData = [
                'user_id' => startEnk('enk', $sesi['user_id']),
                'username' => startEnk('enk', $sesi['username']),
                'name' => startEnk('enk', $sesi['name']),
                'initial' => startEnk('enk', $sesi['initial']),
                'store_id' => startEnk('enk', $sesi['store_id']),
                'role' => startEnk('enk', $sesi['role']),
                'foto' => startEnk('enk', $sesi['picture']),
                'store_name' => startEnk('enk', $storeName),
                'store_address' => startEnk('enk', $storeAddress),
                'store_logo' => startEnk('enk', $storeLogo),
            ];

            $cookieNameMap = [
                'user_id' => 'user_user_id',
                'username' => 'user_username',
                'name' => 'user_name',
                'initial' => 'user_initial',
                'store_id' => 'user_store_id',
                'role' => 'user_role',
                'foto' => 'user_foto',
                'store_name' => 'store_name',
                'store_address' => 'store_address',
                'store_logo' => 'store_logo',
            ];
            foreach ($cookieNameMap as $field => $cookieName) {
                setcookie($cookieName, $encryptedData[$field], $options);
            }

            $signature = authCookieSignature($encryptedData);
            if ($signature !== '') {
                setcookie('user_auth_sig', $signature, $options);
            }

            setcookie('user_mode', startEnk('enk', $mode), $options);
            setcookie('user_access', startEnk('enk', 'all'), $options);
            
            http_response_code(200);
            echo "success";
        } else {
            http_response_code(400);
            echo "failed";
        }
        exit;
    }

    public function getStores($access){
        if ($access == 'ALL') {
            $storesResult = $this->koneksi->query("SELECT store_id, name FROM stores ORDER BY name ASC");
        } else {
            $stmt = $this->koneksi->prepare("SELECT store_id, name FROM stores WHERE administrator = ? ORDER BY name ASC");
            $stmt->bind_param("s", $access);
            $stmt->execute();
            $storesResult = $stmt->get_result();
        }

        $stores = $storesResult->fetch_all(MYSQLI_ASSOC);

        return $stores;
    }
}
