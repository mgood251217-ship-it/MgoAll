<?php

require_once __DIR__ . '/../models/Finance.php';

class FinanceController {
    private $finance;

    public function __construct(Finance $finance) {
        $this->finance = $finance;
    }

    public function index($store_id, array $query, array $post, $request_method = 'GET') {
        $today = date('Y-m-d');
        $start_date = $query['start_date'] ?? $today;
        $end_date = $query['end_date'] ?? $today;
        $type = $query['type'] ?? '';
        $redirect = '/aruskas?' . http_build_query([
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);

        if ($request_method === 'POST' && isset($post['action'])) {
            $date = $post['date'] ?? $today;
            $description = $post['description'] ?? '';
            $amount = $post['amount'] ?? 0;
            $method = $post['payment_method'] ?? 'Cash';

            if ($post['action'] === 'add_income') {
                $this->addIncome($store_id, $date, $description, $amount, $method);
            } elseif ($post['action'] === 'add_expenditure') {
                $this->addExpenditure($store_id, $date, $description, $amount, $method);
            }

            return ['redirect' => $redirect];
        }

        if (($query['action'] ?? '') === 'sync') {
            $this->syncInterval($store_id, $start_date, $end_date);
            return ['redirect' => $redirect];
        }

        return [
            'today' => $today,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'type' => $type,
            'sync_url' => '/aruskas?' . http_build_query([
                'action' => 'sync',
                'start_date' => $start_date,
                'end_date' => $end_date
            ]),
            'summary' => $this->getSummary($store_id, $start_date, $end_date),
            'transactions' => $this->getTransactions($store_id, $start_date, $end_date, $type)
        ];
    }

    public function refreshDailyFinance($store_id, $date) {
        if (!$date) {
            return false;
        }

        $revenue = $this->splitPaymentTotals($this->finance->getPaymentTotals($store_id, $date));
        $income = $this->splitPaymentTotals($this->finance->getIncomeTotals($store_id, $date));
        $expenditure = $this->splitPaymentTotals($this->finance->getExpenditureTotals($store_id, $date));

        return $this->finance->saveDailyFinance($store_id, $date, [
            'cash_revenue' => $revenue['cash'],
            'transfer_revenue' => $revenue['transfer'],
            'cash_income' => $income['cash'],
            'transfer_income' => $income['transfer'],
            'cash_expenditure' => $expenditure['cash'],
            'transfer_expenditure' => $expenditure['transfer']
        ]);
    }

    public function syncInterval($store_id, $start_date, $end_date) {
        $current = DateTimeImmutable::createFromFormat('!Y-m-d', $start_date);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $end_date);

        if (!$current || !$end || $current->format('Y-m-d') !== $start_date || $end->format('Y-m-d') !== $end_date) {
            throw new InvalidArgumentException('Interval tanggal tidak valid.');
        }

        while ($current <= $end) {
            $this->refreshDailyFinance($store_id, $current->format('Y-m-d'));
            $current = $current->modify('+1 day');
        }
    }

    public function addIncome($store_id, $date, $description, $amount, $payment_method) {
        $method = $this->normalizePaymentMethod($payment_method);

        if (!$this->finance->addIncome($store_id, $date, $description, $amount, $method)) {
            return false;
        }

        return $this->refreshDailyFinance($store_id, $date);
    }

    public function addExpenditure($store_id, $date, $description, $amount, $payment_method) {
        $method = $this->normalizePaymentMethod($payment_method);

        if (!$this->finance->addExpenditure($store_id, $date, $description, $amount, $method)) {
            return false;
        }

        return $this->refreshDailyFinance($store_id, $date);
    }

    public function getSummary($store_id, $start_date, $end_date) {
        $opening = $this->finance->getOpeningBalance($store_id, $start_date);
        $interval = $this->finance->getIntervalTotals($store_id, $start_date, $end_date);
        $summary = [];

        foreach (['awal_cash', 'awal_tf'] as $key) {
            $summary[$key] = (float) ($opening[$key] ?? 0);
        }

        foreach (['omzet_cash', 'omzet_tf', 'masuk_lain_cash', 'masuk_lain_tf', 'keluar_cash', 'keluar_tf'] as $key) {
            $summary[$key] = (float) ($interval[$key] ?? 0);
        }

        $summary['masuk_cash'] = $summary['omzet_cash'] + $summary['masuk_lain_cash'];
        $summary['masuk_tf'] = $summary['omzet_tf'] + $summary['masuk_lain_tf'];
        $summary['akhir_cash'] = $summary['awal_cash'] + $summary['masuk_cash'] - $summary['keluar_cash'];
        $summary['akhir_tf'] = $summary['awal_tf'] + $summary['masuk_tf'] - $summary['keluar_tf'];
        $summary['awal_total'] = $summary['awal_cash'] + $summary['awal_tf'];
        $summary['masuk_total'] = $summary['masuk_cash'] + $summary['masuk_tf'];
        $summary['keluar_total'] = $summary['keluar_cash'] + $summary['keluar_tf'];
        $summary['akhir_total'] = $summary['akhir_cash'] + $summary['akhir_tf'];

        return $summary;
    }

    public function getTransactions($store_id, $start_date, $end_date, $type = '') {
        $this->updateAutomaticIncome($store_id, $start_date);
        $transactions = [];

        if ($type === '' || $type === 'in') {
            $transactions = $this->finance->getIncomeTransactions($store_id, $start_date, $end_date);
        }

        if ($type === '' || $type === 'out') {
            $transactions = array_merge($transactions, $this->finance->getExpenditureTransactions($store_id, $start_date, $end_date));
        }

        usort($transactions, function ($a, $b) {
            $time_a = strtotime($a['date'] . ' ' . $a['created_at']);
            $time_b = strtotime($b['date'] . ' ' . $b['created_at']);
            return $time_b <=> $time_a;
        });

        foreach ($transactions as &$transaction) {
            $transaction['method_class'] = strtoupper($transaction['payment_method']) === 'CASH' ? 'bg-success' : 'bg-info';
            $transaction['display_date'] = date('d M Y', strtotime($transaction['date']));
            $transaction['display_time'] = date('H:i', strtotime($transaction['created_at'] ?? '00:00:00'));
            $amount = 'Rp ' . number_format($transaction['amount'], 0, ',', '.');
            $transaction['income_display'] = $transaction['type'] === 'in' ? $amount : '-';
            $transaction['expense_display'] = $transaction['type'] === 'out' ? $amount : '-';
        }
        unset($transaction);

        return $transactions;
    }

    public function getDashboardData($store_id) {
        $today = date('Y-m-d');
        $this->refreshDailyFinance($store_id, $today);
        $totals = $this->finance->getDailyTotals($store_id, $today);
        $chart_dates = [];
        $chart_omzet = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $chart_dates[] = date('d M', strtotime($date));
            $chart_omzet[] = (float) ($this->finance->getDailyRevenue($store_id, $date) ?: 0);
        }

        return [
            'today' => $today,
            'saldo_akhir' => $this->finance->getBalance($store_id, $today) ?: 0,
            'omzet_hari_ini' => $totals['omzet_hari_ini'] ?? 0,
            'keluar_hari_ini' => $totals['keluar_hari_ini'] ?? 0,
            'chart_dates' => $chart_dates,
            'chart_omzet' => $chart_omzet
        ];
    }

    private function splitPaymentTotals(array $rows) {
        $totals = ['cash' => 0, 'transfer' => 0];

        foreach ($rows as $row) {
            $method = strtoupper($row['payment_method']);

            if ($method === 'CASH') {
                $totals['cash'] += $row['total'];
            } elseif ($method === 'TF' || $method === 'TRANSFER') {
                $totals['transfer'] += $row['total'];
            }
        }

        return $totals;
    }

    private function normalizePaymentMethod($payment_method) {
        return in_array(strtoupper(trim($payment_method)), ['TF', 'TRANSFER'], true) ? 'Transfer' : 'Cash';
    }

    private function updateAutomaticIncome($store_id, $date) {
        $opening = $this->finance->getOpeningBalance($store_id, $date);
        $balance = (float) ($opening['awal_cash'] ?? 0);

        if ($balance <= 0) {
            return;
        }

        $description = 'Saldo Otomatis ' . date('Y-m-d', strtotime($date));
        $income = $this->finance->getAutomaticIncome($store_id, $date);

        if ($income) {
            $this->finance->updateAutomaticIncome($store_id, $income['id'], $description, $balance);
        } else {
            $this->finance->addIncome($store_id, $date, $description, $balance, 'Cash');
        }
    }
}
