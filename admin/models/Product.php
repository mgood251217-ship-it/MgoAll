<?php
// Product.php
class Product{
    private $koneksi;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
    }

    public function addProduct ($data) {
        $type = $data->type;
        $name = $data->name;
        $price = $data->price;
        $unit = $data->unit;
        $store_id = $data->store_id;
        $reasonable_price = $data->reasonable_price;
        $failed_price = $data->failed_price;
        $stmt = $this->koneksi->prepare("INSERT INTO products (store_id, type, name, price, unit_type, reasonable_price, failed_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $store_id, $type, $name, $price, $unit, $reasonable_price, $failed_price);
        return $stmt->execute();
    }

    public function updateProduct ($data) {
        $id = $data->id;
        $type = $data->type;
        $name = $data->name;
        $price = $data->price;
        $unit = $data->unit;
        $store_id = $data->store_id;
        $reasonable_price = $data->reasonable_price;
        $failed_price = $data->failed_price;
        $stmt = $this->koneksi->prepare("UPDATE products SET type = ?, name = ?, price = ?, unit_type = ?, reasonable_price = ?, failed_price = ? WHERE product_id = ? AND store_id = ? LIMIT 1");
        $stmt->bind_param("ssssssii", $type, $name, $price, $unit, $reasonable_price, $failed_price, $id, $store_id);
        return $stmt->execute();
    }

    public function getProductByStoreId ($store_id){
        $stmt = $this->koneksi->prepare("SELECT * FROM products WHERE store_id = ?");
        $stmt->bind_param('i', $store_id);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getProductById ($product_id){
        $stmt = $this->koneksi->prepare("SELECT * FROM products WHERE product_id = ?");
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getPrice ($product_id){
        $stmt = $this->koneksi->prepare("SELECT price FROM products WHERE product_id = ?");
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function deleteProductByIdAndStoreId($data){
        $store_id = $data->store_id;
        $id = $data->id;
        $stmt = $this->koneksi->prepare("DELETE FROM products WHERE product_id = ? AND store_id =? LIMIT 1");
        $stmt->bind_param('ii', $id, $store_id);
        return $stmt->execute();
    }
    
}
?>