<?php
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Project.php';
require_once __DIR__ . '/../models/Finance.php';
require_once __DIR__ . '/../controllers/FinanceController.php';
require_once __DIR__ . '/../functions/helpers.php';
require_once __DIR__ . '/../functions/cacheHelpers.php';

class PaymentController {
    private $paymentModel;
    private $orderModel;
    private $projectModel;
    private $financeController;
    private $koneksi;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
        $this->paymentModel = new Payment($koneksi);
        $this->orderModel = new Order($koneksi);
        $this->projectModel = new Project($koneksi);
        $this->financeController = new FinanceController($koneksi);
    }

    public function create(){
        global $store_id;
        $isLunas = isset($_POST['lunas_method']);
        $order_id = $_POST['order_id'] ?? '';

        if (empty($order_id)) {
            send_json_response(false, 'Order tidak ditemukan');
            exit;
        }

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
        updateStoreCache($store_id, 'orders');
        updateStoreCache($store_id, 'payments');
        updateStoreCache($store_id, 'finance');
        updateOrderTrigger($store_id, $order_id);
        send_json_response(true, 'Pembayaran berhasil', [
            'status' => $data->status,
            'bayar' => $totalBayar,
            'keterangan' => $keteranganBaru,
            'isLunas' => $isLunasStatus,
        ]);
    }

    public function orderPayment(){
        $order_id = $_GET['order_id'] ?? 0;
        $total = $this->orderModel->getOneValue($order_id, 'total');
        $payments = $this->paymentModel->getPaymentByOrderId($order_id);
        $paid = $this->paymentModel->getPaidByOrderId($order_id);

        $is_lunas = false;
        if ((int)$paid >= $total) {
            $is_lunas = true;
        };

        return [
            'payments' => $payments,
            'paid' => $paid,
            'is_lunas' => $is_lunas
        ];

    }

}
?>