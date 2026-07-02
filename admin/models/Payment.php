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

    public function deletePaymentById($id) {
        $stmt = $this->koneksi->prepare("DELETE FROM payment WHERE payment_id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function deletePaymentByOrderId($id) {
        $stmt = $this->koneksi->prepare("DELETE FROM payment WHERE order_id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getPaymentById($id){
        $stmt = $this->koneksi->prepare("SELECT * FROM payment WHERE payment_id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result =$stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;

    }

    public function getPaymentByOrderId($id){
        $stmt = $this->koneksi->prepare("SELECT * FROM payment WHERE order_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    public function getPaymentsByDate($start, $end){

    }
    
    public function getPaidByOrderId($id){
        $stmt = $this->koneksi->prepare("SELECT COALESCE(SUM(nominal), 0) AS total_nominal FROM payment WHERE order_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result['total_nominal'] : 0;
    }

    public function addTfImage ($data){
        $stmt = $this->koneksi->prepare("INSERT INTO transfers ( order_id, store_id, img, date ) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $data->order_id, $data->store_id, $data->img, $data->date);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateLastStatusPayment($order_id, $value){
        $stmt = $this->koneksi->prepare("
            UPDATE payment 
            SET status = ? 
            WHERE payment_id = (
                SELECT payment_id FROM (
                    SELECT payment_id FROM payment WHERE order_id = ? ORDER BY date DESC LIMIT 1
                ) AS subquery
            )
        ");
        $stmt->bind_param("si", $value, $order_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updatePayment($data){
        $stmt = $this->koneksi->prepare("UPDATE payment SET nominal = ?, payment_method = ?, date = ?, status = ? WHERE payment_id = ?");
        $stmt->bind_param("isssi", $data->nominal, $data->payment_method, $data->date, $data->status, $data->payment_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
}

?>