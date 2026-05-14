<?php
// Product.php
class Product{
    private $koneksi;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
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

    public function deleteProductByIdAndStoreId($product_id, $store_id){
        $stmt = $this->koneksi->prepare("DELETE FROM products WHERE product_id = ? AND store_id =?");
        return $stmt->execute();
    }
    
}
?>