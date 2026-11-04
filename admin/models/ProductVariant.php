<?php

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
        $stmt = $this->db->prepare('SELECT c.variant_id, c.name, c.quantity FROM product_variant_components c JOIN product_variants v ON v.id = c.variant_id WHERE v.store_id = ? AND v.product_id = ? ORDER BY c.id');
        $stmt->bind_param('ii', $store, $product); $stmt->execute();
        $components = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $c) $components[(int)$c['variant_id']][] = ['name' => $c['name'], 'quantity' => (float)$c['quantity']];
        $stmt->close();
        foreach ($rows as &$row) {
            $row['options'] = json_decode($row['options_json'], true, 512, JSON_THROW_ON_ERROR);
            $row['label'] = implode(' / ', array_values($row['options']));
            $row['components'] = $components[(int)$row['id']] ?? [];
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
        foreach ($variants as $variant) if ((int)$variant['id'] === $id) return [
            'id' => $id, 'options' => $variant['options'], 'label' => $variant['label'], 'price' => (float)$variant['price'],
            'components' => $variant['components'], 'product_name' => $product['name'], 'category' => $product['category']
        ];
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

    // Nama komponen adalah nama yang ditampilkan di meteran, bukan produk tambahan di nota.
    public function productionTotals($store, $start, $end, $category = null, $includeDefault = false) {
        if (!$this->isAvailable()) return [];
        $stmt = $this->db->prepare('SELECT oi.quantity, oi.product_variant_data FROM order_items oi JOIN orders o ON o.order_id = oi.order_id AND o.store_id = oi.store_id WHERE oi.store_id = ? AND o.date BETWEEN ? AND ? AND oi.product_variant_data IS NOT NULL');
        $stmt->bind_param('iss', $store, $start, $end); $stmt->execute();
        $totals = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $saved = self::snapshot($row);
            if ($category !== null && strtoupper(trim($saved['category'])) !== $category) continue;
            $components = $saved['components'];
            if (!$components && $includeDefault) $components = [['name' => $saved['product_name'] . ' - ' . $saved['label'], 'quantity' => 1]];
            foreach ($components as $c) $totals[$c['name']] = round(($totals[$c['name']] ?? 0) + (float)$c['quantity'] * (int)$row['quantity'], 4);
        }
        $stmt->close();
        return $totals;
    }
}
