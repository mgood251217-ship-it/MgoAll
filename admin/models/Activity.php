<?php

class Activity {
    private $koneksi;
    private $ready;
    private $types;
    private $legacyColumns = [];

    public function __construct($koneksi) { $this->koneksi = $koneksi; }

    public function isAvailable() {
        if ($this->ready === null) {
            $row = $this->koneksi->query("SELECT
                EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activity_types') AS types_ready,
                (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activity' AND COLUMN_NAME IN ('activity_type_id', 'data')) AS columns_ready,
                EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activity' AND COLUMN_NAME = 'title') AS has_title,
                EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activity' AND COLUMN_NAME = 'message') AS has_message")->fetch_assoc();
            foreach (['title', 'message'] as $column) if ($row['has_' . $column]) $this->legacyColumns[] = $column;
            $this->ready = (bool)$row['types_ready'] && (int)$row['columns_ready'] === 2;
        }
        return $this->ready;
    }

    private function types() {
        if ($this->types === null) {
            $this->types = array_column($this->koneksi->query('SELECT id, code, title FROM activity_types')->fetch_all(MYSQLI_ASSOC), null, 'code');
        }
        return $this->types;
    }

    public static function changes($before, $after) {
        $changes = [];
        foreach ($after as $key => $value) {
            $old = $before[$key] ?? null;
            $same = in_array($key, ['nominal', 'expenditure_category_id'], true)
                ? (float)$old === (float)$value : (string)$old === (string)$value;
            if (!$same) $changes[$key] = ['before' => $old, 'after' => $value];
        }
        return $changes;
    }

    public function record($store, $code, $data, $information, $date, $order, $administrator) {
        if (!$this->isAvailable()) throw new RuntimeException('Skema aktivitas belum siap.');
        $type = $this->types()[$code] ?? null;
        if (!$type) throw new InvalidArgumentException('Jenis aktivitas tidak ditemukan.');
        $typeId = (int)$type['id'];
        unset($data['customer_name'], $data['nomorator']);
        $json = json_encode((object)$data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $columns = 'store_id, information, date, order_id, administrator_id, activity_type_id, data';
        $values = '?, ?, ?, ?, ?, ?, ?';
        // Menjaga penulisan tetap berjalan selama kode diunggah sebelum SQL pembersihan.
        foreach ($this->legacyColumns as $column) { $columns .= ', ' . $column; $values .= ", ''"; }
        $stmt = $this->koneksi->prepare("INSERT INTO activity ($columns) VALUES ($values)");
        $stmt->bind_param('issiiis', $store, $information, $date, $order, $administrator, $typeId, $json);
        $success = $stmt->execute(); $stmt->close();
        if (!$success) throw new RuntimeException('Aktivitas gagal dicatat.');
        return true;
    }

    public static function message($data) {
        if (isset($data['legacy_message'])) return (string)$data['legacy_message'];
        $labels = ['customer_name' => 'Pelanggan', 'nomorator' => 'Nota', 'payment_id' => 'ID Pembayaran',
            'expenditure_id' => 'ID Pengeluaran', 'income_id' => 'ID Pemasukan', 'information' => 'Keterangan',
            'nominal' => 'Nominal', 'payment_method' => 'Metode Pembayaran', 'date' => 'Tanggal',
            'expenditure_category_id' => 'ID Kategori'];
        $lines = [];
        foreach ($labels as $key => $label) if (isset($data[$key]) && is_scalar($data[$key])) $lines[] = $label . ': ' . $data[$key];
        foreach (($data['changes'] ?? []) as $key => $change) {
            if (is_array($change) && isset($labels[$key])) $lines[] = $labels[$key] . ': ' . ($change['before'] ?? '-') . ' => ' . ($change['after'] ?? '-');
        }
        return implode("\n", $lines);
    }

    private function orderContext($rows) {
        $groups = $context = [];
        foreach ($rows as $row) {
            $store = (int)($row['store_id'] ?? 0); $order = (int)($row['order_id'] ?? 0);
            if ($store > 0 && $order > 0) $groups[$store][$order] = $order;
        }
        foreach ($groups as $store => $ids) {
            // Order aktif diprioritaskan; arsip hanya dicari untuk order yang sudah dihapus.
            foreach (['orders', 'deleted_orders'] as $table) {
                if (!$ids) break;
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sort = $table === 'deleted_orders' ? ' ORDER BY deleted_at DESC' : '';
                $stmt = $this->koneksi->prepare("SELECT order_id, customer_name, nomorator FROM $table WHERE store_id = ? AND order_id IN ($placeholders)$sort");
                $params = array_merge([$store], array_values($ids));
                $stmt->bind_param(str_repeat('i', count($params)), ...$params); $stmt->execute();
                foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $order) {
                    $key = $store . ':' . $order['order_id'];
                    if (!isset($context[$key])) $context[$key] = $order;
                    unset($ids[(int)$order['order_id']]);
                }
                $stmt->close();
            }
        }
        return $context;
    }

    public function describe($rows) {
        if (!$rows || !$this->isAvailable()) return $rows;
        $types = array_column($this->types(), null, 'id');
        $orders = $this->orderContext($rows);
        foreach ($rows as &$row) {
            $type = $types[$row['activity_type_id'] ?? 0] ?? null;
            $data = json_decode($row['data'] ?? '', true);
            if (!$type || !is_array($data)) continue;
            unset($data['customer_name'], $data['nomorator']);
            $order = $orders[$row['store_id'] . ':' . $row['order_id']] ?? null;
            if ($order) { $data['customer_name'] = $order['customer_name']; $data['nomorator'] = $order['nomorator']; }
            $row['title'] = $data['legacy_title'] ?? $type['title'];
            $row['message'] = self::message($data);
        }
        unset($row);
        return $rows;
    }

    public function getActivitiesByStoreId($id, $start_date, $end_date) {
        $stmt = $this->koneksi->prepare('SELECT a.*, o.date AS order_date FROM activity a LEFT JOIN orders o ON a.order_id = o.order_id AND a.store_id = o.store_id WHERE a.store_id = ? AND a.date BETWEEN ? AND ?');
        $stmt->bind_param('iss', $id, $start_date, $end_date); $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        return $this->describe($rows);
    }
}
