<?php
require_once BASE_PATH . '/models/Stock.php';

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

        if ($this->stockModel->checkStock($data)) {
            $success = $this->stockModel->createUpdateStock($data);
        } else {
            $success = $this->stockModel->createStock($data);
        }

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Stok berhasil ditambahkan.']);
        } else {
            echo json_encode(['success' => false, 'errors' => ['Gagal menambahkan stok.']]);
        }
        exit;
    }

    public function updateStock() {
        header('Content-Type: application/json');
        $data = $this->requestData();
        $success = false;

        if ($this->stockModel->checkStock($data)) {
            $success = $this->stockModel->updateStock($data);
        } else {
            $success = $this->stockModel->createStock($data);
        }

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Stok berhasil diperbarui.']);
        } else {
            echo json_encode(['success' => false, 'errors' => ['Gagal memperbarui stok.']]);
        }
        exit;
    }
}