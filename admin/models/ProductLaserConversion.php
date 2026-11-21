<?php

class ProductLaserConversion {
    private $koneksi;
    private $available;
    private $variants;
    private $orderMaterials;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
    }

    public function isAvailable() {
        if ($this->available === null) {
            $result = $this->koneksi->query("SELECT EXISTS(SELECT 1 FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_laser_conversion') AS available");
            if (!$result) {
                throw new RuntimeException('Gagal memeriksa tabel konversi print laser.');
            }
            $this->available = (bool)$result->fetch_assoc()['available'];
        }
        return $this->available;
    }

    public function countsForProducts($store, $productIds) {
        if (!$productIds || !$this->isAvailable()) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $params = array_merge([(int)$store], array_map('intval', $productIds));
        $stmt = $this->koneksi->prepare("SELECT product_id, COUNT(*) AS total FROM product_laser_conversion WHERE store_id=? AND product_id IN ($placeholders) GROUP BY product_id");
        $stmt->bind_param(str_repeat('i', count($params)), ...$params);
        $stmt->execute();
        $counts = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'total', 'product_id');
        $stmt->close();
        return $counts;
    }

    public function hasVariants() {
        if (!$this->isAvailable()) {
            return false;
        }
        if ($this->variants === null) {
            $result = $this->koneksi->query("SELECT COUNT(*) AS total FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_laser_conversion'
                  AND COLUMN_NAME IN ('size', 'finishing_ids', 'price')");
            $this->variants = (int)$result->fetch_assoc()['total'] === 3;
        }
        return $this->variants;
    }

    public function hasOrderMaterials() {
        if ($this->orderMaterials === null) {
            $result = $this->koneksi->query("SELECT COUNT(*) AS total FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items'
                  AND COLUMN_NAME IN ('laser_product_id', 'laser_material_name')");
            $this->orderMaterials = (int)$result->fetch_assoc()['total'] === 2;
        }
        return $this->orderMaterials;
    }

    public function getByStore($store_id) {
        if (!$this->isAvailable()) {
            return [];
        }
        $legacyFields = $this->hasVariants() ? '' : ", '' AS size, '' AS finishing_ids, NULL AS price";
        $stmt = $this->koneksi->prepare("SELECT r.*, p.name AS product_name, c.name AS category,
                p.category_id, p.price AS product_price, lp.name AS laser_name $legacyFields,
                (p.product_id IS NOT NULL AND c.category_id IS NOT NULL
                    AND UPPER(TRIM(c.name)) = 'ANEKA CETAK' AND p.unit_type IN ('PCS', '~')
                    AND lp.product_id IS NOT NULL AND UPPER(TRIM(lc.name)) = 'LASER A3'
                    AND lp.unit_type IN ('PCS', '~') AND r.product_quantity > 0 AND r.print_quantity > 0) AS valid
            FROM product_laser_conversion r
            LEFT JOIN products p ON p.product_id = r.product_id AND p.store_id = ?
            LEFT JOIN categories c ON c.category_id = p.category_id AND c.store_id = ?
            LEFT JOIN products lp ON lp.product_id = r.laser_product_id AND lp.store_id = ?
            LEFT JOIN categories lc ON lc.category_id = lp.category_id AND lc.store_id = ?
            WHERE r.store_id = ? ORDER BY c.name, p.name, r.id");
        // Semua alias tetap dibatasi ke toko yang sama; store_id lama bisa berupa VARCHAR.
        $stmt->bind_param('iiiii', $store_id, $store_id, $store_id, $store_id, $store_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getFinishings($store_id) {
        $stmt = $this->koneksi->prepare("SELECT finishing_id, category_id, name FROM finishings WHERE store_id = ?");
        $stmt->bind_param('i', $store_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

}
