<?php
require_once BASE_PATH . '/models/Location.php';

class LocationController {
    private $locationModel;

    public function __construct($koneksi) {
        $this->locationModel = new Location($koneksi);
    }

    private function requestData() {
        global $store_id;

        $data = new stdClass();
        $data->store_id = $store_id;
        $data->latitude = $_POST['latitude'] ?? null;
        $data->longitude = $_POST['longitude'] ?? null;

        return $data;
    }

    public function setLocation() {
        header('Content-Type: application/json');
        $data = $this->requestData();

        if ($this->locationModel->checkLocation($data->store_id)) {
            $this->locationModel->updateLocation($data);
        } else {
            $this->locationModel->createLocation($data);
        }

        echo json_encode(['success' => true, 'message' => "Lokasi berhasil diperbarui."]);
        exit;
    }
}
?>