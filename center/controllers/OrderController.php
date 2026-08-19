<?php
class OrderController {
    private $koneksi;

    public function __construct($db) {
        $this->koneksi = $db;
    }

    public function getIndexData($access, $startDateF = null, $endDateF = null) {
        $startDateF = $startDateF ?: date('Y-m-d');
        $endDateF = $endDateF ?: date('Y-m-d');

        $startDate = $startDateF . ' 00:00:00';
        $endDate = $endDateF . ' 23:59:59';

        $sql = "
            SELECT 
                s.store_id, 
                s.name, 
                s.branch,
                COALESCE(os.jumlahPesanan, 0) AS jumlahPesanan,
                COALESCE(os.totalNominal, 0) AS totalNominal,
                COALESCE(ps.pendapatan, 0) AS pendapatan,
                COALESCE(os.belumBayar, 0) AS belumBayar,
                COALESCE(os.dp, 0) AS dp,
                COALESCE(os.lunas, 0) AS lunas
            FROM stores s
            LEFT JOIN (
                SELECT 
                    o.store_id,
                    COUNT(o.order_id) AS jumlahPesanan,
                    SUM(o.total) AS totalNominal,
                    COUNT(CASE WHEN p_agg.total_bayar IS NULL OR p_agg.total_bayar = 0 THEN 1 END) AS belumBayar,
                    COUNT(CASE WHEN p_agg.total_bayar > 0 AND p_agg.total_bayar < o.total THEN 1 END) AS dp,
                    COUNT(CASE WHEN p_agg.total_bayar >= o.total THEN 1 END) AS lunas
                FROM orders o
                LEFT JOIN (
                    SELECT order_id, SUM(nominal) AS total_bayar 
                    FROM payment 
                    GROUP BY order_id
                ) p_agg ON o.order_id = p_agg.order_id
                WHERE o.date BETWEEN ? AND ?
                GROUP BY o.store_id
            ) os ON s.store_id = os.store_id
            LEFT JOIN (
                SELECT 
                    o.store_id,
                    SUM(p.nominal) AS pendapatan
                FROM payment p
                JOIN orders o ON p.order_id = o.order_id
                WHERE p.date BETWEEN ? AND ?
                GROUP BY o.store_id
            ) ps ON s.store_id = ps.store_id
        ";

        if ($access !== 'ALL') {
            $sql .= " WHERE s.administrator = ?";
            $stmt = $this->koneksi->prepare($sql);
            $stmt->bind_param("sssss", $startDate, $endDate, $startDate, $endDate, $access);
        } else {
            $stmt = $this->koneksi->prepare($sql);
            $stmt->bind_param("ssss", $startDate, $endDate, $startDate, $endDate);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $storesData = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $storesData[] = [
                    'name' => $row['name'],
                    'branch' => $row['branch'],
                    'jumlahPesanan' => (int)$row['jumlahPesanan'],
                    'belumBayar' => (int)$row['belumBayar'],
                    'dp' => (int)$row['dp'],
                    'lunas' => (int)$row['lunas'],
                    'totalNominal' => (float)$row['totalNominal'],
                    'pendapatan' => (float)$row['pendapatan']
                ];
            }
        }

        return [
            'startDate' => $startDateF,
            'endDate' => $endDateF,
            'storesData' => $storesData
        ];
    }
}