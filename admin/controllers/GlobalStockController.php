<?php
require_once BASE_PATH . '/models/GlobalStock.php';
require_once BASE_PATH . '/functions/helpers.php';

class GlobalStockController {
    private $globalStockModel;

    public function __construct($koneksi) {
        $this->globalStockModel = new GlobalStock($koneksi);
    }

    public function createCategory() {
        header('Content-Type: application/json');
        $data = new stdClass();
        $data->name = $_POST['name'] ?? '';
        $data->store_id = $_POST['store_id'] ?? 0;

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

    public function createStock() {
        header('Content-Type: application/json');
        $data = new stdClass();
        $data->name = $_POST['name'] ?? '';
        $data->size = $_POST['size'] ?? '';
        $data->price = $_POST['price'] ?? 0;
        $data->category_id = $_POST['category_id'] ?? 0;
        $data->store_id = $_POST['store_id'] ?? 0;

        if ($this->globalStockModel->createGlobalStock($data)) {
            send_json_response(true, 'Barang stok berhasil ditambahkan.');
        } else {
            send_json_response(false, 'Gagal menambahkan barang stok.');
        }
        exit;
    }

    public function updateStock() {
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

    // public function sendStock() {
    //     header('Content-Type: application/json');
    //     global $koneksi, $store_id;

    //     $global_stock_id     = (int) $_POST['global_stock_id'];
    //     $to_store_id         = $_POST['to_store_id'];
    //     $to_global_stock_id_input = $_POST['to_global_stock_id']; 
    //     $qty                 = $_POST['qty'];
    //     $date                = $_POST['date'];

    //     if ($this->globalStockModel->sendGlobalStock($global_stock_id, $to_store_id, $to_global_stock_id_input, $qty, $date, $store_id)) {
    //         send_json_response(true, 'Barang berhasil dikirim dan terhubung dengan stok toko tujuan.');
    //     } else {
    //         send_json_response(false, 'Gagal mengirim barang.');
    //     }
    //     exit;
    // }


    // if ($action === 'send_stock') {
    //     $global_stock_id     = (int) $_POST['global_stock_id'];
    //     $to_store_id         = $_POST['to_store_id'];
    //     $to_global_stock_id_input = $_POST['to_global_stock_id']; 
    //     $qty                 = (double) $_POST['qty'];
    //     $date                = $_POST['date'];
    //     $month_year          = date('Y-m', strtotime($date));

    //     $qItem = $koneksi->prepare("
    //         SELECT gs.name, gs.size, gs.price, gsc.name as cat_name 
    //         FROM global_stocks gs 
    //         JOIN global_stock_categories gsc ON gs.global_stock_category_id = gsc.id 
    //         WHERE gs.id = ? AND gs.store_id = ?
    //     ");
    //     $qItem->bind_param("is", $global_stock_id, $store_id);
    //     $qItem->execute();
    //     $item = $qItem->get_result()->fetch_assoc();

    //     if (!$item) {
    //         $_SESSION['error'] = "Barang tidak ditemukan atau tidak memiliki akses.";
    //         echo json_encode(['status' => 'error', 'message' => 'Barang tidak ditemukan.']);
    //         exit;
    //     }

    //     $to_global_stock_id = 0;

    //     if ($to_global_stock_id_input === 'NEW') {
    //         $qCatTujuan = $koneksi->prepare("SELECT id FROM global_stock_categories WHERE store_id = ? AND name = ?");
    //         $qCatTujuan->bind_param("ss", $to_store_id, $item['cat_name']);
    //         $qCatTujuan->execute();
    //         $resCat = $qCatTujuan->get_result();
            
    //         if ($resCat->num_rows > 0) {
    //             $to_cat_id = $resCat->fetch_assoc()['id'];
    //         } else {
    //             $insCat = $koneksi->prepare("INSERT INTO global_stock_categories (store_id, name) VALUES (?, ?)");
    //             $insCat->bind_param("ss", $to_store_id, $item['cat_name']);
    //             $insCat->execute();
    //             $to_cat_id = $insCat->insert_id;
    //         }

    //         // Bawa juga harga saat bikin barang baru di toko tujuan otomatis
    //         $insStock = $koneksi->prepare("INSERT INTO global_stocks (store_id, global_stock_category_id, name, size, price) VALUES (?, ?, ?, ?, ?)");
    //         $insStock->bind_param("sissd", $to_store_id, $to_cat_id, $item['name'], $item['size'], $item['price']);
    //         $insStock->execute();
    //         $to_global_stock_id = $insStock->insert_id;

    //     } else {
    //         $to_global_stock_id = (int) $to_global_stock_id_input;
    //     }

    //     $insDel = $koneksi->prepare("INSERT INTO global_stock_deliveries (store_id, to_store_id, global_stock_id, to_global_stock_id, qty, date) VALUES (?, ?, ?, ?, ?, ?)");
    //     $insDel->bind_param("ssiids", $store_id, $to_store_id, $global_stock_id, $to_global_stock_id, $qty, $date);
    //     $insDel->execute();

    //     $qSender = $koneksi->prepare("SELECT id, stock_out FROM global_stock_daily_values WHERE global_stock_id = ? AND store_id = ? AND date = ?");
    //     $qSender->bind_param("iss", $global_stock_id, $store_id, $date);
    //     $qSender->execute();
    //     $resSender = $qSender->get_result();
    //     if ($resSender->num_rows > 0) {
    //         $rowS = $resSender->fetch_assoc();
    //         $upd1 = $koneksi->prepare("UPDATE global_stock_daily_values SET stock_out = ? WHERE id = ?");
    //         $new_sk = $rowS['stock_out'] + $qty;
    //         $upd1->bind_param("di", $new_sk, $rowS['id']);
    //         $upd1->execute();
    //     } else {
    //         $ins1 = $koneksi->prepare("INSERT INTO global_stock_daily_values (global_stock_id, store_id, stock_in, stock_out, date) VALUES (?, ?, 0, ?, ?)");
    //         $ins1->bind_param("isds", $global_stock_id, $store_id, $qty, $date);
    //         $ins1->execute();
    //     }

    //     $qReceiver = $koneksi->prepare("SELECT id, stock_in FROM global_stock_daily_values WHERE global_stock_id = ? AND store_id = ? AND date = ?");
    //     $qReceiver->bind_param("iss", $to_global_stock_id, $to_store_id, $date);
    //     $qReceiver->execute();
    //     $resReceiver = $qReceiver->get_result();
    //     if ($resReceiver->num_rows > 0) {
    //         $rowR = $resReceiver->fetch_assoc();
    //         $upd2 = $koneksi->prepare("UPDATE global_stock_daily_values SET stock_in = ? WHERE id = ?");
    //         $new_sm = $rowR['stock_in'] + $qty;
    //         $upd2->bind_param("di", $new_sm, $rowR['id']);
    //         $upd2->execute();
    //     } else {
    //         $ins2 = $koneksi->prepare("INSERT INTO global_stock_daily_values (global_stock_id, store_id, stock_in, stock_out, date) VALUES (?, ?, ?, 0, ?)");
    //         $ins2->bind_param("isds", $to_global_stock_id, $to_store_id, $qty, $date);
    //         $ins2->execute();
    //     }

    //     $targets_to_recalc = [
    //         ['id' => $global_stock_id, 'store' => $store_id],
    //         ['id' => $to_global_stock_id, 'store' => $to_store_id]
    //     ];

    //     foreach ($targets_to_recalc as $target) {
    //         $t_id = $target['id'];
    //         $t_store = $target['store'];

    //         $q_prev = $koneksi->prepare("SELECT final_stock FROM global_stock_monthly_values WHERE global_stock_id = ? AND store_id = ? AND month_year < ? ORDER BY month_year DESC LIMIT 1");
    //         $q_prev->bind_param("iss", $t_id, $t_store, $month_year);
    //         $q_prev->execute();
    //         $res_prev = $q_prev->get_result();
    //         $prev_final = $res_prev->num_rows > 0 ? $res_prev->fetch_assoc()['final_stock'] : 0;

    //         $q_check_m = $koneksi->prepare("SELECT id FROM global_stock_monthly_values WHERE global_stock_id = ? AND store_id = ? AND month_year = ?");
    //         $q_check_m->bind_param("iss", $t_id, $t_store, $month_year);
    //         $q_check_m->execute();
    //         if ($q_check_m->get_result()->num_rows === 0) {
    //             $q_ins_m = $koneksi->prepare("INSERT INTO global_stock_monthly_values (global_stock_id, store_id, month_year, initial_stock, final_stock) VALUES (?, ?, ?, ?, ?)");
    //             $q_ins_m->bind_param("issdd", $t_id, $t_store, $month_year, $prev_final, $prev_final);
    //             $q_ins_m->execute();
    //         }

    //         $q_months = $koneksi->prepare("SELECT id, month_year, initial_stock FROM global_stock_monthly_values WHERE global_stock_id = ? AND store_id = ? AND month_year >= ? ORDER BY month_year ASC");
    //         $q_months->bind_param("iss", $t_id, $t_store, $month_year);
    //         $q_months->execute();
    //         $months_res = $q_months->get_result();

    //         $running_initial = null;
    //         while ($m_row = $months_res->fetch_assoc()) {
    //             $m_id = $m_row['id'];
    //             $m_my = $m_row['month_year'];
                
    //             if ($running_initial !== null) {
    //                 $m_initial = $running_initial;
    //                 $upd_init = $koneksi->prepare("UPDATE global_stock_monthly_values SET initial_stock = ? WHERE id = ?");
    //                 $upd_init->bind_param("di", $m_initial, $m_id);
    //                 $upd_init->execute();
    //             } else {
    //                 $m_initial = $m_row['initial_stock'];
    //             }

    //             $like_m = $m_my . '%';
    //             $q_sum = $koneksi->prepare("SELECT SUM(stock_in) as sm, SUM(stock_out) as sk FROM global_stock_daily_values WHERE global_stock_id = ? AND store_id = ? AND date LIKE ?");
    //             $q_sum->bind_param("iss", $t_id, $t_store, $like_m);
    //             $q_sum->execute();
    //             $sums = $q_sum->get_result()->fetch_assoc();
                
    //             $m_final = $m_initial + ($sums['sm'] ?? 0) - ($sums['sk'] ?? 0);

    //             $upd_fin = $koneksi->prepare("UPDATE global_stock_monthly_values SET final_stock = ? WHERE id = ?");
    //             $upd_fin->bind_param("di", $m_final, $m_id);
    //             $upd_fin->execute();

    //             $running_initial = $m_final;
    //         }

    //         $upd_main = $koneksi->prepare("UPDATE global_stocks SET current_stock = (SELECT final_stock FROM global_stock_monthly_values WHERE global_stock_id = ? ORDER BY month_year DESC LIMIT 1) WHERE id = ?");
    //         $upd_main->bind_param("ii", $t_id, $t_id);
    //         $upd_main->execute();
    //     }

    //     $_SESSION['success'] = "Barang berhasil dikirim dan terhubung dengan stok toko tujuan.";
    //     echo json_encode(['status' => 'success']);
    //     exit;
    // }

    // if ($action === 'update_daily_stock') {
    //     $global_stock_id = (int) $_POST['global_stock_id'];
    //     $stock_in        = (double) $_POST['stock_in'];
    //     $stock_out       = (double) $_POST['stock_out'];
    //     $date            = $_POST['date'];
    //     $month_year      = date('Y-m', strtotime($date));

    //     $check = $koneksi->prepare("SELECT id FROM global_stock_daily_values WHERE global_stock_id = ? AND store_id = ? AND date = ?");
    //     $check->bind_param("iss", $global_stock_id, $store_id, $date);
    //     $check->execute();
    //     $res = $check->get_result();

    //     if ($res->num_rows > 0) {
    //         $row = $res->fetch_assoc();
    //         $upd = $koneksi->prepare("UPDATE global_stock_daily_values SET stock_in = ?, stock_out = ? WHERE id = ?");
    //         $upd->bind_param("ddi", $stock_in, $stock_out, $row['id']);
    //         $upd->execute();
    //     } else {
    //         $ins = $koneksi->prepare("INSERT INTO global_stock_daily_values (global_stock_id, store_id, stock_in, stock_out, date) VALUES (?, ?, ?, ?, ?)");
    //         $ins->bind_param("isdds", $global_stock_id, $store_id, $stock_in, $stock_out, $date);
    //         $ins->execute();
    //     }

    //     $q_prev = $koneksi->prepare("SELECT final_stock FROM global_stock_monthly_values WHERE global_stock_id = ? AND store_id = ? AND month_year < ? ORDER BY month_year DESC LIMIT 1");
    //     $q_prev->bind_param("iss", $global_stock_id, $store_id, $month_year);
    //     $q_prev->execute();
    //     $res_prev = $q_prev->get_result();
    //     $prev_final = $res_prev->num_rows > 0 ? $res_prev->fetch_assoc()['final_stock'] : 0;

    //     $q_check_m = $koneksi->prepare("SELECT id FROM global_stock_monthly_values WHERE global_stock_id = ? AND store_id = ? AND month_year = ?");
    //     $q_check_m->bind_param("iss", $global_stock_id, $store_id, $month_year);
    //     $q_check_m->execute();
    //     if ($q_check_m->get_result()->num_rows === 0) {
    //         $q_ins_m = $koneksi->prepare("INSERT INTO global_stock_monthly_values (global_stock_id, store_id, month_year, initial_stock, final_stock) VALUES (?, ?, ?, ?, ?)");
    //         $q_ins_m->bind_param("issdd", $global_stock_id, $store_id, $month_year, $prev_final, $prev_final);
    //         $q_ins_m->execute();
    //     }

    //     $q_months = $koneksi->prepare("SELECT id, month_year, initial_stock FROM global_stock_monthly_values WHERE global_stock_id = ? AND store_id = ? AND month_year >= ? ORDER BY month_year ASC");
    //     $q_months->bind_param("iss", $global_stock_id, $store_id, $month_year);
    //     $q_months->execute();
    //     $months_res = $q_months->get_result();

    //     $running_initial = null;
    //     while ($m_row = $months_res->fetch_assoc()) {
    //         $m_id = $m_row['id'];
    //         $m_my = $m_row['month_year'];
            
    //         if ($running_initial !== null) {
    //             $m_initial = $running_initial;
    //             $upd_init = $koneksi->prepare("UPDATE global_stock_monthly_values SET initial_stock = ? WHERE id = ?");
    //             $upd_init->bind_param("di", $m_initial, $m_id);
    //             $upd_init->execute();
    //         } else {
    //             $m_initial = $m_row['initial_stock'];
    //         }

    //         $like_m = $m_my . '%';
    //         $q_sum = $koneksi->prepare("SELECT SUM(stock_in) as sm, SUM(stock_out) as sk FROM global_stock_daily_values WHERE global_stock_id = ? AND store_id = ? AND date LIKE ?");
    //         $q_sum->bind_param("iss", $global_stock_id, $store_id, $like_m);
    //         $q_sum->execute();
    //         $sums = $q_sum->get_result()->fetch_assoc();
            
    //         $m_final = $m_initial + ($sums['sm'] ?? 0) - ($sums['sk'] ?? 0);

    //         $upd_fin = $koneksi->prepare("UPDATE global_stock_monthly_values SET final_stock = ? WHERE id = ?");
    //         $upd_fin->bind_param("di", $m_final, $m_id);
    //         $upd_fin->execute();

    //         $running_initial = $m_final;
    //     }

    //     $upd_main = $koneksi->prepare("UPDATE global_stocks SET current_stock = (SELECT final_stock FROM global_stock_monthly_values WHERE global_stock_id = ? ORDER BY month_year DESC LIMIT 1) WHERE id = ?");
    //     $upd_main->bind_param("ii", $global_stock_id, $global_stock_id);
    //     $upd_main->execute();

    //     $_SESSION['success'] = "Data stok berhasil diperbarui.";
    //     echo json_encode(['status' => 'success']);
    //     exit;
    // }

    // if ($action === 'export_csv') {
    //     if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] != true) {
    //         die("Akses ditolak.");
    //     }

    //     $filename = "Master_Barang_" . date('Y-m-d') . ".csv";
        
    //     header('Content-Type: text/csv');
    //     header('Content-Disposition: attachment; filename="' . $filename . '"');
        
    //     $output = fopen('php://output', 'w');
        
    //     fputcsv($output, ['Nama Kategori', 'Nama Barang', 'Ukuran', 'Harga Dasar']);
        
    //     $query = "
    //         SELECT c.name as cat_name, s.name as item_name, s.size, s.price 
    //         FROM global_stocks s 
    //         JOIN global_stock_categories c ON s.global_stock_category_id = c.id 
    //         WHERE s.store_id = '$store_id'
    //         ORDER BY c.name ASC, s.name ASC
    //     ";
    //     $result = mysqli_query($koneksi, $query);
        
    //     while ($row = mysqli_fetch_assoc($result)) {
    //         fputcsv($output, [
    //             $row['cat_name'], 
    //             $row['item_name'], 
    //             $row['size'], 
    //             $row['price']
    //         ]);
    //     }
    //     fclose($output);
    //     exit;
    // }

    // if ($action === 'import_csv') {
    //     if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] != true) {
    //         die("Akses ditolak.");
    //     }
        
    //     $imported_count = 0;
    //     $updated_count = 0;

    //     if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
    //         $file = $_FILES['csv_file']['tmp_name'];
    //         $handle = fopen($file, "r");
            
    //         fgetcsv($handle, 1000, ",", "\"", "\\");
            
    //         while (($data = fgetcsv($handle, 1000, ",", "\"", "\\")) !== FALSE) {
    //             if (count($data) == 1 && strpos($data[0], ';') !== false) {
    //                 $data = explode(';', $data[0]);
    //             }

    //             $cat_name  = isset($data[0]) ? trim(mysqli_real_escape_string($koneksi, $data[0])) : '';
    //             $item_name = isset($data[1]) ? trim(mysqli_real_escape_string($koneksi, $data[1])) : '';
    //             $size      = isset($data[2]) ? trim(mysqli_real_escape_string($koneksi, $data[2])) : '';
    //             $price     = isset($data[3]) ? (float)$data[3] : 0;
                
    //             if (empty($cat_name) || empty($item_name)) continue;

    //             $cat_name_lower = strtolower($cat_name);
    //             $item_name_lower = strtolower($item_name);
    //             $size_lower = strtolower($size);

    //             $cekCat = mysqli_query($koneksi, "SELECT id FROM global_stock_categories WHERE LOWER(name) = '$cat_name_lower' AND store_id = '$store_id'");
    //             if (mysqli_num_rows($cekCat) > 0) {
    //                 $rowCat = mysqli_fetch_assoc($cekCat);
    //                 $cat_id = $rowCat['id'];
    //             } else {
    //                 mysqli_query($koneksi, "INSERT INTO global_stock_categories (store_id, name) VALUES ('$store_id', '$cat_name')");
    //                 $cat_id = mysqli_insert_id($koneksi);
    //             }

    //             $cekItem = mysqli_query($koneksi, "SELECT id, price FROM global_stocks WHERE LOWER(name) = '$item_name_lower' AND LOWER(size) = '$size_lower' AND global_stock_category_id = '$cat_id' AND store_id = '$store_id'");
                
    //             if (mysqli_num_rows($cekItem) == 0) {
    //                 mysqli_query($koneksi, "INSERT INTO global_stocks (store_id, global_stock_category_id, name, size, price) VALUES ('$store_id', '$cat_id', '$item_name', '$size', '$price')");
    //                 $imported_count++;
    //             } else {
    //                 $rowItem = mysqli_fetch_assoc($cekItem);
                    
    //                 if ($price > 0) {
    //                     $item_id = $rowItem['id'];
    //                     mysqli_query($koneksi, "UPDATE global_stocks SET price = '$price' WHERE id = '$item_id'");
    //                     $updated_count++;
    //                 }
    //             }
    //         }
    //         fclose($handle);
            
    //         $_SESSION['success'] = "Berhasil: $imported_count barang baru ditambahkan, dan harga $updated_count barang di-update.";
    //     } else {
    //         $_SESSION['error'] = "Gagal mengunggah file CSV.";
    //     }
        
    //     header("Location: index.php");
    //     exit;
    // }

    // if ($action === 'delete_category') {
    //     if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] != true) {
    //         echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Anda bukan admin.']);
    //         exit;
    //     }
        
    //     $id = (int) $_POST['id'];

    //     $qItems = $koneksi->query("SELECT id FROM global_stocks WHERE global_stock_category_id = $id AND store_id = '$store_id'");
    //     $stock_ids = [];
    //     while($row = $qItems->fetch_assoc()) {
    //         $stock_ids[] = $row['id'];
    //     }

    //     if (count($stock_ids) > 0) {
    //         $ids_str = implode(',', $stock_ids);
    //         $koneksi->query("DELETE FROM global_stock_daily_values WHERE global_stock_id IN ($ids_str)");
    //         $koneksi->query("DELETE FROM global_stock_monthly_values WHERE global_stock_id IN ($ids_str)");
    //         $koneksi->query("DELETE FROM global_stock_deliveries WHERE global_stock_id IN ($ids_str) OR to_global_stock_id IN ($ids_str)");
    //     }

    //     $koneksi->query("DELETE FROM global_stocks WHERE global_stock_category_id = $id AND store_id = '$store_id'");

    //     $stmt = $koneksi->prepare("DELETE FROM global_stock_categories WHERE id = ? AND store_id = ?");
    //     $stmt->bind_param("is", $id, $store_id);
    //     if ($stmt->execute()) {
    //         $_SESSION['success'] = "Kategori dan seluruh barang di dalamnya berhasil dihapus.";
    //         echo json_encode(['status' => 'success']);
    //     } else {
    //         echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus kategori']);
    //     }
    //     exit;
    // }

    // if ($action === 'delete_stock') {
    //     if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] != true) {
    //         echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Anda bukan admin.']);
    //         exit;
    //     }
        
    //     $id = (int) $_POST['id'];

    //     $koneksi->query("DELETE FROM global_stock_daily_values WHERE global_stock_id = $id");
    //     $koneksi->query("DELETE FROM global_stock_monthly_values WHERE global_stock_id = $id");
    //     $koneksi->query("DELETE FROM global_stock_deliveries WHERE global_stock_id = $id OR to_global_stock_id = $id");

    //     $stmt = $koneksi->prepare("DELETE FROM global_stocks WHERE id = ? AND store_id = ?");
    //     $stmt->bind_param("is", $id, $store_id);
    //     if ($stmt->execute()) {
    //         $_SESSION['success'] = "Barang beserta riwayatnya berhasil dihapus.";
    //         echo json_encode(['status' => 'success']);
    //     } else {
    //         echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus barang']);
    //     }
    //     exit;
    // }
    
}


