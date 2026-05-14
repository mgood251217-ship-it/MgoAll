<?php

require_once '../connect.php';
require_once BASE_PATH . '/session.php';

header('Content-Type: application/json'); // Format kembalian menjadi JSON

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'add_category') {
    $name = trim($_POST['name']);
    if ($name == '') {
        $_SESSION['error'] = "Nama kategori wajib diisi";
        echo json_encode(['status' => 'error', 'message' => 'Nama kategori wajib diisi']);
        exit;
    }
    $stmt = $koneksi->prepare("INSERT INTO global_stock_categories (name, store_id) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $store_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Kategori berhasil ditambahkan";
        echo json_encode(['status' => 'success']);
    } else {
        $_SESSION['error'] = "Gagal menambahkan kategori";
        echo json_encode(['status' => 'error']);
    }
    exit;
}

if ($action === 'edit_category') {
    $id   = (int) $_POST['id'];
    $name = trim($_POST['name']);
    if ($name != '') {
        $stmt = $koneksi->prepare("UPDATE global_stock_categories SET name = ? WHERE id = ? AND store_id = ?");
        $stmt->bind_param("sis", $name, $id, $store_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Nama kategori berhasil diperbarui";
            echo json_encode(['status' => 'success']);
        } else {
            $_SESSION['error'] = "Gagal memperbarui kategori";
            echo json_encode(['status' => 'error']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Nama tidak valid']);
    }
    exit;
}

if ($action === 'add_stock') {
    $name        = trim($_POST['name']);
    $size        = trim($_POST['size']);
    $category_id = (int) $_POST['category_id'];
    
    $stmt = $koneksi->prepare("INSERT INTO global_stocks (name, size, global_stock_category_id, store_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssis", $name, $size, $category_id, $store_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Barang stok berhasil ditambahkan";
        echo json_encode(['status' => 'success']);
    } else {
        $_SESSION['error'] = "Gagal menambahkan barang";
        echo json_encode(['status' => 'error']);
    }
    exit;
}

if ($action === 'edit_stock') {
    $id          = (int) $_POST['id'];
    $name        = trim($_POST['name']);
    $size        = trim($_POST['size']);
    $category_id = (int) $_POST['category_id'];
    if ($name != '' && $size != '') {
        $stmt = $koneksi->prepare("UPDATE global_stocks SET name = ?, size = ?, global_stock_category_id = ? WHERE id = ? AND store_id = ?");
        $stmt->bind_param("ssiis", $name, $size, $category_id, $id, $store_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Data barang berhasil diperbarui";
            echo json_encode(['status' => 'success']);
        } else {
            $_SESSION['error'] = "Gagal memperbarui barang";
            echo json_encode(['status' => 'error']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
    }
    exit;
}

if ($action === 'send_stock') {
    $global_stock_id     = (int) $_POST['global_stock_id'];
    $to_store_id         = $_POST['to_store_id'];
    $to_global_stock_id_input = $_POST['to_global_stock_id']; 
    $qty                 = (double) $_POST['qty'];
    $date                = $_POST['date'];
    $month_year          = date('Y-m', strtotime($date));

    $qItem = $koneksi->prepare("
        SELECT gs.name, gs.size, gsc.name as cat_name 
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
        $qCatTujuan = $koneksi->prepare("SELECT id FROM global_stock_categories WHERE store_id = ? AND name = ?");
        $qCatTujuan->bind_param("ss", $to_store_id, $item['cat_name']);
        $qCatTujuan->execute();
        $resCat = $qCatTujuan->get_result();
        
        if ($resCat->num_rows > 0) {
            $to_cat_id = $resCat->fetch_assoc()['id'];
        } else {
            $insCat = $koneksi->prepare("INSERT INTO global_stock_categories (store_id, name) VALUES (?, ?)");
            $insCat->bind_param("ss", $to_store_id, $item['cat_name']);
            $insCat->execute();
            $to_cat_id = $insCat->insert_id;
        }

        $insStock = $koneksi->prepare("INSERT INTO global_stocks (store_id, global_stock_category_id, name, size) VALUES (?, ?, ?, ?)");
        $insStock->bind_param("siss", $to_store_id, $to_cat_id, $item['name'], $item['size']);
        $insStock->execute();
        $to_global_stock_id = $insStock->insert_id;

    } else {
        $to_global_stock_id = (int) $to_global_stock_id_input;
    }

    $insDel = $koneksi->prepare("INSERT INTO global_stock_deliveries (store_id, to_store_id, global_stock_id, to_global_stock_id, qty, date) VALUES (?, ?, ?, ?, ?, ?)");
    $insDel->bind_param("ssiids", $store_id, $to_store_id, $global_stock_id, $to_global_stock_id, $qty, $date);
    $insDel->execute();

    $qSender = $koneksi->prepare("SELECT id, stock_out FROM global_stock_daily_values WHERE global_stock_id = ? AND store_id = ? AND date = ?");
    $qSender->bind_param("iss", $global_stock_id, $store_id, $date);
    $qSender->execute();
    $resSender = $qSender->get_result();
    if ($resSender->num_rows > 0) {
        $rowS = $resSender->fetch_assoc();
        $upd1 = $koneksi->prepare("UPDATE global_stock_daily_values SET stock_out = ? WHERE id = ?");
        $new_sk = $rowS['stock_out'] + $qty;
        $upd1->bind_param("di", $new_sk, $rowS['id']);
        $upd1->execute();
    } else {
        $ins1 = $koneksi->prepare("INSERT INTO global_stock_daily_values (global_stock_id, store_id, stock_in, stock_out, date) VALUES (?, ?, 0, ?, ?)");
        $ins1->bind_param("isds", $global_stock_id, $store_id, $qty, $date);
        $ins1->execute();
    }

    $qReceiver = $koneksi->prepare("SELECT id, stock_in FROM global_stock_daily_values WHERE global_stock_id = ? AND store_id = ? AND date = ?");
    $qReceiver->bind_param("iss", $to_global_stock_id, $to_store_id, $date);
    $qReceiver->execute();
    $resReceiver = $qReceiver->get_result();
    if ($resReceiver->num_rows > 0) {
        $rowR = $resReceiver->fetch_assoc();
        $upd2 = $koneksi->prepare("UPDATE global_stock_daily_values SET stock_in = ? WHERE id = ?");
        $new_sm = $rowR['stock_in'] + $qty;
        $upd2->bind_param("di", $new_sm, $rowR['id']);
        $upd2->execute();
    } else {
        $ins2 = $koneksi->prepare("INSERT INTO global_stock_daily_values (global_stock_id, store_id, stock_in, stock_out, date) VALUES (?, ?, ?, 0, ?)");
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

        $q_prev = $koneksi->prepare("SELECT final_stock FROM global_stock_monthly_values WHERE global_stock_id = ? AND store_id = ? AND month_year < ? ORDER BY month_year DESC LIMIT 1");
        $q_prev->bind_param("iss", $t_id, $t_store, $month_year);
        $q_prev->execute();
        $res_prev = $q_prev->get_result();
        $prev_final = $res_prev->num_rows > 0 ? $res_prev->fetch_assoc()['final_stock'] : 0;

        $q_check_m = $koneksi->prepare("SELECT id FROM global_stock_monthly_values WHERE global_stock_id = ? AND store_id = ? AND month_year = ?");
        $q_check_m->bind_param("iss", $t_id, $t_store, $month_year);
        $q_check_m->execute();
        if ($q_check_m->get_result()->num_rows === 0) {
            $q_ins_m = $koneksi->prepare("INSERT INTO global_stock_monthly_values (global_stock_id, store_id, month_year, initial_stock, final_stock) VALUES (?, ?, ?, ?, ?)");
            $q_ins_m->bind_param("issdd", $t_id, $t_store, $month_year, $prev_final, $prev_final);
            $q_ins_m->execute();
        }

        $q_months = $koneksi->prepare("SELECT id, month_year, initial_stock FROM global_stock_monthly_values WHERE global_stock_id = ? AND store_id = ? AND month_year >= ? ORDER BY month_year ASC");
        $q_months->bind_param("iss", $t_id, $t_store, $month_year);
        $q_months->execute();
        $months_res = $q_months->get_result();

        $running_initial = null;
        while ($m_row = $months_res->fetch_assoc()) {
            $m_id = $m_row['id'];
            $m_my = $m_row['month_year'];
            
            if ($running_initial !== null) {
                $m_initial = $running_initial;
                $upd_init = $koneksi->prepare("UPDATE global_stock_monthly_values SET initial_stock = ? WHERE id = ?");
                $upd_init->bind_param("di", $m_initial, $m_id);
                $upd_init->execute();
            } else {
                $m_initial = $m_row['initial_stock'];
            }

            $like_m = $m_my . '%';
            $q_sum = $koneksi->prepare("SELECT SUM(stock_in) as sm, SUM(stock_out) as sk FROM global_stock_daily_values WHERE global_stock_id = ? AND store_id = ? AND date LIKE ?");
            $q_sum->bind_param("iss", $t_id, $t_store, $like_m);
            $q_sum->execute();
            $sums = $q_sum->get_result()->fetch_assoc();
            
            $m_final = $m_initial + ($sums['sm'] ?? 0) - ($sums['sk'] ?? 0);

            $upd_fin = $koneksi->prepare("UPDATE global_stock_monthly_values SET final_stock = ? WHERE id = ?");
            $upd_fin->bind_param("di", $m_final, $m_id);
            $upd_fin->execute();

            $running_initial = $m_final;
        }

        $upd_main = $koneksi->prepare("UPDATE global_stocks SET current_stock = (SELECT final_stock FROM global_stock_monthly_values WHERE global_stock_id = ? ORDER BY month_year DESC LIMIT 1) WHERE id = ?");
        $upd_main->bind_param("ii", $t_id, $t_id);
        $upd_main->execute();
    }

    $_SESSION['success'] = "Barang berhasil dikirim dan terhubung dengan stok toko tujuan.";
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'update_daily_stock') {
    $global_stock_id = (int) $_POST['global_stock_id'];
    $stock_in        = (double) $_POST['stock_in'];
    $stock_out       = (double) $_POST['stock_out'];
    $date            = $_POST['date'];
    $month_year      = date('Y-m', strtotime($date));

    $check = $koneksi->prepare("SELECT id FROM global_stock_daily_values WHERE global_stock_id = ? AND store_id = ? AND date = ?");
    $check->bind_param("iss", $global_stock_id, $store_id, $date);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $upd = $koneksi->prepare("UPDATE global_stock_daily_values SET stock_in = ?, stock_out = ? WHERE id = ?");
        $upd->bind_param("ddi", $stock_in, $stock_out, $row['id']);
        $upd->execute();
    } else {
        $ins = $koneksi->prepare("INSERT INTO global_stock_daily_values (global_stock_id, store_id, stock_in, stock_out, date) VALUES (?, ?, ?, ?, ?)");
        $ins->bind_param("isdds", $global_stock_id, $store_id, $stock_in, $stock_out, $date);
        $ins->execute();
    }

    $q_prev = $koneksi->prepare("SELECT final_stock FROM global_stock_monthly_values WHERE global_stock_id = ? AND store_id = ? AND month_year < ? ORDER BY month_year DESC LIMIT 1");
    $q_prev->bind_param("iss", $global_stock_id, $store_id, $month_year);
    $q_prev->execute();
    $res_prev = $q_prev->get_result();
    $prev_final = $res_prev->num_rows > 0 ? $res_prev->fetch_assoc()['final_stock'] : 0;

    $q_check_m = $koneksi->prepare("SELECT id FROM global_stock_monthly_values WHERE global_stock_id = ? AND store_id = ? AND month_year = ?");
    $q_check_m->bind_param("iss", $global_stock_id, $store_id, $month_year);
    $q_check_m->execute();
    if ($q_check_m->get_result()->num_rows === 0) {
        $q_ins_m = $koneksi->prepare("INSERT INTO global_stock_monthly_values (global_stock_id, store_id, month_year, initial_stock, final_stock) VALUES (?, ?, ?, ?, ?)");
        $q_ins_m->bind_param("issdd", $global_stock_id, $store_id, $month_year, $prev_final, $prev_final);
        $q_ins_m->execute();
    }

    $q_months = $koneksi->prepare("SELECT id, month_year, initial_stock FROM global_stock_monthly_values WHERE global_stock_id = ? AND store_id = ? AND month_year >= ? ORDER BY month_year ASC");
    $q_months->bind_param("iss", $global_stock_id, $store_id, $month_year);
    $q_months->execute();
    $months_res = $q_months->get_result();

    $running_initial = null;
    while ($m_row = $months_res->fetch_assoc()) {
        $m_id = $m_row['id'];
        $m_my = $m_row['month_year'];
        
        if ($running_initial !== null) {
            $m_initial = $running_initial;
            $upd_init = $koneksi->prepare("UPDATE global_stock_monthly_values SET initial_stock = ? WHERE id = ?");
            $upd_init->bind_param("di", $m_initial, $m_id);
            $upd_init->execute();
        } else {
            $m_initial = $m_row['initial_stock'];
        }

        $like_m = $m_my . '%';
        $q_sum = $koneksi->prepare("SELECT SUM(stock_in) as sm, SUM(stock_out) as sk FROM global_stock_daily_values WHERE global_stock_id = ? AND store_id = ? AND date LIKE ?");
        $q_sum->bind_param("iss", $global_stock_id, $store_id, $like_m);
        $q_sum->execute();
        $sums = $q_sum->get_result()->fetch_assoc();
        
        $m_final = $m_initial + ($sums['sm'] ?? 0) - ($sums['sk'] ?? 0);

        $upd_fin = $koneksi->prepare("UPDATE global_stock_monthly_values SET final_stock = ? WHERE id = ?");
        $upd_fin->bind_param("di", $m_final, $m_id);
        $upd_fin->execute();

        $running_initial = $m_final;
    }

    $upd_main = $koneksi->prepare("UPDATE global_stocks SET current_stock = (SELECT final_stock FROM global_stock_monthly_values WHERE global_stock_id = ? ORDER BY month_year DESC LIMIT 1) WHERE id = ?");
    $upd_main->bind_param("ii", $global_stock_id, $global_stock_id);
    $upd_main->execute();

    $_SESSION['success'] = "Data stok berhasil diperbarui.";
    echo json_encode(['status' => 'success']);
    exit;
}