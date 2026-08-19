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

        $name     = $_POST['name'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $initial  = $_POST['initial'] ?? '';
        $role     = $_POST['role'] ?? '';
        $store_id = $_POST['store_id'] ?? null;

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $pictureName = '';

        if (!empty($_FILES['picture']['name'])) {
            $pictureName = $this->processImageUpload($_FILES['picture']);
        }

        if ($name && $username && $password && $role && $store_id) {
            $stmt = $this->koneksi->prepare("INSERT INTO users (name, username, password, initial, role, store_id, picture) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssis", $name, $username, $hashedPassword, $initial, $role, $store_id, $pictureName);

            if ($stmt->execute()) {
                $_SESSION['swal_success'] = "User berhasil ditambahkan.";
            } else {
                $_SESSION['swal_error'] = "Gagal menyimpan data: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['swal_error'] = "Pastikan semua field wajib diisi.";
        }

        header("Location: /center/users");
        exit;
    }

    public function editUser() {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $user_id  = $_POST['user_id'] ?? null;
        $name     = $_POST['name'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $initial  = $_POST['initial'] ?? '';
        $role     = $_POST['role'] ?? '';
        $store_id = $_POST['store_id'] ?? null;

        if ($user_id && $name && $username && $role && $store_id) {
            $updateSql = "UPDATE users SET name=?, username=?, initial=?, role=?, store_id=?";
            $params = [$name, $username, $initial, $role, $store_id];
            $types = "ssssi";

            if (!empty($password)) {
                $updateSql .= ", password=?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
                $types .= "s";
            }

            if (!empty($_FILES['picture']['name'])) {
                $pictureName = $this->processImageUpload($_FILES['picture']);
                if ($pictureName) {
                    $updateSql .= ", picture=?";
                    $params[] = $pictureName;
                    $types .= "s";
                }
            }

            $updateSql .= " WHERE user_id=?";
            $params[] = $user_id;
            $types .= "i";

            $stmt = $this->koneksi->prepare($updateSql);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $_SESSION['swal_success'] = "User berhasil diperbarui.";
            } else {
                $_SESSION['swal_error'] = "Gagal memperbarui data: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['swal_error'] = "Field wajib (Nama, Username, Peran, Toko) harus diisi.";
        }

        header("Location: /center/users");
        exit;
    }

    public function deleteUser() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $user_id = $_POST['user_id'] ?? null;

        if ($user_id) {
            $stmt = $this->koneksi->prepare("UPDATE users SET is_deleted = 1 WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
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
        $user_id = $_POST['user_id'] ?? null;

        if ($user_id) {
            $stmt = $this->koneksi->prepare("UPDATE users SET is_deleted = 0 WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                echo "OK";
            } else {
                echo "Error";
            }
            $stmt->close();
        }
        exit;
    }

    public function changeStore() {
        $user_id = $_POST['user_id'] ?? null;
        $store_id = $_POST['store_id'] ?? null;

        if ($user_id && $store_id) {
            $stmt = $this->koneksi->prepare("UPDATE users SET store_id = ? WHERE user_id = ?");
            $stmt->bind_param("ii", $store_id, $user_id);
            
            if ($stmt->execute()) {
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

    private function processImageUpload($fileInfo) {
        $targetDir = __DIR__ . "/../../../assets/img/user/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $ext = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
        $pictureName = uniqid("user_", true) . '.' . $ext;
        $targetPath = $targetDir . $pictureName;

        $maxWidth = 400;
        $maxHeight = 400;
        $maxSize = 100 * 1024;

        list($origWidth, $origHeight) = getimagesize($fileInfo['tmp_name']);
        if (!$origWidth || !$origHeight) return '';

        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
        $newWidth = (int)($origWidth * $ratio);
        $newHeight = (int)($origHeight * $ratio);

        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $srcImage = imagecreatefromjpeg($fileInfo['tmp_name']);
                break;
            case 'png':
                $srcImage = imagecreatefrompng($fileInfo['tmp_name']);
                break;
            case 'gif':
                $srcImage = imagecreatefromgif($fileInfo['tmp_name']);
                break;
            default:
                $srcImage = false;
        }

        if ($srcImage) {
            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resizedImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

            if ($ext === 'jpg' || $ext === 'jpeg') {
                $quality = 85;
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
            return $pictureName;
        }

        return '';
    }
}