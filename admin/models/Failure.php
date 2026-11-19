<?php

class Failure {
    private $koneksi;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
    }

    public function createFailure($data) {
        $columns = ['user_id', 'store_id', 'nomorator', 'customer_name', 'machine_id', 'product_id', 'judul',
            'size', 'quantity', 'finishing', 'date', 'failure_cause_other', 'loss_burden', 'info', 'failure_reason_ids'];
        $values = [$data['user_id_fail'], $data['store_id'], $data['nomorator'], $data['customer_name'],
            $data['machine_id'], $data['product_id'], $data['judul'], $data['size'], $data['quantity'],
            $data['finishing_str'], $data['date'], $data['failure_cause_other'], $data['loss_burden'], $data['info'], $data['failure_reason_ids']];
        $types = 'iissiississssss';
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $stmt = $this->koneksi->prepare('INSERT INTO failure (' . implode(',', $columns) . ") VALUES ($placeholders)");
        $stmt->bind_param($types, ...$values);
        $success = $stmt->execute();
        $id = $success ? (int)$this->koneksi->insert_id : false;
        $stmt->close();
        return $id;
    }

    public function deleteFailure($id, $storeId) {
        $stmt = $this->koneksi->prepare('DELETE FROM failure WHERE failure_id = ? AND store_id = ?');
        $stmt->bind_param('ii', $id, $storeId);
        $success = $stmt->execute() && $stmt->affected_rows > 0;
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
