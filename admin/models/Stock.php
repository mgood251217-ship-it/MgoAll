<?php
// Stock.php
class Stock{
    private $koneksi;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
    }

    public function getAllStock ($store_id) {
        $stmt = $this->koneksi->prepare("
            SELECT 
                p.product_id, 
                p.type, 
                p.name, 
                p.unit_type, 
                s.stock_id, 
                COALESCE(s.quantity, 0) AS quantity
            FROM products p
            LEFT JOIN stock s ON p.product_id = s.product_id AND s.store_id = ?
            WHERE p.store_id = ?
            AND name != 'KISS CUT'
            AND name != 'DIE CUT'
            AND unit_type != '~'
        ");
        $stmt->bind_param("ii", $store_id, $store_id);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function createStock($data){
        $stmt = $this->koneksi->prepare("INSERT INTO stock (product_id, store_id, quantity) VALUES (?, ?, ?)");
        $stmt->bind_param("iid", $data->id, $data->store_id, $data->quantity);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function createUpdateStock($data) {
        $stmt = $this->koneksi->prepare("UPDATE stock SET quantity = quantity + ? WHERE product_id = ? AND store_id = ?");
        $stmt->bind_param("dii", $data->quantity, $data->id, $data->store_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateStock($data) {
        $stmt = $this->koneksi->prepare("UPDATE stock SET quantity = ? WHERE store_id = ? AND product_id = ?");
        $stmt->bind_param("dii", $data->quantity, $data->store_id, $data->id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function checkStock ($data){
        $stmt = $this->koneksi->prepare("SELECT 1 FROM stock WHERE product_id = ? AND store_id = ?");
        $stmt->bind_param("ii", $data->id, $data->store_id);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }
    
}
?>