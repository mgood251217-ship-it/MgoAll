<?php
require_once BASE_PATH . '/models/User.php';

class UserController {
    private $userModel;

    public function __construct($koneksi) {
        $this->userModel = new User($koneksi);
    }

    public function index() {
        global $store_id;
        return $this->userModel->getUsersByStoreId($store_id);
    }

    private function requestData() {
        global $store_id, $storeName;

        $data = new stdClass();
        $data->id = (int)($_POST['user_id'] ?? 0);
        $data->name = strtoupper(trim($_POST['name'] ?? ''));
        $data->username = strtolower(trim($_POST['username'] ?? ''));
        $data->password = $_POST['password'] ?? '';
        $data->initial = strtoupper(trim($_POST['initial'] ?? ''));
        $data->role = strtoupper(trim($_POST['role'] ?? ''));
        $data->store_id = $store_id;
        $data->picture = $_POST['old_picture'] ?? '';
        $data->store_name = $storeName;

        return $data;
    }

    public function updateUser() {
        header('Content-Type: application/json');
        $data = $this->requestData();
        $errors = [];
        $uploadDir = BASE_PATH . "/assets/img/user/";
        $old_picture = $_POST['old_picture'] ?? '';

        if (!empty($_FILES['picture']['name']) && $_FILES['picture']['error'] === 0) {
            $result = compress($_FILES['picture'], $uploadDir);
            
            if (!$result['success']) {
                $errors[] = $result['error'];
            } else {
                $data->picture = $result['file'];
                if (!empty($old_picture) && file_exists($uploadDir . $old_picture)) {
                    unlink($uploadDir . $old_picture);
                }
            }
        }

        if ($this->userModel->checkDuplicateUser($data)) {
            $errors[] = "Username sudah digunakan oleh user lain.";
        }

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }

        if ($this->userModel->updateUser($data)) {
            echo json_encode(['success' => true, 'message' => "User berhasil diperbarui."]);
        } else {
            echo json_encode(['success' => false, 'errors' => ["Gagal memperbarui user."]]);
        }
        exit;
    }

    public function addUser() {
        header('Content-Type: application/json');
        $data = $this->requestData();
        $errors = [];
        $uploadDir = BASE_PATH . "/assets/img/user/";
        $data->picture = ''; 

        if (!empty($_FILES['picture']['name']) && $_FILES['picture']['error'] === 0) {
            $result = compress($_FILES['picture'], $uploadDir);
            
            if (!$result['success']) {
                $errors[] = $result['error'];
            } else {
                $data->picture = $result['file'];
            }
        }

        if ($this->userModel->checkUser($data->username)) {
            $errors[] = "Username sudah terdaftar.";
        }

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }

        if ($this->userModel->createUser($data)) {
            echo json_encode(['success' => true, 'message' => "User berhasil ditambahkan."]);
        } else {
            echo json_encode(['success' => false, 'errors' => ["Gagal menambahkan user."]]);
        }
        exit;
    }

    public function deleteUser() {
        header('Content-Type: application/json');
        global $store_id, $picture;
        $data = $this->requestData();

        if ($this->userModel->checkUserStore($store_id) == 1 || $data->role != 'MANAGER') {
            echo json_encode(['success' => false, 'errors' => ["Tidak bisa menghapus user terakhir."]]);
            exit;
        }

        if ($this->userModel->deleteUserById($data->id)) {
            if (!empty($picture)) {
                 $filePath = BASE_PATH . "/assets/img/" . $picture;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            echo json_encode(['success' => true, 'message' => "User berhasil dihapus."]);
        } else {
            echo json_encode(['success' => false, 'errors' => ["Gagal menghapus user."]]);
        }
        exit;
    }
}
?>