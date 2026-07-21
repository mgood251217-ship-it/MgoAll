<?php

class Failure {
    private $koneksi;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
    }

    public function createFailure($data) {
        $stmt = $this->koneksi->prepare("INSERT INTO failure 
            (user_id, store_id, nomorator, customer_name, machine_id, product_id, judul, size, quantity, finishing, date, failure_design, failure_print, failure_finishing, failure_cause, failure_cause_other, loss_burden, info) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "iissiisssissssssss", 
            $data['user_id_fail'], 
            $data['store_id'], 
            $data['nomorator'], 
            $data['customer_name'], 
            $data['machine_id'], 
            $data['product_id'], 
            $data['judul'], 
            $data['size'], 
            $data['quantity'], 
            $data['finishing_str'], 
            $data['date'],
            $data['failure_design'],
            $data['failure_print'],
            $data['failure_finishing'],
            $data['failure_cause'],
            $data['failure_cause_other'],
            $data['loss_burden'],
            $data['info']
        );
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    public function deleteFailure($id){
        $stmt = $this->koneksi->prepare("DELETE FROM failure WHERE failure_id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateFailureInfo($data){
        $stmt = $this->koneksi->prepare("UPDATE failure SET info = ? WHERE failure_id = ?");
        $stmt->bind_param("si", $data->info, $data->failure_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

}

?>