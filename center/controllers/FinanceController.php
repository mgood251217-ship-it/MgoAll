<?php
class FinanceController {
    private $koneksi;

    public function __construct($db) {
        $this->koneksi = $db;
    }

    public function getIndexData($access, $startDateF = null, $endDateF = null) {
        $startDateF = $startDateF ?: date('Y-m-d');
        $endDateF = $endDateF ?: date('Y-m-d');

        $startDate = $startDateF . ' 00:00:00';
        $endDate = $endDateF . ' 23:59:59';

        if ($access == 'ALL') {
            $stmt = $this->koneksi->prepare("
                SELECT f.*, s.name AS store_name, s.branch 
                FROM finance f 
                JOIN stores s ON f.store_id = s.store_id 
                WHERE f.date BETWEEN ? AND ?
                ORDER BY f.date DESC
            ");
            $stmt->bind_param("ss", $startDate, $endDate);
        } else {
            $stmt = $this->koneksi->prepare("
                SELECT f.*, s.name AS store_name, s.branch 
                FROM finance f 
                JOIN stores s ON f.store_id = s.store_id 
                WHERE f.date BETWEEN ? AND ? AND s.administrator = ?
                ORDER BY f.date DESC
            ");
            $stmt->bind_param("sss", $startDate, $endDate, $access);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $finances = [];
        $totalOffline = 0;
        $totalOnline = 0;
        $totalSaldo = 0;
        $totalTransfer = 0;
        $totalExpenditure = 0;

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $finances[] = $row;
                $totalOffline += (float)$row['omset_offline'];
                $totalOnline += (float)$row['omset_online'];
                $totalSaldo += (float)$row['saldo'];
                $totalTransfer += (float)$row['transfer'];
                $totalExpenditure += (float)$row['expenditure'];
            }
        }

        return [
            'startDate' => $startDateF,
            'endDate' => $endDateF,
            'finances' => $finances,
            'totals' => [
                'offline' => $totalOffline,
                'online' => $totalOnline,
                'saldo' => $totalSaldo,
                'transfer' => $totalTransfer,
                'expenditure' => $totalExpenditure
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
}