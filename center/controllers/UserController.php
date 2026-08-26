<?php
class UserController {
    private $koneksi;

    public function __construct($db) {
        $this->koneksi = $db;
    }

    public function getIndexData() {
        $queryActive = "
            SELECT 
                u.user_id, 
                u.name,
                u.username,
                u.initial,
                u.role, 
                u.picture, 
                s.store_id,
                s.name AS store_name 
            FROM users u
            LEFT JOIN stores s ON u.store_id = s.store_id
            WHERE u.is_deleted = 0
            ORDER BY s.name ASC, u.name ASC
        ";
        $resultActive = $this->koneksi->query($queryActive);

        $groupedUsers = [];
        if ($resultActive) {
            while ($row = $resultActive->fetch_assoc()) {
                $storeName = $row['store_name'] ?: 'Tanpa Toko';
                $groupedUsers[$storeName][] = $row;
            }
        }

        $queryDeleted = "
            SELECT 
                u.user_id, 
                u.name,
                u.username,
                u.role, 
                s.name AS store_name 
            FROM users u
            LEFT JOIN stores s ON u.store_id = s.store_id
            WHERE u.is_deleted = 1
            ORDER BY u.name ASC
        ";
        $resultDeleted = $this->koneksi->query($queryDeleted);
        
        $deletedUsers = [];
        if ($resultDeleted) {
            while ($row = $resultDeleted->fetch_assoc()) {
                $deletedUsers[] = $row;
            }
        }

        $storesResult = $this->koneksi->query("SELECT store_id, name FROM stores ORDER BY name ASC");
        $stores = [];
        if ($storesResult) {
            while ($row = $storesResult->fetch_assoc()) {
                $stores[] = $row;
            }
        }

        return [
            'groupedUsers' => $groupedUsers,
            'deletedUsers' => $deletedUsers,
            'stores' => $stores
        ];
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