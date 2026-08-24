<?php
class OrderController {
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

        $orders = [];
        $totalPages = 0;
        $total = 0;

        if ($store_id !== null) {
            $search_param = "%" . $search . "%";
            
            $countStmt = $this->koneksi->prepare("
                SELECT COUNT(*) 
                FROM orders 
                WHERE store_id = ? AND date BETWEEN ? AND ? 
                AND (customer_name LIKE ? OR nomorator LIKE ?)
            ");
            $countStmt->bind_param("issss", $store_id, $start_date_db, $end_date_db, $search_param, $search_param);
            $countStmt->execute();
            $total = $countStmt->get_result()->fetch_row()[0];
            $countStmt->close();

            $totalPages = ceil($total / $limit);
            $offset = ($page - 1) * $limit;

            $stmt = $this->koneksi->prepare("
                SELECT o.*, 
                       (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) as item_count 
                FROM orders o
                WHERE o.store_id = ? AND o.date BETWEEN ? AND ? 
                AND (o.customer_name LIKE ? OR o.nomorator LIKE ?)
                ORDER BY o.order_id DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param("issssii", $store_id, $start_date_db, $end_date_db, $search_param, $search_param, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $all_orders = [];
            while ($row = $result->fetch_assoc()) {
                $all_orders[] = $row;
            }
            $stmt->close();

            if (!empty($all_orders)) {
                $orderIds = array_column($all_orders, 'order_id');
                $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
                $types = str_repeat('i', count($orderIds));

                $sqlPay = "
                    SELECT 
                        order_id,
                        SUM(CASE WHEN status = 'DP' THEN nominal ELSE 0 END) as total_dp,
                        MAX(CASE WHEN status = 'LUNAS' THEN 1 ELSE 0 END) as is_lunas,
                        MAX(CASE WHEN status = 'LUNAS' THEN payment_method ELSE NULL END) as lunas_method,
                        COALESCE(SUM(nominal),0) as total_paid
                    FROM payment
                    WHERE order_id IN ($placeholders)
                    GROUP BY order_id
                ";
                $stmtPay = $this->koneksi->prepare($sqlPay);
                $stmtPay->bind_param($types, ...$orderIds);
                $stmtPay->execute();
                $resPay = $stmtPay->get_result();
                $paymentData = [];
                while ($r = $resPay->fetch_assoc()) {
                    $paymentData[$r['order_id']] = $r;
                }
                $stmtPay->close();

                $sqlProj = "
                    SELECT p1.order_id, p1.status, p1.process, p1.user_id
                    FROM projects p1
                    INNER JOIN (
                        SELECT order_id, MAX(date) as max_date
                        FROM projects
                        WHERE order_id IN ($placeholders)
                        GROUP BY order_id
                    ) p2 
                    ON p1.order_id = p2.order_id 
                    AND p1.date = p2.max_date
                ";
                $stmtProj = $this->koneksi->prepare($sqlProj);
                $stmtProj->bind_param($types, ...$orderIds);
                $stmtProj->execute();
                $resProj = $stmtProj->get_result();
                $projectData = [];
                while ($r = $resProj->fetch_assoc()) {
                    $projectData[$r['order_id']] = $r;
                }
                $stmtProj->close();

                $userIds = [];
                foreach ($all_orders as $o) $userIds[$o['user_id']] = true;
                foreach ($projectData as $p) $userIds[$p['user_id']] = true;
                
                $usersInitial = [];
                if (!empty($userIds)) {
                    $uIds = array_keys($userIds);
                    $uPlaceholders = implode(',', array_fill(0, count($uIds), '?'));
                    $uTypes = str_repeat('i', count($uIds));
                    $stmtUser = $this->koneksi->prepare("SELECT user_id, initial FROM users WHERE user_id IN ($uPlaceholders)");
                    $stmtUser->bind_param($uTypes, ...$uIds);
                    $stmtUser->execute();
                    $resUser = $stmtUser->get_result();
                    while ($u = $resUser->fetch_assoc()) {
                        $usersInitial[$u['user_id']] = $u['initial'];
                    }
                    $stmtUser->close();
                }

                foreach ($all_orders as $row) {
                    $orderId = $row['order_id'];
                    $pay = $paymentData[$orderId] ?? [];
                    $proj = $projectData[$orderId] ?? [];

                    $row['total_paid'] = (float)($pay['total_paid'] ?? 0);
                    $row['total_dp'] = (float)($pay['total_dp'] ?? 0);
                    $row['is_lunas_status'] = $pay['is_lunas'] ?? 0;
                    $row['lunas_method'] = $pay['lunas_method'] ?? '';
                    $row['is_lunas'] = ($row['total'] <= $row['total_paid'] || $row['is_lunas_status'] == 1);
                    
                    $row['project_status'] = $proj['status'] ?? '-';
                    $row['project_process'] = $proj['process'] ?? '';
                    $row['project_user'] = $proj['user_id'] ?? 0;
                    $row['project_initial'] = $usersInitial[$row['project_user']] ?? '';
                    $row['op_initial'] = $usersInitial[$row['user_id']] ?? '-';

                    $orders[] = $row;
                }
            }
        }

        return [
            'stores' => $stores,
            'current_store_id' => $store_id,
            'orders' => $orders,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $total,
            'search' => $search,
            'limit' => $limit,
            'start_date' => $start_date,
            'end_date' => $end_date
        ];
    }

    public function deleteOrder() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        header('Content-Type: application/json');
        require_once __DIR__ . '/../functions/helpers.php';

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
            $stmt = $this->koneksi->prepare("SELECT * FROM orders WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$order) throw new Exception("Order tidak ditemukan");

            $this->koneksi->query("DELETE FROM payment WHERE order_id = $order_id");
            $this->koneksi->query("DELETE FROM projects WHERE order_id = $order_id");
            $this->koneksi->query("DELETE FROM note_orders WHERE order_id = $order_id");
            $this->koneksi->query("DELETE FROM diskon_order_items WHERE order_id = $order_id");

            $title = "HAPUS ORDER";
            $message = "HAPUS ORDERAN DENGAN NAMA " . $order['customer_name'] . " NOMORATOR " . $order['nomorator'];
            $done = 0;
            
            $stmt = $this->koneksi->prepare("INSERT INTO activity (store_id, title, message, information, date, order_id, done, administrator_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssiii", $order['store_id'], $title, $message, $keterangan, $date, $order_id, $done, $administrator_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $this->koneksi->prepare("INSERT INTO deleted_orders (order_id, store_id, nomorator, nomor, customer_name, total, deadline, user_id, system, date, deleted_by, deleted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssisissis", $order['order_id'], $order['store_id'], $order['nomorator'], $order['nomor'], $order['customer_name'], $order['total'], $order['deadline'], $order['user_id'], $order['system'], $order['date'], $administrator_id, $date);
            $stmt->execute();
            $stmt->close();

            $stmtItems = $this->koneksi->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmtItems->bind_param("i", $order_id);
            $stmtItems->execute();
            $resultItems = $stmtItems->get_result();
            $stmtItems->close();

            $stmtInsert = $this->koneksi->prepare("INSERT INTO deleted_order_items (order_item_id, store_id, order_id, product_id, judul, finishing, size, quantity, unit, amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            while ($item = $resultItems->fetch_assoc()) {
                $stmtInsert->bind_param("iiiisssiii", $item['order_item_id'], $item['store_id'], $item['order_id'], $item['product_id'], $item['judul'], $item['finishing'], $item['size'], $item['quantity'], $item['unit'], $item['amount']);
                $stmtInsert->execute();
            }
            $stmtInsert->close();

            $this->koneksi->query("DELETE FROM order_items WHERE order_id = $order_id");
            $this->koneksi->query("DELETE FROM orders WHERE order_id = $order_id");

            $this->koneksi->commit();

            if (function_exists('updateStoreCache')) {
                updateStoreCache($order['store_id'], 'orders');
                updateStoreCache($order['store_id'], 'activities');
            }

            echo json_encode(['success' => true, 'message' => 'Order berhasil dihapus']);
        } catch (Exception $e) {
            $this->koneksi->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function clearOrderItems() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        header('Content-Type: application/json');
        require_once __DIR__ . '/../functions/helpers.php';

        if (!isset($_POST['order_id']) || !isset($_SESSION['admin_logged_in'])) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak atau data tidak valid.']);
            exit;
        }

        $order_id = (int)$_POST['order_id'];

        $this->koneksi->begin_transaction();
        try {
            $this->koneksi->query("DELETE FROM diskon_order_items WHERE order_id = $order_id");
            $this->koneksi->query("DELETE FROM order_items WHERE order_id = $order_id");
            $this->koneksi->query("UPDATE orders SET total = 0 WHERE order_id = $order_id");

            $this->koneksi->commit();
            echo json_encode(['success' => true, 'message' => 'Semua item dalam order berhasil dikosongkan.']);
        } catch (Exception $e) {
            $this->koneksi->rollback();
            echo json_encode(['success' => false, 'message' => 'Gagal mengosongkan item: ' . $e->getMessage()]);
        }
        exit;
    }
}