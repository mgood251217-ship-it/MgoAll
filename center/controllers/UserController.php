<?php
require_once __DIR__ . '/AdministratorAccessController.php';
class UserController {
    private $koneksi;

    public function __construct($db) {
        $this->koneksi = $db;
    }

    public function getIndexData() {
        $groupedUsers = [];
        $deletedUsers = [];
        $stores = [];

        $queryActive = "
            SELECT 
                u.user_id, 
                u.name,
                u.username,
                u.initial,
                u.role, 
                u.picture, 
                s.store_id,
                s.name AS store_name,
                (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.user_id AND o.store_id = u.store_id) AS order_count
            FROM users u
            LEFT JOIN stores s ON u.store_id = s.store_id
            WHERE u.is_deleted = 0
            ORDER BY s.name ASC, u.name ASC
        ";
        
        if ($resultActive = $this->koneksi->query($queryActive)) {
            while ($row = $resultActive->fetch_assoc()) {
                $storeName = !empty($row['store_name']) ? $row['store_name'] : 'Tanpa Toko';
                $groupedUsers[$storeName][] = $row;
            }
            $resultActive->free();
        }

        $queryDeleted = "
            SELECT 
                u.user_id, 
                u.name,
                u.username,
                u.role,
                u.store_id,
                s.name AS store_name,
                (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.user_id AND o.store_id = u.store_id) AS order_count
            FROM users u
            LEFT JOIN stores s ON u.store_id = s.store_id
            WHERE u.is_deleted = 1
            ORDER BY u.name ASC
        ";
        
        if ($resultDeleted = $this->koneksi->query($queryDeleted)) {
            while ($row = $resultDeleted->fetch_assoc()) {
                $deletedUsers[] = $row;
            }
            $resultDeleted->free();
        }

        if ($storesResult = $this->koneksi->query("SELECT store_id, name, administrator FROM stores ORDER BY name ASC")) {
            while ($row = $storesResult->fetch_assoc()) {
                $stores[] = $row;
            }
            $storesResult->free();
        }

        return [
            'groupedUsers' => $groupedUsers,
            'deletedUsers' => $deletedUsers,
            'stores' => $stores
        ];
    }

    public function migrateUser() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $transaction = $migrated = false;
        $storeId = 0;
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_SESSION['admin_logged_in'])) throw new InvalidArgumentException('Silakan login kembali.');
            if (!is_string($_POST['csrf'] ?? null) || empty($_SESSION['user_migration_csrf']) || !hash_equals($_SESSION['user_migration_csrf'], $_POST['csrf'])) throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
            if (!(new AdministratorAccessController($this->koneksi))->can('migrate_user')) throw new InvalidArgumentException('Anda tidak memiliki izin migrasi user.');
            $administrator = (new ProductHistoryController($this->koneksi))->getAdministrator();
            if (!$administrator) throw new InvalidArgumentException('Sesi administrator tidak valid.');
            $ids = [];
            foreach (['source_user_id', 'target_user_id', 'store_id'] as $field) {
                $id = filter_var($_POST[$field] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
                if (!$id) throw new InvalidArgumentException('Pilih user asal, user tujuan, dan toko yang valid.');
                $ids[$field] = $id;
            }
            $sourceId = $ids['source_user_id']; $targetId = $ids['target_user_id']; $storeId = $ids['store_id'];
            if ($sourceId === $targetId) throw new InvalidArgumentException('User tujuan harus berbeda dari user asal.');
            if (!$this->koneksi->begin_transaction()) throw new RuntimeException('Transaksi migrasi gagal dimulai.');
            $transaction = true;
            $stmt = $this->koneksi->prepare('SELECT administrator FROM stores WHERE store_id = ? FOR UPDATE');
            $stmt->bind_param('i', $storeId); $stmt->execute(); $store = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$store || ($administrator['access'] !== 'ALL' && ((string)$administrator['access'] === '' || (string)$administrator['access'] !== (string)$store['administrator']))) throw new InvalidArgumentException('Anda tidak memiliki akses ke toko ini.');
            $stmt = $this->koneksi->prepare('SELECT user_id, name, store_id, is_deleted FROM users WHERE user_id IN (?, ?) ORDER BY user_id FOR UPDATE');
            $stmt->bind_param('ii', $sourceId, $targetId); $stmt->execute(); $users = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), null, 'user_id'); $stmt->close();
            $source = $users[$sourceId] ?? null; $target = $users[$targetId] ?? null;
            if (!$source || !$target || (int)$source['store_id'] !== $storeId || (int)$target['store_id'] !== $storeId || (int)$target['is_deleted'] !== 0) throw new InvalidArgumentException('User asal dan user tujuan harus berada di toko yang sama. User tujuan harus aktif.');
            $stmt = $this->koneksi->prepare('UPDATE orders SET user_id = ? WHERE store_id = ? AND user_id = ?');
            $stmt->bind_param('iii', $targetId, $storeId, $sourceId); $stmt->execute(); $count = $stmt->affected_rows; $stmt->close();
            if ($count < 1) throw new InvalidArgumentException('Tidak ada order user asal pada toko ini yang perlu dimigrasikan.');
            if (!$this->koneksi->commit()) throw new RuntimeException('Migrasi gagal disimpan.');
            $transaction = false; $migrated = true;
            $_SESSION['swal_success'] = "$count order berhasil dipindahkan dari " . $source['name'] . ' ke ' . $target['name'] . '.';
        } catch (Throwable $e) {
            if ($transaction) $this->koneksi->rollback();
            error_log('User order migration: ' . $e->getMessage());
            $_SESSION['swal_error'] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Gagal memigrasikan order. Perubahan dibatalkan.';
        }
        if ($migrated) {
            require_once __DIR__ . '/../functions/cacheHelper.php';
            try {
                set_error_handler(function ($severity, $message) { throw new RuntimeException($message); });
                try { updateStoreCache($storeId, 'orders'); } finally { restore_error_handler(); }
            } catch (Throwable $e) {
                error_log('User migration cache: ' . $e->getMessage());
                unset($_SESSION['swal_success']);
                $_SESSION['swal_error'] = 'Migrasi order berhasil, tetapi cache belum diperbarui. Gunakan Update Cache pada toko ini.';
            }
        }
        header('Location: /users'); exit;
    }

    public function addUser() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        require_once __DIR__ . '/../functions/cacheHelper.php';
        require_once __DIR__ . '/../functions/imageHelpers.php';

        $name     = $_POST['name'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $initial  = $_POST['initial'] ?? '';
        $role     = $_POST['role'] ?? '';
        $store_id = $_POST['store_id'] ?? null;

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $pictureName = '';

        if (!empty($_FILES['picture']['name'])) {
            $targetDir = __DIR__ . "/../../../assets/img/user/";
            $resultUpload = compress($_FILES['picture'], $targetDir);
            if ($resultUpload['success']) {
                $pictureName = $resultUpload['file'];
            } else {
                $_SESSION['swal_error'] = $resultUpload['error'];
                header("Location: /users");
                exit;
            }
        }

        if ($name && $username && $password && $role && $store_id) {
            $stmt = $this->koneksi->prepare("INSERT INTO users (name, username, password, initial, role, store_id, picture) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssis", $name, $username, $hashedPassword, $initial, $role, $store_id, $pictureName);

            if ($stmt->execute()) {
                updateStoreCache($store_id, 'users');
                $_SESSION['swal_success'] = "User berhasil ditambahkan.";
            } else {
                $_SESSION['swal_error'] = "Gagal menyimpan data: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['swal_error'] = "Pastikan semua field wajib diisi.";
        }

        header("Location: /users");
        exit;
    }

    public function editUser() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        require_once __DIR__ . '/../functions/cacheHelper.php';
        require_once __DIR__ . '/../functions/imageHelpers.php';

        $user_id  = $_POST['user_id'] ?? null;
        $name     = $_POST['name'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $initial  = $_POST['initial'] ?? '';
        $role     = $_POST['role'] ?? '';
        $store_id = $_POST['store_id'] ?? null;

        if ($user_id && $name && $username && $role && $store_id) {
            $stmtGet = $this->koneksi->prepare("SELECT store_id FROM users WHERE user_id = ?");
            $stmtGet->bind_param("i", $user_id);
            $stmtGet->execute();
            $old_store_id = $stmtGet->get_result()->fetch_assoc()['store_id'] ?? null;
            $stmtGet->close();

            $updateSql = "UPDATE users SET name=?, username=?, initial=?, role=?, store_id=?";
            $params = [$name, $username, $initial, $role, $store_id];
            $types = "ssssi";

            if (!empty($password)) {
                $updateSql .= ", password=?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
                $types .= "s";
            }

            if (!empty($_FILES['picture']['name'])) {
                $targetDir = __DIR__ . "/../../admin/assets/img/user/";
                $resultUpload = compress($_FILES['picture'], $targetDir);
                if ($resultUpload['success']) {
                    $updateSql .= ", picture=?";
                    $params[] = $resultUpload['file'];
                    $types .= "s";
                } else {
                    $_SESSION['swal_error'] = $resultUpload['error'];
                    header("Location: /users");
                    exit;
                }
            }

            $updateSql .= " WHERE user_id=?";
            $params[] = $user_id;
            $types .= "i";

            $stmt = $this->koneksi->prepare($updateSql);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                updateStoreCache($store_id, 'users');
                if ($old_store_id && $old_store_id != $store_id) {
                    updateStoreCache($old_store_id, 'users');
                }
                $_SESSION['swal_success'] = "User berhasil diperbarui.";
            } else {
                $_SESSION['swal_error'] = "Gagal memperbarui data: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['swal_error'] = "Field wajib (Nama, Username, Peran, Toko) harus diisi.";
        }

        header("Location: /users");
        exit;
    }

    public function deleteUser() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        require_once __DIR__ . '/../functions/cacheHelper.php';
        
        $user_id = $_POST['user_id'] ?? null;

        if ($user_id) {
            $stmtGet = $this->koneksi->prepare("SELECT store_id FROM users WHERE user_id = ?");
            $stmtGet->bind_param("i", $user_id);
            $stmtGet->execute();
            $store_id = $stmtGet->get_result()->fetch_assoc()['store_id'] ?? null;
            $stmtGet->close();

            $stmt = $this->koneksi->prepare("UPDATE users SET is_deleted = 1 WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                if ($store_id) {
                    updateStoreCache($store_id, 'users');
                }
                echo "OK";
            } else {
                echo "Error";
            }
            $stmt->close();
        }
        exit;
    }

    public function restoreUser() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        require_once __DIR__ . '/../functions/cacheHelper.php';
        
        $user_id = $_POST['user_id'] ?? null;

        if ($user_id) {
            $stmtGet = $this->koneksi->prepare("SELECT store_id FROM users WHERE user_id = ?");
            $stmtGet->bind_param("i", $user_id);
            $stmtGet->execute();
            $store_id = $stmtGet->get_result()->fetch_assoc()['store_id'] ?? null;
            $stmtGet->close();

            $stmt = $this->koneksi->prepare("UPDATE users SET is_deleted = 0 WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                if ($store_id) {
                    updateStoreCache($store_id, 'users');
                }
                echo "OK";
            } else {
                echo "Error";
            }
            $stmt->close();
        }
        exit;
    }

    public function changeStore() {
        require_once __DIR__ . '/../functions/cacheHelper.php';
        
        $user_id = $_POST['user_id'] ?? null;
        $store_id = $_POST['store_id'] ?? null;

        if ($user_id && $store_id) {
            $stmtGet = $this->koneksi->prepare("SELECT store_id FROM users WHERE user_id = ?");
            $stmtGet->bind_param("i", $user_id);
            $stmtGet->execute();
            $old_store_id = $stmtGet->get_result()->fetch_assoc()['store_id'] ?? null;
            $stmtGet->close();

            $stmt = $this->koneksi->prepare("UPDATE users SET store_id = ? WHERE user_id = ?");
            $stmt->bind_param("ii", $store_id, $user_id);
            
            if ($stmt->execute()) {
                updateStoreCache($store_id, 'users');
                if ($old_store_id && $old_store_id != $store_id) {
                    updateStoreCache($old_store_id, 'users');
                }
                echo "OK";
            } else {
                echo "Error: " . $this->koneksi->error;
            }
            $stmt->close();
        } else {
            echo "Data tidak valid";
        }
        exit;
    }
}