<?php
class FinishingMigration {
    private $db;
    private $columns = [];
    public function __construct($db) { $this->db = $db; }

    public function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        $stmt->execute();
        return $stmt;
    }

    public function columns($table) {
        if (!isset($this->columns[$table])) {
            $stmt = $this->query('SELECT COLUMN_NAME, COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', [$table]);
            $this->columns[$table] = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'COLUMN_KEY', 'COLUMN_NAME');
            $stmt->close();
        }
        return $this->columns[$table];
    }

    public function lockStore($store) {
        $stmt = $this->query('SELECT administrator FROM stores WHERE store_id = ? FOR UPDATE', [$store]);
        $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        return $row;
    }

    public function lockFinishings($store, $ids) {
        sort($ids); $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->query("SELECT * FROM finishings WHERE store_id = ? AND finishing_id IN ($marks) ORDER BY finishing_id FOR UPDATE", array_merge([$store], $ids));
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        return array_column($rows, null, 'finishing_id');
    }

    public function rows($table, $store, $source, $csv, $snapshot = null) {
        $columns = $this->columns($table);
        if (!isset($columns['store_id'], $columns[$csv])) return [];
        $condition = "FIND_IN_SET(?, REPLACE(`$csv`, ' ', '')) > 0";
        $params = [$store, $source];
        if ($snapshot && isset($columns[$snapshot])) {
            $condition .= " OR `$snapshot` LIKE ?";
            $params[] = '%' . $source . '%';
        }
        $stmt = $this->query("SELECT * FROM `$table` WHERE store_id = ? AND ($condition) FOR UPDATE", $params);
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        return $rows;
    }

    public function updateRow($table, $row, $values) {
        $columns = $this->columns($table);
        $keys = array_keys(array_filter($columns, function ($key) { return $key === 'PRI'; }));
        if (!$keys) {
            foreach (['order_item_id', 'failure_id', 'id'] as $key) if (isset($columns[$key])) { $keys = [$key]; break; }
        }
        if (!$keys) throw new RuntimeException('Identitas baris ' . $table . ' tidak ditemukan.');
        if (isset($columns['store_id']) && !in_array('store_id', $keys, true)) $keys[] = 'store_id';
        // Nilai lama ikut dicocokkan agar arsip dengan ID sama tidak saling menimpa.
        $keys = array_unique(array_merge($keys, array_keys($values)));
        $where = []; $set = []; $params = array_values($values);
        foreach ($values as $key => $value) $set[] = "`$key` = ?";
        foreach ($keys as $key) { $where[] = "`$key` <=> ?"; $params[] = $row[$key]; }
        $this->query("UPDATE `$table` SET " . implode(', ', $set) . ' WHERE ' . implode(' AND ', $where), $params)->close();
    }

    public function linked($store, $source) {
        if (!$this->columns('product_finishings')) return false;
        $stmt = $this->query('SELECT 1 FROM product_finishings pf JOIN products p ON p.product_id = pf.product_id WHERE p.store_id = ? AND pf.finishing_id = ? LIMIT 1', [$store, $source]);
        $found = $stmt->get_result()->num_rows > 0; $stmt->close();
        return $found;
    }

    public function moveLinks($store, $source, $target) {
        if (!$this->columns('product_finishings')) return;
        $this->query('INSERT IGNORE INTO product_finishings(product_id, finishing_id) SELECT pf.product_id, ? FROM product_finishings pf JOIN products p ON p.product_id = pf.product_id WHERE p.store_id = ? AND pf.finishing_id = ?', [$target, $store, $source])->close();
        $this->query('DELETE pf FROM product_finishings pf JOIN products p ON p.product_id = pf.product_id WHERE p.store_id = ? AND pf.finishing_id = ?', [$store, $source])->close();
    }
}
