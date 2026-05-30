<?php
require_once BASE_PATH . '/models/Order.php';
require_once BASE_PATH . '/models/User.php';
require_once BASE_PATH . '/models/Project.php';
require_once BASE_PATH . '/models/Product.php';
require_once BASE_PATH . '/models/Stock.php';
require_once BASE_PATH . '/models/Activity.php';
require_once BASE_PATH . '/models/Payment.php';

class OrderController {
    private $koneksi;
    private $orderModel;
    private $userModel;
    private $projectModel;
    private $productModel;
    private $stockModel;
    private $paymentModel;
    private $activityModel;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
        $this->orderModel = new Order($koneksi);
        $this->userModel = new User($koneksi);
        $this->projectModel = new Project($koneksi);
        $this->productModel = new Product($koneksi);
        $this->stockModel = new Stock($koneksi);
        $this->paymentModel = new Payment($koneksi);
        $this->activityModel = new Activity($koneksi);
    }

    private function requestData() {
        global $store_id;

        $deadline_input = $_POST['deadline'] ?? '';
        $user_id = $_POST['user_id'] ?? 0;
        $system = ($this->userModel->getRoleById($user_id) === 'ONLINE') ? 'ONLINE' : 'OFFLINE';

        $data = new stdClass();
        $data->order_id = (int)($_POST['order_id'] ?? 0);
        $data->store_id = $store_id;
        $data->user_id = $user_id;
        $data->system = $system;
        
        if ($data->order_id > 0) {
            $data->nomorator = trim($_POST['nomorator'] ?? '');
        } else {
            $data->nomorator = generateNomorator($this->koneksi, $data->store_id, $data->system);
        }

        $data->customer_name = trim($_POST['customer_name'] ?? '');
        $data->nomor = trim($_POST['nomor'] ?? '');
        $data->total = 0;
        
        $data->deadline = $deadline_input ? date('Y-m-d H:i:s', strtotime($deadline_input)) : null;
        
        $data->date = trim($_POST['date'] ?? date('Y-m-d H:i:s')); 

        return $data;
    }

    public function create() {
        header('Content-Type: application/json');
        date_default_timezone_set('Asia/Jakarta');

        $deadline_input = $_POST['deadline'] ?? '';
        $customer_name = $_POST['customer_name'] ?? '';
        $user_id = $_POST['user_id'] ?? 0;

        $deadline_check = date('Y-m-d', strtotime($deadline_input));
        $today_check = date('Y-m-d');

        if (($deadline_check < $today_check) || $customer_name == '' || $user_id == 0) {
            echo json_encode(['status' => 'error', 'message' => 'Validasi gagal. Data tidak lengkap atau deadline tidak valid.']);
            exit;
        }

        $data = $this->requestData();
        $insert = $this->orderModel->createOrder($data);

        if ($insert) {
            $order_id = $this->koneksi->insert_id;
            $data->order_id = $order_id;
            $this->projectModel->createProject($data);
            
            echo json_encode(['status' => 'success', 'order_id' => $order_id]);
            exit;
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menambahkan order']);
            exit;
        }
    }

    public function update() {
        $data = $this->requestData();

        if ($data->order_id === 0 || $data->store_id === 0 || $data->user_id === 0 || empty($data->nomorator) || empty($data->customer_name)) {
            $_SESSION['error'] = "Data tidak lengkap.";
            header("Location: customer.php");
            exit;
        }

        if (!$this->userModel->checkValidOperator($data->user_id, $data->store_id)) {
            $_SESSION['error'] = "Operator tidak valid.";
            header("Location: customer.php");
            exit;
        }

        if ($this->orderModel->updateOrder($data)) {
            $_SESSION['success'] = "Order berhasil diperbarui.";
        } else {
            $_SESSION['error'] = "Gagal memperbarui order.";
        }

        header("Location: index");
        exit;
    }

    public function delete() {
        header('Content-Type: application/json');
        global $store_id;

        if (!isset($_POST['order_id']) || !isset($_SESSION['admin_logged_in'])) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak atau data tidak valid.']);
            exit;
        }

        $administrator_id = startEnk('dek', $_SESSION['admin_logged_in']['administrator_id']);
        $order_id = (int) $_POST['order_id'];
        $keterangan = isset($_POST['keterangan_hapus']) ? trim($_POST['keterangan_hapus']) : '';
        $date = date("Y-m-d H:i:s");

        $this->koneksi->begin_transaction();

        try {
            $order = $this->orderModel->getOrderById($order_id);
            if (!$order) throw new Exception("Order tidak ditemukan");

            $this->paymentModel->deletePaymentByOrderId($order_id);
            $this->projectModel->deleteProjectByOrderId($order_id);
            $this->orderModel->deleteOrderDependencies($order_id);

            $this->logActivity($order['store_id'], $order_id, $order['customer_name'], $order['nomorator'], $keterangan, $administrator_id);
            $this->orderModel->archiveOrder($order, $administrator_id, $date);
            $this->orderModel->archiveOrderItems($order_id);
            $this->orderModel->deleteOrderAndItems($order_id);
            $this->koneksi->commit();
            refreshFinance($order['store_id'], date('Y-m-d', strtotime($order['date'])));
            
            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            $this->koneksi->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    private function logActivity($store_id, $order_id, $customer_name, $nomorator, $keterangan, $administrator_id) {
        $data = new stdClass();
        $data->store_id = $store_id;
        $data->title = "HAPUS ORDER";
        $data->message = "HAPUS ORDERAN DENGAN NAMA " . $customer_name . " NOMORATOR " . $nomorator;
        $data->information = $keterangan;
        $data->date = date("Y-m-d H:i:s");
        $data->order_id = $order_id;
        $data->done = 0;
        $data->administrator_id = $administrator_id;

        return $this->activityModel->createActivity($data);
    }

    public function saveNote() {
        $order_id = (int)($_POST['order_id'] ?? 0);
        $note = trim($_POST['note'] ?? '');

        if ($order_id && $note !== '') {
            $existing = $this->orderModel->getLatestCustomerNote($order_id);

            if ($existing) {
                $this->orderModel->updateNote((int)$existing['note_order_id'], $note);
            } else {
                $this->orderModel->createNote($order_id, $note);
            }

            echo htmlspecialchars($note);
            exit;
        }
    }

    public function deleteItem() {
        header('Content-Type: application/json');
        global $store_id;

        $order_item_id = isset($_POST['order_item_id']) ? (int)$_POST['order_item_id'] : 0;

        if ($order_item_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID item tidak valid.']);
            exit;
        }

        $item = $this->orderModel->getOrderItem($order_item_id, $store_id);

        if (!$item) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Item tidak ditemukan.']);
            exit;
        }

        $product_id = $item['product_id'];
        $quantity = $item['quantity'];
        $size = $item['size'];
        $finishing_ids = $item['finishing'];
        $order_id = $item['order_id'];

        $product = $this->productModel->getProductById($product_id);
        $unit_type = $product['unit_type'] ?? '';
        $type = $product['type'] ?? '';

        $stok_kembali = $quantity;
        $panjang = 0;
        $lebar = 0;

        if (preg_match('/([\d.]+)x([\d.]+)/', $size, $matches)) {
            $panjang = (float)$matches[1];
            $lebar = (float)$matches[2];
        }

        if ($unit_type === 'M2' || $unit_type === 'CM2') {
            $stok_kembali = round(($panjang / 100) * ($lebar / 100) * $quantity, 4);
        }
        if (strtoupper($type) === 'SPANDUK') {
            $stok_kembali = round((($panjang + 5) * ($lebar + 5)) / 10000 * $quantity, 4);
        }

        $stockData = new stdClass();
        $stockData->id = $product_id;
        $stockData->store_id = $store_id;
        $stockData->quantity = $stok_kembali;
        $this->stockModel->createUpdateStock($stockData);

        if ($finishing_ids !== '-') {
            $finishing_array = explode(',', $finishing_ids);
            foreach ($finishing_array as $fid) {
                $fid = (int)$fid;
                if ($fid === 0) continue;

                $fin_product = $this->productModel->getProductById($fid);
                $fin_type = strtoupper($fin_product['type'] ?? '');

                $stok_kembali_fin = $quantity;

                if ($fin_type === 'FINISHING STIKER A3' || $fin_type === 'FINISHING PHOTO A3') {
                    $stok_kembali_fin = 0.1536 * $quantity;
                } elseif ($fin_type === 'FINISHING STIKER PERMETER' || $fin_type === 'FINISHING PHOTO PERMETER') {
                    $panjang_meter = ($panjang > 20) ? $panjang / 100 : $panjang;
                    $lebar_meter = ($lebar > 20) ? $lebar / 100 : $lebar;
                    $stok_kembali_fin = $panjang_meter * $lebar_meter * $quantity;
                }

                $finStockData = new stdClass();
                $finStockData->id = $fid;
                $finStockData->store_id = $store_id;
                $finStockData->quantity = $stok_kembali_fin;
                $this->stockModel->createUpdateStock($finStockData);
            }
        }

        if ($this->orderModel->deleteOrderItem($order_item_id, $store_id)) {
            updateOrderTotal($order_id, $this->koneksi);
            echo json_encode(['success' => true, 'message' => 'Item berhasil dihapus dan stok dikembalikan.']);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus item.']);
            exit;
        }
    }
}