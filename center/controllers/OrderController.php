<?php
require_once __DIR__ . '/../functions/helpers.php';
require_once __DIR__ . '/../functions/cacheHelper.php';
require_once 'ProductController.php';
require_once 'PaymentController.php';
class OrderController {
    private $koneksi;
    private $productController;
    private $paymentController;

    public function __construct($db) {
        $this->koneksi = $db;
        $this->productController = new ProductController($db);
        $this->paymentController = new PaymentController($db);
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
        require_once __DIR__ . '/../functions/cacheHelper.php';

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

            updateStoreCache($order['store_id'], 'orders');
            updateStoreCache($order['store_id'], 'activities');
            updateOrderTrigger($order['store_id'], $order_id);

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
        require_once __DIR__ . '/../functions/cacheHelper.php';

        if (!isset($_POST['order_id']) || !isset($_SESSION['admin_logged_in'])) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak atau data tidak valid.']);
            exit;
        }

        $order_id = (int)$_POST['order_id'];

        $this->koneksi->begin_transaction();
        try {
            $stmt = $this->koneksi->prepare("SELECT store_id FROM orders WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$order) throw new Exception("Order tidak ditemukan.");
            $store_id = $order['store_id'];

            $this->koneksi->query("DELETE FROM diskon_order_items WHERE order_id = $order_id");
            $this->koneksi->query("DELETE FROM order_items WHERE order_id = $order_id");
            $this->koneksi->query("UPDATE orders SET total = 0 WHERE order_id = $order_id");

            $this->koneksi->commit();
            
            updateStoreCache($store_id, 'orders');
            updateOrderTrigger($store_id, $order_id);
            
            echo json_encode(['success' => true, 'message' => 'Semua item dalam order berhasil dikosongkan.']);
        } catch (Exception $e) {
            $this->koneksi->rollback();
            echo json_encode(['success' => false, 'message' => 'Gagal mengosongkan item: ' . $e->getMessage()]);
        }
        exit;
    }

    public function getOrderById($id) {
        $stmt = $this->koneksi->prepare("SELECT o.*, u.initial AS operator_initial 
                                            FROM orders o
                                            JOIN users u ON o.user_id = u.user_id
                                            WHERE o.order_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function getDiscount($order_id, $product_id) {
        $stmt = $this->koneksi->prepare("SELECT diskon FROM diskon_order_items WHERE order_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $order_id, $product_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result['diskon'] : 0;
    }

    public function checkDiscount($order_id, $product_id) {
        $stmt = $this->koneksi->prepare("SELECT 1 FROM diskon_order_items WHERE order_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $order_id, $product_id);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function createDiscount( $order_id, $product_id, $value ) {
        $stmt = $this->koneksi->prepare("INSERT INTO diskon_order_items (order_id, product_id, diskon) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $order_id, $product_id, $value);
        $stmt->execute();
        $stmt->close();
    }

    public function updateDiscount($order_id, $product_id, $value) {
        $stmt = $this->koneksi->prepare("UPDATE diskon_order_items SET diskon = ? WHERE order_id = ? AND product_id = ?");
        $stmt->bind_param("iii", $value, $order_id, $product_id);
        $stmt->execute();
        $stmt->close();
    }

    public function orderDetail(){
        $order_id = (int)($_GET['order_id'] ?? 0);

        $diskon_per_produk = [];

        $stmt = $this->koneksi->prepare("
            SELECT 
                oi.*, 
                p.name AS product_name, 
                c.name AS category, 
                p.unit_type, 
                p.price, 
                UPPER(COALESCE(c.name, '')) AS category,
                COALESCE(doi.diskon, 0) AS diskon,
                COALESCE(s.name, '') AS maklun_store,
                COALESCE(
                    (SELECT GROUP_CONCAT(f.name SEPARATOR ' ') 
                     FROM finishings f
                     WHERE FIND_IN_SET(f.finishing_id, REPLACE(oi.finishing, ' ', '')) > 0
                    ), '-'
                ) AS finishing_names
            FROM order_items oi
            LEFT JOIN stores s ON oi.maklun = s.store_id
            LEFT JOIN products p ON oi.product_id = p.product_id
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN diskon_order_items doi ON doi.order_id = oi.order_id AND doi.product_id = oi.product_id
            WHERE oi.order_id = ?
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?? [];
        $stmt->close();
        $items_raw = $result;

        $stmt = $this->koneksi->prepare("SELECT * FROM note_orders WHERE order_id = ? AND note_for = 'CTM' ORDER BY note_order_id DESC LIMIT 1");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $note = $result ?? [];

        $stmt = $this->koneksi->prepare("SELECT o.*, u.initial AS operator_initial 
                                            FROM orders o
                                            JOIN users u ON o.user_id = u.user_id
                                            WHERE o.order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $order = $result ?? [];

        array_walk($items_raw, function($row) use (&$diskon_per_produk) {
            if (!empty($row['diskon']) && $row['diskon'] > 0) {
                $diskon_per_produk[$row['judul']] = (int)$row['diskon'];
            }
        });

        $items = array_map(fn($row) => array_merge($row, [
            'category'         => $row['category'] ?? '',
            'product_name' => $row['product_name'] ?? ''
        ]), $items_raw);

        send_json_response(true, 'Berhasil mengambil data item', [
            'order' => $order,
            'total' => $order['total'] ?? 0,
            'items' => $items,
            'diskon_per_produk' => $diskon_per_produk,
            'note' => $note['note'] ?? ''
        ]);
    }

    public function createNote() {
        $store_id = startEnk('dek', $_POST['store']) ?? 0;
        if ($store_id == 0 || $store_id == '') {
            send_json_response(false, "error store");
            exit;
        }
        $note_for = 'CTM';
        $order_id = (int)($_POST['order_id'] ?? 0);
        $note = trim($_POST['note'] ?? '');

        if ($order_id && $note !== '') {
            $existing = $this->getLatestCustomerNote($order_id);

            if ($existing) {
                $stmt = $this->koneksi->prepare("UPDATE note_orders SET note = ? WHERE note_order_id = ?");
                $stmt->bind_param("si", $note, $order_id);
                $stmt->execute();
            } else {
                $stmt = $this->koneksi->prepare("INSERT INTO note_orders (order_id, note, note_for) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $order_id, $note, $note_for);
                $stmt->execute();
            }
            updateOrderTrigger($store_id,$order_id);
            send_json_response(true, 'Note saved successfully.', ['note' => $note]);
            exit;
        }
    }

    public function orderTotal($id) {
        $result = $this->getOrderItemsWithDetails($id);

        $grand_total = 0;
        $outdoorGroups = [];

        foreach ($result as $row) {
            $type = $row['category'];
            $unit_type = $row['unit_type'] ?? '';
            $product_name = $row['product_name'];
            
            $is_outdoor = (($type === 'OUTDOOR' || ($type === 'SUBLIM' && $unit_type == 'M2')) && $product_name != 'ONEWAY');

            if ($is_outdoor) {
                $pid = $row['product_id'];
                
                if (!isset($outdoorGroups[$pid])) {
                    $outdoorGroups[$pid] = [
                        'total_size' => 0,
                        'total_amount' => 0,
                        'harga_per_meter_dasar' => max((float)$row['price'] - (float)($row['diskon'] ?? 0), 0)
                    ];
                }

                $luas = 0;
                if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $row['size'], $matches)) {
                    $luas = floatval($matches[1]) * floatval($matches[2]);
                }
                
                $outdoorGroups[$pid]['total_size'] += ($luas * (int)$row['quantity']);
                $outdoorGroups[$pid]['total_amount'] += (float)$row['amount'];
                
            } else {
                $grand_total += (float)$row['amount'];
            }
        }

        foreach ($outdoorGroups as $group) {
            if ($group['total_size'] > 0 && $group['total_size'] < 1) {
                $harga_full_1_meter = $group['total_amount'] / $group['total_size'];
                $amount_minimal = max($harga_full_1_meter, $group['harga_per_meter_dasar']);
                
                $grand_total += $amount_minimal;
                
            } else {
                $grand_total += $group['total_amount'];
            }
        }

        $grand_total = floor(round($grand_total) / 500) * 500;
        
        return $this->updateOrderTotal($id, $grand_total);
    }
    public function discount( $order_id, $product_id, $diskonInput) {
        if ($diskonInput > 0) {
            if ($this->checkDiscount($order_id, $product_id)) {
                $this->updateDiscount($order_id, $product_id, $diskonInput);
            } else {
                $this->createDiscount($order_id, $product_id, $diskonInput);
            }
        }

        return $this->getDiscount($order_id, $product_id);
    }

    public function paymentStatus($order_id) {
        $total_bayar = (float)$this->paymentController->getPaidByOrderId($order_id);
        $total_order = (float)$this->getOneValue($order_id, 'total');
        $status_bayar = ($total_bayar >= $total_order) ? 'LUNAS' : 'DP';
        $this->paymentController->updateLastStatusPayment($order_id, $status_bayar);
        return true;
    }
    
    private function _prepareItemData($data, $store_id) {
        $order_id = (int)($data['order_id'] ?? 0);
        $product_id = (int)($data['product_id'] ?? 0);
        $judul = trim($data['judul'] ?? '');
        $size = trim($data['size'] ?? '-');
        $quantity = (int)($data['quantity'] ?? 1);

        if ($quantity < 1) $quantity = 1;

        $panjang = (float)($data['panjang'] ?? 0);
        $lebar = (float)($data['lebar'] ?? 0);
        if ($panjang > 0 && $lebar > 0) {
            $size = "{$panjang}x{$lebar}";
        }

        $product = $this->productController->getProductById($product_id);
        if (!$product) {
            return ['error' => 'Produk tidak ditemukan', 'status' => 404];
        }

        if ($product['category'] === 'PAKET INDOOR OUTDOOR') {
            $nama_pencarian = trim($judul . ' ' . $size);
            $produk_baru = $this->productController->getProductByNameAndStore($nama_pencarian, $store_id);
            
            if ($produk_baru) {
                $product_id = $produk_baru['product_id'] ?? $produk_baru['product_id'];
                $product = $produk_baru;
            } else {
                return ['error' => "Produk paket ($nama_pencarian) tidak ditemukan", 'status' => 404];
            }
        }

        $diskonInput = (int)($data['diskon'] ?? 0);
        $diskon = $this->discount($order_id, $product_id, $diskonInput);

        $finishing = trim($data['finishing'] ?? '-');
        $waktu = (float)($data['waktu'] ?? 0);
        $kiloan = (float)($data['kiloan'] ?? 0);

        $stok_butuh = 0;
        if ($product['category'] === 'DTF' && $panjang > 0) {
            $stok_butuh = $panjang * $quantity;
        } elseif ($panjang > 0 && $lebar > 0) {
            $stok_butuh = $panjang * $lebar * $quantity;
        } elseif ($kiloan > 0) {
            $stok_butuh = $kiloan * $quantity;
        } else {
            $stok_butuh = $quantity;
        }

        $fData = $this->finishingData($finishing, $panjang, $lebar);
        $finishing_ids = $fData['ids'] ?? [];
        $finishing_price = $fData['price'] ?? 0;
        $finishing_str = count($finishing_ids) ? implode(',', $finishing_ids) : '-';

        $finishing_to_reduce = [];
        if (!empty($fData['stocks'])) {
            foreach ($fData['stocks'] as $f_stock) {
                $finishing_to_reduce[] = [
                    'finishing_id' => $f_stock['finishing_id'],
                    'qty' => (float)$f_stock['qty'] * $quantity
                ];
            }
        }

        $base_unit_price = $product['price'] - $diskon;
        $pricing = $this->calculatePricingDetails($product, $base_unit_price, $finishing_price, $quantity, $panjang, $lebar, $waktu, $kiloan, $size);

        $unit = is_array($pricing) ? ($pricing['unit'] ?? 0) : ($pricing->unit ?? 0);
        $amount = is_array($pricing) ? ($pricing['amount'] ?? 0) : ($pricing->amount ?? 0);
        $final_size = is_array($pricing) ? ($pricing['size'] ?? $size) : ($pricing->size ?? $size);

        return [
            'success' => true,
            'order_id' => $order_id,
            'product_id' => $product_id,
            'product' => $product,
            'judul' => $judul,
            'size' => $final_size,
            'quantity' => $quantity,
            'stok_butuh' => $stok_butuh,
            'finishing_str' => $finishing_str,
            'finishing_to_reduce' => $finishing_to_reduce,
            'unit' => $unit,
            'amount' => $amount
        ];
    }
    public function finishingData($input_finishing, $panjang, $lebar) {
        try {
            $finishing_ids = [];

            if ($input_finishing !== '-' && !empty($input_finishing)) {
                $ids = explode(',', $input_finishing);
                foreach ($ids as $id) {
                    if (is_numeric(trim($id))) {
                        $finishing_ids[] = (int)trim($id);
                    }
                }
            }

            $unique_ids = array_values(array_unique($finishing_ids));
            $total_price = 0;
            $required_stocks = [];

            if (!empty($unique_ids)) {
                $placeholders = implode(',', array_fill(0, count($unique_ids), '?'));
                $types = str_repeat('i', count($unique_ids));
                
                $stmt = $this->koneksi->prepare("SELECT finishing_id, name, unit_type, price FROM finishings WHERE finishing_id IN ($placeholders)");
                
                if (!$stmt) {
                    throw new Exception("Query Prepare Error: " . $this->koneksi->error);
                }

                $stmt->bind_param($types, ...$unique_ids);
                
                if (!$stmt->execute()) {
                    throw new Exception("Query Execute Error: " . $stmt->error);
                }
                
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $pid = (int)$row['finishing_id'];
                    $price = (float)$row['price'];
                    
                    $qty = 1;
                    if ((float)$panjang > 0 && (float)$lebar > 0) {
                        $qty = (float)$panjang * (float)$lebar;
                    }

                    $total_price += $price;

                    if ($row['unit_type'] !== '~') {
                        $required_stocks[] = [
                            'finishing_id' => $pid,
                            'qty' => $qty
                        ];
                    }
                }
                $stmt->close();
            }

            return [
                'ids' => $unique_ids,
                'price' => $total_price,
                'stocks' => $required_stocks
            ];
            
        } catch (Throwable $e) {
            throw new Exception("finishingData Error: " . $e->getMessage() . " on line " . $e->getLine());
        }
    }
    public function calculatePricingDetails($product, $base_price, $finishing_price, $quantity, $panjang, $lebar, $waktu, $kiloan, $size) {
        try {
            $unit = $base_price + $finishing_price;
            
            $name = $product['name'] ?? '';
            $category = $product['category'] ?? '';
            $unit_type = $product['unit_type'] ?? '';

            if ($unit_type === 'M2') {
                $unit *= ($category === 'DTF') ? $panjang : ($panjang * $lebar);
            }

            if ($unit_type === 'CM2') {
                $unit *= ($panjang * $lebar);
            }

            if ($unit_type === 'PCS' && str_contains($name, 'BAHAN') && $kiloan != 0) {
                $unit *= $kiloan;
                $size = "{$kiloan} KG";
            }

            if ($category === 'JASA') {
                if ($name === 'SETTING') {
                    $waktu = max(15, $waktu);
                    $jam = floor($waktu / 60);
                    $sisa_menit = $waktu % 60;
                    $size = ($waktu >= 60) ? "{$jam} Jam {$sisa_menit} Menit" : "{$waktu} Menit";
                    $unit *= ($waktu / 60);
                }

                if ($name === 'POTONG AKRILIK') {
                    $unit *= $waktu;
                    $size = "{$waktu} MENIT";
                }
            }

            if ($category === 'JERSEY') {
                $extra_charge = ['5XL' => 50000, '4XL' => 40000, '3XL' => 30000, '2XL' => 20000, 'XL' => 10000];
                $unit += $extra_charge[$size] ?? 0;
            }

            $amount = $unit * $quantity;

            if ($category === 'AKRILIK' && $name === 'PRINT UV' && $amount < 7500) {
                $amount = 7500;
            }

            return (object)[
                'unit'   => $unit,
                'size'   => $size,
                'amount' => $amount
            ];
            
        } catch (Throwable $e) {
            throw new Exception("calculatePricingDetails Error: " . $e->getMessage() . " on line " . $e->getLine());
        }
    }

    public function fullPrice() {
        $store_id = startEnk('dek', $_POST['store']) ?? 0;
        if ($store_id == 0 || $store_id == '') {
            send_json_response(false, "error store");
            exit;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $data = $input ?: $_POST;

            if (empty($data['product_id'])) {
                send_json_response(false, 'Product ID tidak valid.');
                exit;
            }

            $itemData = $this->_prepareItemData($data, $store_id);

            if (isset($itemData['error'])) {
                send_json_response(false, $itemData['error']);
                exit;
            }

            send_json_response(true, 'Berhasil menghitung harga total', ['total' => $itemData['amount']]);
            exit;
            
        } catch (Throwable $e) {
            send_json_response(false, 'Debug fullPrice: ' . $e->getMessage());
            exit;
        }
    }

    public function createItem() {
        $store_id = startEnk('dek', $_POST['store']) ?? 0;
        if ($store_id == 0 || $store_id == '') {
            send_json_response(false, "error store");
            exit;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $data = $input ?: $_POST;

            $itemData = $this->_prepareItemData($data, $store_id);

            if (isset($itemData['error'])) {
                http_response_code($itemData['status'] ?? 400);
                send_json_response(false, $itemData['error']);
                exit;
            }

            $product = $itemData['product'];
            $stok_butuh = $itemData['stok_butuh'];

            $existing_stock = $this->productController->getStockByProductId($product['product_id']);
            if ($product['unit_type'] !== '~' && $existing_stock < $stok_butuh) {
                http_response_code(400);
                send_json_response(false, 'Stock Barang Utama tidak mencukupi');
                exit;
            }

            foreach ($itemData['finishing_to_reduce'] as $f_reduce) {
                $f_existing = $this->productController->getFinishingStockByProductId($f_reduce['finishing_id']);
                if ($f_existing < $f_reduce['qty']) {
                    http_response_code(400);
                    send_json_response(false, 'Stock Finishing tidak mencukupi');
                    exit;
                }
            }

            $data_item = (object)[
                'store_id' => $store_id,
                'order_id' => $itemData['order_id'],
                'product_id' => $itemData['product_id'], 
                'judul' => $itemData['judul'],
                'size' => $itemData['size'],
                'quantity' => $itemData['quantity'],
                'unit' => round($itemData['unit'], 2),
                'amount' => round($itemData['amount'], 2),
                'finishing_str' => $itemData['finishing_str']
            ];

            $rowExist = $this->cekOrderItem($itemData['order_id'], $itemData['judul'], $itemData['finishing_str'], $itemData['size']);
            
            if ($rowExist) {
                $data_item->quantity = $rowExist['quantity'] + $itemData['quantity'];
                $data_item->amount = round($itemData['unit'] * $data_item->quantity, 2);
                $data_item->id = $rowExist['order_item_id'];

                if ($this->updateOrderItem($data_item)) {
                    if ($product['unit_type'] !== '~') {
                        $this->productController->reduceStock($stok_butuh, $itemData['product_id']);
                    }
                    foreach ($itemData['finishing_to_reduce'] as $f_reduce) {
                        $this->productController->reduceFinishingStock($f_reduce['qty'], $f_reduce['finishing_id']);
                    }

                    $this->orderTotal($itemData['order_id']);
                    $this->paymentStatus($itemData['order_id']);
                    
                    send_json_response(true, 'Item berhasil diperbarui.');
                    exit;
                } else {
                    http_response_code(500);
                    send_json_response(false, 'Gagal memperbarui item');
                    exit;
                }
            } else {
                if ($this->createOrderItem($data_item)) {
                    if ($product['unit_type'] !== '~') {
                        $this->productController->reduceStock($stok_butuh, $itemData['product_id']);
                    }
                    foreach ($itemData['finishing_to_reduce'] as $f_reduce) {
                        $this->productController->reduceFinishingStock($f_reduce['qty'], $f_reduce['finishing_id']);
                    }

                    $this->orderTotal($itemData['order_id']);
                    $this->paymentStatus($itemData['order_id']);
                    
                    send_json_response(true, 'Item berhasil ditambahkan.');
                    exit;
                } else {
                    http_response_code(500);
                    send_json_response(false, 'Gagal menambahkan item');
                    exit;
                }
            }
            
        } catch (Throwable $e) {
            http_response_code(500);
            send_json_response(false, 'Debug createItem: ' . $e->getMessage());
            exit;
        }
    }

    public function deleteItem() {
        header('Content-Type: application/json');
        $store_id = startEnk('dek', $_POST['store']) ?? 0;
        if ($store_id == 0 || $store_id == '') {
            send_json_response(false, "error store");
            exit;
        }

        $order_item_id = (int)$_POST['order_item_id'] ?? 0;

        if ($order_item_id <= 0) {
            http_response_code(400);
            send_json_response(false, 'ID item tidak valid.');
            exit;
        }

        $item = $this->getOrderItem($order_item_id, $store_id);

        if (!$item) {
            http_response_code(404);
            send_json_response(false, 'Item tidak ditemukan.' . $store_id);
            exit;
        }

        $product_id = $item['product_id'];
        $quantity = $item['quantity'];
        $size = $item['size'];
        $finishing_ids = $item['finishing'];
        $order_id = $item['order_id'];

        $product = $this->productController->getProductById($product_id);
        $unit_type = $product['unit_type'] ?? '';
        $type = $product['category'] ?? '';

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

        $this->productController->addStock($product_id, $stok_kembali);

        if ($finishing_ids !== '-') {
            $finishing_array = explode(',', $finishing_ids);
            foreach ($finishing_array as $fid) {
                $fid = (int)$fid;
                if ($fid === 0) continue;

                $fin_product = $this->productController->getProductById($fid);
                $fin_type = strtoupper($fin_product['category'] ?? '');

                $stok_kembali_fin = $quantity;

                if ($fin_type === 'FINISHING STIKER A3' || $fin_type === 'FINISHING PHOTO A3') {
                    $stok_kembali_fin = 0.1536 * $quantity;
                } elseif ($fin_type === 'FINISHING STIKER PERMETER' || $fin_type === 'FINISHING PHOTO PERMETER') {
                    $panjang_meter = ($panjang > 20) ? $panjang / 100 : $panjang;
                    $lebar_meter = ($lebar > 20) ? $lebar / 100 : $lebar;
                    $stok_kembali_fin = $panjang_meter * $lebar_meter * $quantity;
                }

                $this->productController->addFinishingStock($fid, $stok_kembali_fin);
            }
        }

        if ($this->deleteOrderItem($order_item_id, $store_id)) {
            $this->orderTotal($order_id);
            send_json_response(true, 'Item berhasil dihapus dan stok dikembalikan.');
            exit;
        } else {
            http_response_code(500);
            send_json_response(false, 'Gagal menghapus item.');
            exit;
        }
    }

    public function getNoteOrder(){
        $order_id = $_GET['order_id'] ?? 0;
        $note = $this->getLatestCustomerNote($order_id);
        send_json_response(true, "Note", $note);
    }

    public function deleteOrderItem($order_item_id, $store_id) {
        $stmt = $this->koneksi->prepare("DELETE FROM order_items WHERE order_item_id = ? AND store_id = ?");
        $stmt->bind_param("ii", $order_item_id, $store_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getOrderItemsWithDetails($order_id) {
        $stmt = $this->koneksi->prepare("
            SELECT 
                oi.*, 
                p.name AS product_name, 
                c.name AS category, 
                p.unit_type, 
                p.price, 
                UPPER(COALESCE(c.name, '')) AS category,
                COALESCE(doi.diskon, 0) AS diskon,
                COALESCE(s.name, '') AS maklun_store,
                COALESCE(
                    (SELECT GROUP_CONCAT(f.name SEPARATOR ' ') 
                     FROM finishings f
                     WHERE FIND_IN_SET(f.finishing_id, REPLACE(oi.finishing, ' ', '')) > 0
                    ), '-'
                ) AS finishing_names
            FROM order_items oi
            LEFT JOIN stores s ON oi.maklun = s.store_id
            LEFT JOIN products p ON oi.product_id = p.product_id
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN diskon_order_items doi ON doi.order_id = oi.order_id AND doi.product_id = oi.product_id
            WHERE oi.order_id = ?
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function getOrderItem($order_item_id, $store_id) {
        $stmt = $this->koneksi->prepare("SELECT * FROM order_items WHERE order_item_id = ? AND store_id = ?");
        $stmt->bind_param("ii", $order_item_id, $store_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function getLatestCustomerNote($order_id) {
        $stmt = $this->koneksi->prepare("SELECT * FROM note_orders WHERE order_id = ? AND note_for = 'CTM' ORDER BY note_order_id DESC LIMIT 1");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result ?? [];
    }

    public function updateOrderTotal($id, $value) {
        $stmt = $this->koneksi->prepare("UPDATE orders SET total = ? WHERE order_id = ?");
        $stmt->bind_param("ii", $value, $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getOneValue($id, $column){
        $columnName = ['store_id', 'nomorator', 'nomor', 'customer_name', 'total', 'deadline', 'user_id', 'system', 'date'];
        if (!in_array($column, $columnName)) {
            return ''; 
        }
        $stmt = $this->koneksi->prepare("SELECT `{$column}` FROM orders WHERE order_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result[$column] : '';
    }

    public function cekOrderItem($order_id, $judul, $finishing, $size){
        $stmt = $this->koneksi->prepare("SELECT order_item_id, quantity, unit, amount FROM order_items WHERE order_id = ? AND judul = ? AND finishing = ? AND size = ?");
        $stmt->bind_param("isss", $order_id, $judul, $finishing, $size);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function createOrderItem($data){
        $stmt = $this->koneksi->prepare("INSERT INTO order_items (store_id, order_id, product_id, judul, size, quantity, unit, amount, finishing) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssiiis", $data->store_id, $data->order_id, $data->product_id, $data->judul, $data->size, $data->quantity, $data->unit, $data->amount, $data->finishing_str);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateOrderItem($data) {
        $stmt = $this->koneksi->prepare("UPDATE order_items SET quantity = ?, unit = ?, amount = ? WHERE order_item_id = ?");
        $stmt->bind_param("iddi", $data->quantity, $data->unit, $data->amount, $data->id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

}