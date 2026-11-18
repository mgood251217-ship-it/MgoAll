<?php
require_once __DIR__ . '/../models/ProductVariant.php';
require_once __DIR__ . '/../models/ProductionComponent.php';

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
        if (
            !is_array($data) || empty($data['id']) || empty($data['options']) ||
            !isset($data['price'], $data['components'], $data['product_name'], $data['category'])
        ) {
            throw new RuntimeException('Data varian pada nota tidak lengkap.');
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

        $componentModel = new ProductionComponent($this->db);
        $hasMasters = $componentModel->isAvailable();
        $masterNames = $hasMasters ? array_column($componentModel->getByStore($store), 'name', 'id') : [];
        $links = $hasMasters ? $componentModel->links($store) : [];
        $components = $this->getComponentsByVariant($store, $productIds, $hasMasters);

        foreach ($rows as &$row) {
            $row['options'] = json_decode($row['options_json'], true, 512, JSON_THROW_ON_ERROR);
            $row['label'] = implode(' / ', array_values($row['options']));
            $row['components'] = $components[(int)$row['id']] ?? [];
            foreach ($row['components'] as &$component) {
                $componentId = $component['component_id'] ?? null;
                if ($componentId === null) {
                    $legacyKey = ProductionComponent::legacyKey($row, $component['name']);
                    $componentId = $links[$legacyKey] ?? 0;
                }
                $linkedId = ProductionComponent::resolveId($componentId, $links);
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
            'category' => $product['category']
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
            unset($row['product_variant_data']);
        }
        unset($row);

        return $items;
    }

    // Harga dan jumlah mengikuti nota; nama mengikuti master komponen yang terhubung.
    public function productionRows($store, $start, $end, $category = null, $includeDefault = false, $unitTypes = null) {
        if (!$this->isAvailable()) {
            return [];
        }
        $masterModel = new ProductionComponent($this->db);
        $hasMasters = $masterModel->isAvailable();
        $masters = array_column($masterModel->getByStore($store), 'name', 'id');
        $links = $masterModel->links($store);
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
            if ($categories !== null && !in_array(strtoupper(trim($saved['category'])), $categories, true)) {
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
                $legacyKey = ProductionComponent::legacyKey($saved, $component['name']);
                $componentId = !empty($component['component_id']) ? $component['component_id'] : ($links[$legacyKey] ?? 0);
                $id = ProductionComponent::resolveId($componentId, $links);
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
