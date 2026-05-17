<?php
class Location{
    private $koneksi;

    public function __construct($koneksi){
        $this->koneksi = $koneksi;
    }

    public function checkLocation($id){
        $stmt = $this->koneksi->prepare("SELECT 1 FROM locations WHERE store_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function getAllLocation(){
        return $this->koneksi->query("SELECT * FROM locations");
    }

    public function addLocation($data){
        $stmt = $this->koneksi->prepare("INSERT INTO locations (store_id, name, latitude, longitude) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $data->store_id, $data->store_name, $data->latitude, $data->longitude);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateLocation($data){
        $stmt = $this->koneksi->prepare("UPDATE locations SET name = ?, latitude = ?, longitude = ? WHERE store_id = ?");
        $stmt->bind_param("sssi", $data->name, $data->latitude, $data->longitude, $data->store_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function deleteLocation($id){
        
    }

}

?>