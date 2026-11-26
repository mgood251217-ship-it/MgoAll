<?php
require_once __DIR__ . '/../models/ProductVariant.php';
require_once __DIR__ . '/ProductionComponentController.php';

class ProductVariantController {
    private $db;
    private $variantModel;

    public function __construct($db) {
        $this->db = $db;
        $this->variantModel = new ProductVariant($db);
    }

    public function isAvailable() {
        return $this->variantModel->isAvailable();
    }

    public static function options($names, $values) {
        if (
            !is_array($names) || !is_array($values) ||
            count($names) !== count($values) || count($names) < 1 || count($names) > 8
        ) {
            throw new InvalidArgumentException('Isi 1 sampai 8 pilihan varian.');
        }

        $result = [];
        $seen = [];
        $values = array_values($values);
        foreach (array_values($names) as $index => $name) {
            $value = $values[$index];
            if (!is_scalar($name) || !is_scalar($value)) {
                throw new InvalidArgumentException('Pilihan varian tidak valid.');
            }
            $name = preg_replace('/\s+/u', ' ', trim((string)$name));
            $value = preg_replace('/\s+/u', ' ', trim((string)$value));
            if (
                !$name || !$value || strlen($name) > 120 || strlen($value) > 240 ||
                isset($seen[strtolower($name)])
            ) {
                throw new InvalidArgumentException('Nama pilihan dan nilainya wajib diisi, singkat, dan tidak boleh berulang.');
            }
            $seen[strtolower($name)] = true;
            $result[$name] = $value;
        }

        return $result;
    }

    public static function snapshot($row) {
        if (empty($row['product_variant_data'])) {
            return null;
        }
        $data = json_decode($row['product_variant_data'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['components']) || !is_array($data['components'])) {
            throw new RuntimeException('Data komponen varian pada nota tidak lengkap.');
        }
        $id = (int)($row['product_variant_id'] ?? $data['id'] ?? 0);
        if (!$id) return null;
        $options = isset($row['variant_options_json'])
            ? json_decode($row['variant_options_json'], true, 512, JSON_THROW_ON_ERROR)
            : ($data['options'] ?? $data['legacy_options'] ?? []);
        $pricing = !empty($row['tier_price_data']) ? json_decode($row['tier_price_data'], true, 512, JSON_THROW_ON_ERROR) : [];
        $price = (float)($pricing['base'] ?? $data['price'] ?? $row['variant_master_price'] ?? $row['price'] ?? $row['unit'] ?? 0);
        $minuteRate = self::usesLegacyHourlyRate($row, $data);
        if ($minuteRate && (isset($pricing['base']) || isset($data['price']))) $price = round($price / 60, 2);
        return [
            'id' => $id,
            'options' => $options,
            'label' => $options ? implode(' / ', array_values($options)) : ($data['label'] ?? 'Varian #' . $id),
            'price' => $price,
            'components' => $data['components'],
            'product_name' => $row['variant_product_name'] ?? $data['product_name'] ?? $row['product_name'] ?? 'Produk #' . ($row['product_id'] ?? 0),
            'category' => $data['category'] ?? $row['variant_category_name'] ?? $row['category'] ?? '',
            'unit_type' => $minuteRate ? 'MENIT' : ($data['unit_type'] ?? $row['current_unit_type'] ?? $row['unit_type'] ?? 'PCS'),
            '_legacy_options' => $data['legacy_options'] ?? $data['options'] ?? $options,
        ];
    }

    public static function usesLegacyHourlyRate($row, $data = null) {
        $data = $data ?? json_decode($row['product_variant_data'] ?? 'null', true);
        return ($row['variant_unit_type'] ?? $row['unit_type'] ?? '') === 'MENIT'
            && in_array($data['unit_type'] ?? '', ['PCS', '~'], true)
            && strtoupper(trim($data['category'] ?? '')) === 'JASA'
            && strtoupper(trim($row['variant_product_name'] ?? $row['product_name'] ?? $row['judul'] ?? '')) === 'SETTING';
    }

    public static function productionData($variant) {
        $data = ['components' => $variant['components'], 'category' => $variant['category'],
            'unit_type' => $variant['unit_type'] ?? 'PCS'];
        foreach ($data['components'] as $component) {
            if (empty($component['component_id'])) {
                $data['legacy_options'] = $variant['_legacy_options'] ?? $variant['options'];
                break;
            }
        }
        return $data;
    }

    public function getByProduct($store, $product) {
        $variants = $this->getByProducts($store, [$product]);

        return $variants[(int)$product] ?? [];
    }

    public function getByProducts($store, $productIds) {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (!$productIds || !$this->isAvailable()) {
            return [];
        }
        $rows = $this->variantModel->getByProducts($store, $productIds);

        $componentModel = new ProductionComponentController($this->db);
        $hasMasters = $componentModel->isAvailable();
        $masterNames = $hasMasters ? array_column($componentModel->getByStore($store), 'name', 'id') : [];
        $components = $this->getComponentsByVariant($store, $productIds, $hasMasters);

        foreach ($rows as &$row) {
            $row['options'] = json_decode($row['options_json'], true, 512, JSON_THROW_ON_ERROR);
            $row['label'] = implode(' / ', array_values($row['options']));
            $row['components'] = $components[(int)$row['id']] ?? [];
            foreach ($row['components'] as &$component) {
                $linkedId = (int)($component['component_id'] ?? 0);
                if (isset($masterNames[$linkedId])) {
                    $component['component_id'] = (int)$linkedId;
                    $component['name'] = $masterNames[$linkedId];
                }
            }
            unset($component);
            unset($row['options_json'], $row['combination_key']);
        }
        unset($row);

        $byProduct = [];
        foreach ($rows as $row) {
            $byProduct[(int)$row['product_id']][] = $row;
        }

        return $byProduct;
    }

    public static function orderData($variant, $product) {
        // ID baris resep dipakai formulir pengaturan; nota menyimpan identitas komponen.
        foreach ($variant['components'] as &$component) {
            unset($component['recipe_id']);
        }
        unset($component);

        return [
            'id' => (int)$variant['id'],
            'options' => $variant['options'],
            'label' => $variant['label'],
            'price' => (float)$variant['price'],
            'components' => $variant['components'],
            'product_name' => $product['name'],
            'category' => $product['category'],
            'unit_type' => $product['unit_type'] ?? 'PCS'
        ];
    }

    public function resolve($store, $product, $input, $availableVariants = null) {
        $id = $input['product_variant_id'] ?? 0;
        if ($id === '') {
            $id = 0;
        }
        if (!is_scalar($id) || !preg_match('/^\d+$/D', (string)$id) || (float)$id > 2147483647) {
            throw new InvalidArgumentException('Pilihan varian tidak valid.');
        }
        $id = (int)$id;
        if (!$this->isAvailable()) {
            if ($id) {
                throw new InvalidArgumentException('Jalankan SQL product_variants.sql terlebih dahulu.');
            }
            return null;
        }

        $editing = (int)($input['order_item_id'] ?? 0);
        if ($editing) {
            $old = $this->variantModel->getOrderItem(
                $store, (int)$product['product_id'], (int)($input['order_id'] ?? 0), $editing
            );
            if ($old && (int)$old['product_variant_id'] === $id) {
                // Harga dan komponen nota tetap terbaca setelah varian diubah atau dihapus.
                $saved = self::snapshot($old);
                if ($saved) {
                    return $saved;
                }
                if (!$id) {
                    return null;
                }
            }
            if ($old && !empty($old['product_variant_id']) && !$id) {
                throw new InvalidArgumentException('Item ini memakai varian. Pilih varian sesuai nota atau perbarui aplikasi.');
            }
        }

        $variants = $availableVariants ?? $this->getByProduct($store, (int)$product['product_id']);
        foreach ($variants as $variant) {
            if ((int)$variant['id'] === $id) {
                return self::orderData($variant, $product);
            }
        }
        if ($id || $variants) {
            throw new InvalidArgumentException('Pilih kombinasi varian yang tersedia. Jika pilihan varian belum muncul, perbarui aplikasi atau muat ulang data.');
        }

        return null;
    }

    public static function describe($items) {
        foreach ($items as &$row) {
            $row['variant'] = self::snapshot($row);
            $row['product_variant_id'] = $row['product_variant_id'] ?? null;
            if ($row['variant']) unset($row['variant']['_legacy_options']);
            unset($row['product_variant_data'], $row['variant_options_json'], $row['variant_master_price'], $row['variant_product_name'], $row['variant_unit_type'], $row['variant_category_name']);
        }
        unset($row);

        return $items;
    }

    // Harga dan jumlah mengikuti nota; nama mengikuti master komponen yang terhubung.
    public function productionRows($store, $start, $end, $category = null, $includeDefault = false, $unitTypes = null) {
        if (!$this->isAvailable()) {
            return [];
        }
        $masterModel = new ProductionComponentController($this->db);
        $hasMasters = $masterModel->isAvailable();
        $masters = array_column($masterModel->getByStore($store), 'name', 'id');
        $units = $unitTypes === null ? null : array_map(function ($unit) {
            return strtoupper(trim($unit));
        }, (array)$unitTypes);

        $result = $this->variantModel->getProductionRows($store, $start, $end, $units !== null);
        $totals = [];
        $categories = $category === null ? null : array_map(function ($name) {
            return strtoupper(trim($name));
        }, (array)$category);
        while ($row = $result->fetch_assoc()) {
            $saved = self::snapshot($row);
            // Snapshot nota tetap menyimpan nama kategori sebelum diganti menjadi TEKSTIL.
            $savedCategory = strtoupper(trim($saved['category']));
            if (in_array($savedCategory, ['JERSEY', 'PAKAIAN'], true)) $savedCategory = 'TEKSTIL';
            if ($categories !== null && !in_array($savedCategory, $categories, true)) {
                continue;
            }
            // Nota lama tanpa satuan pada snapshot tetap dihitung per pcs setelah produk dihapus.
            $unit = $row['current_unit_type'] ?? $saved['unit_type'] ?? 'PCS';
            if ($units !== null && !in_array(strtoupper(trim($unit)), $units, true)) {
                continue;
            }
            $components = $saved['components'];
            $useDefault = !$components && $includeDefault;
            if ($useDefault) {
                $components = [[
                    'name' => $saved['product_name'] . ' - ' . $saved['label'],
                    'quantity' => 1
                ]];
            }
            foreach ($components as $component) {
                $legacyKey = ProductionComponentController::legacyKey($saved, $component['name']);
                $id = (int)($component['component_id'] ?? 0);
                if ($id) {
                    $key = 'component:' . $id;
                } elseif ($hasMasters) {
                    $key = 'legacy:' . $legacyKey;
                } else {
                    $key = 'name:' . $component['name'];
                }
                $name = $masters[$id] ?? $component['name'];
                if ($hasMasters && !$id && !$useDefault) {
                    $name .= ' - ' . $saved['product_name'] . ' / ' . $saved['label'] . ' (belum dihubungkan)';
                }
                if (!isset($totals[$key])) {
                    $totals[$key] = ['component_id' => $id ?: null, 'name' => $name, 'total_qty' => 0];
                }
                $totals[$key]['total_qty'] = round(
                    $totals[$key]['total_qty'] + (float)$component['quantity'] * (int)$row['quantity'],
                    4
                );
            }
        }

        return array_values($totals);
    }

    private function getComponentsByVariant($store, $productIds, $hasMasters) {
        $rows = $this->variantModel->getComponentsByProducts($store, $productIds, $hasMasters);
        $components = [];
        foreach ($rows as $row) {
            $component = [
                'name' => $row['name'],
                'quantity' => (float)$row['quantity'],
                'recipe_id' => (int)$row['recipe_id']
            ];
            if (!empty($row['component_id'])) {
                $component['component_id'] = (int)$row['component_id'];
            }
            $components[(int)$row['variant_id']][] = $component;
        }

        return $components;
    }
}
