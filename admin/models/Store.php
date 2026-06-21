<?php

class Store{
    private $koneksi;

    public function __construct($koneksi){
        $this->koneksi = $koneksi;
    }

    public function getStoreById($id){
        $stmt = $this->koneksi->prepare("SELECT * FROM stores WHERE store_id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function getStoreForMaklun($id){
        $stmt = $this->koneksi->prepare("SELECT store_id, name FROM stores WHERE NOT store_id = ? ORDER BY name");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function getMachineByStore_id($id){
        $stmt = $this->koneksi->prepare("SELECT machine_id, name, type FROM machine WHERE store_id = ? ORDER BY type ASC, name ASC");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function countNotif($id){
        $stmt = $this->koneksi->prepare("SELECT COUNT(*) as total FROM notifications WHERE is_read = 0 AND store_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['total'] ?? '';
    }

    public function getNotifByStoreId($id){
        $stmt = $this->koneksi->prepare("SELECT * FROM notifications WHERE store_id = ? ORDER BY created_at DESC LIMIT 5");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }
    
}

?>