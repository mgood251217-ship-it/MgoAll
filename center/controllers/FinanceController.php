<?php
class FinanceController {
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

        $store_id = isset($_GET['store_id']) && $_GET['store_id'] !== '' ? (int)$_GET['store_id'] : null;
        $startDateF = $_GET['start_date'] ?? date('Y-m-d');
        $endDateF = $_GET['end_date'] ?? date('Y-m-d');

        $startDate = $startDateF . ' 00:00:00';
        $endDate = $endDateF . ' 23:59:59';

        $finances = [];
        $expenditures = [];
        $incomes = [];
        
        $totalOffline = 0;
        $totalOnline = 0;
        $totalSaldo = 0;
        $totalTransfer = 0;
        $totalExpenditure = 0;

        $financeQuery = "
            SELECT f.*, s.name AS store_name, s.branch 
            FROM finance f 
            JOIN stores s ON f.store_id = s.store_id 
            WHERE f.date BETWEEN ? AND ?
        ";
        $financeParams = [$startDateF, $endDateF]; 
        $financeTypes = "ss";

        if ($store_id !== null) {
            $financeQuery .= " AND f.store_id = ?";
            $financeParams[] = $store_id;
            $financeTypes .= "i";
        } elseif ($access !== 'ALL') {
            $financeQuery .= " AND s.administrator = ?";
            $financeParams[] = $access;
            $financeTypes .= "s";
        }
        $financeQuery .= " ORDER BY f.date DESC";

        $stmt = $this->koneksi->prepare($financeQuery);
        $stmt->bind_param($financeTypes, ...$financeParams);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['total_omset'] = (float)$row['omset_offline'] + (float)$row['omset_online'];
                $row['cash'] = $row['total_omset'] - (float)$row['transfer'];
                
                $finances[] = $row;
                $totalOffline += (float)$row['omset_offline'];
                $totalOnline += (float)$row['omset_online'];
                $totalSaldo += (float)$row['saldo'];
                $totalTransfer += (float)$row['transfer'];
                $totalExpenditure += (float)$row['expenditure'];
            }
        }
        $stmt->close();

        $totalOmset = $totalOffline + $totalOnline;
        $totalCash = $totalOmset - $totalTransfer;

        $expQuery = "SELECT e.*, s.name as store_name FROM expenditures e JOIN stores s ON e.store_id = s.store_id WHERE e.date BETWEEN ? AND ?";
        $expParams = [$startDate, $endDate];
        $expTypes = "ss";

        $incQuery = "SELECT i.*, s.name as store_name FROM income i JOIN stores s ON i.store_id = s.store_id WHERE i.date BETWEEN ? AND ?";
        $incParams = [$startDate, $endDate];
        $incTypes = "ss";

        if ($store_id !== null) {
            $expQuery .= " AND e.store_id = ?";
            $expParams[] = $store_id;
            $expTypes .= "i";
            
            $incQuery .= " AND i.store_id = ?";
            $incParams[] = $store_id;
            $incTypes .= "i";
        } elseif ($access !== 'ALL') {
            $expQuery .= " AND s.administrator = ?";
            $expParams[] = $access;
            $expTypes .= "s";
            
            $incQuery .= " AND s.administrator = ?";
            $incParams[] = $access;
            $incTypes .= "s";
        }
        
        $expQuery .= " ORDER BY e.date DESC";
        $incQuery .= " ORDER BY i.date DESC";

        $stmtExp = $this->koneksi->prepare($expQuery);
        $stmtExp->bind_param($expTypes, ...$expParams);
        $stmtExp->execute();
        $resExp = $stmtExp->get_result();
        if ($resExp) {
            while ($row = $resExp->fetch_assoc()) {
                $expenditures[] = $row;
            }
        }
        $stmtExp->close();

        $stmtInc = $this->koneksi->prepare($incQuery);
        $stmtInc->bind_param($incTypes, ...$incParams);
        $stmtInc->execute();
        $resInc = $stmtInc->get_result();
        if ($resInc) {
            while ($row = $resInc->fetch_assoc()) {
                $incomes[] = $row;
            }
        }
        $stmtInc->close();

        return [
            'stores' => $stores,
            'current_store_id' => $store_id,
            'startDate' => $startDateF,
            'endDate' => $endDateF,
            'finances' => $finances,
            'expenditures' => $expenditures,
            'incomes' => $incomes,
            'totals' => [
                'offline' => $totalOffline,
                'online' => $totalOnline,
                'saldo' => $totalSaldo,
                'transfer' => $totalTransfer,
                'expenditure' => $totalExpenditure,
                'omset' => $totalOmset,
                'cash' => $totalCash
            ]
        ];
    }

    public function refreshFinance($store_id, $date) {
        try {
            $start = $date . ' 00:00:00';
            $end   = $date . ' 23:59:59';

            $omset_offline = 0;
            $omset_online  = 0;
            $cash          = 0;
            $transfer      = 0;
            $pengeluaran   = 0;
            $income_id = 0 ;
            $nominalOld = 0 ;
            $count = 0;

            $payments    = [];
            $allOrderIds = [];

            $stmt = $this->koneksi->prepare("SELECT * FROM payment WHERE date BETWEEN ? AND ?");
            $stmt->bind_param("ss", $start, $end);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $payments[] = $row;
                $ids = explode(',', $row['order_id']);
                foreach ($ids as $id) {
                    $id = trim($id);
                    if ($id !== '') {
                        $allOrderIds[$id] = (int)$id;
                    }
                }
            }
            $stmt->close();

            $ordersLookup = [];
            if (!empty($allOrderIds)) {
                $inClause = implode(',', $allOrderIds);
                $stmt = $this->koneksi->prepare("SELECT order_id, system FROM orders WHERE order_id IN ($inClause) AND store_id = ?");
                $stmt->bind_param("i", $store_id);
                $stmt->execute();
                $res = $stmt->get_result();
                
                while ($o = $res->fetch_assoc()) {
                    $ordersLookup[$o['order_id']] = $o['system'];
                }
                $stmt->close();
            }

            foreach ($payments as $payment) {
                $ids      = explode(',', $payment['order_id']);
                $countIds = count($ids);
                $perOrder = $payment['nominal'] / max($countIds, 1);

                foreach ($ids as $vid) {
                    $vid = trim($vid);
                    if (isset($ordersLookup[$vid])) {
                        if ($ordersLookup[$vid] === 'OFFLINE') {
                            $omset_offline += $perOrder;
                        } else {
                            $omset_online += $perOrder;
                        }

                        if ($payment['payment_method'] === 'CASH') {
                            $cash += $perOrder;
                        } else {
                            $transfer += $perOrder;
                        }
                    }
                }
            }

            $prevDate  = date('Y-m-d', strtotime($date . ' -1 day'));
            $saldoPrev = 0;

            $stmt = $this->koneksi->prepare("SELECT saldo FROM finance WHERE store_id = ? AND date = ? LIMIT 1");
            $stmt->bind_param("is", $store_id, $prevDate);
            $stmt->execute();
            $stmt->bind_result($saldoPrev);
            $stmt->fetch();
            $stmt->close();

            $saldoPrev = $saldoPrev ?? 0;
            $infoSaldo = "INPUT SALDO OTOMATIS " . $date;

            $stmt = $this->koneksi->prepare("SELECT income_id, nominal FROM income WHERE store_id = ? AND information = ? AND DATE(date) = ? LIMIT 1");
            $stmt->bind_param("iss", $store_id, $infoSaldo, $date);
            $stmt->execute();
            $stmt->bind_result($income_id, $nominalOld);
            $exists = $stmt->fetch();
            $stmt->close();

            if ($exists) {
                $stmt = $this->koneksi->prepare("UPDATE income SET nominal = ? WHERE income_id = ?");
                $stmt->bind_param("ii", $saldoPrev, $income_id);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $this->koneksi->prepare("INSERT INTO income (store_id, information, nominal, date) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isis", $store_id, $infoSaldo, $saldoPrev, $date);
                $stmt->execute();
                $stmt->close();
            }

            $pemasukan_lain = 0;
            
            $stmt = $this->koneksi->prepare("SELECT IFNULL(SUM(nominal),0) FROM income WHERE store_id = ? AND DATE(date) = ? AND information NOT LIKE 'INPUT SALDO OTOMATIS%'");
            $stmt->bind_param("is", $store_id, $date);
            $stmt->execute();
            $stmt->bind_result($pemasukan_lain);
            $stmt->fetch();
            $stmt->close();

            $stmt = $this->koneksi->prepare("SELECT IFNULL(SUM(nominal),0) FROM expenditures WHERE store_id = ? AND DATE(date) = ?");
            $stmt->bind_param("is", $store_id, $date);
            $stmt->execute();
            $stmt->bind_result($pengeluaran);
            $stmt->fetch();
            $stmt->close();

            $saldo = $saldoPrev + $cash + $pemasukan_lain - $pengeluaran;

            $stmt = $this->koneksi->prepare("SELECT COUNT(*) FROM finance WHERE store_id = ? AND date = ?");
            $stmt->bind_param("is", $store_id, $date);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();

            if ($count > 0) {
                $stmt = $this->koneksi->prepare("UPDATE finance SET omset_offline = ?, omset_online = ?, saldo = ?, transfer = ?, expenditure = ? WHERE store_id = ? AND date = ?");
                $stmt->bind_param("iiiiiss", $omset_offline, $omset_online, $saldo, $transfer, $pengeluaran, $store_id, $date);
            } else {
                $stmt = $this->koneksi->prepare("INSERT INTO finance (store_id, omset_offline, omset_online, saldo, transfer, expenditure, date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iiiiiss", $store_id, $omset_offline, $omset_online, $saldo, $transfer, $pengeluaran, $date);
            }
            $stmt->execute();
            $stmt->close();

            return json_encode(['success' => true]);

        } catch (Exception $e) {
            return json_encode([
                'success' => false, 
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ]);
        }
    }

    public function deleteExpenditure() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        header('Content-Type: application/json');
        require_once __DIR__ . '/../functions/helpers.php';
        require_once __DIR__ . '/../functions/cacheHelper.php';

        $administrator_id = isset($_SESSION['admin_logged_in']['administrator_id']) ? startEnk('dek', $_SESSION['admin_logged_in']['administrator_id']) : 0;
        $expenditure_id = isset($_POST['expenditure_id']) ? (int)$_POST['expenditure_id'] : 0;
        $keterangan_hapus = isset($_POST['keterangan_hapus']) ? trim($_POST['keterangan_hapus']) : '';
        $date_now = date("Y-m-d");
        $done = 0;
        $order_id = 0;

        if (!$expenditure_id || !$administrator_id) {
            echo json_encode(['success' => false, 'message' => 'Data tidak valid atau sesi berakhir.']);
            exit;
        }

        $this->koneksi->begin_transaction();

        try {
            $stmt = $this->koneksi->prepare("SELECT store_id, information, nominal, DATE(date) as exec_date FROM expenditures WHERE expenditure_id = ?");
            $stmt->bind_param("i", $expenditure_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$res) throw new Exception("Data tidak ditemukan");

            $store_id = $res['store_id'];
            $date_finance = $res['exec_date'];

            $title = "HAPUS PENGELUARAN";
            $message = "HAPUS PENGELUARAN " . $res['information'] . " SEBESAR " . $res['nominal'];
            
            $stmtAct = $this->koneksi->prepare("INSERT INTO activity (store_id, title, message, information, date, order_id, done, administrator_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtAct->bind_param("issssiii", $store_id, $title, $message, $keterangan_hapus, $date_now, $order_id, $done, $administrator_id);
            $stmtAct->execute();
            $stmtAct->close();

            $stmtDel = $this->koneksi->prepare("DELETE FROM expenditures WHERE expenditure_id = ?");
            $stmtDel->bind_param("i", $expenditure_id);
            $stmtDel->execute();
            $stmtDel->close();

            $this->refreshFinance($store_id, $date_finance);

            $this->koneksi->commit();
            updateStoreCache($store_id, 'activities');
            updateStoreCache($store_id, 'finance');

            echo json_encode(['success' => true, 'message' => 'Pengeluaran berhasil dihapus']);
        } catch (Exception $e) {
            $this->koneksi->rollback();
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus pengeluaran: ' . $e->getMessage()]);
        }
        exit;
    }

    public function updateExpenditure() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        header('Content-Type: application/json');
        require_once __DIR__ . '/../functions/helpers.php';
        require_once __DIR__ . '/../functions/cacheHelper.php';

        $administrator_id = isset($_SESSION['admin_logged_in']['administrator_id']) ? startEnk('dek', $_SESSION['admin_logged_in']['administrator_id']) : 0;
        $expenditure_id = isset($_POST['expenditure_id']) ? (int)$_POST['expenditure_id'] : 0;
        $nominal = isset($_POST['nominal']) ? (float)$_POST['nominal'] : 0;
        $information = isset($_POST['information']) ? trim($_POST['information']) : '';
        $datetime = isset($_POST['date']) ? str_replace('T', ' ', $_POST['date']) : '';
        $keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : '';
        
        if (strlen($datetime) == 16) $datetime .= ':00';
        $date_now = date("Y-m-d");
        $done = 0;
        $order_id = 0;

        if (!$expenditure_id || !$administrator_id) {
            echo json_encode(['success' => false, 'message' => 'Data tidak valid atau sesi berakhir.']);
            exit;
        }

        $this->koneksi->begin_transaction();

        try {
            $stmt = $this->koneksi->prepare("SELECT store_id, nominal, information, date, DATE(date) as old_date FROM expenditures WHERE expenditure_id = ?");
            $stmt->bind_param("i", $expenditure_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$res) throw new Exception("Data tidak ditemukan");

            $store_id = $res['store_id'];
            $old_date = $res['old_date'];
            $new_date_only = substr($datetime, 0, 10);
            
            $messageLog = "";
            if ($res['nominal'] != $nominal || $res['information'] != $information || $res['date'] != $datetime) {
                $messageLog = "UBAH PENGELUARAN DARI:\nNOMINAL: " . $res['nominal'] . " => " . $nominal . "\nINFO: " . $res['information'] . " => " . $information . "\nTANGGAL: " . $res['date'] . " => " . $datetime;
            }

            if ($messageLog != "") {
                $title = "UBAH PENGELUARAN";
                $stmtAct = $this->koneksi->prepare("INSERT INTO activity (store_id, title, message, information, date, order_id, done, administrator_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtAct->bind_param("issssiii", $store_id, $title, $messageLog, $keterangan, $date_now, $order_id, $done, $administrator_id);
                $stmtAct->execute();
                $stmtAct->close();
            }

            $stmtUpd = $this->koneksi->prepare("UPDATE expenditures SET nominal = ?, information = ?, date = ? WHERE expenditure_id = ?");
            $stmtUpd->bind_param("issi", $nominal, $information, $datetime, $expenditure_id);
            $stmtUpd->execute();
            $stmtUpd->close();

            $this->refreshFinance($store_id, $old_date);
            if ($old_date !== $new_date_only) {
                $this->refreshFinance($store_id, $new_date_only);
            }

            $this->koneksi->commit();

            updateStoreCache($store_id, 'activities');
            updateStoreCache($store_id, 'finance');

            echo json_encode(['success' => true, 'message' => 'Pengeluaran berhasil diubah']);
        } catch (Exception $e) {
            $this->koneksi->rollback();
            echo json_encode(['success' => false, 'message' => 'Gagal mengubah pengeluaran: ' . $e->getMessage()]);
        }
        exit;
    }

    public function deleteIncome() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        header('Content-Type: application/json');
        require_once __DIR__ . '/../functions/helpers.php';
        require_once __DIR__ . '/../functions/cacheHelper.php';

        $administrator_id = isset($_SESSION['admin_logged_in']['administrator_id']) ? startEnk('dek', $_SESSION['admin_logged_in']['administrator_id']) : 0;
        $income_id = isset($_POST['income_id']) ? (int)$_POST['income_id'] : 0;
        $keterangan_hapus = isset($_POST['keterangan_hapus']) ? trim($_POST['keterangan_hapus']) : '';
        $date_now = date("Y-m-d");
        $done = 0;
        $order_id = 0;

        if (!$income_id || !$administrator_id) {
            echo json_encode(['success' => false, 'message' => 'Data tidak valid atau sesi berakhir.']);
            exit;
        }

        $this->koneksi->begin_transaction();

        try {
            $stmt = $this->koneksi->prepare("SELECT store_id, information, nominal, DATE(date) as exec_date FROM income WHERE income_id = ?");
            $stmt->bind_param("i", $income_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$res) throw new Exception("Data tidak ditemukan");

            $store_id = $res['store_id'];
            $date_finance = $res['exec_date'];

            $title = "HAPUS PEMASUKAN";
            $message = "HAPUS PEMASUKAN " . $res['information'] . " SEBESAR " . $res['nominal'];
            
            $stmtAct = $this->koneksi->prepare("INSERT INTO activity (store_id, title, message, information, date, order_id, done, administrator_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtAct->bind_param("issssiii", $store_id, $title, $message, $keterangan_hapus, $date_now, $order_id, $done, $administrator_id);
            $stmtAct->execute();
            $stmtAct->close();

            $stmtDel = $this->koneksi->prepare("DELETE FROM income WHERE income_id = ?");
            $stmtDel->bind_param("i", $income_id);
            $stmtDel->execute();
            $stmtDel->close();

            $this->refreshFinance($store_id, $date_finance);

            $this->koneksi->commit();

            updateStoreCache($store_id, 'activities');
            updateStoreCache($store_id, 'finance');

            echo json_encode(['success' => true, 'message' => 'Pemasukan berhasil dihapus']);
        } catch (Exception $e) {
            $this->koneksi->rollback();
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus pemasukan: ' . $e->getMessage()]);
        }
        exit;
    }

    public function updateIncome() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        header('Content-Type: application/json');
        require_once __DIR__ . '/../functions/helpers.php';
        require_once __DIR__ . '/../functions/cacheHelper.php';

        $administrator_id = isset($_SESSION['admin_logged_in']['administrator_id']) ? startEnk('dek', $_SESSION['admin_logged_in']['administrator_id']) : 0;
        $income_id = isset($_POST['income_id']) ? (int)$_POST['income_id'] : 0;
        $nominal = isset($_POST['nominal']) ? (float)$_POST['nominal'] : 0;
        $information = isset($_POST['information']) ? trim($_POST['information']) : '';
        $datetime = isset($_POST['date']) ? str_replace('T', ' ', $_POST['date']) : '';
        $keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : '';
        
        if (strlen($datetime) == 16) $datetime .= ':00';
        $date_now = date("Y-m-d");
        $done = 0;
        $order_id = 0;

        if (!$income_id || !$administrator_id) {
            echo json_encode(['success' => false, 'message' => 'Data tidak valid atau sesi berakhir.']);
            exit;
        }

        $this->koneksi->begin_transaction();

        try {
            $stmt = $this->koneksi->prepare("SELECT store_id, nominal, information, date, DATE(date) as old_date FROM income WHERE income_id = ?");
            $stmt->bind_param("i", $income_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$res) throw new Exception("Data tidak ditemukan");

            $store_id = $res['store_id'];
            $old_date = $res['old_date'];
            $new_date_only = substr($datetime, 0, 10);

            $messageLog = "";
            if ($res['nominal'] != $nominal || $res['information'] != $information || $res['date'] != $datetime) {
                $messageLog = "UBAH PEMASUKAN DARI:\nNOMINAL: " . $res['nominal'] . " => " . $nominal . "\nINFO: " . $res['information'] . " => " . $information . "\nTANGGAL: " . $res['date'] . " => " . $datetime;
            }

            if ($messageLog != "") {
                $title = "UBAH PEMASUKAN";
                $stmtAct = $this->koneksi->prepare("INSERT INTO activity (store_id, title, message, information, date, order_id, done, administrator_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtAct->bind_param("issssiii", $store_id, $title, $messageLog, $keterangan, $date_now, $order_id, $done, $administrator_id);
                $stmtAct->execute();
                $stmtAct->close();
            }

            $stmtUpd = $this->koneksi->prepare("UPDATE income SET nominal = ?, information = ?, date = ? WHERE income_id = ?");
            $stmtUpd->bind_param("issi", $nominal, $information, $datetime, $income_id);
            $stmtUpd->execute();
            $stmtUpd->close();

            $this->refreshFinance($store_id, $old_date);
            if ($old_date !== $new_date_only) {
                $this->refreshFinance($store_id, $new_date_only);
            }

            $this->koneksi->commit();

            updateStoreCache($store_id, 'activities');
            updateStoreCache($store_id, 'finance');

            echo json_encode(['success' => true, 'message' => 'Pemasukan berhasil diubah']);
        } catch (Exception $e) {
            $this->koneksi->rollback();
            echo json_encode(['success' => false, 'message' => 'Gagal mengubah pemasukan: ' . $e->getMessage()]);
        }
        exit;
    }
}