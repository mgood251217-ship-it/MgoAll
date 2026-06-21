<?php
class Location {
    private $koneksi;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
    }

    public function checkLocation($id) {
        $stmt = $this->koneksi->prepare("SELECT 1 FROM locations WHERE store_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function getAllLocation() {
        $result = $this->koneksi->query("SELECT l.*, s.name FROM locations l JOIN stores s ON l.store_id = s.store_id");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function createLocation($data) {
        $stmt = $this->koneksi->prepare("INSERT INTO locations (store_id, latitude, longitude) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $data->store_id, $data->latitude, $data->longitude);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateLocation($data) {
        $stmt = $this->koneksi->prepare("UPDATE locations SET latitude = ?, longitude = ? WHERE store_id = ?");
        $stmt->bind_param("ssi", $data->latitude, $data->longitude, $data->store_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function deleteLocation($id) {
        $stmt = $this->koneksi->prepare("DELETE FROM locations WHERE store_id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}
?>