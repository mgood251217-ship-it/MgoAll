<?php
require_once BASE_PATH . '/models/Store.php';

class StoreController {
    private $storeModel;

    public function __construct($koneksi) {
        $this->storeModel = new Store($koneksi);
    }

    public function createMachine(){
        global $store_id;
        $data = (object)[
            'name' => trim($_POST['name'] ?? ''),
            'type' => trim($_POST['type'] ?? ''),
            'store_id' => $store_id
        ];

        if ($this->storeModel->createMachine($data)) {
            echo json_encode(['success' => true, 'message' => 'Mesin baru berhasil ditambahkan.']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data ke database']);
        }

    }

}
?>