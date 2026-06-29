<?php
require_once BASE_PATH . '/models/Order.php';
require_once BASE_PATH . '/models/User.php';
require_once BASE_PATH . '/models/Project.php';
require_once BASE_PATH . '/models/Product.php';
require_once BASE_PATH . '/models/Stock.php';
require_once BASE_PATH . '/models/Activity.php';
require_once BASE_PATH . '/models/Payment.php';
require_once BASE_PATH . '/functions/helpers.php';

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

        $data = new stdClass();
        $data->order_id = (int)($_POST['order_id'] ?? 0);
        $data->store_id = $store_id;
        $data->user_id = $user_id;
        $data->system = ($this->userModel->getOneValue($user_id, 'role') === 'ONLINE') ? 'ONLINE' : 'OFFLINE';
            
        if ($data->order_id > 0) {
            $data->nomorator = trim($_POST['nomorator'] ?? '');
        } else {
            $data->nomorator = $this->nomorator($data->store_id, $data->system);
        }

        $data->customer_name = trim($_POST['customer_name'] ?? '');
        $data->nomor = trim($_POST['nomor'] ?? '');
        $data->total = 0;
        
        $data->deadline = $deadline_input ? date('Y-m-d H:i:s', strtotime($deadline_input)) : null;
        
        $data->date = trim($_POST['date'] ?? date('Y-m-d H:i:s')); 

        return $data;
    }

    public function index() {
        global $is_all_access;
        global $search_text;
        global $store_id;
        global $customerLimit;
        global $start_date;
        global $end_date;
        global $system;
        global $koneksi;
        global $usersInitial;

        $all_orders = $this->orderModel->getFilteredOrders(
            $is_all_access, $search_text, $store_id, $customerLimit, $start_date, $end_date, $system
        );

        $paymentData = [];
        $projectData = [];

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

            $stmt = $koneksi->prepare($sqlPay);
            $stmt->bind_param($types, ...$orderIds);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $paymentData[$row['order_id']] = $row;
            }
            $stmt->close();

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

            $stmtProj = $koneksi->prepare($sqlProj);
            $stmtProj->bind_param($types, ...$orderIds);
            $stmtProj->execute();
            $resultProj = $stmtProj->get_result();
            while ($row = $resultProj->fetch_assoc()) {
                $projectData[$row['order_id']] = $row;
            }
            $stmtProj->close();
        }

        $ordersOnline = [];
        $ordersOffline = [];

        foreach ($all_orders as $row) {
            $orderId = $row['order_id'];
            $pay = $paymentData[$orderId] ?? [];
            $proj = $projectData[$orderId] ?? [];

            $row['total_paid'] = $pay['total_paid'] ?? 0;
            $row['total_dp'] = $pay['total_dp'] ?? 0;
            $row['is_lunas_status'] = $pay['is_lunas'] ?? 0;
            $row['lunas_method'] = $pay['lunas_method'] ?? '';
            $row['is_lunas'] = ($row['total'] <= $row['total_paid']);
            
            $row['project_status'] = $proj['status'] ?? '';
            $row['project_process'] = $proj['process'] ?? '';
            $row['project_user'] = $proj['user_id'] ?? 0;
            $row['project_initial'] = $usersInitial[$row['project_user']] ?? '';
            $row['op_initial'] = $usersInitial[$row['user_id']] ?? '-';

            if ($row['system'] === 'ONLINE') {
                $ordersOnline[] = $row;
            } else {
                $ordersOffline[] = $row;
            }
        }

        return [
            'offline' => $ordersOffline,
            'online' => $ordersOnline
        ];
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
            send_json_response(false, 'Validasi gagal. Data tidak lengkap atau deadline tidak valid.');
            exit;
        }

        $data = $this->requestData();
        $insert = $this->orderModel->createOrder($data);

        if ($insert) {
            require_once BASE_PATH . '/global_functions.php';
            $order_id = $this->koneksi->insert_id;
            $data->order_id = $order_id;
            $this->projectModel->createProject($data);
            
            send_json_response(true, 'Order berhasil ditambahkan', ['order_id' => $order_id, 'id' => startEnk('enk', $order_id)]);
            exit;
        } else {
            send_json_response(false, 'Gagal menambahkan order');
            exit;
        }
    }

    public function nomorator($store_id, $sys) {

        $session = 0;
        $lastNomorator = 0;

        if ($sys == 'OFFLINE') {
            $maxNomorator = 199999;
            $defaultStart = 100001;
        }elseif ($sys == 'ONLINE'){
            $defaultStart = 200001;
            $maxNomorator = 299999;
        }

        $stmt = $this->koneksi->prepare("
            SELECT session, last_nomorator 
            FROM nomorator_sessions 
            WHERE store_id = ? AND system = ?
            ORDER BY session DESC LIMIT 1
        ");
        $stmt->bind_param("is", $store_id, $sys);
        $stmt->execute();
        $stmt->bind_result($session, $lastNomorator);
        $found = $stmt->fetch();
        $stmt->close();

        if ($found) {
            if ($lastNomorator >= $maxNomorator) {
                $session += 1;
                $nextNomorator = $defaultStart;

                $stmtInsert = $this->koneksi->prepare("
                    INSERT INTO nomorator_sessions (store_id, system, session, last_nomorator) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmtInsert->bind_param("isii", $store_id, $sys, $session, $nextNomorator);
                $stmtInsert->execute();
                $stmtInsert->close();
            } else {
                $nextNomorator = $lastNomorator + 1;

                $stmtUpdate = $this->koneksi->prepare("
                    UPDATE nomorator_sessions 
                    SET last_nomorator = ? 
                    WHERE store_id = ? AND system = ? AND session = ?
                ");
                $stmtUpdate->bind_param("iisi", $nextNomorator, $store_id, $sys, $session);
                $stmtUpdate->execute();
                $stmtUpdate->close();
            }
        } else {
            $session = 1;
            $nextNomorator = $defaultStart;

            $stmtInsert = $this->koneksi->prepare("
                INSERT INTO nomorator_sessions (store_id, system, session, last_nomorator) 
                VALUES (?, ?, ?, ?)
            ");
            $stmtInsert->bind_param("isii", $store_id, $sys, $session, $nextNomorator);
            $stmtInsert->execute();
            $stmtInsert->close();
        }

        return str_pad($nextNomorator, 6, '0', STR_PAD_LEFT);
    }

    public function update() {
        $data = $this->requestData();
        $data->system = trim($_POST['sistem'] ?? 'OFFLINE');

        if ($data->order_id === 0 || $data->store_id === 0 || $data->user_id === 0 || empty($data->nomorator) || empty($data->customer_name)) {
            $_SESSION['error'] = "Data tidak lengkap.";
            header("Location: index");
            exit;
        }

        if (!$this->userModel->checkValidOperator($data->user_id, $data->store_id)) {
            $_SESSION['error'] = "Operator tidak valid.";
            header("Location: index");
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
            send_json_response(false, 'Akses ditolak atau data tidak valid.');
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
            send_json_response(true, 'Order berhasil dihapus');

        } catch (Exception $e) {
            $this->koneksi->rollback();
            send_json_response(false, $e->getMessage());
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

    public function saveNote($note_for) {
        $order_id = (int)($_POST['order_id'] ?? 0);
        $note = trim($_POST['note'] ?? '');

        if ($order_id && $note !== '') {
            $existing = $this->orderModel->getLatestCustomerNote($order_id, $note_for);

            if ($existing) {
                $this->orderModel->updateNote((int)$existing['note_order_id'], $note);
            } else {
                $this->orderModel->createNote($order_id, $note, $note_for);
            }

            echo sanitize($note);
            exit;
        }
    }

    public function deleteItem() {
        header('Content-Type: application/json');
        global $store_id;

        $order_item_id = (int)$_POST['order_item_id'] ?? 0;

        if ($order_item_id <= 0) {
            http_response_code(400);
            send_json_response(false, 'ID item tidak valid.');
            exit;
        }

        $item = $this->orderModel->getOrderItem($order_item_id, $store_id);

        if (!$item) {
            http_response_code(404);
            send_json_response(false, 'Item tidak ditemukan.');
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
            $this->orderTotal($order_id);
            send_json_response(true, 'Item berhasil dihapus dan stok dikembalikan.');
            exit;
        } else {
            http_response_code(500);
            send_json_response(false, 'Gagal menghapus item.');
            exit;
        }
    }

    public function finishingData($input_finishing, $jersey_ids, $use_cut, $use_die, $product_type, $panjang, $lebar) {
        global $store_id;

        $finishing_ids = [];

        if ($input_finishing !== '-' && !empty($input_finishing)) {
            $ids = explode(',', $input_finishing);
            foreach ($ids as $id) {
                if (is_numeric(trim($id))) $finishing_ids[] = (int)trim($id);
            }
        }

        if (!empty($jersey_ids) && is_array($jersey_ids)) {
            $finishing_ids = array_merge($finishing_ids, array_map('intval', $jersey_ids));
        }

        $searchFinishingId = function($name) use ($store_id, $product_type) {
            $type = ($product_type === 'INDOOR') ? 'FINISHING INDOOR' : 'FINISHING LASER A3';
            $stmt = $this->koneksi->prepare("SELECT product_id FROM products WHERE type = ? AND name = ? AND store_id = ?");
            $stmt->bind_param("ssi", $type, $name, $store_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $res ? (int)$res['product_id'] : null;
        };

        if ($use_cut) {
            $id = $searchFinishingId('KISS CUT');
            if ($id) $finishing_ids[] = $id;
        }
        if ($use_die) {
            $id = $searchFinishingId('DIE CUT');
            if ($id) $finishing_ids[] = $id;
        }

        $unique_ids = array_unique($finishing_ids);
        $total_price = 0;
        $required_stocks = [];

        if (!empty($unique_ids)) {
            $placeholders = implode(',', array_fill(0, count($unique_ids), '?'));
            $types = str_repeat('i', count($unique_ids));
            
            $stmt = $this->koneksi->prepare("SELECT product_id, name, unit_type, price FROM products WHERE product_id IN ($placeholders)");
            $stmt->bind_param($types, ...$unique_ids);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $pid = (int)$row['product_id'];
                $price = (float)$row['price'];
                
                $qty = 1;
                if ((float)$panjang > 0 && (float)$lebar > 0) {
                    $qty = (float)$panjang * (float)$lebar;
                }

                $total_price += $price;

                if ($row['unit_type'] !== '~') {
                    $required_stocks[] = [
                        'product_id' => $pid,
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
    }

    public function calculatePricingDetails($product, $base_price, $finishing_price, $quantity, $panjang, $lebar, $waktu, $kiloan, $size) {
        
        $unit = $base_price + $finishing_price;
        
        $name = $product['name'] ?? '';
        $type = $product['type'] ?? '';
        $unit_type = $product['unit_type'] ?? '';

        if ($unit_type === 'M2') {
            $unit *= ($type === 'DTF') ? $panjang : ($panjang * $lebar);
        }

        if ($unit_type === 'CM2') {
            $unit *= ($panjang * $lebar);
        }

        if ($unit_type === 'PCS' && str_contains($name, 'BAHAN') && $kiloan != 0) {
            $unit *= $kiloan;
            $size = "{$kiloan} KG";
        }

        if ($type === 'JASA') {
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

        if ($type === 'JERSEY') {
            $extra_charge = ['5XL' => 50000, '4XL' => 40000, '3XL' => 30000, '2XL' => 20000, 'XL' => 10000];
            $unit += $extra_charge[$size] ?? 0;
        }

        $amount = $unit * $quantity;

        if ($type === 'AKRILIK' && $name === 'PRINT UV' && $amount < 7500) {
            $amount = 7500;
        }

        return (object)[
            'unit'   => $unit,
            'size'   => $size,
            'amount' => $amount
        ];
    }

    public function orderTotal($id) {
        $result = $this->orderModel->getOrderItemsWithDetails($id);

        $grand_total = 0;
        $outdoorGroups = [];

        foreach ($result as $row) {
            $type = $row['type'];
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
        
        return $this->orderModel->updateOrderTotal($id, $grand_total);
    }

    public function discount( $order_id, $product_id, $diskonInput) {
        if ($diskonInput > 0) {
            if ($this->orderModel->checkDiscount($order_id, $product_id)) {
                $this->orderModel->updateDiscount($order_id, $product_id, $diskonInput);
            } else {
                $this->orderModel->createDiscount($order_id, $product_id, $diskonInput);
            }
        }

        return $this->orderModel->getDiscount($order_id, $product_id);
    }

    public function paymentStatus($order_id) {
        $total_bayar = (float)$this->paymentModel->getPaidByOrderId($order_id);
        $total_order = (float)$this->orderModel->getOneValue($order_id, 'total');
        $status_bayar = ($total_bayar >= $total_order) ? 'LUNAS' : 'DP';
        $this->paymentModel->updateLastStatusPayment($order_id, $status_bayar);
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

        $product = $this->productModel->getProductById($product_id);
        if (!$product) {
            return ['error' => 'Produk tidak ditemukan', 'status' => 404];
        }

        if ($product['type'] === 'PAKET INDOOR OUTDOOR') {
            $nama_pencarian = trim($judul . ' ' . $size);
            $produk_baru = $this->productModel->getProductByNameAndStore($nama_pencarian, $store_id);
            
            if ($produk_baru) {
                $product_id = $produk_baru['id'] ?? $produk_baru['product_id'];
                $product = $produk_baru;
            } else {
                return ['error' => "Produk paket ($nama_pencarian) tidak ditemukan", 'status' => 404];
            }
        }

        $diskonInput = (int)($data['diskon'] ?? 0);
        $diskon = $this->discount($order_id, $product_id, $diskonInput);

        $finishing = trim($data['finishing'] ?? '-');
        $waktu = (float)($data['waktu'] ?? 0);
        $finishing_cut = ($data['finishing_cut'] ?? '') == '1';
        $finishing_die = ($data['finishing_die'] ?? '') == '1';
        $finishing_jersey = $data['finishingJersey'] ?? $data['finishing_jersey'] ?? [];
        $kiloan = (float)($data['kiloan'] ?? 0);

        $stok_butuh = 0;
        if ($product['type'] === 'DTF' && $panjang > 0) {
            $stok_butuh = $panjang * $quantity;
        } elseif ($panjang > 0 && $lebar > 0) {
            $stok_butuh = $panjang * $lebar * $quantity;
        } elseif ($kiloan > 0) {
            $stok_butuh = $kiloan * $quantity;
        } else {
            $stok_butuh = $quantity;
        }

        $fData = $this->finishingData($finishing, $finishing_jersey, $finishing_cut, $finishing_die, $product['type'], $panjang, $lebar);
        $finishing_ids = $fData['ids'] ?? [];
        $finishing_price = $fData['price'] ?? 0;
        $finishing_str = count($finishing_ids) ? implode(',', $finishing_ids) : '-';

        $finishing_to_reduce = [];
        if (!empty($fData['stocks'])) {
            foreach ($fData['stocks'] as $f_stock) {
                $finishing_to_reduce[] = [
                    'product_id' => $f_stock['product_id'],
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

    public function fullPrice() {
        global $store_id;

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
    }

    public function createItem() {
        global $store_id;

        $input = json_decode(file_get_contents('php://input'), true);
        $data = $input ?: $_POST;

        $itemData = $this->_prepareItemData($data, $store_id);

        if (isset($itemData['error'])) {
            http_response_code($itemData['status']);
            send_json_response(false, $itemData['error']);
            exit;
        }

        $product = $itemData['product'];
        $stok_butuh = $itemData['stok_butuh'];

        // Cek Stok Barang Utama
        $existing_stock = $this->stockModel->getStockById($itemData['product_id']);
        if ($product['unit_type'] !== '~' && $existing_stock < $stok_butuh) {
            http_response_code(400);
            send_json_response(false, 'Stock Barang Utama tidak mencukupi');
            exit;
        }

        foreach ($itemData['finishing_to_reduce'] as $f_reduce) {
            $f_existing = $this->stockModel->getStockById($f_reduce['product_id']);
            if ($f_existing < $f_reduce['qty']) {
                // http_response_code(400);
                // send_json_response(false, 'Stock Finishing tidak mencukupi');
                // exit;
            }
        }

        $data_item = (object)[
            'store_id' => $store_id,
            'order_id' => $itemData['order_id'],
            'product_id' => $itemData['product_id'], 
            'judul' => $itemData['judul'],
            'size' => $itemData['size'],
            'quantity' => $itemData['quantity'],
            'unit' => $itemData['unit'],
            'amount' => $itemData['amount'],
            'finishing_str' => $itemData['finishing_str']
        ];

        $rowExist = $this->orderModel->cekOrderItem($itemData['order_id'], $itemData['judul'], $itemData['finishing_str'], $itemData['size']);
        
        if ($rowExist) {
            $data_item->quantity = $rowExist['quantity'] + $itemData['quantity'];
            $data_item->amount = $itemData['unit'] * $data_item->quantity;
            $data_item->id = $rowExist['order_item_id'];

            if ($this->orderModel->updateOrderItem($data_item)) {
                if ($product['unit_type'] !== '~') {
                    $this->stockModel->reduceStock($stok_butuh, $itemData['product_id']);
                }
                foreach ($itemData['finishing_to_reduce'] as $f_reduce) {
                    $this->stockModel->reduceStock($f_reduce['qty'], $f_reduce['product_id']);
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
            if ($this->orderModel->createOrderItem($data_item)) {
                if ($product['unit_type'] !== '~') {
                    $this->stockModel->reduceStock($stok_butuh, $itemData['product_id']);
                }
                foreach ($itemData['finishing_to_reduce'] as $f_reduce) {
                    $this->stockModel->reduceStock($f_reduce['qty'], $f_reduce['product_id']);
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
    }

    public function get_order_items($order_id){
        $total = $this->orderModel->getOneValue($order_id, 'total');
        $items_raw = $this->orderModel->getOrderItemsWithDetails($order_id);
        $diskon_per_produk = [];

        array_walk($items_raw, function($row) use (&$diskon_per_produk) {
            if (!empty($row['diskon']) && $row['diskon'] > 0) {
                $diskon_per_produk[$row['judul']] = (int)$row['diskon'];
            }
        });

        $items = array_map(fn($row) => array_merge($row, [
            'type'         => $row['type'] ?? '',
            'product_name' => $row['product_name'] ?? '',
        ]), $items_raw);

        send_json_response(true, 'Berhasil mengambil data item', [
            'total' => $total,
            'items' => $items,
            'diskon_per_produk' => $diskon_per_produk
        ]);
    }

    public function updateProject(){
        date_default_timezone_set('Asia/Jakarta');
;
        $order_ids = $_POST['order_ids'] ?? '';

        if ($order_ids) {
            if (!is_array($order_ids)) {
                $order_ids = explode(',', $order_ids);
            }

            foreach ($order_ids as $order_id) {
                $data = new stdClass();
                $data->id = $order_id;

                $status_terakhir = $this->projectModel->getLastProjectStatusByOrderId($data->id);
                $data->process = $_POST['status'] ?? '';
                $data->status = $status_terakhir;
                $data->user_id = $_POST['user_id'] ?? 0;
                $data->order_id = $order_id;
                $data->date = date('Y-m-d H:i:s');
                $this->projectModel->updateProject($data);
            }
        }

        header("Location: index");
        exit;
    }

}