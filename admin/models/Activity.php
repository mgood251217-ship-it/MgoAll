<?php

class Activity {
    private $koneksi;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
    }

    public function createActivity($data) {
        $stmt = $this->koneksi->prepare("INSERT INTO activity (store_id, title, message, information, date, order_id, administrator_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "issssii",
            $data->store_id, 
            $data->title, 
            $data->message, 
            $data->information, 
            $data->date, 
            $data->order_id,
            $data->administrator_id
        );
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }

    public function getActivitiesByStoreId($id, $start_date, $end_date){
        $stmt = $this->koneksi->prepare("SELECT 
                                            a.activity_id, 
                                            a.title, 
                                            a.message, 
                                            a.information, 
                                            a.order_id, 
                                            a.date,
                                            o.date AS order_date
                                        FROM activity a
                                        LEFT JOIN orders o ON a.order_id = o.order_id
                                        WHERE a.store_id = ? 
                                        AND a.date BETWEEN ? AND ?
                                        ");
        $stmt->bind_param("iss", $id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }
}

?>