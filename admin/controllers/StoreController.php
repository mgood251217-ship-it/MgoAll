<?php
require_once BASE_PATH . '/models/Store.php';
require_once BASE_PATH . '/functions/helpers.php';
require_once BASE_PATH . '/middleware/AuthMiddleware.php';

class StoreController {
    private $storeModel;
    private $authMiddleware;

    public function __construct($koneksi) {
        $this->storeModel = new Store($koneksi);
        $this->authMiddleware = new AuthMiddleware($koneksi);
    }

    public function store(){
        global $store_id;
        $store = $this->storeModel->getStoreById($store_id);
        send_json_response(true, "Berhasil mengambil data Store", $store);
    }

    public function createMachine(){
        if ($this->authMiddleware->isAdminOrManager() == false) { return []; }
        global $store_id;
        $data = (object)[
            'name' => trim($_POST['name'] ?? ''),
            'type' => trim($_POST['type'] ?? ''),
            'store_id' => $store_id
        ];

        if ($this->storeModel->createMachine($data)) {
            updateStoreCache($store_id, 'machines');
            send_json_response(true, 'Mesin baru berhasil ditambahkan.');
        } else {
            http_response_code(500);
            send_json_response(false, 'Gagal menyimpan data ke database');
        }

    }

    public function updateMachine(){
        global $store_id;
        if ($this->authMiddleware->isAdminOrManager() == false) { return []; }
        $data = (object)[
            'name' => trim($_POST['name'] ?? ''),
            'type' => trim($_POST['type'] ?? ''),
            'machine_id' => trim($_POST['machine_id'] ?? ''),
        ];

        if ($this->storeModel->updateMachine($data)) {
            updateStoreCache($store_id, 'machines');
            send_json_response(true, 'Mesin berhasil diperbaharui.');
        } else {
            http_response_code(500);
            send_json_response(false, 'Gagal menyimpan data ke database');
        }

    }

    public function deleteMachine(){
        if ($this->authMiddleware->isAdminOrManager() == false) { return []; }
        global $store_id;
        $id = $_POST['machine_id'] ?? 0;

        if ($this->storeModel->deleteMachine($id, $store_id)) {
            updateStoreCache($store_id, 'machines');
            send_json_response(true, 'Mesin berhasil dihapus.');
        } else {
            http_response_code(500);
            send_json_response(false, 'Gagal menghapus mesin');
        }

    }

    public function machines(){
        global $store_id;
        $machines = $this->storeModel->getMachineByStoreId($store_id);
        send_json_response(true, "Berhasil mengambil data mesin", $machines);
    }

    public function storeName(){
        global $store_id;
        $stores = $this->storeModel->getStoreForMaklun($store_id);
        send_json_response(true, "Berhasil mengambil data mesin", $stores);
    }

}
?>