<?php
require_once BASE_PATH . '/models/Payment.php';
require_once BASE_PATH . '/models/Order.php';
require_once BASE_PATH . '/models/Project.php';
require_once BASE_PATH . '/models/Activity.php';
require_once BASE_PATH . '/models/Finance.php';
require_once BASE_PATH . '/controllers/FinanceController.php';
require_once BASE_PATH . '/functions/helpers.php';

class PaymentController {
    private $paymentModel;
    private $orderModel;
    private $projectModel;
    private $activityModel;
    private $financeModel;
    private $financeController;
    private $koneksi;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
        $this->paymentModel = new Payment($koneksi);
        $this->orderModel = new Order($koneksi);
        $this->projectModel = new Project($koneksi);
        $this->activityModel = new Activity($koneksi);
        $this->financeModel = new Finance($koneksi);
        $this->financeController = new FinanceController($koneksi);
    }

    private function requestData() {
        global $store_id;

        $data = (object)[
            'order_id' => $_POST['order_id'],
            'store_id' => $store_id,
        ];
    }

    public function create(){
        global $store_id;
        $isLunas = isset($_POST['lunas_method']);
        $order_id = $_POST['order_id'];

        $total = $this->orderModel->getOneValue($order_id, 'total');
        $paid = $this->paymentModel->getPaidByOrderId($order_id);

        $nominal = $isLunas ? ($total - $paid) : ((int)($_POST['nominal'] ?? 0));

        if ($nominal <= 0) {
            send_json_response(false, $isLunas ? 'Sudah Lunas' : 'Nominal Invalid');
            exit;
        }

        $total_paid = $paid + $nominal;
        $isLunasStatus = ($total_paid >= $total);

        $data = new stdClass();
        $data->order_id = $order_id;
        $data->store_id = $store_id;
        $data->nominal = $nominal;
        $data->payment_method = $isLunas ? $_POST['lunas_method'] : ($_POST['payment_method'] ?? '');
        $data->status = $isLunasStatus ? 'LUNAS' : 'DP';
        $data->date = date('Y-m-d H:i:s');

        $this->paymentModel->createPayment($data);
        $this->financeController->refreshFinance($store_id, date('Y-m-d'));

        $lastProcess = $this->projectModel->getLastProjectProcessByOrderId($order_id);
        $data->process = ($lastProcess && $lastProcess !== 'BELUM BAYAR') ? $lastProcess : 'BELUM DIPROSES';
        $this->projectModel->updateProject($data);

        $lastStatus = $this->projectModel->getLastProjectStatusByOrderId($order_id);
        $keteranganBaru = title_case($lastProcess ?: ($lastStatus ?: '-'));

        if ($isLunasStatus) {
            $totalBayar = title_case("LUNAS " . $data->payment_method);
        } else {
            $totalBayar = "<div style='font-size: 12px; line-height: 12px;'>DP: " . format_rupiah($total_paid) . " | Sisa : " . format_rupiah($total - $total_paid) . "</div>";
        }

        send_json_response(true, 'Pembayaran berhasil', [
            'status' => $data->status,
            'bayar' => $totalBayar,
            'keterangan' => $keteranganBaru,
            'isLunas' => $isLunasStatus,
        ]);
    }

    public function delete(){
        global $store_id;
        $date = date("Y-m-d H:i:s");
        $administrator_id = startEnk('dek',  $_SESSION['admin_logged_in']['administrator_id']);
        $payment_id = isset($_POST['payment_id']) ? (int)$_POST['payment_id'] : 0;
        $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
        $keterangan = isset($_POST['keterangan_hapus']) ? trim($_POST['keterangan_hapus']) : '';

        $order = $this->orderModel->getOrderById($order_id);
        $orderName = $order['customer_name'];
        $orderNomorator = $order['nomorator'];

        $title = "HAPUS PEMBAYARAN";
        $message = "HAPUS PEMBAYARAN UNTUK ORDERAN DENGAN NAMA " . $orderName . " NOMORATOR " . $orderNomorator;
        $done = 0;

        $data = (object)[
            'store_id' => $store_id,
            'title' => $title,
            'message' => $message,
            'information' => $keterangan,
            'date' => $date,
            'order_id' => $order_id,
            'done' => $done,
            'administrator_id' => $administrator_id
        ];

        $this->activityModel->createActivity($data);
        $this->paymentModel->deletePaymentById($payment_id);
        $tanggalAja = date("Y-m-d");
        $this->financeController->refreshFinance($store_id, $tanggalAja);

        send_json_response(true, 'Pembayaran berhasil dihapus.');

    }

    public function updatePayment(){
        global $store_id;

        if (!isset($_SESSION['admin_logged_in'])) {
            echo json_encode(['success' => false, 'message' => 'Kesalahan Login Administrator']);
            exit;
        }

        $administrator_id = startEnk('dek', $_SESSION['admin_logged_in']['administrator_id']);

        $date = date("Y-m-d H:i:s");

        $payment_id = isset($_POST['payment_id']) ? (int)$_POST['payment_id'] : 0;
        $order_id   = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
        $nominal    = isset($_POST['nominal']) ? (int)$_POST['nominal'] : 0;
        $method     = isset($_POST['payment_method']) ? strtoupper(trim($_POST['payment_method'])) : '';
        $tanggal    = isset($_POST['tanggal']) ? trim($_POST['tanggal']) : '';
        $keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : '';
        $tanggalOld = strtotime($tanggal);
        $tanggalcek = date('Y-m-d', $tanggalOld);

        $tanggal = str_replace('T', ' ', $tanggal) . ':00';

        $title = "UBAH PEMBAYARAN";
        $message = "";
        $done = 0;

        $order = $this->orderModel->getOrderById($order_id);
        $orderName = $order['customer_name'];
        $orderNomorator = $order['nomorator'];

        $payment = $this->paymentModel->getPaymentById($payment_id);
        $paymentNominal = $payment['nominal'] ?? '';
        $paymentPaymentmethod = $payment['payment_method'] ?? '';
        $paymentDateOld = strtotime($payment['date']);
        $paymentDate = date('Y-m-d', $paymentDateOld);

        if ($method != $paymentPaymentmethod && $paymentNominal != $nominal && $paymentDate != $tanggalcek) {
            $message = "UBAH METODE PEMBAYARAN, NOMINAL, DAN TANGGAL BAYAR DARI: \n"
                        . $paymentNominal . " => ". $nominal . "\n"
                        . $paymentPaymentmethod . " => ". $method . "\n"
                        . $paymentDate . " => ". $tanggalcek . "\n"
                        . "NAMA ". $orderName . " NOMORATOR " . $orderNomorator
                        ;
        }elseif ($method != $paymentPaymentmethod && $paymentNominal != $nominal) {
            $message = "UBAH METODE PEMBAYARAN DAN NOMINAL DARI: \n"
                        . $paymentNominal . " => ". $nominal . "\n"
                        . $paymentPaymentmethod . " => ". $method . "\n"
                        . "NAMA ". $orderName . " NOMORATOR " . $orderNomorator
                        ;
        }elseif ($paymentNominal != $nominal && $paymentDate != $tanggalcek) {
            $message = "UBAH NOMINAL, DAN TANGGAL BAYAR DARI: \n"
                        . $paymentNominal . " => ". $nominal . "\n"
                        . $paymentDate . " => ". $tanggalcek . "\n"
                        . "NAMA ". $orderName . " NOMORATOR " . $orderNomorator
                        ;
        }elseif ($method != $paymentPaymentmethod && $paymentDate != $tanggalcek) {
            $message = "UBAH METODE PEMBAYARAN, DAN TANGGAL BAYAR DARI: \n"
                        . $paymentPaymentmethod . " => ". $method . "\n"
                        . $paymentDate . " => ". $tanggalcek . "\n"
                        . "NAMA ". $orderName . " NOMORATOR " . $orderNomorator
                        ;
        }elseif ($method != $paymentPaymentmethod) {
            $message = "UBAH METODE PEMBAYARAN DARI: \n"
                        . $paymentPaymentmethod . " => ". $method . "\n"
                        . "NAMA ". $orderName . " NOMORATOR " . $orderNomorator
                        ;
        }elseif ($paymentNominal != $nominal) {
            $message = "UBAH NOMINAL DARI: \n"
                        . $paymentNominal . " => ". $nominal . "\n"
                        . "NAMA ". $orderName . " NOMORATOR " . $orderNomorator
                        ;
        }elseif ($paymentDate != $tanggalcek) {
            $message = "UBAH NOMINAL, DAN TANGGAL BAYAR DARI: \n"
                        . $paymentDate . " => ". $tanggalcek . "\n"
                        . "NAMA ". $orderName . " NOMORATOR " . $orderNomorator
                        ;
        }else {
            $message = "";
        }

        if ($message != "") {
            $data = (object)[
                'store_id' => $store_id,
                'title' => $title,
                'message' => $message,
                'information' => $keterangan,
                'date' => $date,
                'order_id' => $order_id,
                'done' => $done,
                'administrator_id' => $administrator_id
            ];
            $this->activityModel->createActivity($data);
        }

        $data = (object)[
            'nominal' => $nominal,
            'payment_method' => $method,
            'date' => $tanggal,
            'status' => 'DP',
            'payment_id' => $payment_id
        ];

        $this->paymentModel->updatePayment($data);

        $totalPembayaran = 0;
        $payments = $this->koneksi->query("SELECT payment_id, nominal FROM payment WHERE order_id = $order_id");

        while ($row = $payments->fetch_assoc()) {
            $totalPembayaran += (int)$row['nominal'];
        }

        $orderResult = $this->koneksi->query("SELECT total FROM orders WHERE order_id = $order_id LIMIT 1");
        $orderTotal = 0;
        if ($orderRow = $orderResult->fetch_assoc()) {
            $orderTotal = (int)$orderRow['total'];
        }

        if ($totalPembayaran < $orderTotal) {
            $this->koneksi->query("UPDATE payment SET status = 'DP' WHERE order_id = $order_id");
        } else {
            $this->koneksi->query("UPDATE payment SET status = 'DP' WHERE order_id = $order_id");

            $last = $this->koneksi->query("SELECT payment_id FROM payment WHERE order_id = $order_id ORDER BY payment_id DESC LIMIT 1");
            if ($lastRow = $last->fetch_assoc()) {
                $lastId = (int)$lastRow['payment_id'];
                $this->koneksi->query("UPDATE payment SET status = 'LUNAS' WHERE payment_id = $lastId");
                $tanggalAja = date("Y-m-d");
                $this->financeController->refreshFinance($store_id, $tanggalAja);
            }
        }
        send_json_response(true, 'Pembayaran berhasil diubah.');

    }

}
?>