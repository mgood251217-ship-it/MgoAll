<?php

class Payment {
    private $koneksi;

    public function __construct($koneksi){
        $this->koneksi = $koneksi;
    }

    public function addPayment($data){

    }

    public function getPaymentById($id){
        $stmt = $this->koneksi->prepare("SELECT * FROM payments WHERE payment_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result();
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