<?php

class Finance{
    private $koneksi;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
    }

    public function create_tf($data){
        $stmt = $this->koneksi->prepare("INSERT INTO transfers (order_id, store_id, img, date) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $data->order_id, $data->store_id, $data->pictureName, $data->date);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
    public function getTfById($id){
        $stmt = $this->koneksi->prepare("SELECT * FROM transfers WHERE transfer_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function deleteTf($id){
        $stmt = $this->koneksi->prepare("DELETE FROM transfers WHERE transfer_id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getOmsetItemByIntervalDate( $store_id, $start_date, $end_date){
        $stmt = $this->koneksi->prepare("
            SELECT 
                p.name AS nama_barang,
                p.unit_type AS satuan,
                COALESCE(
                    SUM(
                        CASE
                            WHEN p.unit_type = 'M2' AND oi.size LIKE '%x%' THEN 
                                oi.quantity * CAST(SUBSTRING_INDEX(oi.size, 'x', 1) AS DECIMAL(10,4)) * CAST(SUBSTRING_INDEX(oi.size, 'x', -1) AS DECIMAL(10,4))
                            WHEN p.unit_type = 'M2' THEN 
                                oi.quantity
                            ELSE 
                                oi.quantity
                        END
                    ), 0
                ) AS total_terjual,
                COALESCE(SUM(oi.amount), 0) AS total_omset
            FROM products p
            LEFT JOIN order_items oi ON oi.product_id = p.product_id AND oi.store_id = ?
            LEFT JOIN orders o ON oi.order_id = o.order_id
            WHERE p.store_id = ?
            AND NOT p.unit_type = '~'
            AND (o.date BETWEEN ? AND ?)
            GROUP BY p.product_id
            ORDER BY total_omset DESC
        ");
        $stmt->bind_param("iiss", $store_id, $store_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }
}

?>