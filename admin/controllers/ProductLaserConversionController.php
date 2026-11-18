<?php
require_once __DIR__ . '/../models/ProductLaserConversion.php';

class ProductLaserConversionController {
    private $conversionModel;

    public function __construct($koneksi) {
        $this->conversionModel = new ProductLaserConversion($koneksi);
    }

    public function isAvailable() {
        return $this->conversionModel->isAvailable();
    }

    public function countsForProducts($store, $productIds) {
        return $this->conversionModel->countsForProducts($store, $productIds);
    }

    public function getByStore($store_id, $validOnly = false) {
        if (!$this->isAvailable()) {
            return [];
        }
        $rows = $this->conversionModel->getByStore($store_id);
        $finishings = [];
        foreach ($this->conversionModel->getFinishings($store_id) as $finishing) {
            $finishings[(int)$finishing['finishing_id']] = $finishing;
        }
        foreach ($rows as &$row) {
            $row['legacy_fallback'] = !$this->hasVariants();
            $names = [];
            foreach (self::finishingIds($row['finishing_ids']) as $id) {
                $finishing = $finishings[$id] ?? null;
                if (!$finishing || (int)$finishing['category_id'] !== (int)$row['category_id']) {
                    $row['valid'] = 0;
                }
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
        return $this->conversionModel->hasVariants();
    }

    public static function finishingIds($value) {
        $ids = array_map('intval', explode(',', (string)$value));
        $ids = array_filter($ids, function ($id) {
            return $id > 0;
        });
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    public static function normalizeSize($size) {
        $size = trim(preg_replace('/[\s\p{Z}]+/u', ' ', (string)$size) ?? '');
        return $size === '-' ? '' : $size;
    }

    public function hasOrderMaterials() {
        return $this->conversionModel->hasOrderMaterials();
    }

    public static function matchRule($rules, $size, $finishing, $allowLegacyFallback = true, $laser_product_id = 0) {
        $size = self::normalizeSize($size);
        $ids = self::finishingIds($finishing);
        $matches = [];
        $legacy = [];
        foreach ($rules as $rule) {
            if (empty($rule['valid'])) {
                continue;
            }
            if ($laser_product_id && (int)$rule['laser_product_id'] !== (int)$laser_product_id) {
                continue;
            }
            if (strcasecmp(self::normalizeSize($rule['size']), $size) === 0 && self::finishingIds($rule['finishing_ids']) === $ids) {
                $matches[] = $rule;
            }
            // Konversi lama belum mengatur ukuran, finishing, atau harga.
            if ($allowLegacyFallback && $rule['price'] === null && $rule['size'] === '' && $rule['finishing_ids'] === '') {
                $legacy[] = $rule;
            }
        }
        if ($matches) {
            return count($matches) === 1 ? $matches[0] : null;
        }
        return count($legacy) === 1 ? $legacy[0] : null;
    }

    public function resolve($store_id, $product_id, $size, $finishing, $laser_product_id = 0) {
        if (
            !is_scalar($laser_product_id) || !preg_match('/^\d+$/D', (string)$laser_product_id) ||
            (float)$laser_product_id > 2147483647
        ) {
            throw new InvalidArgumentException('Pilihan bahan tidak valid.');
        }
        $laser_product_id = (int)$laser_product_id;
        if (!preg_match('/^(?:-|\d+(?:,\d+)*)?$/D', trim((string)$finishing))) {
            throw new InvalidArgumentException('Pilihan finishing tidak valid.');
        }
        $ids = self::finishingIds($finishing);
        if ($ids) {
            $found = $this->conversionModel->countProductFinishings($store_id, $product_id, $ids);
            if ($found !== count($ids)) {
                throw new InvalidArgumentException('Finishing bukan milik kategori dan toko produk ini.');
            }
        }
        $rules = array_values(array_filter($this->getByStore($store_id), function ($rule) use ($product_id) {
            return (int)$rule['product_id'] === (int)$product_id;
        }));
        if (!$rules && !$laser_product_id) {
            return null;
        }
        $rule = self::matchRule($rules, $size, $finishing, !$this->hasVariants(), $laser_product_id);
        if (!$rule) {
            throw new InvalidArgumentException('Pilih kombinasi bahan, ukuran, dan finishing yang tersedia. Jika pilihan bahan belum muncul, perbarui aplikasi.');
        }
        return $rule;
    }

    // Hitung kebutuhan print sesuai rasio, lalu bulatkan ke atas per item order.
    public static function printQuantity($quantity, $rule) {
        $quantity = max(0, (int)$quantity);
        $denominator = (int)$rule['product_quantity'];
        $totalPrint = $quantity * (int)$rule['print_quantity'];
        return intdiv($totalPrint, $denominator) + ($totalPrint % $denominator > 0 ? 1 : 0);
    }
}
