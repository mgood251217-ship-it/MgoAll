<?php
class AnalysisController {
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

    public function piutang($access) {
        $store_param = $_GET['store_id'] ?? '';
        $store_id = $store_param ? (int)startEnk('dek', $store_param) : 0;
        
        if ($store_id === 0 && is_numeric($store_param)) {
            $store_id = (int)$store_param;
        }

        $total_hutang = 0;

        if ($access == 'ALL') {
            $query = "
                SELECT 
                    o.order_id,
                    o.store_id,
                    o.customer_name AS nama,
                    o.nomorator,
                    o.nomor,
                    o.total,
                    o.user_id,
                    o.date,
                    IFNULL(u.initial, '') AS op_initial,
                    CASE 
                    WHEN ps.lunas = 1 THEN 0
                    ELSE o.total - IFNULL(ps.total_dp, 0)
                    END AS hutang
                FROM orders o
                LEFT JOIN (
                    SELECT 
                        order_id,
                        MAX(CASE WHEN status = 'LUNAS' THEN 1 ELSE 0 END) AS lunas,
                        SUM(CASE WHEN status = 'DP' THEN nominal ELSE 0 END) AS total_dp
                    FROM payment
                    GROUP BY order_id
                ) ps ON o.order_id = ps.order_id
                LEFT JOIN users u ON o.user_id = u.user_id
                WHERE 1=1
            ";
        } else {
            $query = "
                SELECT 
                    o.order_id,
                    o.store_id,
                    o.customer_name AS nama,
                    o.nomorator,
                    o.nomor,
                    o.total,
                    o.user_id,
                    o.date,
                    IFNULL(u.initial, '') AS op_initial,
                    CASE 
                    WHEN ps.lunas = 1 THEN 0
                    ELSE o.total - IFNULL(ps.total_dp, 0)
                    END AS hutang
                FROM orders o
                LEFT JOIN (
                    SELECT 
                        order_id,
                        MAX(CASE WHEN status = 'LUNAS' THEN 1 ELSE 0 END) AS lunas,
                        SUM(CASE WHEN status = 'DP' THEN nominal ELSE 0 END) AS total_dp
                    FROM payment
                    GROUP BY order_id
                ) ps ON o.order_id = ps.order_id
                LEFT JOIN users u ON o.user_id = u.user_id
                JOIN stores s ON o.store_id = s.store_id
                WHERE s.administrator = ?
            ";
        }

        if ($store_id > 0) {
            $query .= " AND o.store_id = ?";
        }

        $query .= " HAVING hutang > 0 ORDER BY o.order_id DESC, o.nomor DESC";

        $stmt = $this->koneksi->prepare($query);

        if ($access == 'ALL') {
            if ($store_id > 0) {
                $stmt->bind_param("i", $store_id);
            }
        } else {
            if ($store_id > 0) {
                $stmt->bind_param("si", $access, $store_id);
            } else {
                $stmt->bind_param("s", $access);
            }
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $dataPiutang = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($dataPiutang as $row) {
            $total_hutang += $row['hutang'];
        }

        return [
            'data' => $dataPiutang,
            'total' => $total_hutang
        ];
    }
}