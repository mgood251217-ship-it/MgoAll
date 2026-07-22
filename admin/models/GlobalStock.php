<?php
class GlobalStock {
    private $koneksi;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
    }

    public function createGlobalStockCategory($data) {
        $stmt = $this->koneksi->prepare("INSERT INTO global_stock_categories (name, store_id) VALUES (?, ?)");
        $stmt->bind_param("ss", $data->name, $data->store_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getGlobalStockCategoriesByStoreId($store_id) {
        $stmt = $this->koneksi->prepare("SELECT * FROM global_stock_categories WHERE store_id = ?");
        $stmt->bind_param("s", $store_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function updateGlobalStockCategory($data) {
        $stmt = $this->koneksi->prepare("UPDATE global_stock_categories SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $data->name, $data->id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function createGlobalStock($data){
         $stmt = $this->koneksi->prepare("INSERT INTO global_stocks (name, size, price, global_stock_category_id, store_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $data->name, $data->size, $data->price, $data->global_stock_category_id, $data->store_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateGlobalStock($data){
        $stmt = $this->koneksi->prepare("UPDATE global_stocks SET name = ?, size = ?, price = ?, global_stock_category_id = ? WHERE id = ?");
        $stmt->bind_param("ssdii", $data->name, $data->size, $data->price, $data->category_id, $data->id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getGlobalStocksByStoreId($id, $store_id) {
        $stmt = $this->koneksi->prepare("SELECT gs.name, gs.size, gs.price, gsc.name as cat_name 
            FROM global_stocks gs 
            JOIN global_stock_categories gsc ON gs.global_stock_category_id = gsc.id 
            WHERE gs.id = ? AND gs.store_id = ?");
        $stmt->bind_param("ss", $id, $store_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    
    
}
?>