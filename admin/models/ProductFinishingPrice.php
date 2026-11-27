<?php
require_once __DIR__ . '/../functions/productStock.php';

class ProductFinishingPrice {
    private $koneksi;
    private $available;
    private $tableName;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
    }

    public function isAvailable() {
        if ($this->available === null) {
            $result = $this->koneksi->query("SELECT
                CASE
                    WHEN EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_package') THEN 'product_package'
                    WHEN EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_finishing_prices') THEN 'product_finishing_prices'
                    ELSE NULL
                END AS package_table,
                EXISTS(SELECT 1 FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items'
                      AND COLUMN_NAME = 'product_finishing_price_id') AS has_reference");
            if (!$result) {
                throw new RuntimeException('Gagal memeriksa tabel harga paket.');
            }
            $schema = $result->fetch_assoc();
            // Pembacaan tetap berjalan saat backend diunggah sebelum SQL penggantian tabel.
            $this->tableName = $schema['package_table'];
            $this->available = $this->tableName !== null && (bool)$schema['has_reference'];
        }
        return $this->available;
    }

    public function hasCurrentSchema() {
        return $this->isAvailable() && $this->tableName === 'product_package';
    }

    public function countsForProducts($store, $productIds) {
        if (!$productIds || !$this->isAvailable()) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $params = array_merge([(int)$store], array_map('intval', $productIds));
        $stmt = $this->koneksi->prepare("SELECT product_id, COUNT(*) AS total FROM {$this->tableName} WHERE store_id=? AND product_id IN ($placeholders) GROUP BY product_id");
        $stmt->bind_param(str_repeat('i', count($params)), ...$params);
        $stmt->execute();
        $counts = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'total', 'product_id');
        $stmt->close();
        return $counts;
    }

    public function getByStore($store_id, $category_id = 0) {
        if (!$this->isAvailable()) {
            return [];
        }
        $finishingTracking = stockTrackingSql($this->koneksi, 'finishings', 'f');
        $stmt = $this->koneksi->prepare("SELECT r.*, p.name AS product_name,
                p.category_id, c.name AS category, f.name AS finishing_name,
                f.unit_type AS finishing_unit_type, ($finishingTracking) AS finishing_track_stock,
                (c.name IN ('INDOOR', 'OUTDOOR') AND p.unit_type IN ('M', 'M2')) AS uses_area,
                ((c.name IN ('INDOOR', 'OUTDOOR') AND p.unit_type IN ('M', 'M2'))
                    OR (c.name = 'LASER A3' AND r.panjang > 0 AND r.lebar > 0)) AS requires_size,
                (p.product_id IS NOT NULL AND c.category_id IS NOT NULL AND f.finishing_id IS NOT NULL
                    AND (
                        (c.name IN ('INDOOR', 'OUTDOOR') AND p.unit_type IN ('M', 'M2') AND r.panjang > 0 AND r.lebar > 0)
                        OR (c.name = 'LASER' AND p.unit_type IN ('PCS', '~') AND r.panjang = 0 AND r.lebar = 0)
                        OR (c.name = 'LASER A3' AND p.unit_type IN ('PCS', '~')
                            AND ((r.panjang = 0 AND r.lebar = 0) OR (r.panjang > 0 AND r.lebar > 0)))
                    )) AS valid
            FROM {$this->tableName} r
            LEFT JOIN products p ON p.product_id = r.product_id AND p.store_id = ?
            LEFT JOIN categories c ON c.category_id = p.category_id AND c.store_id = ?
            LEFT JOIN finishings f ON f.finishing_id = r.finishing_id AND f.store_id = ?
            WHERE r.store_id = ? AND (? = 0 OR p.category_id = ?)
            ORDER BY c.name, p.name, r.panjang, r.lebar");
        $stmt->bind_param('iiiiii', $store_id, $store_id, $store_id, $store_id, $category_id, $category_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getFinishings($store_id, $ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->koneksi->prepare("SELECT finishing_id FROM finishings WHERE store_id = ? AND finishing_id IN ($placeholders)");
        $params = array_merge([(int)$store_id], $ids);
        $types = str_repeat('i', count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $owned = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $owned;
    }

    public function getUnmappedFinishings($store_id) {
        $stmt = $this->koneksi->prepare("SELECT finishing_id FROM finishings
            WHERE store_id = ? AND UPPER(TRIM(name)) IN ('XBANNER', 'ROLLUP') AND price = 0");
        $stmt->bind_param('i', $store_id);
        $stmt->execute();
        $unmapped = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $unmapped;
    }
}
