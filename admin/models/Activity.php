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
}

?>