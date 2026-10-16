<?php

require_once __DIR__ . '/FinanceController.php';
require_once __DIR__ . '/OrderController.php';
require_once __DIR__ . '/../models/Center.php';

class CenterFinanceController {
    private $center;
    private $finance;
    private $finances;
    private $base_url;

    public function __construct(Center $center, Finance $finance, FinanceController $finances, $base_url = '/center') {
        $this->center = $center;
        $this->finance = $finance;
        $this->finances = $finances;
        $this->base_url = rtrim($base_url, '/');
    }

    public function handle($action, array $query, array $post) {
        try {
            if ($action === 'aruskas') {
                return $this->index($query);
            }
            if ($action === 'cash-entry') {
                return $this->entry($query);
            }
            if ($action === 'sync-aruskas') {
                return $this->sync($post);
            }
            return $this->save($action, $post);
        } catch (InvalidArgumentException $e) {
            return ['view' => 'error', 'status_code' => 400, 'error' => $e->getMessage(), 'return_url' => $this->base_url . '/aruskas'];
        } catch (OutOfBoundsException $e) {
            return ['view' => 'error', 'status_code' => 404, 'error' => $e->getMessage(), 'return_url' => $this->base_url . '/aruskas'];
        }
    }

    private function index(array $query) {
        [$start, $end] = $this->interval($query);
        $store_options = $this->center->getStoreOptions();
        $store_id = max(0, (int) ($query['store_id'] ?? 0));
        if ($store_id === 0 && $store_options) {
            $store_id = (int) $store_options[0]['id'];
        }
        if ($store_id === 0) {
            return ['view' => 'aruskas', 'report_available' => false, 'store_options' => $store_options];
        }
        $this->requireStore($store_id);
        $type = $this->text($query, 'type');
        if (!in_array($type, ['', 'in', 'out'], true)) {
            throw new InvalidArgumentException('Filter jenis transaksi tidak valid.');
        }
        $report = $this->finances->index($store_id, ['start_date' => $start, 'end_date' => $end, 'type' => $type], []);
        foreach ($report['transactions'] as &$row) {
            $automatic = ($row['source'] ?? '') === 'income' && stripos($row['description'], 'Saldo Otomatis') === 0;
            $row['action_url'] = !$automatic && isset($row['id'], $row['source'])
                ? $this->base_url . '/cash-entry?' . http_build_query(['store_id' => $store_id, 'source' => $row['source'], 'id' => $row['id']])
                : '';
        }
        unset($row);
        $payments = $type === 'out' ? [] : $this->finance->getOrderPayments($store_id, $start, $end);
        foreach ($payments as &$payment) {
            $payment['payment_type'] = OrderController::paymentType($payment['order_total'], $payment['paid_before'], $payment['nominal']);
            $payment['display_date'] = date('d M Y, H:i', strtotime($payment['create_at']));
            $payment['action_url'] = $this->base_url . '/order?id=' . (int) $payment['order_id'];
        }
        unset($payment);
        return array_replace($report, [
            'view' => 'aruskas', 'report_available' => true,
            'store_id' => $store_id, 'store_options' => $store_options,
            'cash_page_url' => $this->base_url . '/aruskas',
            'cash_post_url' => $this->base_url . '/save-cash-entry',
            'cash_delete_post_url' => $this->base_url . '/delete-cash-entry',
            'cash_sync_post_url' => $this->base_url . '/sync-aruskas',
            'payment_delete_post_url' => $this->base_url . '/delete-payment',
            'payments' => $payments,
            'cash_tables' => [
                ['title' => 'Uang Masuk', 'color' => 'success', 'type' => 'in', 'rows' => array_values(array_filter($report['transactions'], fn($row) => $row['type'] === 'in'))],
                ['title' => 'Uang Keluar', 'color' => 'danger', 'type' => 'out', 'rows' => array_values(array_filter($report['transactions'], fn($row) => $row['type'] === 'out'))]
            ],
            'cash_form_fields' => ['store_id' => $store_id],
            'cash_embedded' => true, 'cash_actions' => true
        ]);
    }

    private function entry(array $query) {
        $source = $this->source($query);
        $store_id = max(0, (int) ($query['store_id'] ?? 0));
        $id = $this->entryId($query);
        $entry = ['id' => 0, 'date' => date('Y-m-d'), 'description' => '', 'amount' => '', 'payment_method' => 'Cash'];
        if ($id > 0) {
            $this->requireStore($store_id);
            $entry = $this->requireEntry($source, $store_id, $id);
        } elseif ($store_id > 0) {
            $this->requireStore($store_id);
        }
        $entry['payment_method'] = strtoupper($entry['payment_method']) === 'CASH' ? 'Cash' : 'Transfer';
        return ['view' => 'cash-entry', 'entry' => $entry, 'source' => $source, 'store_id' => $store_id, 'store_options' => $this->center->getStoreOptions()];
    }

    private function save($action, array $post) {
        if (!in_array($action, ['save-cash-entry', 'delete-cash-entry'], true)) {
            throw new InvalidArgumentException('Aksi arus kas tidak tersedia.');
        }
        $source = $this->source($post);
        $store_id = (int) ($post['store_id'] ?? 0);
        $this->requireStore($store_id);
        $id = $this->entryId($post);
        $deleting = $action === 'delete-cash-entry';
        if ($deleting && $id < 1) {
            throw new InvalidArgumentException('Transaksi tidak valid.');
        }
        $data = [];
        if (!$deleting) {
            $date = $this->date($this->text($post, 'date'));
            $description = $this->text($post, 'description');
            $amount = $post['amount'] ?? null;
            $method = strtoupper($this->text($post, 'payment_method'));
            if ($description === '' || stripos($description, 'Saldo Otomatis') === 0) {
                throw new InvalidArgumentException('Isi keterangan transaksi tanpa awalan Saldo Otomatis.');
            }
            if (!is_numeric($amount) || !is_finite((float) $amount) || round((float) $amount, 2) <= 0) {
                throw new InvalidArgumentException('Nominal harus lebih besar dari nol.');
            }
            if (!in_array($method, ['CASH', 'TF', 'TRANSFER'], true)) {
                throw new InvalidArgumentException('Pilih metode Cash atau Transfer.');
            }
            $data = ['date' => $date, 'description' => $description, 'amount' => round((float) $amount, 2), 'payment_method' => $method === 'CASH' ? 'Cash' : 'Transfer'];
        }
        $date = $this->finance->transaction(function() use ($source, $store_id, $id, $deleting, $data) {
            $existing = $id > 0 ? $this->requireEntry($source, $store_id, $id, true) : null;
            if ($deleting) {
                $saved = $this->finance->deleteManualEntry($source, $store_id, $id);
            } elseif ($existing) {
                $saved = $this->finance->updateManualEntry($source, $store_id, $id, $data);
            } else {
                $saved = $source === 'income'
                    ? $this->finance->addIncome($store_id, $data['date'], $data['description'], $data['amount'], $data['payment_method'])
                    : $this->finance->addExpenditure($store_id, $data['date'], $data['description'], $data['amount'], $data['payment_method']);
            }
            if (!$saved) {
                throw new RuntimeException('Transaksi arus kas gagal disimpan.');
            }
            $dates = array_unique(array_filter([$existing['date'] ?? null, $data['date'] ?? null]));
            foreach ($dates as $date) {
                if (!$this->finances->refreshDailyFinance($store_id, $date)) {
                    throw new RuntimeException('Ringkasan arus kas gagal diperbarui.');
                }
            }
            return $data['date'] ?? $existing['date'];
        });
        return ['redirect' => $this->base_url . '/aruskas?' . http_build_query(['store_id' => $store_id, 'start_date' => $date, 'end_date' => $date]), 'notice' => $deleting ? 'Transaksi arus kas berhasil dihapus.' : 'Transaksi arus kas berhasil disimpan.'];
    }

    private function sync(array $post) {
        [$start, $end] = $this->interval($post);
        $store_id = (int) ($post['store_id'] ?? 0);
        $this->requireStore($store_id);
        $first = new DateTimeImmutable($start);
        $last = new DateTimeImmutable($end);
        if ($first->diff($last)->days > 365) {
            throw new InvalidArgumentException('Sinkronisasi maksimal 366 hari dalam satu proses.');
        }
        $this->finance->transaction(function() use ($store_id, $first, $last) {
            for ($date = $first; $date <= $last; $date = $date->modify('+1 day')) {
                if (!$this->finances->refreshDailyFinance($store_id, $date->format('Y-m-d'))) {
                    throw new RuntimeException('Sinkronisasi arus kas gagal disimpan.');
                }
            }
        });
        return ['redirect' => $this->base_url . '/aruskas?' . http_build_query(['store_id' => $store_id, 'start_date' => $start, 'end_date' => $end]), 'notice' => 'Ringkasan arus kas toko berhasil disinkronkan.'];
    }

    private function requireEntry($source, $store_id, $id, $lock = false) {
        $entry = $this->finance->getManualEntry($source, $store_id, $id, $lock);
        if (!$entry) {
            throw new OutOfBoundsException('Transaksi tidak ditemukan di toko ini.');
        }
        if ($source === 'income' && stripos($entry['description'], 'Saldo Otomatis') === 0) {
            throw new InvalidArgumentException('Saldo otomatis merupakan bawaan saldo dan tidak dapat diedit sebagai pemasukan manual.');
        }
        return $entry;
    }

    private function requireStore($id) {
        if ($id < 1 || !$this->center->findStoreById($id)) {
            throw new OutOfBoundsException('Toko tidak ditemukan.');
        }
    }

    private function entryId(array $data) {
        $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);
        if ($id === false || $id < 0) {
            throw new InvalidArgumentException('ID transaksi tidak valid.');
        }
        return $id;
    }

    private function source(array $data) {
        $source = $this->text($data, 'source', 'income');
        if (!in_array($source, ['income', 'expenditure'], true)) {
            throw new InvalidArgumentException('Pilih pemasukan atau pengeluaran manual.');
        }
        return $source;
    }

    private function interval(array $data) {
        $start = $this->date($this->text($data, 'start_date', date('Y-m-d')));
        $end = $this->date($this->text($data, 'end_date', date('Y-m-d')));
        if ($start > $end) {
            throw new InvalidArgumentException('Tanggal awal tidak boleh melewati tanggal akhir.');
        }
        return [$start, $end];
    }

    private function date($date) {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Tanggal tidak valid.');
        }
        return $date;
    }

    private function text(array $data, $key, $default = '') {
        return is_string($data[$key] ?? $default) ? trim($data[$key] ?? $default) : $default;
    }
}
