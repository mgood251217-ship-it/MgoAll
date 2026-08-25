<?php
class ProductionController {
    private $koneksi;

    public function __construct($db) {
        $this->koneksi = $db;
    }

    public function getIndexData($access) {
        if ($access == 'ALL') {
            $storesResult = $this->koneksi->query("SELECT store_id, name FROM stores ORDER BY name ASC");
        } else {
            $stmt = $this->koneksi->prepare("SELECT store_id, name FROM stores WHERE administrator = ? ORDER BY name ASC");
            $stmt->bind_param("s", $access);
            $stmt->execute();
            $storesResult = $stmt->get_result();
        }

        $stores = [];
        $allowedStoreIds = [];
        if ($storesResult) {
            while ($row = $storesResult->fetch_assoc()) {
                $stores[] = $row;
                $allowedStoreIds[] = $row['store_id'];
            }
        }

        $store_id = isset($_GET['store_id']) && $_GET['store_id'] !== '' ? (int)$_GET['store_id'] : null;
        $startDate = $_GET['start_date'] ?? date('Y-m-d');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');

        $rawProductions = [];

        if (!empty($allowedStoreIds)) {
            $query = "
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
                INNER JOIN order_items oi ON oi.product_id = p.product_id 
                INNER JOIN orders o ON oi.order_id = o.order_id 
                WHERE NOT p.unit_type = '~'
                AND DATE(o.date) BETWEEN ? AND ?
            ";

            $params = [$startDate, $endDate];
            $types = "ss";

            if ($store_id !== null && in_array($store_id, $allowedStoreIds)) {
                $query .= " AND p.store_id = ? AND oi.store_id = ? AND o.store_id = ?";
                $params = array_merge($params, [$store_id, $store_id, $store_id]);
                $types .= "iii";
            } elseif ($access !== 'ALL') {
                $placeholders = implode(',', array_fill(0, count($allowedStoreIds), '?'));
                $query .= " AND p.store_id IN ($placeholders) AND oi.store_id IN ($placeholders) AND o.store_id IN ($placeholders)";
                foreach (range(1, 3) as $iteration) {
                    foreach ($allowedStoreIds as $id) { 
                        $params[] = $id; 
                        $types .= "i"; 
                    }
                }
            }

            $query .= " GROUP BY p.name, p.unit_type ORDER BY total_omset DESC";

            $stmt = $this->koneksi->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $rawProductions[] = $row;
                }
            }
            $stmt->close();
        }

        $mergedProductions = [];
        foreach ($rawProductions as $item) {
            $key = strtolower(trim($item['nama_barang'])) . '_' . strtolower(trim($item['satuan']));
            if (isset($mergedProductions[$key])) {
                $mergedProductions[$key]['total_terjual'] += (float)$item['total_terjual'];
                $mergedProductions[$key]['total_omset'] += (float)$item['total_omset'];
            } else {
                $mergedProductions[$key] = [
                    'nama_barang' => $item['nama_barang'],
                    'satuan' => $item['satuan'],
                    'total_terjual' => (float)$item['total_terjual'],
                    'total_omset' => (float)$item['total_omset']
                ];
            }
        }

        usort($mergedProductions, function($a, $b) {
            return $b['total_omset'] <=> $a['total_omset'];
        });

        return [
            'stores' => $stores,
            'current_store_id' => $store_id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'productions' => array_values($mergedProductions)
        ];
    }
}