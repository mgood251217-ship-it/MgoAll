<?php

class Payment {
    private $koneksi;

    public function __construct($koneksi){
        $this->koneksi = $koneksi;
    }

    public function createPayment($data){
        $stmt = $this->koneksi->prepare("INSERT INTO payment (order_id, store_id, nominal, payment_method, status, date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisss", $data->order_id, $data->store_id, $data->nominal, $data->payment_method, $data->status, $data->date);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getPaymentById($id){
        $stmt = $this->koneksi->prepare("SELECT * FROM payments WHERE payment_id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    public function getPaidByOrderId($id){
        $stmt = $this->koneksi->prepare("SELECT COALESCE(SUM(nominal), 0) FROM payment WHERE order_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result['nominal'] : 0;
    }

    public function addTfImage ($data){
        $stmt = $this->koneksi->prepare("INSERT INTO transfers ( order_id, store_id, img, date ) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $data->order_id, $data->store_id, $data->img, $data->date);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}

?>