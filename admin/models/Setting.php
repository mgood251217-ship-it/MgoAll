<?php

class Setting{
    private $koneksi;

    public function __construct($koneksi){
        $this->koneksi = $koneksi;
    }

    public function create ($data) {
        $stmt = $this->koneksi->prepare("INSERT INTO user_setting (user_id, mode, preview_print, customer_limit) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiii", $data->user_id, $data->mode, $data->preview_print, $data->customer_limit);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateOneValue($id, $column, $value){
        $columnName = ['mode', 'preview_print', 'customer_limit'];
        if (!in_array($column, $columnName)) {
            return ''; 
        }
        $stmt = $this->koneksi->prepare("UPDATE user_setting SET `{$column}` = ? WHERE user_id = ?");
        $stmt->bind_param("ii", $value, $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getUserSettingByUserId($id){
        $stmt = $this->koneksi->prepare("SELECT * FROM user_setting WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function cekUserSetting($id){
        $stmt = $this->koneksi->prepare("SELECT 1 FROM user_setting WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function getOneValue($id, $column){
        $columnName = ['mode', 'preview_print', 'customer_limit'];
        if (!in_array($column, $columnName)) {
            return ''; 
        }
        $stmt = $this->koneksi->prepare("SELECT `{$column}` FROM user_setting WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result[$column] : '';
    }
}

?>