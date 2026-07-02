<?php
require_once BASE_PATH . '/models/Stock.php';
require_once BASE_PATH . '/functions/helpers.php';

class StockController {
    private $stockModel;

    public function __construct($koneksi) {
        $this->stockModel = new Stock($koneksi);
    }

    private function requestData() {
        global $store_id;

        $data = new stdClass();
        $data->id = $_POST['product_id'] ?? 0;
        $data->store_id = $store_id;
        $data->quantity = $_POST['quantity'] ?? 0;

        return $data;
    }

    public function index() {
        global $store_id;
        return $this->stockModel->getAllStock($store_id);
    }

    public function addStock() {
        header('Content-Type: application/json');
        $data = $this->requestData();
        $success = false;

        if ($this->stockModel->checkStock($data->id)) {
            $success = $this->stockModel->createUpdateStock($data);
        } else {
            $success = $this->stockModel->createStock($data);
        }

        if ($success) {
            send_json_response(true, 'Stok berhasil ditambahkan.');
        } else {
            send_json_response(false, 'Gagal menambahkan stok.');
        }
    }

    public function updateStock() {
        header('Content-Type: application/json');
        $data = $this->requestData();
        $success = false;

        if ($this->stockModel->checkStock($data->id)) {
            $success = $this->stockModel->updateStock($data);
        } else {
            $success = $this->stockModel->createStock($data);
        }

        if ($success) {
            send_json_response(true, 'Stok berhasil diperbarui.');
        } else {
            send_json_response(false, 'Gagal memperbarui stok.');
        }
    }
}