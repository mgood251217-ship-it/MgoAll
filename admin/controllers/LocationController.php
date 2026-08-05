<?php
require_once BASE_PATH . '/models/Location.php';
require_once BASE_PATH . '/functions/helpers.php';
require_once BASE_PATH . '/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/functions/cacheHelpers.php';

class LocationController {
    private $locationModel;
    private $authMiddleware;

    public function __construct($koneksi) {
        $this->locationModel = new Location($koneksi);
        $this->authMiddleware = new AuthMiddleware($koneksi);
    }

    private function requestData() {
        global $store_id;

        $data = new stdClass();
        $data->store_id = $store_id;
        $data->latitude = $_POST['latitude'] ?? null;
        $data->longitude = $_POST['longitude'] ?? null;

        return $data;
    }

    public function index() {
        return $this->locationModel->getAllLocation();
    }

    public function setLocation() {
        if ($this->authMiddleware->isAdminOrManager() == false) { return []; }
        header('Content-Type: application/json');
        $data = $this->requestData();

        if ($this->locationModel->checkLocation($data->store_id)) {
            $this->locationModel->updateLocation($data);
        } else {
            $this->locationModel->createLocation($data);
        }
        updateStoreCache($data->store_id, 'locations');
        send_json_response(true, "Lokasi berhasil diperbarui.");
        exit;
    }
}
?>