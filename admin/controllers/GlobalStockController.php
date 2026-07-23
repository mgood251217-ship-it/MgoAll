<?php
require_once BASE_PATH . '/models/GlobalStock.php';
require_once BASE_PATH . '/functions/helpers.php';

class GlobalStockController {
    private $globalStockModel;
    private $koneksi;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
        $this->globalStockModel = new GlobalStock($koneksi);
    }

    public function index() {
        $selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
        $days_in_month = date('t', strtotime($selected_month . '-01'));

        $available_stores = $this->getAvailableStores();
        $other_stores_stocks = $this->getOtherStoresStocks();
        $deliveries = $this->getDeliveries();
        $categories = $this->getCategories();
        
        $stock_data = $this->getGroupedStocks();

        return [
            'days_in_month' => $days_in_month,
            'available_stores' => $available_stores,
            'other_stores_stocks' => $other_stores_stocks,
            'deliveries' => $deliveries,
            'categories' => $categories,
            'stocks_list' => $stock_data['stocks_list'],
            'awal_list' => $stock_data['awal_list'],
            'daily_list' => $stock_data['daily_list'],
            'grouped_stocks' => $stock_data['grouped_stocks']
        ];
    }

    public function getAvailableStores() {
        global $store_id;
        
        $stmt = $this->koneksi->prepare("SELECT store_id, name FROM stores WHERE store_id != ? ORDER BY name ASC");
        $stmt->bind_param("i", $store_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $result;
    }

    public function getOtherStoresStocks() {
        global $store_id;
        
        $stmt = $this->koneksi->prepare("
            SELECT gs.id, gs.store_id, gs.name, gs.size, gsc.name as category_name, gs.price
            FROM global_stocks gs
            JOIN global_stock_categories gsc ON gs.global_stock_category_id = gsc.id
            WHERE gs.store_id != ?
            ORDER BY gsc.name ASC, gs.name ASC
        ");
        $stmt->bind_param("i", $store_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $result;
    }

    public function getDeliveries() {
        global $store_id;
        $selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
        $selected_month_param = $selected_month . '%';
        
        $stmt = $this->koneksi->prepare("
            SELECT 
                d.id, d.qty, d.date, 
                s_from.name AS from_store, 
                s_to.name AS to_store, 
                d.to_store_id, 
                gs_from.name AS item_name, 
                gs_from.size AS item_size,
                gs_from.price, 
                d.store_id AS sender_id
            FROM global_stock_deliveries d
            JOIN stores s_from ON d.store_id = s_from.store_id
            JOIN stores s_to ON d.to_store_id = s_to.store_id
            JOIN global_stocks gs_from ON d.global_stock_id = gs_from.id
            WHERE (d.store_id = ? OR d.to_store_id = ?)
            AND d.date LIKE ? 
            ORDER BY d.date ASC, d.id ASC
        ");
        $stmt->bind_param("iis", $store_id, $store_id, $selected_month_param);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $result;
    }

    public function getCategories() {
        global $store_id;
        
        $stmt = $this->koneksi->prepare("
            SELECT *
            FROM global_stock_categories
            WHERE store_id = ?
            ORDER BY name ASC
        ");
        $stmt->bind_param("i", $store_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $result;
    }

    public function getGroupedStocks() {
        global $store_id;
        $selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
        $selected_month_param = $selected_month . '%';

        $stmtStocks = $this->koneksi->prepare("
            SELECT
                gs.*,
                gsc.name AS category_name
            FROM global_stocks gs
            JOIN global_stock_categories gsc
                ON gsc.id = gs.global_stock_category_id
            WHERE gs.store_id = ?
            ORDER BY gsc.name ASC, gs.name ASC, gs.size ASC
        ");
        $stmtStocks->bind_param("i", $store_id);
        $stmtStocks->execute();
        $stocks_list = $stmtStocks->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtStocks->close();

        $stocks = [];
        foreach ($stocks_list as $row) {
            $id = $row['id'];
            $stocks[$id] = $row;
            $stocks[$id]['sa_awal'] = 0;
            $stocks[$id]['sa_akhir'] = 0;
            $stocks[$id]['daily'] = [];
            
            for ($i = 1; $i <= 31; $i++) {
                $stocks[$id]['daily'][$i] = ['sm' => 0, 'sk' => 0];
            }
        }

        $stmtAwal = $this->koneksi->prepare("
            SELECT global_stock_id, initial_stock, final_stock
            FROM global_stock_monthly_values
            WHERE store_id = ? AND month_year = ?
        ");
        $stmtAwal->bind_param("is", $store_id, $selected_month);
        $stmtAwal->execute();
        $awal_list = $stmtAwal->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtAwal->close();

        foreach ($awal_list as $row) {
            if (isset($stocks[$row['global_stock_id']])) {
                $stocks[$row['global_stock_id']]['sa_awal'] = $row['initial_stock'];
                $stocks[$row['global_stock_id']]['sa_akhir'] = $row['final_stock'];
            }
        }

        $stmtDaily = $this->koneksi->prepare("
            SELECT global_stock_id, DAY(date) as day_date, stock_in, stock_out
            FROM global_stock_daily_values
            WHERE store_id = ? AND date LIKE ?
        ");
        $stmtDaily->bind_param("is", $store_id, $selected_month_param);
        $stmtDaily->execute();
        $daily_list = $stmtDaily->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtDaily->close();

        foreach ($daily_list as $row) {
            if (isset($stocks[$row['global_stock_id']])) {
                $day = (int)$row['day_date'];
                $stocks[$row['global_stock_id']]['daily'][$day]['sm'] += $row['stock_in'];
                $stocks[$row['global_stock_id']]['daily'][$day]['sk'] += $row['stock_out'];
            }
        }

        $grouped_stocks = [];
        foreach ($stocks as $id => $s) {
            $cat_name = $s['category_name'];
            $item_name = $s['name'];
            
            if (!isset($grouped_stocks[$cat_name])) {
                $grouped_stocks[$cat_name] = [];
            }
            if (!isset($grouped_stocks[$cat_name][$item_name])) {
                $grouped_stocks[$cat_name][$item_name] = [];
            }
            $grouped_stocks[$cat_name][$item_name][$id] = $s;
        }

        return [
            'stocks_list' => $stocks_list,
            'awal_list' => $awal_list,
            'daily_list' => $daily_list,
            'grouped_stocks' => $grouped_stocks
        ];
    }

    public function getStockByStoreId() {
        $store_id = isset($_GET['store_id']) ? $_GET['store_id'] : 0;
        
        $stmt = $this->koneksi->prepare("
            SELECT gs.id, gs.store_id, gs.name, gs.size, gsc.name as category_name, gs.price
            FROM global_stocks gs
            JOIN global_stock_categories gsc ON gs.global_stock_category_id = gsc.id
            WHERE gs.store_id = ?
            ORDER BY gsc.name ASC, gs.name ASC
        ");
        $stmt->bind_param("i", $store_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $result;
    }

    public function createCategory() {
        global $store_id;
        header('Content-Type: application/json');
        $data = new stdClass();
        $data->name = $_POST['name'] ?? '';
        $data->store_id = $store_id;

        if ($this->globalStockModel->createGlobalStockCategory($data)) {
            send_json_response(true, 'Kategori berhasil ditambahkan.');
        } else {
            send_json_response(false, 'Gagal menambahkan kategori.');
        }
        exit;
    }

    public function updateCategory() {
        header('Content-Type: application/json');

        global $store_id;
        $data = new stdClass();
        $data->id = $_POST['id'] ?? 0;
        $data->name = $_POST['name'] ?? '';

        if ($this->globalStockModel->updateGlobalStockCategory($data)) {
            send_json_response(true, 'Kategori berhasil diperbarui.');
        } else {
            send_json_response(false, 'Gagal memperbarui kategori.');
        }
        exit;
    }

    public function deleteCategory() {
        global $store_id;
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] != true) {
            echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Anda bukan admin.']);
            exit;
        }
        
        $id = (int) $_POST['id'];

        $qItems = $this->koneksi->query("SELECT id FROM global_stocks WHERE global_stock_category_id = $id AND store_id = '$store_id'");
        $stock_ids = [];
        while($row = $qItems->fetch_assoc()) {
            $stock_ids[] = $row['id'];
        }

        if (count($stock_ids) > 0) {
            $ids_str = implode(',', $stock_ids);
            $this->koneksi->query("DELETE FROM global_stock_daily_values WHERE global_stock_id IN ($ids_str)");
            $this->koneksi->query("DELETE FROM global_stock_monthly_values WHERE global_stock_id IN ($ids_str)");
            $this->koneksi->query("DELETE FROM global_stock_deliveries WHERE global_stock_id IN ($ids_str) OR to_global_stock_id IN ($ids_str)");
        }

        $this->koneksi->query("DELETE FROM global_stocks WHERE global_stock_category_id = $id AND store_id = '$store_id'");

        $stmt = $this->koneksi->prepare("DELETE FROM global_stock_categories WHERE id = ? AND store_id = ?");
        $stmt->bind_param("is", $id, $store_id);
        if ($stmt->execute()) {
            send_json_response(true, "Kategori dan seluruh barang di dalamnya berhasil dihapus.");
        } else {
            send_json_response(false, "Gagal menghapus kategori");
        }
        exit;
    }

    public function createStock() {
        global $store_id;
        header('Content-Type: application/json');
        $data = new stdClass();
        $data->name = $_POST['name'] ?? '';
        $data->size = $_POST['size'] ?? '';
        $data->price = $_POST['price'] ?? 0;
        $data->category_id = $_POST['category_id'] ?? 0;
        $data->store_id = $store_id ?? 0;

        if ($this->globalStockModel->createGlobalStock($data)) {
            send_json_response(true, 'Barang stok berhasil ditambahkan.');
        } else {
            send_json_response(false, 'Gagal menambahkan barang stok.');
        }
        exit;
    }

    public function updateStock() {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        header('Content-Type: application/json');
        $data = new stdClass();
        $data->id = $_POST['id'] ?? 0;
        $data->name = $_POST['name'] ?? '';
        $data->size = $_POST['size'] ?? '';
        $data->price = $_POST['price'] ?? 0;
        $data->category_id = $_POST['category_id'] ?? 0;

        if ($this->globalStockModel->updateGlobalStock($data)) {
            send_json_response(true, 'Barang stok berhasil diperbarui.');
        } else {
            send_json_response(false, 'Gagal memperbarui barang stok.');
        }
        exit;
    }

    public function deleteStock() {
        global $store_id;
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] != true) {
            echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Anda bukan admin.']);
            exit;   
        }
        
        $id = (int) $_POST['id'];

        $this->koneksi->query("DELETE FROM global_stock_daily_values WHERE global_stock_id = $id");
        $this->koneksi->query("DELETE FROM global_stock_monthly_values WHERE global_stock_id = $id");
        $this->koneksi->query("DELETE FROM global_stock_deliveries WHERE global_stock_id = $id OR to_global_stock_id = $id");

        $stmt = $this->koneksi->prepare("DELETE FROM global_stocks WHERE id = ? AND store_id = ?");
        $stmt->bind_param("is", $id, $store_id);
        if ($stmt->execute()) {
            send_json_response(true, "Barang beserta riwayatnya berhasil dihapus.");
        } else {
            send_json_response(false, "Gagal menghapus barang");
        }
        exit;
    }

    public function sendStock() {
        global $store_id;
        $global_stock_id     = (int) $_POST['global_stock_id'];
        $to_store_id         = $_POST['to_store_id'];
        $to_global_stock_id_input = $_POST['to_global_stock_id']; 
        $qty                 = (double) $_POST['qty'];
        $date                = $_POST['date'];
        $month_year          = date('Y-m', strtotime($date));

        $qItem = $this->koneksi->prepare("
            SELECT gs.name, gs.size, gs.price, gsc.name as cat_name 
            FROM global_stocks gs 
            JOIN global_stock_categories gsc ON gs.global_stock_category_id = gsc.id 
            WHERE gs.id = ? AND gs.store_id = ?
        ");
        $qItem->bind_param("is", $global_stock_id, $store_id);
        $qItem->execute();
        $item = $qItem->get_result()->fetch_assoc();

        if (!$item) {
            $_SESSION['error'] = "Barang tidak ditemukan atau tidak memiliki akses.";
            echo json_encode(['status' => 'error', 'message' => 'Barang tidak ditemukan.']);
            exit;
        }

        $to_global_stock_id = 0;

        if ($to_global_stock_id_input === 'NEW') {
            $qCatTujuan = $this->koneksi->prepare("SELECT id FROM global_stock_categories WHERE store_id = ? AND name = ?");
            $qCatTujuan->bind_param("ss", $to_store_id, $item['cat_name']);
            $qCatTujuan->execute();
            $resCat = $qCatTujuan->get_result();
            
            if ($resCat->num_rows > 0) {
                $to_cat_id = $resCat->fetch_assoc()['id'];
            } else {
                $insCat = $this->koneksi->prepare("INSERT INTO global_stock_categories (store_id, name) VALUES (?, ?)");
                $insCat->bind_param("ss", $to_store_id, $item['cat_name']);
                $insCat->execute();
                $to_cat_id = $insCat->insert_id;
            }

            // Bawa juga harga saat bikin barang baru di toko tujuan otomatis
            $insStock = $this->koneksi->prepare("INSERT INTO global_stocks (store_id, global_stock_category_id, name, size, price) VALUES (?, ?, ?, ?, ?)");
            $insStock->bind_param("sissd", $to_store_id, $to_cat_id, $item['name'], $item['size'], $item['price']);
            $insStock->execute();
            $to_global_stock_id = $insStock->insert_id;

        } else {
            $to_global_stock_id = (int) $to_global_stock_id_input;
        }

        $insDel = $this->koneksi->prepare("INSERT INTO global_stock_deliveries (store_id, to_store_id, global_stock_id, to_global_stock_id, qty, date) VALUES (?, ?, ?, ?, ?, ?)");
        $insDel->bind_param("ssiids", $store_id, $to_store_id, $global_stock_id, $to_global_stock_id, $qty, $date);
        $insDel->execute();

        $qSender = $this->koneksi->prepare("SELECT id, stock_out FROM global_stock_daily_values WHERE global_stock_id = ? AND store_id = ? AND date = ?");
        $qSender->bind_param("iss", $global_stock_id, $store_id, $date);
        $qSender->execute();
        $resSender = $qSender->get_result();
        if ($resSender->num_rows > 0) {
            $rowS = $resSender->fetch_assoc();
            $upd1 = $this->koneksi->prepare("UPDATE global_stock_daily_values SET stock_out = ? WHERE id = ?");
            $new_sk = $rowS['stock_out'] + $qty;
            $upd1->bind_param("di", $new_sk, $rowS['id']);
            $upd1->execute();
        } else {
            $ins1 = $this->koneksi->prepare("INSERT INTO global_stock_daily_values (global_stock_id, store_id, stock_in, stock_out, date) VALUES (?, ?, 0, ?, ?)");
            $ins1->bind_param("isds", $global_stock_id, $store_id, $qty, $date);
            $ins1->execute();
        }

        $qReceiver = $this->koneksi->prepare("SELECT id, stock_in FROM global_stock_daily_values WHERE global_stock_id = ? AND store_id = ? AND date = ?");
        $qReceiver->bind_param("iss", $to_global_stock_id, $to_store_id, $date);
        $qReceiver->execute();
        $resReceiver = $qReceiver->get_result();
        if ($resReceiver->num_rows > 0) {
            $rowR = $resReceiver->fetch_assoc();
            $upd2 = $this->koneksi->prepare("UPDATE global_stock_daily_values SET stock_in = ? WHERE id = ?");
            $new_sm = $rowR['stock_in'] + $qty;
            $upd2->bind_param("di", $new_sm, $rowR['id']);
            $upd2->execute();
        } else {
            $ins2 = $this->koneksi->prepare("INSERT INTO global_stock_daily_values (global_stock_id, store_id, stock_in, stock_out, date) VALUES (?, ?, ?, 0, ?)");
            $ins2->bind_param("isds", $to_global_stock_id, $to_store_id, $qty, $date);
            $ins2->execute();
        }

        $targets_to_recalc = [
            ['id' => $global_stock_id, 'store' => $store_id],
            ['id' => $to_global_stock_id, 'store' => $to_store_id]
        ];

        foreach ($targets_to_recalc as $target) {
            $t_id = $target['id'];
            $t_store = $target['store'];

            $q_prev = $this->koneksi->prepare("SELECT final_stock FROM global_stock_monthly_values WHERE global_stock_id = ? AND store_id = ? AND month_year < ? ORDER BY month_year DESC LIMIT 1");
            $q_prev->bind_param("iss", $t_id, $t_store, $month_year);
            $q_prev->execute();
            $res_prev = $q_prev->get_result();
            $prev_final = $res_prev->num_rows > 0 ? $res_prev->fetch_assoc()['final_stock'] : 0;

            $q_check_m = $this->koneksi->prepare("SELECT id FROM global_stock_monthly_values WHERE global_stock_id = ? AND store_id = ? AND month_year = ?");
            $q_check_m->bind_param("iss", $t_id, $t_store, $month_year);
            $q_check_m->execute();
            if ($q_check_m->get_result()->num_rows === 0) {
                $q_ins_m = $this->koneksi->prepare("INSERT INTO global_stock_monthly_values (global_stock_id, store_id, month_year, initial_stock, final_stock) VALUES (?, ?, ?, ?, ?)");
                $q_ins_m->bind_param("issdd", $t_id, $t_store, $month_year, $prev_final, $prev_final);
                $q_ins_m->execute();
            }

            $q_months = $this->koneksi->prepare("SELECT id, month_year, initial_stock FROM global_stock_monthly_values WHERE global_stock_id = ? AND store_id = ? AND month_year >= ? ORDER BY month_year ASC");
            $q_months->bind_param("iss", $t_id, $t_store, $month_year);
            $q_months->execute();
            $months_res = $q_months->get_result();

            $running_initial = null;
            while ($m_row = $months_res->fetch_assoc()) {
                $m_id = $m_row['id'];
                $m_my = $m_row['month_year'];
                
                if ($running_initial !== null) {
                    $m_initial = $running_initial;
                    $upd_init = $this->koneksi->prepare("UPDATE global_stock_monthly_values SET initial_stock = ? WHERE id = ?");
                    $upd_init->bind_param("di", $m_initial, $m_id);
                    $upd_init->execute();
                } else {
                    $m_initial = $m_row['initial_stock'];
                }

                $like_m = $m_my . '%';
                $q_sum = $this->koneksi->prepare("SELECT SUM(stock_in) as sm, SUM(stock_out) as sk FROM global_stock_daily_values WHERE global_stock_id = ? AND store_id = ? AND date LIKE ?");
                $q_sum->bind_param("iss", $t_id, $t_store, $like_m);
                $q_sum->execute();
                $sums = $q_sum->get_result()->fetch_assoc();
                
                $m_final = $m_initial + ($sums['sm'] ?? 0) - ($sums['sk'] ?? 0);

                $upd_fin = $this->koneksi->prepare("UPDATE global_stock_monthly_values SET final_stock = ? WHERE id = ?");
                $upd_fin->bind_param("di", $m_final, $m_id);
                $upd_fin->execute();

                $running_initial = $m_final;
            }

            $upd_main = $this->koneksi->prepare("UPDATE global_stocks SET current_stock = (SELECT final_stock FROM global_stock_monthly_values WHERE global_stock_id = ? ORDER BY month_year DESC LIMIT 1) WHERE id = ?");
            $upd_main->bind_param("ii", $t_id, $t_id);
            $upd_main->execute();
        }
        send_json_response(true, 'Barang berhasil dikirim dan terhubung dengan stok toko tujuan.');
        exit;
    }

    public function updateDailyStock() {
        global $store_id;
        $global_stock_id = (int) $_POST['global_stock_id'];
        $stock_in        = (double) $_POST['stock_in'];
        $stock_out       = (double) $_POST['stock_out'];
        $date            = $_POST['date'];
        $month_year      = date('Y-m', strtotime($date));

        $check = $this->koneksi->prepare("SELECT id FROM global_stock_daily_values WHERE global_stock_id = ? AND store_id = ? AND date = ?");
        $check->bind_param("iss", $global_stock_id, $store_id, $date);
        $check->execute();
        $res = $check->get_result();

        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $upd = $this->koneksi->prepare("UPDATE global_stock_daily_values SET stock_in = ?, stock_out = ? WHERE id = ?");
            $upd->bind_param("ddi", $stock_in, $stock_out, $row['id']);
            $upd->execute();
        } else {
            $ins = $this->koneksi->prepare("INSERT INTO global_stock_daily_values (global_stock_id, store_id, stock_in, stock_out, date) VALUES (?, ?, ?, ?, ?)");
            $ins->bind_param("isdds", $global_stock_id, $store_id, $stock_in, $stock_out, $date);
            $ins->execute();
        }

        $q_prev = $this->koneksi->prepare("SELECT final_stock FROM global_stock_monthly_values WHERE global_stock_id = ? AND store_id = ? AND month_year < ? ORDER BY month_year DESC LIMIT 1");
        $q_prev->bind_param("iss", $global_stock_id, $store_id, $month_year);
        $q_prev->execute();
        $res_prev = $q_prev->get_result();
        $prev_final = $res_prev->num_rows > 0 ? $res_prev->fetch_assoc()['final_stock'] : 0;

        $q_check_m = $this->koneksi->prepare("SELECT id FROM global_stock_monthly_values WHERE global_stock_id = ? AND store_id = ? AND month_year = ?");
        $q_check_m->bind_param("iss", $global_stock_id, $store_id, $month_year);
        $q_check_m->execute();
        if ($q_check_m->get_result()->num_rows === 0) {
            $q_ins_m = $this->koneksi->prepare("INSERT INTO global_stock_monthly_values (global_stock_id, store_id, month_year, initial_stock, final_stock) VALUES (?, ?, ?, ?, ?)");
            $q_ins_m->bind_param("issdd", $global_stock_id, $store_id, $month_year, $prev_final, $prev_final);
            $q_ins_m->execute();
        }

        $q_months = $this->koneksi->prepare("SELECT id, month_year, initial_stock FROM global_stock_monthly_values WHERE global_stock_id = ? AND store_id = ? AND month_year >= ? ORDER BY month_year ASC");
        $q_months->bind_param("iss", $global_stock_id, $store_id, $month_year);
        $q_months->execute();
        $months_res = $q_months->get_result();

        $running_initial = null;
        while ($m_row = $months_res->fetch_assoc()) {
            $m_id = $m_row['id'];
            $m_my = $m_row['month_year'];
            
            if ($running_initial !== null) {
                $m_initial = $running_initial;
                $upd_init = $this->koneksi->prepare("UPDATE global_stock_monthly_values SET initial_stock = ? WHERE id = ?");
                $upd_init->bind_param("di", $m_initial, $m_id);
                $upd_init->execute();
            } else {
                $m_initial = $m_row['initial_stock'];
            }

            $like_m = $m_my . '%';
            $q_sum = $this->koneksi->prepare("SELECT SUM(stock_in) as sm, SUM(stock_out) as sk FROM global_stock_daily_values WHERE global_stock_id = ? AND store_id = ? AND date LIKE ?");
            $q_sum->bind_param("iss", $global_stock_id, $store_id, $like_m);
            $q_sum->execute();
            $sums = $q_sum->get_result()->fetch_assoc();
            
            $m_final = $m_initial + ($sums['sm'] ?? 0) - ($sums['sk'] ?? 0);

            $upd_fin = $this->koneksi->prepare("UPDATE global_stock_monthly_values SET final_stock = ? WHERE id = ?");
            $upd_fin->bind_param("di", $m_final, $m_id);
            $upd_fin->execute();

            $running_initial = $m_final;
        }

        $upd_main = $this->koneksi->prepare("UPDATE global_stocks SET current_stock = (SELECT final_stock FROM global_stock_monthly_values WHERE global_stock_id = ? ORDER BY month_year DESC LIMIT 1) WHERE id = ?");
        $upd_main->bind_param("ii", $global_stock_id, $global_stock_id);
        $upd_main->execute();

        send_json_response(true, "Data stok berhasil diperbarui.");
        exit;
    }

    public function exportCsv() {
        global $store_id;
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] != true) {
            die("Akses ditolak.");
        }

        $filename = "Master_Barang_" . date('Y-m-d') . ".csv";

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        fputcsv($output, ['Nama Kategori', 'Nama Barang', 'Ukuran', 'Harga Dasar'], ",", '"', "");

        $query = "
            SELECT c.name as cat_name, s.name as item_name, s.size, s.price 
            FROM global_stocks s 
            JOIN global_stock_categories c ON s.global_stock_category_id = c.id 
            WHERE s.store_id = '$store_id'
            ORDER BY c.name ASC, s.name ASC
        ";
        $result = mysqli_query($this->koneksi, $query);

        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['cat_name'], 
                $row['item_name'], 
                $row['size'], 
                $row['price']
            ], ",", '"', "");
        }
        fclose($output);
        exit;

    }

    public function importCsv() {
        global $store_id;
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] != true) {
            die("Akses ditolak.");
        }
        
        $imported_count = 0;
        $updated_count = 0;

        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, "r");
            
            fgetcsv($handle, 1000, ",", "\"", "\\");
            
            while (($data = fgetcsv($handle, 1000, ",", "\"", "\\")) !== FALSE) {
                if (count($data) == 1 && strpos($data[0], ';') !== false) {
                    $data = explode(';', $data[0]);
                }

                $cat_name  = isset($data[0]) ? trim(mysqli_real_escape_string($this->koneksi, $data[0])) : '';
                $item_name = isset($data[1]) ? trim(mysqli_real_escape_string($this->koneksi, $data[1])) : '';
                $size      = isset($data[2]) ? trim(mysqli_real_escape_string($this->koneksi, $data[2])) : '';
                $price     = isset($data[3]) ? (float)$data[3] : 0;
                
                if (empty($cat_name) || empty($item_name)) continue;

                $cat_name_lower = strtolower($cat_name);
                $item_name_lower = strtolower($item_name);
                $size_lower = strtolower($size);

                $cekCat = mysqli_query($this->koneksi, "SELECT id FROM global_stock_categories WHERE LOWER(name) = '$cat_name_lower' AND store_id = '$store_id'");
                if (mysqli_num_rows($cekCat) > 0) {
                    $rowCat = mysqli_fetch_assoc($cekCat);
                    $cat_id = $rowCat['id'];
                } else {
                    mysqli_query($this->koneksi, "INSERT INTO global_stock_categories (store_id, name) VALUES ('$store_id', '$cat_name')");
                    $cat_id = mysqli_insert_id($this->koneksi);
                }

                $cekItem = mysqli_query($this->koneksi, "SELECT id, price FROM global_stocks WHERE LOWER(name) = '$item_name_lower' AND LOWER(size) = '$size_lower' AND global_stock_category_id = '$cat_id' AND store_id = '$store_id'");
                
                if (mysqli_num_rows($cekItem) == 0) {
                    mysqli_query($this->koneksi, "INSERT INTO global_stocks (store_id, global_stock_category_id, name, size, price) VALUES ('$store_id', '$cat_id', '$item_name', '$size', '$price')");
                    $imported_count++;
                } else {
                    $rowItem = mysqli_fetch_assoc($cekItem);
                    
                    if ($price > 0) {
                        $item_id = $rowItem['id'];
                        mysqli_query($this->koneksi, "UPDATE global_stocks SET price = '$price' WHERE id = '$item_id'");
                        $updated_count++;
                    }
                }
            }
            fclose($handle);
            
            $_SESSION['success'] = "Berhasil: $imported_count barang baru ditambahkan, dan harga $updated_count barang di-update.";
        } else {
            $_SESSION['error'] = "Gagal mengunggah file CSV.";
        }
        
        send_json_response(true, "Berhasil import product");
    }
    
}