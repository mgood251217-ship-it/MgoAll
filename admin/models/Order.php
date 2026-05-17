<?php
class Order{
    private $koneksi;

    public function __construct($koneksi){
        $this->koneksi = $koneksi;
    }

    public function createOrder($data){
        $stmt = $this->koneksi->prepare("INSERT INTO orders (store_id, nomorator, customer_name, nomor, total, deadline, user_id, system, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssdssss", $data->store_id, $data->nomorator, $data->customer_name, $data->nomor, $data->total, $data->deadline, $data->user_id, $data->system, $data->date);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getOrderById($id){
        $stmt = $this->koneksi->prepare("SELECT * FROM orders WHERE order_id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getTotalById($id){
        $stmt = $this->koneksi->prepare("SELECT total FROM orders WHERE order_id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result['total'] : '';
    }

    public function getNoteOrder($data){
        $stmt = $this->koneksi->prepare("SELECT note FROM note_orders WHERE order_id = ? AND note_for = ? ORDER BY note_order_id DESC LIMIT 1");
        $stmt->bind_param("is", $data->order_id, $data->note_for);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getHistoryNameAndNomor($data){
        $keyword = "%" . $data->name . "%";
        $stmt = $this->koneksi->prepare("SELECT DISTINCT customer_name AS name, nomor FROM orders WHERE store_id = ? AND customer_name LIKE ? LIMIT 10");
        $stmt->bind_param("is", $data->store_id, $keyword);
        $stmt->execute();
        return $stmt->get_result();
    }

}

?>