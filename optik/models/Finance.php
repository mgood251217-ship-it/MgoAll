<?php
// file: models/Finance.php

class Finance {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function refreshDailyFinance($store_id, $date) {
        if (!$date) return;

        $stmt = $this->pdo->prepare("
            SELECT p.payment_method, SUM(p.nominal) as total 
            FROM payments p
            JOIN orders o ON p.order_id = o.id
            WHERE o.store_id = ? AND DATE(p.create_at) = ?
            GROUP BY p.payment_method
        ");
        $stmt->execute([$store_id, $date]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $cash_rev = 0; $tf_rev = 0;
        foreach($payments as $p) {
            $m = strtoupper($p['payment_method']);
            if($m === 'CASH') $cash_rev += $p['total'];
            if($m === 'TF' || $m === 'TRANSFER') $tf_rev += $p['total'];
        }

        $stmt = $this->pdo->prepare("
            SELECT payment_method, SUM(amount) as total 
            FROM income 
            WHERE store_id = ? AND date = ? AND description NOT LIKE 'Saldo Otomatis%' 
            GROUP BY payment_method
        ");
        $stmt->execute([$store_id, $date]);
        $incomes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $cash_inc = 0; $tf_inc = 0;
        foreach($incomes as $i) {
            $m = strtoupper($i['payment_method']);
            if($m === 'CASH') $cash_inc += $i['total'];
            if($m === 'TF' || $m === 'TRANSFER') $tf_inc += $i['total'];
        }

        $stmt = $this->pdo->prepare("SELECT payment_method, SUM(amount) as total FROM expenditure WHERE store_id = ? AND date = ? GROUP BY payment_method");
        $stmt->execute([$store_id, $date]);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $cash_exp = 0; $tf_exp = 0;
        foreach($expenses as $e) {
            $m = strtoupper($e['payment_method']);
            if($m === 'CASH') $cash_exp += $e['total'];
            if($m === 'TF' || $m === 'TRANSFER') $tf_exp += $e['total'];
        }

        $sql = "INSERT INTO finance 
                (store_id, date, cash_revenue, transfer_revenue, cash_income, transfer_income, cash_expenditure, transfer_expenditure)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                cash_revenue = VALUES(cash_revenue), transfer_revenue = VALUES(transfer_revenue),
                cash_income = VALUES(cash_income), transfer_income = VALUES(transfer_income),
                cash_expenditure = VALUES(cash_expenditure), transfer_expenditure = VALUES(transfer_expenditure)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$store_id, $date, $cash_rev, $tf_rev, $cash_inc, $tf_inc, $cash_exp, $tf_exp]);
    }

    public function syncInterval($store_id, $start_date, $end_date) {
        $current = strtotime($start_date);
        $end = strtotime($end_date);
        while ($current <= $end) {
            $date = date('Y-m-d', $current);
            $this->refreshDailyFinance($store_id, $date);
            $current = strtotime('+1 day', $current);
        }
    }

    public function addIncome($store_id, $date, $description, $amount, $payment_method) {
        $method = ($payment_method == 'TF' || $payment_method == 'Transfer') ? 'Transfer' : 'Cash';
        $stmt = $this->pdo->prepare("INSERT INTO income (store_id, date, description, amount, payment_method) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$store_id, $date, $description, $amount, $method])) {
            $this->refreshDailyFinance($store_id, $date); return true;
        }
        return false;
    }

    public function addExpenditure($store_id, $date, $description, $amount, $payment_method) {
        $method = ($payment_method == 'TF' || $payment_method == 'Transfer') ? 'Transfer' : 'Cash';
        $stmt = $this->pdo->prepare("INSERT INTO expenditure (store_id, date, description, amount, payment_method) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$store_id, $date, $description, $amount, $method])) {
            $this->refreshDailyFinance($store_id, $date); return true;
        }
        return false;
    }

    public function getSummary($store_id, $start_date, $end_date) {
        // 1. SALDO AWAL (Histori murni sebelum tanggal start_date)
        $stmt = $this->pdo->prepare("
            SELECT 
                SUM(cash_revenue + cash_income) - SUM(cash_expenditure) as saldo_awal_cash,
                SUM(transfer_revenue + transfer_income) - SUM(transfer_expenditure) as saldo_awal_tf
            FROM finance 
            WHERE store_id = ? AND date < ?
        ");
        $stmt->execute([$store_id, $start_date]);
        $awal = $stmt->fetch(PDO::FETCH_ASSOC);

        $awal_cash = $awal['saldo_awal_cash'] ?? 0;
        $awal_tf = $awal['saldo_awal_tf'] ?? 0;

        // 2. PERGERAKAN PADA INTERVAL
        $stmt2 = $this->pdo->prepare("
            SELECT 
                SUM(cash_revenue) as omzet_cash, SUM(transfer_revenue) as omzet_tf,
                SUM(cash_income) as masuk_lain_cash, SUM(transfer_income) as masuk_lain_tf,
                SUM(cash_expenditure) as keluar_cash, SUM(transfer_expenditure) as keluar_tf
            FROM finance 
            WHERE store_id = ? AND date BETWEEN ? AND ?
        ");
        $stmt2->execute([$store_id, $start_date, $end_date]);
        $rentang = $stmt2->fetch(PDO::FETCH_ASSOC);

        $omzet_cash = $rentang['omzet_cash'] ?? 0; $omzet_tf = $rentang['omzet_tf'] ?? 0;
        $masuk_lain_cash = $rentang['masuk_lain_cash'] ?? 0; $masuk_lain_tf = $rentang['masuk_lain_tf'] ?? 0;
        $keluar_cash = $rentang['keluar_cash'] ?? 0; $keluar_tf = $rentang['keluar_tf'] ?? 0;

        return [
            'awal_cash' => $awal_cash, 'awal_tf' => $awal_tf,
            'omzet_cash' => $omzet_cash, 'omzet_tf' => $omzet_tf,
            'masuk_lain_cash' => $masuk_lain_cash, 'masuk_lain_tf' => $masuk_lain_tf,
            'keluar_cash' => $keluar_cash, 'keluar_tf' => $keluar_tf,
            'akhir_cash' => $awal_cash + $omzet_cash + $masuk_lain_cash - $keluar_cash,
            'akhir_tf' => $awal_tf + $omzet_tf + $masuk_lain_tf - $keluar_tf
        ];
    }

    private function autoUpdateSaldoFisik($store_id, $date) {
        $stmt = $this->pdo->prepare("
            SELECT SUM(cash_revenue + cash_income) - SUM(cash_expenditure) as saldo_cash
            FROM finance WHERE store_id = ? AND date < ?
        ");
        $stmt->execute([$store_id, $date]);
        $saldo_cash = $stmt->fetchColumn() ?: 0;

        if ($saldo_cash > 0) {
            $desc = "Saldo Otomatis " . date('Y-m-d', strtotime($date));
            
            $stmtCek = $this->pdo->prepare("SELECT id FROM income WHERE store_id = ? AND date = ? AND description LIKE 'Saldo Otomatis%'");
            $stmtCek->execute([$store_id, $date]);
            $ada = $stmtCek->fetch(PDO::FETCH_ASSOC);

            if ($ada) {
                $stmtUpdate = $this->pdo->prepare("UPDATE income SET amount = ?, description = ? WHERE id = ?");
                $stmtUpdate->execute([$saldo_cash, $desc, $ada['id']]);
            } else {
                $stmtInsert = $this->pdo->prepare("INSERT INTO income (store_id, date, description, amount, payment_method) VALUES (?, ?, ?, ?, 'Cash')");
                $stmtInsert->execute([$store_id, $date, $desc, $saldo_cash]);
            }
        }
    }

    public function getTransactions($store_id, $start_date, $end_date, $type = '') {
        $results = [];
        
        $this->autoUpdateSaldoFisik($store_id, $start_date);
        
        if ($type == '' || $type == 'in') {
            $stmt = $this->pdo->prepare("
                SELECT date, '00:00:00' as created_at, description, 'Pemasukan / Saldo' as category, 
                       payment_method, 'in' as type, amount 
                FROM income WHERE store_id = ? AND date BETWEEN ? AND ?
            ");
            $stmt->execute([$store_id, $start_date, $end_date]);
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        if ($type == '' || $type == 'out') {
            $stmt = $this->pdo->prepare("
                SELECT date, '00:00:00' as created_at, description, 'Pengeluaran' as category, 
                       payment_method, 'out' as type, amount 
                FROM expenditure WHERE store_id = ? AND date BETWEEN ? AND ?
            ");
            $stmt->execute([$store_id, $start_date, $end_date]);
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        usort($results, function($a, $b) {
            $timeA = strtotime($a['date'] . ' ' . $a['created_at']);
            $timeB = strtotime($b['date'] . ' ' . $b['created_at']);
            return $timeB <=> $timeA;
        });

        return $results;
    }
}
?>