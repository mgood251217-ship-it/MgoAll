<?php
class ProductFinishing {
    private $db;
    private $ready;

    public function __construct($db) { $this->db = $db; }

    private function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        $stmt->execute();
        return $stmt;
    }

    public function isAvailable() {
        if ($this->ready === null) {
            $row = $this->db->query("SELECT EXISTS(SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'finishing_ids') AS ready")->fetch_assoc();
            $this->ready = (bool)$row['ready'];
        }
        return $this->ready;
    }

    public static function ids($json) {
        $ids = json_decode((string)$json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($ids) || !array_is_list($ids)) throw new RuntimeException('Daftar finishing produk tidak valid.');
        foreach ($ids as $id) {
            if ((!is_int($id) && !is_string($id)) || !ctype_digit((string)$id) || (int)$id <= 0) {
                throw new RuntimeException('ID finishing produk tidak valid.');
            }
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids, SORT_NUMERIC);
        return $ids;
    }

    public function product($store, $product) {
        $field = $this->isAvailable() ? 'finishing_ids' : 'NULL AS finishing_ids';
        $stmt = $this->query("SELECT product_id, category_id, $field FROM products WHERE store_id = ? AND product_id = ?", [$store, $product]);
        $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        return $row;
    }

    public function getByProduct($store, $product) {
        $row = $this->product($store, $product);
        if (!$row) throw new InvalidArgumentException('Produk tidak tersedia pada toko ini.');
        if ($this->isAvailable()) return $this->finishingsByIds($store, self::ids($row['finishing_ids']));
        $stmt = $this->query('SELECT * FROM finishings WHERE store_id = ? AND category_id = ? ORDER BY name, finishing_id', [$store, $row['category_id']]);
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        return $rows;
    }

    public function previousIds($store, $product, $item) {
        if (!$item) return [];
        $stmt = $this->query('SELECT finishing FROM order_items WHERE store_id = ? AND product_id = ? AND order_item_id = ?', [$store, $product, $item]);
        $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        return ProductLaserConversionController::finishingIds($row['finishing'] ?? '');
    }

    public function allowedIdsByProducts($store, $products) {
        $products = array_values(array_unique(array_map('intval', $products)));
        if (!$products) return [];
        $marks = implode(',', array_fill(0, count($products), '?'));
        $allowed = [];
        if ($this->isAvailable()) {
            $stmt = $this->query("SELECT product_id, finishing_ids FROM products WHERE store_id = ? AND product_id IN ($marks)", array_merge([$store], $products));
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
            $stmt = $this->query('SELECT finishing_id FROM finishings WHERE store_id = ?', [$store]);
            $available = array_fill_keys(array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'finishing_id'), true); $stmt->close();
            foreach ($rows as $row) $allowed[(int)$row['product_id']] = array_values(array_filter(self::ids($row['finishing_ids']), function ($id) use ($available) { return isset($available[$id]); }));
        } else {
            $stmt = $this->query("SELECT p.product_id, f.finishing_id FROM products p
                JOIN finishings f ON f.store_id = ? AND f.category_id = p.category_id
                WHERE p.store_id = ? AND p.product_id IN ($marks)", array_merge([$store, $store], $products));
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $allowed[(int)$row['product_id']][] = (int)$row['finishing_id'];
            $stmt->close();
        }
        return $allowed;
    }

    public function linksByStore($store) {
        if (!$this->isAvailable()) return [];
        $stmt = $this->query('SELECT product_id, finishing_ids FROM products WHERE store_id = ?', [$store]);
        $rows = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $product) {
            foreach (self::ids($product['finishing_ids']) as $id) $rows[] = ['product_id' => (int)$product['product_id'], 'finishing_id' => $id];
        }
        $stmt->close();
        return $rows;
    }

    public function finishingsByIds($store, $ids) {
        if (!$ids) return [];
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->query("SELECT * FROM finishings WHERE store_id = ? AND finishing_id IN ($marks) ORDER BY name, finishing_id", array_merge([$store], $ids));
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        return $rows;
    }

    public function save($store, $product, $ids) {
        $json = json_encode(self::ids(json_encode(array_values($ids), JSON_THROW_ON_ERROR)), JSON_THROW_ON_ERROR);
        $this->query('UPDATE products SET finishing_ids = ? WHERE store_id = ? AND product_id = ?', [$json, $store, $product])->close();
    }
}
