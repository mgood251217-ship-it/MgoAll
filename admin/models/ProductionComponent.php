<?php

class ProductionComponent {
    private $db;
    private $ready;

    public function __construct($db) {
        $this->db = $db;
    }

    public function isAvailable() {
        if ($this->ready === null) {
            $row = $this->db->query("SELECT
                (SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'production_components') AS tables_ready,
                EXISTS(SELECT 1 FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_variant_components'
                      AND COLUMN_NAME = 'component_id') AS column_ready
            ")->fetch_assoc();
            $this->ready = (int)$row['tables_ready'] === 1 && (bool)$row['column_ready'];
        }

        return $this->ready;
    }

    public function findByName($store, $name) {
        $stmt = $this->db->prepare('SELECT id, name FROM production_components WHERE store_id = ? AND name = ?');
        $stmt->bind_param('is', $store, $name);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row;
    }

    public function findById($store, $id) {
        $stmt = $this->db->prepare('SELECT name FROM production_components WHERE id = ? AND store_id = ?');
        $stmt->bind_param('ii', $id, $store);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row;
    }

    public function create($store, $name) {
        $stmt = $this->db->prepare('INSERT INTO production_components (store_id, name) VALUES (?, ?)');
        $stmt->bind_param('is', $store, $name);
        $stmt->execute();
        $id = (int)$this->db->insert_id;
        $stmt->close();

        return $id;
    }

    public function rename($store, $id, $name) {
        $stmt = $this->db->prepare('UPDATE production_components SET name = ? WHERE id = ? AND store_id = ?');
        $stmt->bind_param('sii', $name, $id, $store);
        $stmt->execute();
        $stmt->close();
    }

    public function updateVariantComponents($store, $source, $target) {
        $stmt = $this->db->prepare('UPDATE product_variant_components c JOIN product_variants v ON v.id = c.variant_id SET c.component_id = ? WHERE v.store_id = ? AND c.component_id = ?');
        $stmt->bind_param('iii', $target, $store, $source);
        $stmt->execute();
        $stmt->close();
    }

    public function delete($store, $id) {
        $stmt = $this->db->prepare('DELETE FROM production_components WHERE id = ? AND store_id = ?');
        $stmt->bind_param('ii', $id, $store);
        $stmt->execute();
        $stmt->close();
    }

    public function getByStore($store) {
        if (!$this->isAvailable()) {
            return [];
        }
        $stmt = $this->db->prepare('SELECT id, name FROM production_components WHERE store_id = ? ORDER BY name, id');
        $stmt->bind_param('i', $store);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    public function rewriteSnapshots($store, $rewrite, $product = 0) {
        $changed = 0;
        foreach (['order_items', 'deleted_order_items', 'failure'] as $table) {
            $stmt = $this->db->prepare('SELECT COLUMN_NAME, COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->bind_param('s', $table); $stmt->execute();
            $columns = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'COLUMN_KEY', 'COLUMN_NAME'); $stmt->close();
            if (!isset($columns['store_id'])) continue;
            $fields = array_values(array_intersect(['product_variant_data', 'tier_price_data'], array_keys($columns)));
            if (!$fields) continue;
            $keys = array_keys(array_filter($columns, function ($key) { return $key === 'PRI'; }));
            if (!$keys) foreach (['order_item_id', 'failure_id', 'id'] as $key) if (isset($columns[$key])) { $keys = [$key]; break; }
            if (!$keys) throw new RuntimeException('Identitas baris ' . $table . ' tidak ditemukan.');
            $where = 'store_id = ?'; $params = [$store];
            if ($product && isset($columns['product_id'])) { $where .= ' AND product_id = ?'; $params[] = $product; }
            $conditions = array_map(function ($field) { return "`$field` LIKE '%components%'"; }, $fields);
            $stmt = $this->db->prepare("SELECT * FROM `$table` WHERE $where AND (" . implode(' OR ', $conditions) . ') FOR UPDATE');
            $stmt->bind_param(str_repeat('i', count($params)), ...$params); $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
            foreach ($rows as $row) foreach ($fields as $field) {
                if (empty($row[$field])) continue;
                $data = json_decode($row[$field], true, 512, JSON_THROW_ON_ERROR);
                $updated = $rewrite($data, $row);
                if ($updated === $data) continue;
                $json = json_encode($updated, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $match = array_unique(array_merge($keys, ['store_id', $field]));
                $params = [$json]; $where = [];
                foreach ($match as $key) { $where[] = "`$key` <=> ?"; $params[] = $row[$key]; }
                $stmt = $this->db->prepare("UPDATE `$table` SET `$field` = ? WHERE " . implode(' AND ', $where));
                $stmt->bind_param(str_repeat('s', count($params)), ...$params); $stmt->execute(); $changed += $stmt->affected_rows; $stmt->close();
            }
        }
        return $changed;
    }
}
