<?php
class Project{
    private $koneksi;

    public function __construct($koneksi){
        $this->koneksi = $koneksi;
    }

    public function createProject($data){
        $stmt = $this->koneksi->prepare("INSERT INTO projects (order_id, date, status, process, user_id) VALUES (?, ?, 'BELUM BAYAR', 'BELUM BAYAR', 0)");
        $stmt->bind_param("is", $data->order_id, $data->date);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateProject($data){
        $stmt = $this->koneksi->prepare("UPDATE projects SET status = ?, process = ?, date = ?, user_id = ? WHERE order_id = ?");
        $stmt->bind_param("sssii", $data->status, $data->process, $data->date, $data->user_id, $data->order_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function deleteProjectByOrderId($id) {
        $stmt = $this->koneksi->prepare("DELETE FROM projects WHERE order_id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getLastProjectProcessByOrderId($id){
        $stmt = $this->koneksi->prepare("SELECT process FROM projects WHERE order_id = ? ORDER BY date DESC LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result['process'] : '';
    }

    public function getLastProjectStatusByOrderId($id){
        $stmt = $this->koneksi->prepare("SELECT `status` FROM projects WHERE order_id = ? ORDER BY date DESC LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result['status'] : '';
    }

}

?>