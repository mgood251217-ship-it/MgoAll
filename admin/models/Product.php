<?php
// Product.php
class Product{
    private $koneksi;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
    }

    public function createProduct ($data) {
        $stmt = $this->koneksi->prepare("INSERT INTO products (store_id, type, name, price, unit_type, reasonable_price, failed_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $data->store_id, $data->type, $data->name, $data->price, $data->unit, $data->reasonable_price, $data->failed_price);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateProduct ($data) {
        $stmt = $this->koneksi->prepare("UPDATE products SET type = ?, name = ?, price = ?, unit_type = ?, reasonable_price = ?, failed_price = ? WHERE product_id = ? LIMIT 1");
        $stmt->bind_param("ssssssi", $data->type, $data->name, $data->price, $data->unit, $data->reasonable_price, $data->failed_price, $data->id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getProductByStoreId ($store_id){
        $stmt = $this->koneksi->prepare("SELECT * FROM products WHERE store_id = ?");
        $stmt->bind_param('i', $store_id);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getProductById ($product_id){
        $stmt = $this->koneksi->prepare("SELECT * FROM products WHERE product_id = ? LIMIT 1");
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getPrice ($product_id){
        $stmt = $this->koneksi->prepare("SELECT price FROM products WHERE product_id = ? LIMIT 1");
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function deleteProductById($data){
        $stmt = $this->koneksi->prepare("DELETE FROM products WHERE product_id = ? AND LIMIT 1");
        $stmt->bind_param('i', $data->id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
}
?>