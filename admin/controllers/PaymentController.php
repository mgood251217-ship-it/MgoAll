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

}
?>