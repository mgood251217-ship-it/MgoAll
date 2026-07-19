<?php
require_once BASE_PATH . '/models/Order.php';
require_once BASE_PATH . '/models/User.php';
require_once BASE_PATH . '/models/Project.php';
require_once BASE_PATH . '/models/Product.php';

require_once BASE_PATH . '/models/Activity.php';
require_once BASE_PATH . '/models/Finance.php';
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
    private $financeModel;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
        $this->orderModel = new Order($koneksi);
        $this->userModel = new User($koneksi);
        $this->projectModel = new Project($koneksi);
        $this->productModel = new Product($koneksi);
        $this->paymentModel = new Payment($koneksi);
        $this->activityModel = new Activity($koneksi);
        $this->financeModel = new Finance($koneksi);
    }
    
    public function index(){
        global $store_id;

        $startMonth  = date('Y-m-01 00:00:00');
        $endMonth    = date('Y-m-t 23:59:59');
        $today       = date('Y-m-d');

        $cashTotal = $tfTotal = $jumlahPaymentHarian = $jumlahPaymentBulanan = 0;
        $pendapatanHarian = $pendapatanBulanan = $total_qty_all_products = 0;
        $max_qty = $totalOmsetSemuaProduk = $topSalesOmset = 0;
        $jumlah_pelanggan_belum_bayar = $total_hutang = $omset_offline = $omset_online = 0;

        $top_product_name = $topSalesName = $topUserName = $topKonsumenName = '-';

        $digunakan_short = [];
        $tidak_short = [];

        $stmt = $this->koneksi->prepare("
            SELECT 
                SUM(CASE WHEN DATE(p.date) = ? THEN 1 ELSE 0 END) AS jml_harian,
                SUM(CASE WHEN DATE(p.date) = ? THEN p.nominal ELSE 0 END) AS nom_harian,
                COUNT(p.payment_id) AS jml_bulanan,
                SUM(p.nominal) AS nom_bulanan,
                SUM(CASE WHEN UPPER(p.payment_method) = 'CASH' THEN p.nominal ELSE 0 END) AS cash_total,
                SUM(CASE WHEN UPPER(p.payment_method) IN ('TF', 'TRANSFER') THEN p.nominal ELSE 0 END) AS tf_total
            FROM payment p
            JOIN orders o ON p.order_id = o.order_id
            WHERE o.store_id = ? AND p.date BETWEEN ? AND ?
        ");
        $stmt->bind_param("ssiss", $today, $today, $store_id, $startMonth, $endMonth);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?? [];
        $stmt->close();

        $jumlahPaymentHarian = (int)($row['jml_harian'] ?? 0);
        $pendapatanHarian = (int)($row['nom_harian'] ?? 0);
        $jumlahPaymentBulanan = (int)($row['jml_bulanan'] ?? 0);
        $pendapatanBulanan = (int)($row['nom_bulanan'] ?? 0);
        $cashTotal = (int)($row['cash_total'] ?? 0);
        $tfTotal = (int)($row['tf_total'] ?? 0);

        $product_ids = [];
        $stmt = $this->koneksi->prepare("
            SELECT 
                p.product_id,
                p.name, 
                SUM(oi.quantity) AS total_qty,
                SUM(CASE WHEN p.unit_type <> '~' THEN oi.amount ELSE 0 END) AS total_omset
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            JOIN products p ON p.product_id = oi.product_id
            WHERE o.store_id = ? AND o.date BETWEEN ? AND ?
            GROUP BY p.product_id, p.name
        ");
        $stmt->bind_param("iss", $store_id, $startMonth, $endMonth);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($rows as $row) {
            $pid = (int)$row['product_id'];
            $prod_name = $row['name'];
            $qty = (int)$row['total_qty'];
            $omset = (int)$row['total_omset'];

            $product_ids[] = $pid;

            if (count($digunakan_short) < 3) {
                $digunakan_short[] = $prod_name;
            }

            $total_qty_all_products += $qty;

            if ($qty > $max_qty) {
                $max_qty = $qty;
                $top_product_name = $prod_name;
            }

            $totalOmsetSemuaProduk += $omset;

            if ($omset > $topSalesOmset) {
                $topSalesOmset = $omset;
                $topSalesName = $prod_name;
            }
        }

        $not_in = !empty($product_ids) ? implode(',', array_map('intval', $product_ids)) : '0';
        $stmt = $this->koneksi->prepare("SELECT name FROM products WHERE store_id = ? AND product_id NOT IN ($not_in) LIMIT 3");
        $stmt->bind_param("i", $store_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($rows as $row) {
            $tidak_short[] = $row['name'];
        }

        $stmt = $this->koneksi->prepare("
            SELECT 
                COUNT(CASE WHEN IFNULL(p.lunas, 0) = 0 AND o.total > IFNULL(p.total_dp, 0) THEN 1 END),
                SUM(CASE WHEN IFNULL(p.lunas, 0) = 0 AND o.total > IFNULL(p.total_dp, 0) THEN (o.total - IFNULL(p.total_dp, 0)) ELSE 0 END)
            FROM orders o
            LEFT JOIN (
                SELECT order_id, 
                    SUM(CASE WHEN status='DP' THEN nominal ELSE 0 END) AS total_dp,
                    MAX(CASE WHEN status='LUNAS' THEN 1 ELSE 0 END) AS lunas
                FROM payment GROUP BY order_id
            ) p ON p.order_id = o.order_id
            WHERE o.store_id = ?
        ");
        $stmt->bind_param("i", $store_id);
        $stmt->execute();
        $stmt->bind_result($jumlah_pelanggan_belum_bayar, $total_hutang);
        $stmt->fetch();
        $stmt->close();

        $jumlah_pelanggan_belum_bayar = (int)$jumlah_pelanggan_belum_bayar;
        $total_hutang = (int)$total_hutang;

        $stmt = $this->koneksi->prepare("SELECT omset_offline, omset_online FROM finance WHERE store_id=? ORDER BY date DESC LIMIT 1");
        $stmt->bind_param("i", $store_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?? [];
        $stmt->close();

        $omset_offline = (int)($row['omset_offline'] ?? 0);
        $omset_online = (int)($row['omset_online'] ?? 0);

        $stmt = $this->koneksi->prepare("
            SELECT u.name FROM projects p
            JOIN users u ON p.user_id = u.user_id
            WHERE u.store_id=? AND p.process='DIAMBIL' AND p.date BETWEEN ? AND ?
            GROUP BY p.user_id
            ORDER BY COUNT(*) DESC LIMIT 1
        ");
        $stmt->bind_param("iss", $store_id, $startMonth, $endMonth);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?? [];
        $stmt->close();

        $topUserName = $row['name'] ?? '-';

        $stmt = $this->koneksi->prepare("
            SELECT u.name FROM orders o
            JOIN users u ON o.user_id=u.user_id
            WHERE u.store_id=? AND o.date BETWEEN ? AND ?
            GROUP BY o.user_id
            ORDER BY COUNT(*) DESC LIMIT 1
        ");
        $stmt->bind_param("iss", $store_id, $startMonth, $endMonth);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?? [];
        $stmt->close();

        $topKonsumenName = $row['name'] ?? '-';

        return [
            'cashTotal' => $cashTotal,
            'tfTotal' => $tfTotal,
            'jumlahPembayaranHarian' => $jumlahPaymentHarian,
            'jumlahPembayaranBulanan' => $jumlahPaymentBulanan,
            'omsetHarian' => $pendapatanHarian,
            'omsetBulanan' => $pendapatanBulanan,
            'productSold' => $total_qty_all_products,
            'piutang' => $jumlah_pelanggan_belum_bayar,
            'totalHutang' => $total_hutang,
            'omsetOffline' => $omset_offline,
            'omsetOnline' => $omset_online,
            'topProductName' => $top_product_name,
            'topProductQty' => $max_qty,
            'topSalesName' => $topSalesName,
            'topSalesOmset' => $topSalesOmset,
            'topUserName' => $topUserName,
            'topCustomerName' => $topKonsumenName,
            'usedItem' => $digunakan_short,
            'unusedItem' => $tidak_short
        ];
    }

    public function allDetailOrderByIntervalDate(){
        global $store_id;
        $start_date = ($_GET['start_date'] ?? date('Y-m-d')). ' 00:00:00';
        $end_date = ($_GET['end_date'] ?? date('Y-m-d')). ' 23:59:59';
        $items = $this->orderModel->getDetailedOrderByIntervalDate($store_id, $start_date, $end_date);

        $transaksi_konsumen = [];
        $transaksi_item = [];

        foreach ($items as $item) {
            $customer = !empty($item['customer_name']) ? $item['customer_name'] : 'Tanpa Nama';
            $transaksi_konsumen[$customer][] = $item;

            $nama_item = !empty($item['judul']) ? $item['judul'] : 'Item Tidak Diketahui';
            $transaksi_item[$nama_item][] = $item;
        }

        return [
            'transaksi_konsumen' => $transaksi_konsumen,
            'transaksi_item' => $transaksi_item
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
        $start_date = ($_GET['start_date'] ?? date('Y-m-d')). ' 00:00:00';
        $end_date = ($_GET['end_date'] ?? date('Y-m-d')). ' 23:59:59';

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

    public function orderAnalysis(){
        global $store_id;

        $data_tanggal = [];
        $data_jumlah = [];
        $data_total = [];
        $data_bulan_365 = [];
        $data_jumlah_365 = [];
        $data_total_365 = [];

        $stmtChart30 = $this->koneksi->prepare(
            "SELECT
                DATE(o.date) AS tanggal,
                COUNT(DISTINCT o.order_id) AS jumlah_order,
                COALESCE(SUM(p.nominal),0) AS total_order
            FROM orders o
            LEFT JOIN payment p ON p.order_id = o.order_id
            WHERE o.store_id = ?
                AND o.date >= CURDATE() - INTERVAL 30 DAY
            GROUP BY tanggal
            ORDER BY tanggal ASC"
        );
        $stmtChart30->bind_param('i', $store_id);
        $stmtChart30->execute();
        $rows30 = $stmtChart30->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtChart30->close();

        foreach ($rows30 as $row) {
            $data_tanggal[] = $row['tanggal'];
            $data_jumlah[] = (int)$row['jumlah_order'];
            $data_total[] = (int)$row['total_order'];
        }

        $stmt365 = $this->koneksi->prepare(
            "SELECT
                DATE_FORMAT(p.date, '%Y-%m') AS bulan,
                COUNT(DISTINCT o.order_id) AS jumlah_order,
                SUM(p.nominal) AS total_order
            FROM orders o
            JOIN payment p ON o.order_id = p.order_id
            WHERE o.store_id = ?
                AND p.date >= CURDATE() - INTERVAL 1 YEAR
            GROUP BY bulan
            ORDER BY bulan ASC"
        );
        $stmt365->bind_param('i', $store_id);
        $stmt365->execute();
        $rows365 = $stmt365->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt365->close();

        foreach ($rows365 as $row) {
            $data_bulan_365[] = $row['bulan'];
            $data_jumlah_365[] = (int)$row['jumlah_order'];
            $data_total_365[] = (int)$row['total_order'];
        }

        $stmtSummary = $this->koneksi->prepare(
            "SELECT
                SUM(CASE WHEN p.date >= CURDATE() - INTERVAL 30 DAY THEN p.nominal ELSE 0 END ) AS total30,
                SUM(CASE WHEN DATE(p.date) = CURDATE() THEN p.nominal ELSE 0 END ) AS total_today
            FROM payment p
            JOIN orders o ON o.order_id = p.order_id
            WHERE o.store_id = ?"
        );
        $stmtSummary->bind_param('i', $store_id);
        $stmtSummary->execute();
        $summaryRow = $stmtSummary->get_result()->fetch_assoc();
        $stmtSummary->close();

        $total30 = (int)($summaryRow['total30'] ?? 0);
        $total_today = (int)($summaryRow['total_today'] ?? 0);

        $stmtTop = $this->koneksi->prepare(
            "SELECT
                o.customer_name,
                SUM(p.nominal) AS total
            FROM orders o
            JOIN payment p ON o.order_id = p.order_id
            WHERE o.store_id = ?
                AND p.date >= CURDATE() - INTERVAL 30 DAY
            GROUP BY o.customer_name
            ORDER BY total DESC
            LIMIT 1"
        );
        $stmtTop->bind_param('i', $store_id);
        $stmtTop->execute();
        $topRow = $stmtTop->get_result()->fetch_assoc();
        $stmtTop->close();

        return [
            'chart_30' => [
                'tanggal' => $data_tanggal,
                'jumlah' => $data_jumlah,
                'total' => $data_total
            ],
            'chart_365' => [
                'bulan' => $data_bulan_365,
                'jumlah' => $data_jumlah_365,
                'total' => $data_total_365
            ],
            'summary' => [
                'total_30' => $total30,
                'total_today' => $total_today,
                'top_customer' => $topRow['customer_name'] ?? '-',
                'top_total' => (int)($topRow['total'] ?? 0)
            ]
        ];
    }

    public function transactionsDetail(){
        global $store_id;
        $start_date = ($_GET['start_date'] ?? date('Y-m-d')) . ' 00:00:00';
        $end_date = ($_GET['end_date'] ?? date('Y-m-d')) . ' 23:59:59';

        $stmt = $this->koneksi->prepare("SELECT o.order_id, o.nomorator, o.nomor, o.customer_name, o.date, o.total, o.system, u.name AS operator
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.user_id
                WHERE o.store_id = ? AND o.date BETWEEN ? AND ?
                ORDER BY o.system ASC, o.order_id DESC");
        $stmt->bind_param("iss", $store_id, $start_date, $end_date);
        $stmt->execute();
        $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $itemsByOrder = [];
        $paymentsByOrder = [];
        $transfersByOrder = [];
        $notesByOrder = [];

        $orderIds = array_column($orders, 'order_id');

        if (!empty($orderIds)) {

            $ids = implode(',', array_map('intval', $orderIds));

            $items = $this->koneksi->query("
                SELECT order_id, judul, finishing, size, quantity, unit, amount,
                    ( SELECT GROUP_CONCAT(fp.name SEPARATOR ', ')
                        FROM finishings fp
                        WHERE FIND_IN_SET(fp.finishing_id, REPLACE(order_items.finishing, ' ', ''))
                    ) AS finishing_names
                FROM order_items
                WHERE order_id IN ($ids)
            ")->fetch_all(MYSQLI_ASSOC);

            foreach ($items as $item) {
                $itemsByOrder[$item['order_id']][] = $item;
            }

            $payments = $this->koneksi->query(" SELECT * FROM payment WHERE order_id IN ($ids) ")->fetch_all(MYSQLI_ASSOC);

            foreach ($payments as $payment) {
                $paymentsByOrder[$payment['order_id']][] = $payment;
            }

            $transfers = $this->koneksi->query("SELECT order_id, transfer_id, img FROM transfers WHERE order_id IN ($ids) ")->fetch_all(MYSQLI_ASSOC);

            foreach ($transfers as $transfer) {
                $transfersByOrder[$transfer['order_id']][] = $transfer;
            }

            $notes = $this->koneksi->query(" SELECT order_id, note FROM note_orders WHERE note_for = 'OP' AND order_id IN ($ids) ")->fetch_all(MYSQLI_ASSOC);

            foreach ($notes as $note) {
                $notesByOrder[$note['order_id']] = $note['note'];
            }
        }

        return [
            'orders' => $orders,
            'itemsByOrder' => $itemsByOrder,
            'paymentsByOrder' => $paymentsByOrder,
            'transfersByOrder' => $transfersByOrder,
            'notesByOrder' => $notesByOrder
        ];

    }

    public function omsetItem(){
        global $store_id;
        $start_date = ($_GET['start_date'] ?? date('Y-m-d')). ' 00:00:00';
        $end_date = ($_GET['end_date'] ?? date('Y-m-d')). ' 23:59:59';

        $dataOmsetPerItem = $this->financeModel->getOmsetItemByIntervalDate($store_id, $start_date, $end_date);
        return $dataOmsetPerItem;
    }

    public function productUsed() {
        global $store_id;
        $start_date = ($_GET['start_date'] ?? date('Y-m-d')). ' 00:00:00';
        $end_date = ($_GET['end_date'] ?? date('Y-m-d')). ' 23:59:59';

        $dataPemakaian = $this->productModel->getMaterialUsageByIntervalDate($store_id, $start_date, $end_date);
        return $dataPemakaian;
    }

    public function activity() {
        global $store_id;
        $start_date = ($_GET['start_date'] ?? date('Y-m-d')). ' 00:00:00';
        $end_date = ($_GET['end_date'] ?? date('Y-m-d')). ' 23:59:59';

        $activity = $this->activityModel->getActivitiesByStoreId($store_id, $start_date, $end_date);
        return $activity;
    }

    public function statistics(){
        global $store_id;

        $start_date = ($_GET['start_date'] ?? date('Y-m-d')). ' 00:00:00';
        $end_date = ($_GET['end_date'] ?? date('Y-m-d')). ' 23:59:59';
        $users = $this->userModel->getUsersInitial($store_id);

        $receiverCounts = [];
        $pickupCounts   = [];
        $settingCounts  = [];
        $omsetPerUser   = [];

        $stmtOrders = $this->koneksi->prepare("
            SELECT o.user_id, COUNT(*) as total 
            FROM orders o
            WHERE o.store_id = ? AND DATE(o.date) BETWEEN ? AND ?
            GROUP BY o.user_id
        ");
        $stmtOrders->bind_param("iss", $store_id, $start_date, $end_date);
        $stmtOrders->execute();
        $resOrders = $stmtOrders->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($resOrders as $row) {
            if (isset($users[$row['user_id']])) {
                $receiverCounts[$row['user_id']] = (int)$row['total'];
            }
        }
        $stmtOrders->close();

        $stmtHitung = $this->koneksi->prepare("
            SELECT p.user_id, COUNT(*) as total 
            FROM projects p
            JOIN users u ON p.user_id = u.user_id
            WHERE u.store_id = ? AND DATE(p.date) BETWEEN ? AND ? AND p.process = 'DIAMBIL'
            GROUP BY p.user_id
        ");
        $stmtHitung->bind_param("iss", $store_id, $start_date, $end_date);
        $stmtHitung->execute();
        $resHitung = $stmtHitung->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($resHitung as $row) {
            if (isset($users[$row['user_id']])) {
                $pickupCounts[$row['user_id']] = (int)$row['total'];
            }
        }
        $stmtHitung->close();

        $stmtSetting = $this->koneksi->prepare("
            SELECT o.user_id, COUNT(DISTINCT o.order_id) AS total
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.order_id
            JOIN products p ON p.product_id = oi.product_id
            WHERE o.store_id = ?
            AND p.store_id = ?
            AND p.name = 'SETTING'
            AND o.date BETWEEN ? AND ?
            GROUP BY o.user_id
        ");
        $stmtSetting->bind_param( "iiss", $store_id, $store_id, $start_date, $end_date);

        $stmtSetting->execute();
        $resSetting = $stmtSetting->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtSetting->close();

        foreach ($resSetting as $row) {
            if (isset($users[$row['user_id']])) {
                $settingCounts[$row['user_id']] = (int)$row['total'];
            }
        }

        $stmt = $this->koneksi->prepare("
            SELECT p.nominal, p.order_id, o.order_id, o.user_id
            FROM payment p
            JOIN orders o
                ON FIND_IN_SET(o.order_id, p.order_id)
            WHERE p.store_id = ? AND o.store_id = ? AND p.date BETWEEN ? AND ?
        ");

        $stmt->bind_param("iiss", $store_id, $store_id, $start_date, $end_date);

        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($result as $row) {
            $jumlahOrder = substr_count($row['order_id'], ',') + 1;
            $nominal = $row['nominal'] / $jumlahOrder;
            if (!isset($omsetPerUser[$row['user_id']])) {
                $omsetPerUser[$row['user_id']] = 0;
            }
            $omsetPerUser[$row['user_id']] += $nominal;
        }
        return [
            'users' => $users,
            'receiverCounts' => $receiverCounts,
            'pickupCounts' => $pickupCounts,
            'settingCounts' => $settingCounts,
            'omsetPerUser' => $omsetPerUser
        ];

    }

    public function orderArchive() {
        global $store_id;

        $start_date = ($_GET['start_date'] ?? date('Y-m-d')) . ' 00:00:00';
        $end_date = ($_GET['end_date'] ?? date('Y-m-d')) . ' 23:59:59';

        $archive = $this->orderModel->getOrderArchive($store_id, $start_date, $end_date);

        $result = [];

        foreach ($archive as $row) {
            $orderId = $row['order_id'];

            if (!isset($result[$orderId])) {
                $result[$orderId] = $row;
                $result[$orderId]['items'] = [];
            }

            if ($row['deleted_order_item_id'] !== null) {
                $result[$orderId]['items'][] = [
                    'deleted_order_item_id' => $row['deleted_order_item_id'],
                    'order_item_id'         => $row['order_item_id'],
                    'product_id'            => $row['product_id'],
                    'judul'                 => $row['judul'],
                    'finishing'             => $row['finishing'],
                    'finishing_names'       => $row['finishing_names'],
                    'size'                  => $row['size'],
                    'quantity'              => $row['quantity'],
                    'unit'                  => $row['unit'],
                    'amount'                => $row['amount']
                ];
            }

            unset(
                $result[$orderId]['deleted_order_item_id'],
                $result[$orderId]['order_item_id'],
                $result[$orderId]['product_id'],
                $result[$orderId]['judul'],
                $result[$orderId]['finishing'],
                $result[$orderId]['finishing_names'],
                $result[$orderId]['size'],
                $result[$orderId]['quantity'],
                $result[$orderId]['unit'],
                $result[$orderId]['amount']
            );
        }

        return $result;
    }

    public function maklun(){
        global $store_id;

        $start_date = ($_GET['start_date'] ?? date('Y-m-d')) . ' 00:00:00';
        $end_date = ($_GET['end_date'] ?? date('Y-m-d')) . ' 23:59:59';

        $stmtMaklunan = $this->koneksi->prepare("
                        SELECT
                            oi.order_item_id, oi.judul, oi.product_id, oi.size, oi.quantity, oi.finishing,
                            o.store_id, o.date,
                            p.name AS product_name, p.unit_type, p.reasonable_price,
                            c.name AS category,
                            s.name AS branch_name
                        FROM order_items oi
                        JOIN orders o ON o.order_id = oi.order_id
                        LEFT JOIN products p ON p.product_id = oi.product_id
                        LEFT JOIN categories c ON c.category_id = p.category_id
                        LEFT JOIN stores s ON s.store_id = o.store_id
                        WHERE oi.maklun = ? AND o.date BETWEEN ? AND ? ORDER BY o.date ASC
                        ");
        $stmtMaklunan->bind_param("iss", $store_id, $start_date, $end_date);
        $stmtMaklunan->execute();
        $dataMaklunMasuk = $stmtMaklunan->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtMaklunan->close();

        $stmtNgemaklun = $this->koneksi->prepare("
                        SELECT
                            oi.order_item_id, oi.judul, oi.maklun, oi.product_id, oi.size, oi.quantity, oi.finishing,
                            o.date,
                            p.name AS product_name, p.unit_type, p.reasonable_price,
                            c.name AS category,
                            s.name AS branch_name
                        FROM order_items oi
                        JOIN orders o ON o.order_id = oi.order_id
                        LEFT JOIN products p ON p.product_id = oi.product_id
                        LEFT JOIN categories c ON c.category_id = p.category_id
                        LEFT JOIN stores s ON s.store_id = oi.maklun
                        WHERE o.store_id = ? AND oi.maklun != 0 AND o.date BETWEEN ? AND ? ORDER BY o.date ASC
                        ");
        $stmtNgemaklun->bind_param("iss", $store_id, $start_date, $end_date);
        $stmtNgemaklun->execute();
        $dataMaklunKeluar = $stmtNgemaklun->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtNgemaklun->close();

        $all_finishing_ids = [];
        foreach (array_merge($dataMaklunMasuk, $dataMaklunKeluar) as $row) {
            if (!empty($row['finishing']) && $row['finishing'] !== '-') {
                $ids = explode(',', $row['finishing']);
                foreach ($ids as $id) {
                    $clean_id = trim($id);
                    if (ctype_digit($clean_id)) {
                        $all_finishing_ids[$clean_id] = true;
                    }
                }
            }
        }

        $finishing_names_map = [];
        $finishing_prices_map = [];

        if (!empty($all_finishing_ids)) {
            $ids_array = array_keys($all_finishing_ids);
            $placeholders = implode(',', array_fill(0, count($ids_array), '?'));
            $types = str_repeat('i', count($ids_array));

            $stmtF = $this->koneksi->prepare("SELECT finishing_id, name FROM finishings WHERE finishing_id IN ($placeholders)");
            $stmtF->bind_param($types, ...$ids_array);
            $stmtF->execute();
            $resF = $stmtF->get_result();
            while ($rF = $resF->fetch_assoc()) {
                $finishing_names_map[$rF['finishing_id']] = $rF['name'];
            }
            $stmtF->close();

            $stmtP = $this->koneksi->prepare("SELECT product_id, reasonable_price FROM products WHERE product_id IN ($placeholders)");
            $stmtP->bind_param($types, ...$ids_array);
            $stmtP->execute();
            $resP = $stmtP->get_result();
            while ($rP = $resP->fetch_assoc()) {
                $finishing_prices_map[$rP['product_id']] = (int)$rP['reasonable_price'];
            }
            $stmtP->close();
        }

        function processMaklunData(&$dataArray, $finishing_names_map, $finishing_prices_map) {
            foreach ($dataArray as &$row) {
                $finishing_price = 0;
                $f_names = [];

                if (!empty($row['finishing']) && $row['finishing'] !== '-') {
                    $ids = explode(',', $row['finishing']);
                    foreach ($ids as $id) {
                        $clean_id = trim($id);
                        if (isset($finishing_names_map[$clean_id])) {
                            $f_names[] = $finishing_names_map[$clean_id];
                        }
                        if (isset($finishing_prices_map[$clean_id])) {
                            $finishing_price += $finishing_prices_map[$clean_id];
                        }
                    }
                }
                $row['finishing_names_str'] = !empty($f_names) ? implode(', ', $f_names) : '-';

                $hargaSatuan = 0;
                if (!empty($row['product_id']) && $row['product_id'] != 0) {
                    $unit_type = $row['unit_type'] ?? '';
                    $type = $row['category'] ?? '';
                    $product_name = $row['product_name'] ?? '';
                    $reasonable_price = (float)($row['reasonable_price'] ?? 0);
                    $size = $row['size'] ?? '';
                    
                    $base_price_plus_finishing = $reasonable_price + $finishing_price;

                    if ($unit_type === 'M2') {
                        if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $size, $match)) {
                            $p = floatval($match[1]);
                            $l = floatval($match[2]);
                            if ($type === 'DTF') {
                                $hargaSatuan = $p * $base_price_plus_finishing;
                            } else {
                                $hargaSatuan = $p * $l * $base_price_plus_finishing;
                            }
                        }
                    } elseif ($unit_type === 'PCS') {
                        $hargaSatuan = $base_price_plus_finishing;
                        
                        if ($type === 'JERSEY') {
                            $harga_jersey = 0;
                            if ($size === '5XL') {
                                $harga_jersey += 50000;
                            } elseif ($size === '4XL') {
                                $harga_jersey += 40000;
                            } elseif ($size === '3XL') {
                                $harga_jersey += 30000;
                            } elseif ($size === '2XL') {
                                $harga_jersey += 20000;
                            } elseif ($size === 'XL') {
                                $harga_jersey += 10000;
                            }
                            $hargaSatuan += $harga_jersey;
                        } elseif ($type === 'SUBLIM' && str_contains((string)$product_name, 'BAHAN')) {
                            $kata = explode(" ", $size);
                            if (isset($kata[0]) && is_numeric($kata[0])) {
                                $hargaSatuan *= (float)$kata[0];
                            }
                        }
                    } elseif ($product_name === 'POTONG AKRILIK') {
                        $hargaSatuan = $base_price_plus_finishing;
                        $kata = explode(" ", $size);
                        if (isset($kata[0]) && is_numeric($kata[0])) {
                            $hargaSatuan *= (float)$kata[0];
                        }
                    }
                }
                
                $row['harga_satuan_calc'] = $hargaSatuan;
                $row['jumlah_harga_calc'] = $hargaSatuan * (float)($row['quantity'] ?? 0);
            }
        }

        processMaklunData($dataMaklunMasuk, $finishing_names_map, $finishing_prices_map);
        processMaklunData($dataMaklunKeluar, $finishing_names_map, $finishing_prices_map);

        return[
            'maklunIn' => $dataMaklunMasuk,
            'maklunOut' => $dataMaklunKeluar
        ];
    }

}