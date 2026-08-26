<?php
class PaymentController {
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
        if ($storesResult) {
            while ($row = $storesResult->fetch_assoc()) {
                $stores[] = $row;
            }
        }

        $store_id = isset($_GET['store_id']) && $_GET['store_id'] !== '' ? (int)$_GET['store_id'] : (!empty($stores) ? $stores[0]['store_id'] : null);
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $search = $_GET['search'] ?? '';
        $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 25;
        $start_date = $_GET['start_date'] ?? date('Y-m-d');
        $end_date = $_GET['end_date'] ?? date('Y-m-d');

        $start_date_db = $start_date . ' 00:00:00';
        $end_date_db = $end_date . ' 23:59:59';

        $payments = [];
        $totalPages = 0;
        $total = 0;

        if ($store_id !== null) {
            $search_param = "%" . $search . "%";
            
            $countStmt = $this->koneksi->prepare("
                SELECT COUNT(*) 
                FROM payment p
                JOIN orders o ON p.order_id = o.order_id
                WHERE p.store_id = ? AND p.date BETWEEN ? AND ? 
                AND (o.customer_name LIKE ? OR o.nomorator LIKE ?)
            ");
            $countStmt->bind_param("issss", $store_id, $start_date_db, $end_date_db, $search_param, $search_param);
            $countStmt->execute();
            $total = $countStmt->get_result()->fetch_row()[0];
            $countStmt->close();

            $totalPages = ceil($total / $limit);
            $offset = ($page - 1) * $limit;

            $stmt = $this->koneksi->prepare("
                SELECT p.*, o.customer_name, o.nomorator 
                FROM payment p
                JOIN orders o ON p.order_id = o.order_id
                WHERE p.store_id = ? AND p.date BETWEEN ? AND ? 
                AND (o.customer_name LIKE ? OR o.nomorator LIKE ?)
                ORDER BY p.date DESC, p.payment_id DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param("issssii", $store_id, $start_date_db, $end_date_db, $search_param, $search_param, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $payments[] = $row;
            }
            $stmt->close();
        }

        return [
            'stores' => $stores,
            'current_store_id' => $store_id,
            'payments' => $payments,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $total,
            'search' => $search,
            'limit' => $limit,
            'start_date' => $start_date,
            'end_date' => $end_date
        ];
    }

    private function recalculatePaymentStatus($order_id) {
        $stmtOrder = $this->koneksi->prepare("SELECT total FROM orders WHERE order_id = ?");
        $stmtOrder->bind_param("i", $order_id);
        $stmtOrder->execute();
        $orderRow = $stmtOrder->get_result()->fetch_assoc();
        $stmtOrder->close();

        if (!$orderRow) return;
        $orderTotal = (float)$orderRow['total'];

        $stmtPay = $this->koneksi->prepare("SELECT payment_id, nominal FROM payment WHERE order_id = ? ORDER BY date ASC, payment_id ASC");
        $stmtPay->bind_param("i", $order_id);
        $stmtPay->execute();
        $resPay = $stmtPay->get_result();
        
        $payments = [];
        $totalPembayaran = 0;
        while ($row = $resPay->fetch_assoc()) {
            $payments[] = $row;
            $totalPembayaran += (float)$row['nominal'];
        }
        $stmtPay->close();

        if (empty($payments)) return;

        $stmtReset = $this->koneksi->prepare("UPDATE payment SET status = 'DP' WHERE order_id = ?");
        $stmtReset->bind_param("i", $order_id);
        $stmtReset->execute();
        $stmtReset->close();

        if ($totalPembayaran >= $orderTotal) {
            $lastPayment = end($payments);
            $lastPaymentId = $lastPayment['payment_id'];
            
            $stmtLunas = $this->koneksi->prepare("UPDATE payment SET status = 'LUNAS' WHERE payment_id = ?");
            $stmtLunas->bind_param("i", $lastPaymentId);
            $stmtLunas->execute();
            $stmtLunas->close();
        }
    }

    public function delete() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        header('Content-Type: application/json');
        require_once __DIR__ . '/../functions/helpers.php';
        require_once __DIR__ . '/../functions/cacheHelper.php';
        
        $store_id = isset($_POST['store_id']) ? (int)$_POST['store_id'] : 0;
        $date = date("Y-m-d H:i:s");
        $administrator_id = isset($_SESSION['admin_logged_in']['administrator_id']) ? startEnk('dek', $_SESSION['admin_logged_in']['administrator_id']) : 0;
        
        $payment_id = isset($_POST['payment_id']) ? (int)$_POST['payment_id'] : 0;
        $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
        $keterangan = isset($_POST['keterangan_hapus']) ? trim($_POST['keterangan_hapus']) : '';

        if (!$payment_id || !$administrator_id) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak atau data tidak valid.']);
            exit;
        }

        $this->koneksi->begin_transaction();

        try {
            $stmtOrder = $this->koneksi->prepare("SELECT customer_name, nomorator FROM orders WHERE order_id = ?");
            $stmtOrder->bind_param("i", $order_id);
            $stmtOrder->execute();
            $order = $stmtOrder->get_result()->fetch_assoc();
            $stmtOrder->close();

            if (!$order) throw new Exception("Order tidak ditemukan.");

            $title = "HAPUS PEMBAYARAN";
            $message = "HAPUS PEMBAYARAN UNTUK ORDERAN DENGAN NAMA " . $order['customer_name'] . " NOMORATOR " . $order['nomorator'];
            $done = 0;

            $stmtAct = $this->koneksi->prepare("INSERT INTO activity (store_id, title, message, information, date, order_id, done, administrator_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtAct->bind_param("issssiii", $store_id, $title, $message, $keterangan, $date, $order_id, $done, $administrator_id);
            $stmtAct->execute();
            $stmtAct->close();

            $stmtDel = $this->koneksi->prepare("DELETE FROM payment WHERE payment_id = ?");
            $stmtDel->bind_param("i", $payment_id);
            $stmtDel->execute();
            $stmtDel->close();

            $this->recalculatePaymentStatus($order_id);

            $this->koneksi->commit();

            if (file_exists(__DIR__ . '/FinanceController.php')) {
                require_once __DIR__ . '/FinanceController.php';
                $financeController = new FinanceController($this->koneksi);
                $financeController->refreshFinance($store_id, date("Y-m-d"));
            }

            updateStoreCache($store_id, 'activities');
            updateStoreCache($store_id, 'orders');
            updateStoreCache($store_id, 'payments');
            updateStoreCache($store_id, 'finance');
            updateOrderTrigger($store_id, $order_id);

            echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil dihapus.']);
        } catch (Exception $e) {
            $this->koneksi->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function update() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        header('Content-Type: application/json');
        require_once __DIR__ . '/../functions/helpers.php';
        require_once __DIR__ . '/../functions/cacheHelper.php';

        if (!isset($_SESSION['admin_logged_in'])) {
            echo json_encode(['success' => false, 'message' => 'Kesalahan Login Administrator']);
            exit;
        }

        $administrator_id = startEnk('dek', $_SESSION['admin_logged_in']['administrator_id']);
        $store_id = isset($_POST['store_id']) ? (int)$_POST['store_id'] : 0;
        $date = date("Y-m-d H:i:s");

        $payment_id = isset($_POST['payment_id']) ? (int)$_POST['payment_id'] : 0;
        $order_id   = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
        $nominal    = isset($_POST['nominal']) ? (int)$_POST['nominal'] : 0;
        $method     = isset($_POST['payment_method']) ? strtoupper(trim($_POST['payment_method'])) : '';
        $tanggal    = isset($_POST['tanggal']) ? trim($_POST['tanggal']) : '';
        $keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : '';
        
        $tanggalOld = strtotime($tanggal);
        $tanggalcek = date('Y-m-d', $tanggalOld);
        $tanggal = str_replace('T', ' ', $tanggal);
        if (strlen($tanggal) == 16) $tanggal .= ':00'; 

        $title = "UBAH PEMBAYARAN";
        $messageLog = "";
        $done = 0;

        $this->koneksi->begin_transaction();

        try {
            $stmtOrder = $this->koneksi->prepare("SELECT customer_name, nomorator, total FROM orders WHERE order_id = ?");
            $stmtOrder->bind_param("i", $order_id);
            $stmtOrder->execute();
            $order = $stmtOrder->get_result()->fetch_assoc();
            $stmtOrder->close();
            
            if (!$order) throw new Exception("Order tidak ditemukan.");

            $stmtPay = $this->koneksi->prepare("SELECT nominal, payment_method, date FROM payment WHERE payment_id = ?");
            $stmtPay->bind_param("i", $payment_id);
            $stmtPay->execute();
            $payment = $stmtPay->get_result()->fetch_assoc();
            $stmtPay->close();

            $paymentNominal = $payment['nominal'] ?? '';
            $paymentPaymentmethod = $payment['payment_method'] ?? '';
            $paymentDateOld = strtotime($payment['date']);
            $paymentDate = date('Y-m-d', $paymentDateOld);

            if ($method != $paymentPaymentmethod && $paymentNominal != $nominal && $paymentDate != $tanggalcek) {
                $messageLog = "UBAH METODE PEMBAYARAN, NOMINAL, DAN TANGGAL BAYAR DARI: \n"
                            . $paymentNominal . " => ". $nominal . "\n"
                            . $paymentPaymentmethod . " => ". $method . "\n"
                            . $paymentDate . " => ". $tanggalcek . "\n"
                            . "NAMA ". $order['customer_name'] . " NOMORATOR " . $order['nomorator'];
            } elseif ($method != $paymentPaymentmethod && $paymentNominal != $nominal) {
                $messageLog = "UBAH METODE PEMBAYARAN DAN NOMINAL DARI: \n"
                            . $paymentNominal . " => ". $nominal . "\n"
                            . $paymentPaymentmethod . " => ". $method . "\n"
                            . "NAMA ". $order['customer_name'] . " NOMORATOR " . $order['nomorator'];
            } elseif ($paymentNominal != $nominal && $paymentDate != $tanggalcek) {
                $messageLog = "UBAH NOMINAL, DAN TANGGAL BAYAR DARI: \n"
                            . $paymentNominal . " => ". $nominal . "\n"
                            . $paymentDate . " => ". $tanggalcek . "\n"
                            . "NAMA ". $order['customer_name'] . " NOMORATOR " . $order['nomorator'];
            } elseif ($method != $paymentPaymentmethod && $paymentDate != $tanggalcek) {
                $messageLog = "UBAH METODE PEMBAYARAN, DAN TANGGAL BAYAR DARI: \n"
                            . $paymentPaymentmethod . " => ". $method . "\n"
                            . $paymentDate . " => ". $tanggalcek . "\n"
                            . "NAMA ". $order['customer_name'] . " NOMORATOR " . $order['nomorator'];
            } elseif ($method != $paymentPaymentmethod) {
                $messageLog = "UBAH METODE PEMBAYARAN DARI: \n"
                            . $paymentPaymentmethod . " => ". $method . "\n"
                            . "NAMA ". $order['customer_name'] . " NOMORATOR " . $order['nomorator'];
            } elseif ($paymentNominal != $nominal) {
                $messageLog = "UBAH NOMINAL DARI: \n"
                            . $paymentNominal . " => ". $nominal . "\n"
                            . "NAMA ". $order['customer_name'] . " NOMORATOR " . $order['nomorator'];
            } elseif ($paymentDate != $tanggalcek) {
                $messageLog = "UBAH TANGGAL BAYAR DARI: \n"
                            . $paymentDate . " => ". $tanggalcek . "\n"
                            . "NAMA ". $order['customer_name'] . " NOMORATOR " . $order['nomorator'];
            }

            if ($messageLog != "") {
                $stmtAct = $this->koneksi->prepare("INSERT INTO activity (store_id, title, message, information, date, order_id, done, administrator_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtAct->bind_param("issssiii", $store_id, $title, $messageLog, $keterangan, $date, $order_id, $done, $administrator_id);
                $stmtAct->execute();
                $stmtAct->close();
            }

            $stmtUpd = $this->koneksi->prepare("UPDATE payment SET nominal = ?, payment_method = ?, date = ? WHERE payment_id = ?");
            $stmtUpd->bind_param("issi", $nominal, $method, $tanggal, $payment_id);
            $stmtUpd->execute();
            $stmtUpd->close();

            $this->recalculatePaymentStatus($order_id);

            $this->koneksi->commit();

            if (file_exists(__DIR__ . '/FinanceController.php')) {
                require_once __DIR__ . '/FinanceController.php';
                $financeController = new FinanceController($this->koneksi);
                $financeController->refreshFinance($store_id, date("Y-m-d"));
            }

            updateStoreCache($store_id, 'activities');
            updateStoreCache($store_id, 'orders');
            updateStoreCache($store_id, 'payments');
            updateStoreCache($store_id, 'finance');
            updateOrderTrigger($store_id, $order_id);

            echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil diubah.']);
        } catch (Exception $e) {
            $this->koneksi->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}