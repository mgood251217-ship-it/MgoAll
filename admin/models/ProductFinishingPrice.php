<?php

class ProductFinishingPrice {
    private $koneksi;
    private $available;
    private $tableName;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
    }

    public static function requiresDimensions($product) {
        return in_array(strtoupper(trim($product['category'] ?? '')), ['INDOOR', 'OUTDOOR'], true)
            && strtoupper($product['unit_type'] ?? '') === 'M2';
    }

    public static function supportsDimensions($product) {
        return self::requiresDimensions($product)
            || (strtoupper(trim($product['category'] ?? '')) === 'LASER A3'
                && in_array(strtoupper($product['unit_type'] ?? ''), ['PCS', '~'], true));
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
            if (!$result) throw new RuntimeException('Gagal memeriksa tabel harga paket.');
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

    public function getByStore($store_id, $category_id = 0, $validOnly = true) {
        if (!$this->isAvailable()) return [];
        $stmt = $this->koneksi->prepare("SELECT r.*, p.name AS product_name,
                p.category_id, c.name AS category, f.name AS finishing_name,
                f.unit_type AS finishing_unit_type,
                (c.name IN ('INDOOR', 'OUTDOOR') AND p.unit_type = 'M2') AS uses_area,
                ((c.name IN ('INDOOR', 'OUTDOOR') AND p.unit_type = 'M2')
                    OR (c.name = 'LASER A3' AND r.panjang > 0 AND r.lebar > 0)) AS requires_size,
                (p.product_id IS NOT NULL AND c.category_id IS NOT NULL AND f.finishing_id IS NOT NULL
                    AND f.category_id = p.category_id AND (
                        (c.name IN ('INDOOR', 'OUTDOOR') AND p.unit_type = 'M2' AND r.panjang > 0 AND r.lebar > 0)
                        OR (c.name = 'LASER' AND p.unit_type IN ('PCS', '~') AND r.panjang = 0 AND r.lebar = 0)
                        OR (c.name = 'LASER A3' AND p.unit_type IN ('PCS', '~')
                            AND ((r.panjang = 0 AND r.lebar = 0) OR (r.panjang > 0 AND r.lebar > 0)))
                    )) AS valid
            FROM {$this->tableName} r
            LEFT JOIN products p ON p.product_id = r.product_id AND p.store_id = r.store_id
            LEFT JOIN categories c ON c.category_id = p.category_id AND c.store_id = r.store_id
            LEFT JOIN finishings f ON f.finishing_id = r.finishing_id AND f.store_id = r.store_id
            WHERE r.store_id = ? AND (? = 0 OR p.category_id = ?)
            ORDER BY c.name, p.name, r.panjang, r.lebar");
        $stmt->bind_param('iii', $store_id, $category_id, $category_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $validOnly ? array_values(array_filter($rows, function ($row) {
            return (int)$row['valid'] === 1;
        })) : $rows;
    }

    // Finishing yang sudah dipetakan wajib memakai kombinasi dan ukuran yang sah.
    // Kombinasi tidak sah tidak boleh jatuh ke harga finishing biasa (misalnya 0).
    public function resolve($store_id, $product_id, $finishing, $panjang, $lebar) {
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $finishing)))));
        if (!$ids) return null;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->koneksi->prepare("SELECT finishing_id FROM finishings WHERE store_id = ? AND finishing_id IN ($placeholders)");
        $params = array_merge([(int)$store_id], $ids);
        $types = str_repeat('i', count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $owned = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if (count($owned) !== count($ids)) throw new InvalidArgumentException('Finishing tidak ditemukan pada toko ini.');
        $rules = array_filter($this->getByStore($store_id, 0, false), function ($rule) use ($ids) {
            return in_array((int)$rule['finishing_id'], $ids, true);
        });
        if (!$rules) {
            // Katalog yang diimpor ke toko baru belum tentu memiliki pemetaan paket.
            // Finishing paket bernilai 0 hanya boleh dipakai setelah harga paketnya diatur.
            $stmt = $this->koneksi->prepare("SELECT finishing_id FROM finishings
                WHERE store_id = ? AND UPPER(TRIM(name)) IN ('XBANNER', 'ROLLUP') AND price = 0");
            $stmt->bind_param('i', $store_id);
            $stmt->execute();
            $unmapped = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            foreach ($unmapped as $row) {
                if (in_array((int)$row['finishing_id'], $ids, true)) {
                    throw new InvalidArgumentException('Harga paket di toko ini belum dipetakan. Atur melalui Center terlebih dahulu.');
                }
            }
            return null;
        }
        if (count($ids) !== 1) {
            throw new InvalidArgumentException('Untuk paket ini, pilih satu finishing paket saja.');
        }
        foreach ($rules as $rule) {
            if ((int)$rule['product_id'] === (int)$product_id && (int)$rule['valid'] === 1
                && ((!$rule['requires_size'] && strtoupper(trim($rule['category'])) !== 'LASER A3') || (abs((float)$rule['panjang'] - $panjang) < 0.00001
                    && abs((float)$rule['lebar'] - $lebar) < 0.00001))) {
                return $rule;
            }
        }
        throw new InvalidArgumentException('Paket belum tersedia untuk bahan dan ukuran ini. Pilih ukuran paket yang tersedia.');
    }

    public static function calculate($rule, $quantity, $discount = 0) {
        $unit = max(0, (float)$rule['price'] - max(0, (float)$discount));
        return (object)[
            'unit' => $unit,
            'amount' => $unit * $quantity,
            'size' => (float)$rule['panjang'] > 0 && (float)$rule['lebar'] > 0
                ? (float)$rule['panjang'] . 'x' . (float)$rule['lebar'] : '-',
        ];
    }
}
