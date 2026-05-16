<?php

class User{
    private $koneksi;

    public function __construct($koneksi){
        $this->koneksi = $koneksi;
    }

    public function addUser($data) {
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

    public function getUserByUserId($id){
        $stmt = $this->koneksi->prepare("SELECT 1 FROM users WHERE user_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getUserByStoreId($id){
        $stmt = $this->koneksi->prepare("SELECT * FROM users WHERE store_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function checkUser ($username) {
        $stmt = $this->koneksi->prepare("SELECT 1 FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function checkDuplicateUser ($data) {
        $stmt = $this->koneksi->prepare("SELECT 1 FROM users WHERE username = ? AND user_id != ?");
        $stmt->bind_param("si", $data->username, $data->user_id);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function checkUserStore ($id) {
        $stmt = $this->koneksi->prepare("SELECT user_id FROM users WHERE store_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows();
        $stmt->close();
        return $exists;
    }

    public function deleteUserById($id){
        $stmt = $this->koneksi->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}

?>