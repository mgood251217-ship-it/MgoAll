<?php
require_once BASE_PATH . '/models/Store.php';
require_once BASE_PATH . '/functions/helpers.php';

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
            send_json_response(true, 'Mesin baru berhasil ditambahkan.');
        } else {
            http_response_code(500);
            send_json_response(false, 'Gagal menyimpan data ke database');
        }

    }

}
?>