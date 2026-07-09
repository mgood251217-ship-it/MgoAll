<?php
require_once BASE_PATH . '/models/Order.php';
require_once BASE_PATH . '/models/User.php';
require_once BASE_PATH . '/models/Project.php';
require_once BASE_PATH . '/models/Product.php';

require_once BASE_PATH . '/models/Activity.php';
require_once BASE_PATH . '/models/Payment.php';
require_once BASE_PATH . '/functions/helpers.php';

class ReportController {
    private $koneksi;
    private $orderModel;
    private $userModel;
    private $projectModel;
    private $productModel;
    private $paymentModel;
    private $activityModel;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
        $this->orderModel = new Order($koneksi);
        $this->userModel = new User($koneksi);
        $this->projectModel = new Project($koneksi);
        $this->productModel = new Product($koneksi);
        $this->paymentModel = new Payment($koneksi);
        $this->activityModel = new Activity($koneksi);
    }

    public function allDetailOrderByIntervalDate(){
        global $store_id;
        $start_date = ($_GET['start_date'] ?? date('Y-m-d')). ' 00:00:00';
        $end_date = ($_GET['end_date'] ?? date('Y-m-d')). ' 23:59:59';
        $items = $this->orderModel->getDetailedOrderByIntervalDate($store_id, $start_date, $end_date);

        $all_finishing_ids = [];
        foreach ($items as $item) {
            if (!empty($item['finishing'])) {
                foreach (explode(',', $item['finishing']) as $id) {
                    $all_finishing_ids[$id] = true;
                }
            }
        }

        $finishing_map = [];
        if (!empty($all_finishing_ids)) {
            $ids = array_keys($all_finishing_ids);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $finishing_data = $this->productModel->getProductByPlaceholders($placeholders, $ids);
            
            foreach ($finishing_data as $row) {
                $finishing_map[$row['product_id']] = $row['name'];
            }
        }

        $produkData = [];
        foreach ($items as $item) {
            $finishing_names = [];
            if (!empty($item['finishing'])) {
                foreach (explode(',', $item['finishing']) as $fid) {
                    if (isset($finishing_map[$fid])) {
                        $finishing_names[] = $finishing_map[$fid];
                    }
                }
            }
            $item['finishing_names'] = implode(', ', $finishing_names);
            $produkData[$item['customer_name']][] = $item;
        }

        return [
            'product' => $produkData
        ];
    }
    public function piutang(){
        global $store_id;
        $total_hutang = 0;

        $query = "
            SELECT 
                o.order_id,
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
            WHERE o.store_id = ?
            HAVING hutang > 0
            ORDER BY o.order_id DESC, o.nomor DESC
        ";

        $stmt = $this->koneksi->prepare($query);
        $stmt->bind_param("i", $store_id);
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
    public function transactionsCapture() {
        global $store_id;
        global $start_date;
        global $end_date;

        $sqlTransaksi = "
            SELECT 
                p.order_id,
                o.nomorator, 
                o.customer_name, 
                o.system,
                p.nominal, 
                p.payment_method, 
                p.status,
                p.date AS payment_date,
                o.date AS order_date
            FROM payment p
            JOIN orders o ON p.order_id = o.order_id
            WHERE o.store_id = ? AND p.date BETWEEN ? AND ?
            ORDER BY o.system ASC, p.date ASC
        ";

        $stmt = $this->koneksi->prepare($sqlTransaksi);
        $stmt->bind_param("iss", $store_id, $start_date, $end_date);
        $stmt->execute();
        $rawPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($rawPayments)) {
            return [
                'harian'    => ['data' => [], 'total_tf' => 0, 'total_cash' => 0, 'grand_total' => 0],
                'pelunasan' => ['data' => [], 'total_tf' => 0, 'total_cash' => 0, 'grand_total' => 0],
                'rekap'     => ['data_per_tanggal' => [], 'total_bulan' => 0, 'total_bulan_tf' => 0, 'total_bulan_cash' => 0, 'total_transaksi_all' => 0]
            ];
        }

        $orderIds     = array_unique(array_column($rawPayments, 'order_id'));
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $types        = str_repeat('i', count($orderIds));

        $sqlDpHistory = "
            WITH ranked_payments AS (
                SELECT 
                    order_id, nominal, payment_method, date,
                    ROW_NUMBER() OVER (PARTITION BY order_id ORDER BY date ASC) as rn,
                    COUNT(*) OVER (PARTITION BY order_id) as total_frekuensi_bayar
                FROM payment
                WHERE order_id IN ($placeholders)
            )
            SELECT 
                order_id, 
                nominal AS dp_nominal, 
                payment_method AS dp_method, 
                date AS dp_date, 
                total_frekuensi_bayar
            FROM ranked_payments
            WHERE rn = 1
        ";

        $stmtDp = $this->koneksi->prepare($sqlDpHistory);
        $stmtDp->bind_param($types, ...$orderIds);
        $stmtDp->execute();
        $dpResult = $stmtDp->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtDp->close();

        $dpData = [];
        foreach ($dpResult as $d) {
            $dpData[$d['order_id']] = $d;
        }

        $dataHarian     = [];
        $harian_tf      = 0; 
        $harian_cash    = 0; 
        $harian_total   = 0;

        $dataPelunasan  = [];
        $pelunasan_tf   = 0;
        $pelunasan_cash = 0;

        $rekapPerTanggal = [];
        $uniqueOrdersPerDay = [];
        $total_rekap_tf = 0;
        $total_rekap_cash = 0;
        $total_rekap_nominal = 0;
        $total_rekap_transaksi = 0;

        foreach ($rawPayments as $row) {
            $oid    = $row['order_id'];
            $status = strtoupper($row['status']);
            $pCount = isset($dpData[$oid]) ? $dpData[$oid]['total_frekuensi_bayar'] : 1;

            $tanggal_bayar = date('Y-m-d', strtotime($row['payment_date']));
            $tanggal_order = date('Y-m-d', strtotime($row['order_date']));
            $nominal = (float)$row['nominal'];
            $method = strtoupper(trim($row['payment_method']));

            if (!isset($rekapPerTanggal[$tanggal_bayar])) {
                $rekapPerTanggal[$tanggal_bayar] = [
                    'tanggal' => $tanggal_bayar,
                    'total_nominal' => 0,
                    'jumlah_order' => 0,
                    'jumlah_transaksi' => 0,
                    'CASH' => 0,
                    'TF' => 0
                ];
            }

            $rekapPerTanggal[$tanggal_bayar]['total_nominal'] += $nominal;
            $rekapPerTanggal[$tanggal_bayar]['jumlah_transaksi'] += 1;
            $uniqueOrdersPerDay[$tanggal_bayar][$oid] = true;

            if (in_array($method, ['TF', 'TRANSFER'])) {
                $rekapPerTanggal[$tanggal_bayar]['TF'] += $nominal;
                $total_rekap_tf += $nominal;
            } else {
                $rekapPerTanggal[$tanggal_bayar]['CASH'] += $nominal;
                $total_rekap_cash += $nominal;
            }
            $total_rekap_nominal += $nominal;
            $total_rekap_transaksi += 1;

            if ($status === 'LUNAS' && $pCount > 1) {
                $statusLabel = 'PELUNASAN';
            } elseif ($status === 'DP') {
                $statusLabel = 'BAYAR DP';
            } elseif ($tanggal_bayar > $tanggal_order) {
                $statusLabel = 'PELUNASAN';
            } else {
                $statusLabel = 'LUNAS';
            }

            $row['status_label'] = $statusLabel;

            if ($method === 'TF' || $method === 'TRANSFER') { 
                $harian_tf += $nominal; 
            } else { 
                $harian_cash += $nominal; 
            }
            
            $harian_total += $nominal;
            $dataHarian[]  = $row;

            if ($statusLabel === 'PELUNASAN') {
                $dp = $dpData[$oid] ?? null;

                $punyaDp = ($dp && $pCount > 1);

                $row['dp_nominal'] = $punyaDp ? $dp['dp_nominal'] : 0;
                $row['dp_method']  = $punyaDp ? $dp['dp_method']  : '-';
                $row['dp_date']    = $punyaDp ? $dp['dp_date']    : '-';

                if ($method === 'TF' || $method === 'TRANSFER') { 
                    $pelunasan_tf += $nominal; 
                } else { 
                    $pelunasan_cash += $nominal; 
                }

                $dataPelunasan[] = $row;
            }
        }

        $rekapValues = [];
        foreach ($rekapPerTanggal as $tgl => $data) {
            $data['jumlah_order'] = count($uniqueOrdersPerDay[$tgl]);
            $rekapValues[] = $data;
        }

        return [
            'harian' => [
                'data'        => $dataHarian,
                'total_tf'    => $harian_tf,
                'total_cash'  => $harian_cash,
                'grand_total' => $harian_total
            ],
            'pelunasan' => [
                'data'        => $dataPelunasan,
                'total_tf'    => $pelunasan_tf,
                'total_cash'  => $pelunasan_cash,
                'grand_total' => ($pelunasan_tf + $pelunasan_cash)
            ],
            'rekap' => [
                'data_per_tanggal'    => $rekapValues,
                'total_bulan'         => $total_rekap_nominal,
                'total_bulan_tf'      => $total_rekap_tf,
                'total_bulan_cash'    => $total_rekap_cash,
                'total_transaksi_all' => $total_rekap_transaksi
            ]
        ];
    }

}