<?php
require_once __DIR__ . '/ProductionComponent.php';

class ProductVariant {
    private $db;
    private $ready;

    public function __construct($db) { $this->db = $db; }

    public function isAvailable() {
        if ($this->ready === null) {
            $row = $this->db->query("SELECT
                (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('product_variants', 'product_variant_components')) AS tables_ready,
                (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME IN ('product_variant_id', 'product_variant_data')) AS columns_ready")->fetch_assoc();
            $this->ready = (int)$row['tables_ready'] === 2 && (int)$row['columns_ready'] === 2;
        }
        return $this->ready;
    }

    public static function options($names, $values) {
        if (!is_array($names) || !is_array($values) || count($names) !== count($values) || count($names) < 1 || count($names) > 8) throw new InvalidArgumentException('Isi 1 sampai 8 pilihan varian.');
        $result = []; $seen = [];
        foreach (array_values($names) as $i => $name) {
            $value = array_values($values)[$i];
            if (!is_scalar($name) || !is_scalar($value)) throw new InvalidArgumentException('Pilihan varian tidak valid.');
            $name = preg_replace('/\s+/u', ' ', trim((string)$name)); $value = preg_replace('/\s+/u', ' ', trim((string)$value));
            if (!$name || !$value || strlen($name) > 120 || strlen($value) > 240 || isset($seen[strtolower($name)])) throw new InvalidArgumentException('Nama pilihan dan nilainya wajib diisi, singkat, dan tidak boleh berulang.');
            $seen[strtolower($name)] = true; $result[$name] = $value;
        }
        return $result;
    }

    public static function snapshot($row) {
        if (empty($row['product_variant_data'])) return null;
        $data = json_decode($row['product_variant_data'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || empty($data['id']) || empty($data['options']) || !isset($data['price'], $data['components'], $data['product_name'], $data['category'])) throw new RuntimeException('Data varian pada nota tidak lengkap.');
        return $data;
    }

    public function getByProduct($store, $product) {
        if (!$this->isAvailable()) return [];
        $stmt = $this->db->prepare('SELECT v.* FROM product_variants v JOIN products p ON p.product_id = v.product_id AND p.store_id = v.store_id WHERE v.store_id = ? AND v.product_id = ? ORDER BY v.id');
        $stmt->bind_param('ii', $store, $product); $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        $componentModel = new ProductionComponent($this->db);
        $masters = $componentModel->isAvailable();
        $masterNames = $masters ? array_column($componentModel->getByStore($store), 'name', 'id') : [];
        $links = $masters ? $componentModel->links($store) : [];
        $fields = $masters ? 'c.id AS recipe_id, c.variant_id, c.component_id, COALESCE(m.name, c.name) AS name, c.quantity' : 'c.id AS recipe_id, c.variant_id, c.name, c.quantity';
        $join = $masters ? ' LEFT JOIN production_components m ON m.id = c.component_id AND m.store_id = v.store_id' : '';
        $stmt = $this->db->prepare("SELECT $fields FROM product_variant_components c JOIN product_variants v ON v.id = c.variant_id $join WHERE v.store_id = ? AND v.product_id = ? ORDER BY c.id");
        $stmt->bind_param('ii', $store, $product); $stmt->execute();
        $components = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $c) {
            $entry = ['name' => $c['name'], 'quantity' => (float)$c['quantity'], 'recipe_id' => (int)$c['recipe_id']];
            if (!empty($c['component_id'])) $entry['component_id'] = (int)$c['component_id'];
            $components[(int)$c['variant_id']][] = $entry;
        }
        $stmt->close();
        foreach ($rows as &$row) {
            $row['options'] = json_decode($row['options_json'], true, 512, JSON_THROW_ON_ERROR);
            $row['label'] = implode(' / ', array_values($row['options']));
            $row['components'] = $components[(int)$row['id']] ?? [];
            foreach ($row['components'] as &$component) {
                $linked = ProductionComponent::resolveId($component['component_id'] ?? ($links[ProductionComponent::legacyKey($row, $component['name'])] ?? 0), $links);
                if (isset($masterNames[$linked])) { $component['component_id'] = (int)$linked; $component['name'] = $masterNames[$linked]; }
            }
            unset($component);
            unset($row['options_json'], $row['combination_key']);
        }
        unset($row);
        return $rows;
    }

    public function resolve($store, $product, $input) {
        $id = $input['product_variant_id'] ?? 0;
        if ($id === '') $id = 0;
        if (!is_scalar($id) || !preg_match('/^\d+$/D', (string)$id) || (float)$id > 2147483647) throw new InvalidArgumentException('Pilihan varian tidak valid.');
        $id = (int)$id;
        if (!$this->isAvailable()) {
            if ($id) throw new InvalidArgumentException('Jalankan SQL product_variants.sql terlebih dahulu.');
            return null;
        }
        $editing = (int)($input['order_item_id'] ?? 0);
        if ($editing) {
            $stmt = $this->db->prepare('SELECT product_variant_id, product_variant_data FROM order_items WHERE order_item_id = ? AND order_id = ? AND store_id = ? AND product_id = ?');
            $order = (int)($input['order_id'] ?? 0); $productId = (int)$product['product_id'];
            $stmt->bind_param('iiii', $editing, $order, $store, $productId); $stmt->execute(); $old = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if ($old && (int)$old['product_variant_id'] === $id) {
                // Harga/pilihan/komponen tersimpan tetap dapat dipakai saat varian diubah atau dihapus.
                if ($saved = self::snapshot($old)) return $saved;
                if (!$id) return null;
            }
            if ($old && !empty($old['product_variant_id']) && !$id) throw new InvalidArgumentException('Item ini memakai varian. Pilih varian sesuai nota atau perbarui aplikasi.');
        }
        $variants = $this->getByProduct($store, (int)$product['product_id']);
        foreach ($variants as $variant) if ((int)$variant['id'] === $id) {
            // ID baris resep hanya untuk formulir pengaturan, bukan identitas komponen pada nota.
            foreach ($variant['components'] as &$component) unset($component['recipe_id']);
            unset($component);
            return [
            'id' => $id, 'options' => $variant['options'], 'label' => $variant['label'], 'price' => (float)$variant['price'],
            'components' => $variant['components'], 'product_name' => $product['name'], 'category' => $product['category']
            ];
        }
        if ($id || $variants) throw new InvalidArgumentException('Pilih kombinasi varian yang tersedia. Jika pilihan varian belum muncul, perbarui aplikasi atau muat ulang data.');
        return null;
    }

    public static function describe($items) {
        foreach ($items as &$row) {
            $row['variant'] = self::snapshot($row);
            $row['product_variant_id'] = $row['product_variant_id'] ?? null;
            unset($row['product_variant_data']);
        }
        unset($row);
        return $items;
    }

    // Harga dan jumlah dari nota; nama terbaru dari master yang terhubung dengan ID.
    public function productionRows($store, $start, $end, $category = null, $includeDefault = false) {
        if (!$this->isAvailable()) return [];
        $masterModel = new ProductionComponent($this->db);
        $ready = $masterModel->isAvailable();
        $masters = array_column($masterModel->getByStore($store), 'name', 'id');
        $links = $masterModel->links($store);
        $stmt = $this->db->prepare('SELECT oi.quantity, oi.product_variant_data FROM order_items oi JOIN orders o ON o.order_id = oi.order_id AND o.store_id = oi.store_id WHERE oi.store_id = ? AND o.date BETWEEN ? AND ? AND oi.product_variant_data IS NOT NULL');
        $stmt->bind_param('iss', $store, $start, $end); $stmt->execute();
        $totals = []; $result = $stmt->get_result();
        $categories = $category === null ? null : array_map(function ($name) { return strtoupper(trim($name)); }, (array)$category);
        while ($row = $result->fetch_assoc()) {
            $saved = self::snapshot($row);
            if ($categories !== null && !in_array(strtoupper(trim($saved['category'])), $categories, true)) continue;
            $components = $saved['components'];
            $default = !$components && $includeDefault;
            if ($default) $components = [['name' => $saved['product_name'] . ' - ' . $saved['label'], 'quantity' => 1]];
            foreach ($components as $c) {
                $legacy = ProductionComponent::legacyKey($saved, $c['name']);
                $id = ProductionComponent::resolveId(!empty($c['component_id']) ? $c['component_id'] : ($links[$legacy] ?? 0), $links);
                $key = $id ? 'component:' . $id : ($ready ? 'legacy:' . $legacy : 'name:' . $c['name']);
                $name = $masters[$id] ?? $c['name'];
                if ($ready && !$id && !$default) $name .= ' - ' . $saved['product_name'] . ' / ' . $saved['label'] . ' (belum dihubungkan)';
                if (!isset($totals[$key])) $totals[$key] = ['component_id' => $id ?: null, 'name' => $name, 'total_qty' => 0];
                $totals[$key]['total_qty'] = round($totals[$key]['total_qty'] + (float)$c['quantity'] * (int)$row['quantity'], 4);
            }
        }
        $stmt->close();
        return array_values($totals);
    }
}
