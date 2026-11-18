<?php
require_once __DIR__ . '/../models/ProductFinishingPrice.php';

class ProductFinishingPriceController {
    private $priceModel;

    public function __construct($koneksi) {
        $this->priceModel = new ProductFinishingPrice($koneksi);
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
        return $this->priceModel->isAvailable();
    }

    public function hasCurrentSchema() {
        return $this->priceModel->hasCurrentSchema();
    }

    public function countsForProducts($store, $productIds) {
        return $this->priceModel->countsForProducts($store, $productIds);
    }

    public function getByStore($store_id, $category_id = 0, $validOnly = true) {
        $rows = $this->priceModel->getByStore($store_id, $category_id);
        if (!$validOnly) {
            return $rows;
        }

        return array_values(array_filter($rows, function ($row) {
            return (int)$row['valid'] === 1;
        }));
    }

    public function resolve($store_id, $product_id, $finishing, $panjang, $lebar) {
        $ids = array_map('intval', explode(',', $finishing));
        $ids = array_values(array_unique(array_filter($ids)));
        if (!$ids) {
            return null;
        }
        $owned = $this->priceModel->getFinishings($store_id, $ids);
        if (count($owned) !== count($ids)) {
            throw new InvalidArgumentException('Finishing tidak ditemukan pada toko ini.');
        }
        $rules = array_filter($this->getByStore($store_id, 0, false), function ($rule) use ($ids) {
            return in_array((int)$rule['finishing_id'], $ids, true);
        });
        if (!$rules) {
            // Finishing paket bernilai 0 memerlukan pemetaan harga di cabang tujuan.
            foreach ($this->priceModel->getUnmappedFinishings($store_id) as $row) {
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
            if ((int)$rule['product_id'] !== (int)$product_id || (int)$rule['valid'] !== 1) {
                continue;
            }
            $withoutSize = !$rule['requires_size'] && strtoupper(trim($rule['category'])) !== 'LASER A3';
            if (
                $withoutSize ||
                (abs((float)$rule['panjang'] - $panjang) < 0.00001 &&
                    abs((float)$rule['lebar'] - $lebar) < 0.00001)
            ) {
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
