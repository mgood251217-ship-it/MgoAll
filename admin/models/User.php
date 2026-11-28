<?php

class User{
    private $koneksi;
    private $productionSectionsAvailable;

    public function __construct($koneksi){
        $this->koneksi = $koneksi;
    }

    public function hasProductionSections() {
        if ($this->productionSectionsAvailable === null) {
            $row = $this->koneksi->query("SELECT EXISTS(SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'production_sections') AS ready")->fetch_assoc();
            $this->productionSectionsAvailable = (bool)$row['ready'];
        }
        return $this->productionSectionsAvailable;
    }

    public function getProductionSettings($id, $store) {
        $field = $this->hasProductionSections() ? 'production_sections' : 'NULL AS production_sections';
        $stmt = $this->koneksi->prepare("SELECT user_id, role, $field FROM users WHERE user_id = ? AND store_id = ? AND is_deleted = 0");
        $stmt->bind_param('ii', $id, $store); $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        return $row;
    }

    public function updateUser($data) {
        $sql = 'UPDATE users SET name=?, username=?, role=?, initial=?, picture=?';
        $params = [$data->name, $data->username, $data->role, $data->initial, $data->picture];
        $types = 'sssss';
        if (!empty($data->password)) {
            $sql .= ', password=?'; $params[] = password_hash($data->password, PASSWORD_DEFAULT); $types .= 's';
        }
        if (isset($data->production_sections) && $this->hasProductionSections()) {
            $sql .= ', production_sections=?'; $params[] = $data->production_sections; $types .= 's';
        }
        $sql .= ' WHERE user_id=? AND store_id=?';
        $params[] = $data->id; $params[] = $data->store_id; $types .= 'ii';
        $stmt = $this->koneksi->prepare($sql); $stmt->bind_param($types, ...$params);
        $success = $stmt->execute(); $stmt->close();
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
        $field = $this->hasProductionSections() ? 'production_sections' : 'NULL AS production_sections';
        $stmt = $this->koneksi->prepare("SELECT user_id, name, username, role, initial, picture, store_id, $field FROM users WHERE store_id = ? AND is_deleted = 0");
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
        $stmt->bind_param("si", $data->username, $data->id);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function createHelp($data){
        $stmt = $this->koneksi->prepare("INSERT INTO help_center (user_id, category, subject, detail, status, datetime, picture) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $data->user_id, $data->category, $data->subject, $data->detail, $data->status, $data->datetime, $data->picture);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateHelpStatus($id, $status){
        $stmt = $this->koneksi->prepare("UPDATE help_center SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getHelps($id){
        $stmt = $this->koneksi->prepare("SELECT * FROM help_center WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }
}

?>