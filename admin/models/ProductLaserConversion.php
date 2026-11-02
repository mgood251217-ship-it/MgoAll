<?php

class ProductLaserConversion {
    private $koneksi;
    private $available;
    private $variants;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
    }

    public function isAvailable() {
        if ($this->available === null) {
            $result = $this->koneksi->query("SELECT EXISTS(SELECT 1 FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_laser_conversion') AS available");
            if (!$result) throw new RuntimeException('Gagal memeriksa tabel konversi print laser.');
            $this->available = (bool)$result->fetch_assoc()['available'];
        }
        return $this->available;
    }

    public function getByStore($store_id, $validOnly = false) {
        if (!$this->isAvailable()) return [];
        $legacyFields = $this->hasVariants() ? '' : ", '' AS size, '' AS finishing_ids, NULL AS price";
        $stmt = $this->koneksi->prepare("SELECT r.*, p.name AS product_name, c.name AS category,
                p.category_id, p.price AS product_price, lp.name AS laser_name $legacyFields,
                (p.product_id IS NOT NULL AND c.category_id IS NOT NULL
                    AND UPPER(TRIM(c.name)) = 'ANEKA CETAK' AND p.unit_type IN ('PCS', '~')
                    AND lp.product_id IS NOT NULL AND UPPER(TRIM(lc.name)) = 'LASER A3'
                    AND lp.unit_type IN ('PCS', '~') AND r.product_quantity > 0 AND r.print_quantity > 0) AS valid
            FROM product_laser_conversion r
            LEFT JOIN products p ON p.product_id = r.product_id AND p.store_id = r.store_id
            LEFT JOIN categories c ON c.category_id = p.category_id AND c.store_id = r.store_id
            LEFT JOIN products lp ON lp.product_id = r.laser_product_id AND lp.store_id = r.store_id
            LEFT JOIN categories lc ON lc.category_id = lp.category_id AND lc.store_id = r.store_id
            WHERE r.store_id = ? ORDER BY c.name, p.name, r.id");
        $stmt->bind_param('i', $store_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $stmt = $this->koneksi->prepare("SELECT finishing_id, category_id, name FROM finishings WHERE store_id = ?");
        $stmt->bind_param('i', $store_id);
        $stmt->execute();
        $finishings = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $finishing) $finishings[(int)$finishing['finishing_id']] = $finishing;
        $stmt->close();
        foreach ($rows as &$row) {
            $row['legacy_fallback'] = !$this->hasVariants();
            $names = [];
            foreach (self::finishingIds($row['finishing_ids']) as $id) {
                $finishing = $finishings[$id] ?? null;
                if (!$finishing || (int)$finishing['category_id'] !== (int)$row['category_id']) $row['valid'] = 0;
                $names[] = $finishing['name'] ?? ('Finishing tidak tersedia (#' . $id . ')');
            }
            $row['finishing_names'] = implode(', ', $names);
        }
        unset($row);
        return $validOnly ? array_values(array_filter($rows, function ($row) {
            return (int)$row['valid'] === 1;
        })) : $rows;
    }

    public function hasVariants() {
        if (!$this->isAvailable()) return false;
        if ($this->variants === null) {
            $result = $this->koneksi->query("SELECT COUNT(*) AS total FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_laser_conversion'
                  AND COLUMN_NAME IN ('size', 'finishing_ids', 'price')");
            $this->variants = (int)$result->fetch_assoc()['total'] === 3;
        }
        return $this->variants;
    }

    public static function finishingIds($value) {
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$value)), function ($id) { return $id > 0; })));
        sort($ids, SORT_NUMERIC);
        return $ids;
    }

    public static function normalizeSize($size) {
        $size = trim(preg_replace('/[\s\p{Z}]+/u', ' ', (string)$size) ?? '');
        return $size === '-' ? '' : $size;
    }

    public static function matchRule($rules, $size, $finishing, $allowLegacyFallback = true) {
        $size = self::normalizeSize($size);
        $ids = self::finishingIds($finishing);
        $legacy = null;
        foreach ($rules as $rule) {
            if (empty($rule['valid'])) continue;
            if (strcasecmp(self::normalizeSize($rule['size']), $size) === 0 && self::finishingIds($rule['finishing_ids']) === $ids) return $rule;
            // Konversi lama belum mengatur ukuran, finishing, atau harga.
            if ($allowLegacyFallback && $rule['price'] === null && $rule['size'] === '' && $rule['finishing_ids'] === '') $legacy = $rule;
        }
        return $legacy;
    }

    public function resolve($store_id, $product_id, $size, $finishing) {
        if (!preg_match('/^(?:-|\d+(?:,\d+)*)?$/D', trim((string)$finishing))) throw new InvalidArgumentException('Pilihan finishing tidak valid.');
        $ids = self::finishingIds($finishing);
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->koneksi->prepare("SELECT f.finishing_id FROM finishings f
                JOIN products p ON p.product_id = ? AND p.store_id = f.store_id AND p.category_id = f.category_id
                WHERE f.store_id = ? AND f.finishing_id IN ($placeholders)");
            $params = array_merge([(int)$product_id, (int)$store_id], $ids);
            $types = str_repeat('i', count($params));
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $found = $stmt->get_result()->num_rows;
            $stmt->close();
            if ($found !== count($ids)) throw new InvalidArgumentException('Finishing bukan milik kategori dan toko produk ini.');
        }
        $rules = array_values(array_filter($this->getByStore($store_id), function ($rule) use ($product_id) {
            return (int)$rule['product_id'] === (int)$product_id;
        }));
        if (!$rules) return null;
        $rule = self::matchRule($rules, $size, $finishing, !$this->hasVariants());
        if (!$rule) throw new InvalidArgumentException('Kombinasi ukuran dan finishing belum tersedia. Pilih varian ANEKA CETAK yang sudah diatur di Center.');
        return $rule;
    }

    // Bulatkan jumlah kelompok produk, lalu kalikan print per kelompok.
    public static function printQuantity($quantity, $rule) {
        $quantity = max(0, (int)$quantity);
        $denominator = (int)$rule['product_quantity'];
        $groups = intdiv($quantity, $denominator) + ($quantity % $denominator > 0 ? 1 : 0);
        return $groups * (int)$rule['print_quantity'];
    }
}
