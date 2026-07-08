<?php
require_once BASE_PATH . '/models/Product.php';
require_once BASE_PATH . '/functions/helpers.php';

class ProductController {
    private $productModel;

    public function __construct($koneksi) {
        $this->productModel = new Product($koneksi);
    }

    private function requestData() {
        global $store_id;
        
        $data = new stdClass();
        $data->id               = $_POST['product_id'] ?? 0;
        $data->finishing_id     = $_POST['finishing_id'] ?? 0;
        $data->store_id         = $store_id ?? 0;
        $data->category_id      = $_POST['category_id'] ?? '';
        $data->name             = $_POST['name'] ?? '';
        $data->price            = $_POST['price'] ?? '';
        $data->unit             = $_POST['unit_type'] ?? '';
        $data->reasonable_price = $_POST['reasonable_price'] ?? '';
        $data->failed_price     = $_POST['failed_price'] ?? '';
        
        return $data;
    }

    public function index() {
        global $store_id;
        return $this->productModel->getProductByStoreId($store_id);
    }

    public function getFinishing() {
        global $store_id;
        return $this->productModel->getFinishingByStoreId($store_id);
    }

    public function getCategory() {
        global $store_id;
        return $this->productModel->getCategoryByStoreId($store_id);
    }

    public function getProductByPagination(){
        global $store_id;

        $page = (int)($_GET['page'] ?? 1);
        $search = $_GET['search'] ?? '';
        $limit = (int)($_GET['limit'] ?? 25);

        $data = $this->productModel->getProductByPagination(
            $store_id,
            $page,
            $search,
            $limit
        );

        $total = $this->productModel->countProducts($store_id, $search);
        $totalPages = ceil($total / $limit);

        return [
            "data" => $data,
            "total_pages" => $totalPages,
            "total" => $total
        ];
    }

    public function createProduct() {
        header('Content-Type: application/json');
        $data = $this->requestData();
        
        if ($this->productModel->createProduct($data)) {
            send_json_response(true, 'Produk berhasil ditambahkan.');
        } else {
            send_json_response(false, 'Gagal menambahkan produk.');
        }
        exit;
    }

    public function createFinishing() {
        header('Content-Type: application/json');
        $data = $this->requestData();
        
        if ($this->productModel->createFinishing($data)) {
            send_json_response(true, 'Finishing berhasil ditambahkan.');
        } else {
            send_json_response(false, 'Gagal menambahkan finishing.');
        }
        exit;
    }

    public function updateProduct() {
        header('Content-Type: application/json');
        $data = $this->requestData();
        
        if ($this->productModel->updateProduct($data)) {
            send_json_response(true, 'Produk berhasil diperbarui.');
        } else {
            send_json_response(false, 'Gagal memperbarui produk.');
        }
        exit;
    }

    public function updateFinishing() {
        header('Content-Type: application/json');
        $data = $this->requestData();
        
        if ($this->productModel->updateFinishing($data)) {
            send_json_response(true, 'Finishing berhasil diperbarui.');
        } else {
            send_json_response(false, 'Gagal memperbarui finishing.');
        }
        exit;
    }

    public function deleteProduct() {
        header('Content-Type: application/json');
        $data = new stdClass();
        $data->id = $_POST['product_id'] ?? 0;
        
        if ($this->productModel->deleteProductById($data)) {
            send_json_response(true, 'Produk berhasil dihapus.');
        } else {
            send_json_response(false, 'Gagal menghapus produk.');
        }
        exit;
    }

    public function deleteFinishing() {
        header('Content-Type: application/json');
        $data = new stdClass();
        $data->id = $_POST['finishing_id'] ?? 0;
        
        if ($this->productModel->deleteFinishingById($data)) {
            send_json_response(true, 'Finishing berhasil dihapus.');
        } else {
            send_json_response(false, 'Gagal menghapus finishing.');
        }
        exit;
    }

    public function updateStock() {
        header('Content-Type: application/json');
        $id       = $_POST['product_id'] ?? 0;
        $quantity = $_POST['quantity'] ?? 0;

        if ($this->productModel->updateStock($id, $quantity)) {
            send_json_response(true, 'Stok berhasil diperbarui.');
        } else {
            send_json_response(false, 'Gagal memperbarui stok.');
        }
        exit;
    }

    public function updateStockFinishing() {
        header('Content-Type: application/json');
        $id       = $_POST['finishing_id'] ?? 0;
        $quantity = $_POST['quantity'] ?? 0;

        if ($this->productModel->updateStockFinishing($id, $quantity)) {
            send_json_response(true, 'Stok berhasil diperbarui.');
        } else {
            send_json_response(false, 'Gagal memperbarui stok.');
        }
        exit;
    }
}
?>