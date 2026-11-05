<?php

class ProductionComponent {
    private $db;
    private $ready;
    public function __construct($db) { $this->db = $db; }

    public function isAvailable() {
        if ($this->ready === null) {
            $row = $this->db->query("SELECT
                (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('production_components', 'production_component_links')) AS tables_ready,
                EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_variant_components' AND COLUMN_NAME = 'component_id') AS column_ready")->fetch_assoc();
            $this->ready = (int)$row['tables_ready'] === 2 && (bool)$row['column_ready'];
        }
        return $this->ready;
    }

    public static function name($value) {
        if (!is_scalar($value)) throw new InvalidArgumentException('Nama komponen tidak valid.');
        $value = preg_replace('/\s+/u', ' ', trim((string)$value));
        if ($value === null || !preg_match('/^.{1,150}$/us', $value)) throw new InvalidArgumentException('Isi nama komponen, maksimal 150 karakter.');
        return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
    }

    // Dipanggil di dalam transaksi pengaturan varian, setelah baris toko dikunci.
    public function findOrCreate($store, $name) {
        $name = self::name($name);
        $stmt = $this->db->prepare('SELECT id, name FROM production_components WHERE store_id = ? AND name = ?');
        $stmt->bind_param('is', $store, $name); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($row) return ['id' => (int)$row['id'], 'name' => $row['name']];
        $stmt = $this->db->prepare('INSERT INTO production_components (store_id, name) VALUES (?, ?)');
        $stmt->bind_param('is', $store, $name); $stmt->execute(); $id = (int)$this->db->insert_id; $stmt->close();
        return ['id' => $id, 'name' => $name];
    }

    public function getByStore($store) {
        if (!$this->isAvailable()) return [];
        $stmt = $this->db->prepare('SELECT id, name FROM production_components WHERE store_id = ? ORDER BY name, id');
        $stmt->bind_param('i', $store); $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        return $rows;
    }

    public function links($store) {
        if (!$this->isAvailable()) return [];
        $stmt = $this->db->prepare('SELECT legacy_key, component_id FROM production_component_links WHERE store_id = ?');
        $stmt->bind_param('i', $store); $stmt->execute();
        $result = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'component_id', 'legacy_key'); $stmt->close();
        return $result;
    }

    public static function legacyKey($snapshot, $name) {
        return hash('sha256', json_encode([(int)$snapshot['id'], $snapshot['options'], $name], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function link($store, $product, $snapshot, $name, $component, $onlyMissing = false) {
        $key = self::legacyKey($snapshot, $name); $variant = (int)$snapshot['id'];
        $update = $onlyMissing ? 'component_id = production_component_links.component_id' : 'component_id = VALUES(component_id)';
        $stmt = $this->db->prepare("INSERT INTO production_component_links (store_id, product_id, variant_id, legacy_key, legacy_name, component_id) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE $update");
        $stmt->bind_param('iiissi', $store, $product, $variant, $key, $name, $component); $stmt->execute(); $stmt->close();
    }

    public function legacyGroups($store, $product = 0) {
        if (!$this->isAvailable()) return [];
        $links = $this->links($store);
        $stmt = $this->db->prepare('SELECT product_id, quantity, product_variant_data FROM order_items WHERE store_id = ? AND (? = 0 OR product_id = ?) AND product_variant_data IS NOT NULL ORDER BY order_item_id');
        $stmt->bind_param('iii', $store, $product, $product); $stmt->execute(); $result = $stmt->get_result();
        $groups = [];
        while ($row = $result->fetch_assoc()) {
            $saved = ProductVariant::snapshot($row);
            foreach ($saved['components'] as $c) {
                if (!empty($c['component_id'])) continue;
                $key = self::legacyKey($saved, $c['name']);
                if (!isset($groups[$key])) $groups[$key] = ['key' => $key, 'product_id' => (int)$row['product_id'],
                    'snapshot' => ['id' => (int)$saved['id'], 'options' => $saved['options']], 'product_name' => $saved['product_name'],
                    'variant_label' => $saved['label'], 'name' => $c['name'], 'quantity' => 0, 'item_count' => 0, 'component_id' => (int)($links[$key] ?? 0)];
                $groups[$key]['quantity'] = round($groups[$key]['quantity'] + (float)$c['quantity'] * (int)$row['quantity'], 4);
                $groups[$key]['item_count']++;
            }
        }
        $stmt->close();
        return $groups;
    }
}
