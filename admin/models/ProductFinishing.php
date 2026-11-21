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
            $row = $this->db->query("SELECT
                EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_finishings') AS links_ready,
                EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'finishing_mode') AS mode_ready")->fetch_assoc();
            $this->ready = (bool)$row['links_ready'] && (bool)$row['mode_ready'];
        }
        return $this->ready;
    }

    public function product($store, $product) {
        $mode = $this->isAvailable() ? 'finishing_mode' : "'CATEGORY' AS finishing_mode";
        $stmt = $this->query("SELECT product_id, category_id, $mode FROM products WHERE store_id = ? AND product_id = ?", [$store, $product]);
        $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        return $row;
    }

    public function getByProduct($store, $product) {
        $row = $this->product($store, $product);
        if (!$row) throw new InvalidArgumentException('Produk tidak tersedia pada toko ini.');
        if ($row['finishing_mode'] === 'PRODUCT') {
            $stmt = $this->query('SELECT f.* FROM finishings f JOIN product_finishings pf ON pf.finishing_id = f.finishing_id WHERE pf.product_id = ? AND f.store_id = ? ORDER BY f.name, f.finishing_id', [$product, $store]);
        } else {
            $stmt = $this->query('SELECT * FROM finishings WHERE store_id = ? AND category_id = ? ORDER BY name, finishing_id', [$store, $row['category_id']]);
        }
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        return $rows;
    }

    public function previousIds($store, $product, $item) {
        if (!$item) return [];
        $stmt = $this->query('SELECT finishing FROM order_items WHERE store_id = ? AND product_id = ? AND order_item_id = ?', [$store, $product, $item]);
        $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        return ProductLaserConversionController::finishingIds($row['finishing'] ?? '');
    }

    public function linksByStore($store) {
        if (!$this->isAvailable()) return [];
        $stmt = $this->query('SELECT pf.product_id, pf.finishing_id FROM product_finishings pf JOIN products p ON p.product_id = pf.product_id WHERE p.store_id = ?', [$store]);
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        return $rows;
    }

    public function finishingsByIds($store, $ids) {
        if (!$ids) return [];
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->query("SELECT * FROM finishings WHERE store_id = ? AND finishing_id IN ($marks)", array_merge([$store], $ids));
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        return $rows;
    }

    public function cleanupDeleted($ids, $column = 'product_id') {
        if (!$ids || !$this->isAvailable() || !in_array($column, ['product_id', 'finishing_id'], true)) return;
        $table = $column === 'product_id' ? 'products' : 'finishings';
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $this->query("DELETE pf FROM product_finishings pf LEFT JOIN $table t ON t.$column = pf.$column
            WHERE t.$column IS NULL AND pf.$column IN ($marks)", $ids)->close();
    }

    public function save($store, $product, $mode, $ids) {
        $this->query('UPDATE products SET finishing_mode = ? WHERE store_id = ? AND product_id = ?', [$mode, $store, $product])->close();
        $this->query('DELETE FROM product_finishings WHERE product_id = ?', [$product])->close();
        if ($mode === 'PRODUCT') foreach ($ids as $id) {
            $this->query('INSERT INTO product_finishings (product_id, finishing_id) VALUES (?, ?)', [$product, $id])->close();
        }
    }
}
