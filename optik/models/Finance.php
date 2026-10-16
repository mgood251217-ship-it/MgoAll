<?php

class Finance {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getPaymentTotals($store_id, $date) {
        $stmt = $this->pdo->prepare("
            SELECT p.payment_method, SUM(p.nominal) as total
            FROM payments p
            JOIN orders o ON p.order_id = o.id
            WHERE o.store_id = ? AND DATE(p.create_at) = ?
            GROUP BY p.payment_method
        ");
        $stmt->execute([$store_id, $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getIncomeTotals($store_id, $date) {
        $stmt = $this->pdo->prepare("
            SELECT payment_method, SUM(amount) as total
            FROM income
            WHERE store_id = ? AND date = ? AND description NOT LIKE 'Saldo Otomatis%'
            GROUP BY payment_method
        ");
        $stmt->execute([$store_id, $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getExpenditureTotals($store_id, $date) {
        $stmt = $this->pdo->prepare("
            SELECT payment_method, SUM(amount) as total
            FROM expenditure
            WHERE store_id = ? AND date = ?
            GROUP BY payment_method
        ");
        $stmt->execute([$store_id, $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveDailyFinance($store_id, $date, array $totals) {
        $stmt = $this->pdo->prepare("
            INSERT INTO finance
                (store_id, date, cash_revenue, transfer_revenue, cash_income, transfer_income, cash_expenditure, transfer_expenditure)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                cash_revenue = VALUES(cash_revenue), transfer_revenue = VALUES(transfer_revenue),
                cash_income = VALUES(cash_income), transfer_income = VALUES(transfer_income),
                cash_expenditure = VALUES(cash_expenditure), transfer_expenditure = VALUES(transfer_expenditure)
        ");
        return $stmt->execute([
            $store_id,
            $date,
            $totals['cash_revenue'],
            $totals['transfer_revenue'],
            $totals['cash_income'],
            $totals['transfer_income'],
            $totals['cash_expenditure'],
            $totals['transfer_expenditure']
        ]);
    }

    public function addIncome($store_id, $date, $description, $amount, $payment_method) {
        $stmt = $this->pdo->prepare("
            INSERT INTO income (store_id, date, description, amount, payment_method)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$store_id, $date, $description, $amount, $payment_method]);
    }

    public function addExpenditure($store_id, $date, $description, $amount, $payment_method) {
        $stmt = $this->pdo->prepare("
            INSERT INTO expenditure (store_id, date, description, amount, payment_method)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$store_id, $date, $description, $amount, $payment_method]);
    }

    public function getOpeningBalance($store_id, $date) {
        $stmt = $this->pdo->prepare("
            SELECT
                SUM(cash_revenue + cash_income) - SUM(cash_expenditure) as awal_cash,
                SUM(transfer_revenue + transfer_income) - SUM(transfer_expenditure) as awal_tf
            FROM finance
            WHERE store_id = ? AND date < ?
        ");
        $stmt->execute([$store_id, $date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getIntervalTotals($store_id, $start_date, $end_date) {
        $stmt = $this->pdo->prepare("
            SELECT
                SUM(cash_revenue) as omzet_cash, SUM(transfer_revenue) as omzet_tf,
                SUM(cash_income) as masuk_lain_cash, SUM(transfer_income) as masuk_lain_tf,
                SUM(cash_expenditure) as keluar_cash, SUM(transfer_expenditure) as keluar_tf
            FROM finance
            WHERE store_id = ? AND date BETWEEN ? AND ?
        ");
        $stmt->execute([$store_id, $start_date, $end_date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAutomaticIncome($store_id, $date) {
        $stmt = $this->pdo->prepare("
            SELECT id FROM income
            WHERE store_id = ? AND date = ? AND description LIKE 'Saldo Otomatis%'
        ");
        $stmt->execute([$store_id, $date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateAutomaticIncome($store_id, $id, $description, $amount) {
        $stmt = $this->pdo->prepare("
            UPDATE income SET amount = ?, description = ?
            WHERE id = ? AND store_id = ?
        ");
        return $stmt->execute([$amount, $description, $id, $store_id]);
    }

    public function getIncomeTransactions($store_id, $start_date, $end_date) {
        $stmt = $this->pdo->prepare("
            SELECT id, store_id, 'income' as source, date, '00:00:00' as created_at, description, 'Pemasukan / Saldo' as category,
                   payment_method, 'in' as type, amount
            FROM income WHERE store_id = ? AND date BETWEEN ? AND ?
        ");
        $stmt->execute([$store_id, $start_date, $end_date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getExpenditureTransactions($store_id, $start_date, $end_date) {
        $stmt = $this->pdo->prepare("
            SELECT id, store_id, 'expenditure' as source, date, '00:00:00' as created_at, description, 'Pengeluaran' as category,
                   payment_method, 'out' as type, amount
            FROM expenditure WHERE store_id = ? AND date BETWEEN ? AND ?
        ");
        $stmt->execute([$store_id, $start_date, $end_date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBalance($store_id, $date) {
        $stmt = $this->pdo->prepare("
            SELECT
                SUM(cash_revenue + transfer_revenue + cash_income + transfer_income) -
                SUM(cash_expenditure + transfer_expenditure)
            FROM finance WHERE store_id = ? AND date <= ?
        ");
        $stmt->execute([$store_id, $date]);
        return $stmt->fetchColumn();
    }

    public function getDailyTotals($store_id, $date) {
        $stmt = $this->pdo->prepare("
            SELECT
                SUM(cash_revenue + transfer_revenue) as omzet_hari_ini,
                SUM(cash_expenditure + transfer_expenditure) as keluar_hari_ini
            FROM finance WHERE store_id = ? AND date = ?
        ");
        $stmt->execute([$store_id, $date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getOrderPayments($store_id, $start_date, $end_date) {
        $stmt = $this->pdo->prepare("SELECT p.*, o.inv_no, o.total AS order_total,
            (SELECT COALESCE(SUM(prior_payment.nominal), 0) FROM payments prior_payment
                WHERE prior_payment.order_id = p.order_id AND prior_payment.store_id = p.store_id
                AND (prior_payment.create_at < p.create_at
                    OR (prior_payment.create_at = p.create_at AND prior_payment.id < p.id))) AS paid_before
            FROM payments p JOIN orders o ON o.id = p.order_id AND o.store_id = p.store_id
            WHERE p.store_id = :store_id AND DATE(p.create_at) BETWEEN :start_date AND :end_date
            ORDER BY p.create_at DESC, p.id DESC");
        $stmt->execute(['store_id' => $store_id, 'start_date' => $start_date, 'end_date' => $end_date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function manualTable($source) {
        if (!in_array($source, ['income', 'expenditure'], true)) {
            throw new InvalidArgumentException('Jenis transaksi manual tidak valid.');
        }
        return $source;
    }

    public function getManualEntry($source, $store_id, $id, $lock = false) {
        $table = $this->manualTable($source);
        $stmt = $this->pdo->prepare("SELECT * FROM $table WHERE id = :id AND store_id = :store_id" . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute(['id' => $id, 'store_id' => $store_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateManualEntry($source, $store_id, $id, array $data) {
        $table = $this->manualTable($source);
        $stmt = $this->pdo->prepare("UPDATE $table SET date = :date, description = :description, amount = :amount, payment_method = :payment_method WHERE id = :id AND store_id = :store_id");
        return $stmt->execute($data + ['id' => $id, 'store_id' => $store_id]);
    }

    public function deleteManualEntry($source, $store_id, $id) {
        $table = $this->manualTable($source);
        $stmt = $this->pdo->prepare("DELETE FROM $table WHERE id = :id AND store_id = :store_id");
        return $stmt->execute(['id' => $id, 'store_id' => $store_id]);
    }

    public function transaction(callable $callback) {
        if (!$this->pdo->beginTransaction()) {
            throw new RuntimeException('Transaksi database tidak dapat dimulai.');
        }
        try {
            $result = $callback();
            if (!$this->pdo->commit()) {
                throw new RuntimeException('Transaksi database gagal disimpan.');
            }
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function getDailyRevenue($store_id, $date) {
        $stmt = $this->pdo->prepare("
            SELECT SUM(cash_revenue + transfer_revenue)
            FROM finance WHERE store_id = ? AND date = ?
        ");
        $stmt->execute([$store_id, $date]);
        return $stmt->fetchColumn();
    }
}
