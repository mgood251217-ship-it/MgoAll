<?php

class Activity {
    private $koneksi;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
    }

    public function createActivity($data) {
        $stmt = $this->koneksi->prepare("INSERT INTO activity (store_id, title, message, information, date, order_id, done, administrator_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "issssiii", 
            $data->store_id, 
            $data->title, 
            $data->message, 
            $data->information, 
            $data->date, 
            $data->order_id, 
            $data->done, 
            $data->administrator_id
        );
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }

    public function updateActivity($data){
        $stmt = $this->koneksi->prepare("UPDATE activity SET done = ? WHERE activity_id = ?");
        $stmt->bind_param("ii", $data->done, $data->id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getActivitiesByStoreId($id){
        $stmt = $this->koneksi->prepare("SELECT activity_id, title, message, information, order_id, date, done FROM activity WHERE store_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }
}

?>