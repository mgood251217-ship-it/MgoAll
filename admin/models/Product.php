<?php
class Product{
    private $koneksi;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
    }

    public function createProduct ($data) {
        $stmt = $this->koneksi->prepare("INSERT INTO products (store_id, category_id, name, price, unit_type, reasonable_price, failed_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssss", $data->store_id, $data->category_id, $data->name, $data->price, $data->unit, $data->reasonable_price, $data->failed_price);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function createFinishing ($data) {
        $stmt = $this->koneksi->prepare("INSERT INTO finishings (store_id, category_id, name, price, unit_type, reasonable_price, failed_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssss", $data->store_id, $data->category_id, $data->name, $data->price, $data->unit, $data->reasonable_price, $data->failed_price);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
 
    public function updateProduct ($data) {
        $stmt = $this->koneksi->prepare("UPDATE products SET category_id = ?, name = ?, price = ?, unit_type = ?, reasonable_price = ?, failed_price = ? WHERE product_id = ? LIMIT 1");
        $stmt->bind_param("isssssi", $data->category_id, $data->name, $data->price, $data->unit, $data->reasonable_price, $data->failed_price, $data->id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateFinishing ($data) {
        $stmt = $this->koneksi->prepare("UPDATE finishings SET category_id = ?, name = ?, price = ?, unit_type = ?, reasonable_price = ?, failed_price = ? WHERE finishing_id = ? LIMIT 1");
        $stmt->bind_param("isssssi", $data->category_id, $data->name, $data->price, $data->unit, $data->reasonable_price, $data->failed_price, $data->finishing_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getProductByStoreId ($store_id){
        $stmt = $this->koneksi->prepare("
            SELECT
                p.*,
                c.name AS category
            FROM products p
            LEFT JOIN categories c
                ON c.category_id = p.category_id
            WHERE p.store_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('i', $store_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    public function getProductById($id) {
        $stmt = $this->koneksi->prepare("
            SELECT
                p.*,
                c.name AS category
            FROM products p
            LEFT JOIN categories c
                ON c.category_id = p.category_id
            WHERE p.product_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();

        return $data;
    }

    public function getProductByTypeAndStoreId($data){
        $stmt = $this->koneksi->prepare("SELECT * FROM products WHERE type = ? AND store_id = ?");
        $stmt->bind_param('si', $data->type, $data->store_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $products = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $products;
    }

    public function getFinishingByStoreId($store_id){
        $stmt = $this->koneksi->prepare("
            SELECT
                f.*,
                c.name AS category
            FROM finishings f
            LEFT JOIN categories c
                ON c.category_id = f.category_id
            WHERE f.store_id = ?
        ");
        $stmt->bind_param('i', $store_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $finishings = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $finishings;
    }

    public function getCategoryByStoreId($store_id){
        $stmt = $this->koneksi->prepare("SELECT * FROM categories WHERE store_id = ?");
        $stmt->bind_param('i', $store_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $caregories = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $caregories;
    }

    public function getProductByCategoryId($category_id){
        $stmt = $this->koneksi->prepare("SELECT * FROM products WHERE category_id = ?");
        $stmt->bind_param('i', $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $products = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $products;
    }

    public function getFinishingByCategoryId($category_id){
        $stmt = $this->koneksi->prepare("SELECT * FROM finishings WHERE category_id = ?");
        $stmt->bind_param('i', $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $finishings = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $finishings;
    }

    public function getProductByNameAndStore($name, $store_id) {
        $stmt = $this->koneksi->prepare("SELECT p.*, c.name AS category FROM products p LEFT JOIN categories c ON c.category_id = p.category_id WHERE p.name = ? AND p.store_id = ? LIMIT 1");
        $stmt->bind_param("si", $name, $store_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function getOneValue($id, $column){
        $columnName = ['price', 'reasonable_price', 'failed_price', 'type', 'name', 'store_id'];
        if (!in_array($column, $columnName)) {
            return ''; 
        }
        $stmt = $this->koneksi->prepare("SELECT `{$column}` FROM orders WHERE order_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result[$column] : '';
    }

    public function deleteProductById($data){
        $stmt = $this->koneksi->prepare("DELETE FROM products WHERE product_id = ? LIMIT 1");
        $stmt->bind_param('i', $data->id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function deleteFinishingById($data){
        $stmt = $this->koneksi->prepare("DELETE FROM finishings WHERE finishing_id = ? LIMIT 1");
        $stmt->bind_param('i', $data->id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getMaterialUsageByIntervalDate($store_id, $start_date, $end_date){
        $stmt = $this->koneksi->prepare("
            SELECT 
                p.product_id, 
                p.name AS nama_barang, 
                p.unit_type AS satuan, 
                COALESCE(
                    SUM(
                        CASE 
                            WHEN p.unit_type = 'M2' AND oi.size LIKE '%x%' 
                            THEN oi.quantity * CAST(SUBSTRING_INDEX(oi.size, 'x', 1) AS DECIMAL(10,4)) * CAST(SUBSTRING_INDEX(oi.size, 'x', -1) AS DECIMAL(10,4)) 
                            WHEN p.unit_type = 'M2' 
                            THEN oi.quantity 
                            ELSE oi.quantity 
                        END
                    ), 0
                ) AS total_pemakaian 
            FROM products p 
            INNER JOIN order_items oi ON oi.product_id = p.product_id AND oi.store_id = ? 
            INNER JOIN orders o ON o.order_id = oi.order_id AND o.store_id = ? 
            WHERE 
                p.store_id = ? 
                AND NOT p.unit_type = '~' 
                AND DATE(o.date) BETWEEN ? AND ?
            GROUP BY p.product_id 
            ORDER BY p.type DESC

        ");
        $stmt->bind_param("issss", $store_id, $store_id, $store_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result ?? [];
    }

    public function getProductByPlaceholders($placeholders, $ids){
        $stmt = $this->koneksi->prepare("SELECT product_id, name FROM products WHERE product_id IN ($placeholders)");
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result ?? [];
    }

    public function updateStock($id, $quantity) {
        $stmt = $this->koneksi->prepare("UPDATE products SET stock = ? WHERE product_id = ?");
        $stmt->bind_param("di", $quantity, $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateStockFinishing($id, $quantity) {
        $stmt = $this->koneksi->prepare("UPDATE finishings SET stock = ? WHERE finishing_id = ?");
        $stmt->bind_param("di", $quantity, $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getStockByProductId($product_id) {
        $stmt = $this->koneksi->prepare("SELECT stock FROM products WHERE product_id = ? LIMIT 1");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? (float)$result['stock'] : 0;
    }

    public function getFinishingStockByProductId($finishing_id) {
        $stmt = $this->koneksi->prepare("SELECT stock FROM finishings WHERE finishing_id = ? LIMIT 1");
        $stmt->bind_param("i", $finishing_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? (float)$result['stock'] : 0;
    }

    public function reduceStock($quantity, $product_id) {
        $stmt = $this->koneksi->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ?");
        $stmt->bind_param("di", $quantity, $product_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function reduceFinishingStock($quantity, $finishing_id) {
        $stmt = $this->koneksi->prepare("UPDATE finishings SET stock = stock - ? WHERE finishing_id = ?");
        $stmt->bind_param("di", $quantity, $finishing_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function addStock($quantity, $product_id) {
        $stmt = $this->koneksi->prepare("UPDATE products SET stock = stock + ? WHERE product_id = ?");
        $stmt->bind_param("di", $quantity, $product_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function addFinishingStock($quantity, $finishing_id) {
        $stmt = $this->koneksi->prepare("UPDATE finishings SET stock = stock + ? WHERE finishing_id = ?");
        $stmt->bind_param("di", $quantity, $finishing_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function countProducts($store_id, $search){
        $search_param = "%" . $search . "%";

        $stmt = $this->koneksi->prepare("SELECT COUNT(*) as total FROM products WHERE store_id = ? AND name LIKE ?");

        $stmt->bind_param("is", $store_id, $search_param);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result['total'] ?? 0;
    }

    public function getProductByPagination($store_id, $page, $search, $limit){
        $offset = ($page - 1) * $limit;
        $search_param = "%" . $search . "%";
        $stmt = $this->koneksi->prepare("
            SELECT p.*, c.name AS category 
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE p.store_id = ? AND p.name LIKE ? 
            ORDER BY p.product_id DESC 
            LIMIT ? OFFSET ?
        ");
        
        $stmt->bind_param("isii", $store_id, $search_param, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $result ?? [];
    }



    
}
?>