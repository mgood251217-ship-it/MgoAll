<?php

class User{
    private $koneksi;

    public function __construct($koneksi){
        $this->koneksi = $koneksi;
    }

    public function createUser($data) {
        $passwordHash = password_hash($data->password, PASSWORD_DEFAULT);
        $stmt = $this->koneksi->prepare("INSERT INTO users (name, username, password, role, initial, picture, store_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssssi', $data->name, $data->username, $passwordHash, $data->role, $data->initial, $data->picture, $data->store_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateUser ($data){
        if (!empty($data->password)) {
            $passwordHash = password_hash($data->password, PASSWORD_DEFAULT);
            $stmt = $this->koneksi->prepare("UPDATE users SET name = ?, username = ?, password = ?, role = ?, initial = ?, picture = ? WHERE user_id = ? AND store_id = ?");
            $stmt->bind_param("ssssssii", $data->name, $data->username, $passwordHash, $data->role, $data->initial, $data->picture, $data->id, $data->store_id);
        } else {
            $stmt = $this->koneksi->prepare("UPDATE users SET name = ?, username = ?, role = ?, initial = ?, picture = ? WHERE user_id = ? AND store_id = ?");
            $stmt->bind_param("ssssssi", $data->name, $data->username, $data->role, $data->initial, $data->picture, $data->id, $data->store_id);
        }
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getUsersInitial($store_id) {
        $stmt = $this->koneksi->prepare("SELECT user_id, initial FROM users WHERE store_id = ? AND is_deleted = 0");
        $stmt->bind_param("i", $store_id);
        $stmt->execute();
        $userResult = $stmt->get_result();
        while ($u = $userResult->fetch_assoc()) {
            $users[$u['user_id']] = $u['initial'];
        }
        $stmt->close();
        
        return $users;
    }

    public function getOneValue($id, $column){
        $columnName = ['name', 'username', 'role', 'initial', 'picture'];
        if (!in_array($column, $columnName)) {
            return ''; 
        }
        $stmt = $this->koneksi->prepare("SELECT `{$column}` FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result[$column] : '';
    }

    public function getUsersByStoreId($id){
        $stmt = $this->koneksi->prepare("SELECT user_id, name, username, role, initial, picture, store_id FROM users WHERE store_id = ? AND is_deleted = 0");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function getUserByUsername($username){
        $stmt = $this->koneksi->prepare("SELECT user_id, username, name, store_id, initial, role, picture FROM users WHERE LOWER(username) = ? AND is_deleted = 0");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user;
    }

    public function getUserAuthData($username) {
        $stmt = $this->koneksi->prepare("SELECT password, store_id FROM users WHERE LOWER(username) = ? AND is_deleted = 0");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user;
    }

    public function checkUser ($username) {
        $stmt = $this->koneksi->prepare("SELECT 1 FROM users WHERE username = ? AND is_deleted = 0");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function checkValidOperator($user_id, $store_id) {
        $stmt = $this->koneksi->prepare("SELECT user_id FROM users WHERE user_id = ? AND store_id = ? AND is_deleted = 0");
        $stmt->bind_param("ii", $user_id, $store_id);
        $stmt->execute();
        $stmt->store_result();
        $isValid = $stmt->num_rows > 0;
        $stmt->close();
        return $isValid;
    }

    public function checkDuplicateUser ($data) {
        $stmt = $this->koneksi->prepare("SELECT 1 FROM users WHERE username = ? AND user_id != ? ");
        $stmt->bind_param("si", $data->username, $data->user_id);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function checkUserStore ($id) {
        $stmt = $this->koneksi->prepare("SELECT COUNT(*) AS total FROM users WHERE store_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return isset($result['total']) ? (int)$result['total'] : 0;
    }

    public function deleteUserById($id){
        $stmt = $this->koneksi->prepare("UPDATE users SET is_deleted = 1 WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}

?>