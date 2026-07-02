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

    public function getFinanceByIntervalDate($store_id, $start_date, $end_date){
        $stmt = $this->koneksi->prepare("SELECT * FROM finance WHERE store_id = ? AND date BETWEEN ? AND ? ORDER BY date ASC");
        $stmt->bind_param("iss", $store_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function getExpenditureByIntervalDate($store_id, $start_date, $end_date){
        $stmt = $this->koneksi->prepare("SELECT * FROM expenditures WHERE store_id = ? AND date BETWEEN ? AND ? ORDER BY date ASC");
        $stmt->bind_param("iss", $store_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function getIncomeByIntervalDate($store_id, $start_date, $end_date){
        $stmt = $this->koneksi->prepare("SELECT * FROM income WHERE store_id = ? AND date BETWEEN ? AND ? ORDER BY date ASC");
        $stmt->bind_param("iss", $store_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }
    
    public function createExpenditure($data){
        $stmt = $this->koneksi->prepare("INSERT INTO expenditures (store_id, information, nominal, img, date) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isiss", $data->store_id, $data->information, $data->nominal, $data->img, $data->date);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function createIncome($data){
        $stmt = $this->koneksi->prepare("INSERT INTO income (store_id, information, nominal, date) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isis", $data->store_id, $data->information, $data->nominal, $data->date);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateExpenditure($data){
        $stmt = $this->koneksi->prepare("UPDATE expenditures SET nominal = ?, information = ? WHERE expenditure_id =?");
        $stmt->bind_param("iss", $data->nominal, $data->information, $data->expenditure_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateIncome($data){
        $stmt = $this->koneksi->prepare("UPDATE income SET nominal = ?, information = ? WHERE income_id =?");
        $stmt->bind_param("iss", $data->nominal, $data->information, $data->income_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function deleteExpenditure($id, $store_id){
        $stmt = $this->koneksi->prepare("DELETE FROM expenditures WHERE expenditure_id = ? AND store_id = ?");
        $stmt->bind_param("ii", $id, $store_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function deleteIncome($id, $store_id){
        $stmt = $this->koneksi->prepare("DELETE FROM income WHERE income_id = ? AND store_id = ?");
        $stmt->bind_param("ii", $id, $store_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getExpenditureById($id){
        $stmt = $this->koneksi->prepare("SELECT * FROM expenditures WHERE expenditure_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

}

?>