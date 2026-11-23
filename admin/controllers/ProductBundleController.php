<?php
require_once __DIR__ . '/../models/ProductBundle.php';
require_once __DIR__ . '/ProductPriceTierController.php';
require_once __DIR__ . '/ProductVariantController.php';
require_once __DIR__ . '/ProductLaserConversionController.php';
require_once __DIR__ . '/ProductionComponentController.php';

class ProductBundleController {
    private $db;
    private $bundleModel;

    public function __construct($db) {
        $this->db = $db;
        $this->bundleModel = new ProductBundle($db);
    }

    public function isAvailable() {
        return $this->bundleModel->isAvailable();
    }

    public function countsForProducts($store, $productIds) {
        return $this->bundleModel->countsForProducts($store, $productIds);
    }

    public function getByStore($store) {
        return $this->bundleModel->getByStore($store);
    }

    public function getCatalog($store) {
        $bundles = $this->getByStore($store);
        if (!$bundles) {
            return [];
        }
        $productIds = [];
        foreach ($bundles as $bundle) {
            foreach ($bundle['items'] as $item) {
                $productIds[] = (int)$item['product_id'];
            }
        }
        $productIds = array_values(array_unique($productIds));
        $products = [];
        if ($productIds) {
            $products = $this->bundleModel->productsByIds($store, $productIds);
        }
        $variants = new ProductVariantController($this->db);
        $context = [
            'store' => (int)$store,
            'products' => $products,
            'bundle_products' => array_fill_keys(array_column($bundles, 'product_id'), true),
            'variant_model' => $variants,
            'variants' => $variants->getByProducts($store, $productIds),
            'laser_rules' => (new ProductLaserConversionController($this->db))->getByStore($store, true)
        ];
        foreach ($bundles as &$bundle) {
            $bundle['contents'] = [];
            $bundle['valid'] = true;
            try {
                foreach ($this->compile($store, $bundle, $context) as $item) {
                    $label = $item['product']['name'];
                    if ($item['variant']) {
                        $label .= ' - ' . $item['variant']['label'];
                    }
                    if ($item['laser']) {
                        $label .= ' - ' . implode(' / ', array_filter([
                            $item['laser']['laser_name'],
                            $item['laser']['size'],
                            $item['laser']['finishing_names'] ?? ''
                        ]));
                    }
                    $bundle['contents'][] = ['quantity' => $item['quantity'], 'label' => $label];
                }
            } catch (InvalidArgumentException $e) {
                $bundle['valid'] = false;
                $bundle['error'] = $e->getMessage();
            }
        }
        unset($bundle);
        return $bundles;
    }

    public function find($store, $product) {
        return $this->bundleModel->find($store, $product);
    }

    // Pemanggil memegang transaksi dan kunci toko sumber/tujuan.
    public function copyToStore(
        $sourceStore,
        $sourceProduct,
        $targetStore,
        $targetProduct,
        $productMap,
        $variantMap,
        $laserMap
    ) {
        if ($this->find($targetStore, $targetProduct)) {
            return false;
        }
        $source = $this->find($sourceStore, $sourceProduct);
        if (!$source) {
            throw new InvalidArgumentException('Paket gabungan sumber tidak tersedia.');
        }
        $this->compile($sourceStore, $source);
        $mapId = function ($map, $id, $label) {
            if (!isset($map[$id])) {
                throw new InvalidArgumentException('Relasi ' . $label . ' isi paket belum tersalin.');
            }
            return (int) $map[$id];
        };
        $items = [];
        foreach ($source['items'] as $item) {
            $items[] = [
                'product_id' => $mapId($productMap, $item['product_id'], 'produk'),
                'product_variant_id' => empty($item['product_variant_id'])
                    ? null
                    : $mapId($variantMap, $item['product_variant_id'], 'varian'),
                'laser_conversion_id' => empty($item['laser_conversion_id'])
                    ? null
                    : $mapId($laserMap, $item['laser_conversion_id'], 'konversi laser'),
                'quantity' => $item['quantity'],
                'size' => $item['size']
            ];
        }
        $parent = $this->bundleModel->parentProduct($targetStore, $targetProduct);
        if (
            !$parent ||
            $parent['unit_type'] !== '~' ||
            strtoupper(trim($parent['category'])) !== 'PELENGKAP' ||
            (new ProductVariantController($this->db))->getByProduct($targetStore, $targetProduct)
        ) {
            throw new InvalidArgumentException('Produk paket tujuan harus PELENGKAP, satuan ~, tanpa varian sendiri.');
        }
        $this->compile($targetStore, ['product_id' => $targetProduct, 'items' => $items]);
        $this->bundleModel->insert($targetStore, $targetProduct, $source['price'], $items);
        return true;
    }

    public function saved($store, $input) {
        if (!$this->isAvailable() || empty($input['order_item_id'])) {
            return null;
        }
        $row = $this->bundleModel->savedItem(
            $store,
            $input['order_id'] ?? 0,
            $input['order_item_id'],
            $input['product_id'] ?? 0
        );
        if (empty($row['tier_price_data'])) {
            return null;
        }
        return json_decode($row['tier_price_data'], true, 512, JSON_THROW_ON_ERROR)['bundle'] ?? null;
    }

    public function compile($store, $bundle, $context = null) {
        if ($context !== null && $context['store'] !== (int)$store) {
            throw new InvalidArgumentException('Toko isi paket tidak sesuai.');
        }
        $items = $bundle['items'];
        if (count($items) < 2 || count($items) > 30) {
            throw new InvalidArgumentException('Isi paket harus 2 sampai 30 baris produk.');
        }
        $result = [];
        $seen = [];
        $variants = $context['variant_model'] ?? new ProductVariantController($this->db);
        $rules = $context['laser_rules'] ?? (new ProductLaserConversionController($this->db))->getByStore($store, true);
        foreach ($items as $item) {
            // Select "Tanpa konversi" mengirim string kosong; data tersimpan memakai NULL.
            $laserId = is_array($item) ? $item['laser_conversion_id'] ?? 0 : 0;
            if ($laserId === '') {
                $laserId = 0;
            }
            if (
                !is_array($item) ||
                !is_scalar($item['size'] ?? '') ||
                !is_scalar($item['laser_conversion_id'] ?? 0) ||
                !preg_match('/^\d+$/D', (string) $laserId)
            ) {
                throw new InvalidArgumentException('Pilihan isi paket tidak valid.');
            }
            $id = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1]
            ]);
            $qty = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 10000]
            ]);
            if (!$id || !$qty || (int) $id === (int) $bundle['product_id']) {
                throw new InvalidArgumentException('Produk isi dan jumlah paket tidak valid.');
            }
            if ($context !== null) {
                $p = $context['products'][$id] ?? null;
            } else {
                $p = $this->bundleModel->itemProduct($store, $id);
            }
            if (
                !$p ||
                !in_array($p['unit_type'], ['PCS', '~'], true) ||
                !in_array(
                    strtoupper(trim($p['category'])),
                    ['TEKSTIL', 'PAKAIAN', 'JERSEY', 'PELENGKAP', 'ANEKA CETAK'],
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'Isi paket harus barang PCS/~ dari Tekstil, Pelengkap, atau Aneka Cetak pada toko yang sama.'
                );
            }
            $nestedBundle = $context !== null
                ? isset($context['bundle_products'][$id])
                : (bool)$this->find($store, $id);
            if ($nestedBundle) {
                throw new InvalidArgumentException('Paket tidak boleh berisi paket lain.');
            }
            $variant = $variants->resolve($store, $p, [
                'product_variant_id' => $item['product_variant_id'] ?? 0
            ], $context !== null ? ($context['variants'][$id] ?? []) : null);
            $rule = null;
            foreach ($rules as $r) {
                if ((int) $r['id'] === (int) $laserId && (int) $r['product_id'] === $id) {
                    $rule = $r;
                }
            }
            if (strtoupper(trim($p['category'])) === 'ANEKA CETAK' && !$rule) {
                throw new InvalidArgumentException('Pilih aturan konversi print laser untuk produk Aneka Cetak.');
            }
            if ($laserId && !$rule) {
                throw new InvalidArgumentException('Aturan print laser tidak tersedia untuk produk ini.');
            }
            if ($variant && $rule) {
                throw new InvalidArgumentException(
                    'Varian produk dan konversi laser tidak dapat digabung pada satu isi paket.'
                );
            }
            $size = trim((string) ($item['size'] ?? ''));
            if (strlen($size) > 80) {
                throw new InvalidArgumentException('Ukuran terlalu panjang.');
            }
            $key = json_encode([$id, $variant['id'] ?? 0, $rule['id'] ?? 0, strtoupper($rule ? $rule['size'] : $size)]);
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Isi produk/varian yang sama cukup satu baris; ubah jumlahnya.');
            }
            $seen[$key] = true;
            $result[] = [
                'product' => $p,
                'variant' => $variant,
                'laser' => $rule
                    ? array_intersect_key(
                        $rule,
                        array_flip([
                            'id',
                            'laser_product_id',
                            'laser_name',
                            'product_quantity',
                            'print_quantity',
                            'size',
                            'finishing_ids',
                            'finishing_names'
                        ])
                    )
                    : null,
                'quantity' => $qty,
                'size' => $rule ? $rule['size'] : $size,
                'finishing' => $rule ? $rule['finishing_ids'] : ''
            ];
        }
        return $result;
    }

    public function prepare($store, $product, $input, $prepareChild) {
        $old = $this->saved($store, $input);
        $definition = $this->find($store, $product['product_id']);
        if (!$old && !$definition) {
            return null;
        }
        if (!empty($input['bundle_child'])) {
            throw new InvalidArgumentException('Paket tidak boleh berisi paket lain.');
        }
        if (
            !is_scalar($input['finishing'] ?? '') ||
            !empty($input['product_variant_id']) ||
            !empty(ProductLaserConversionController::finishingIds($input['finishing'] ?? ''))
        ) {
            throw new InvalidArgumentException(
                'Paket gabungan memakai pilihan isi yang diatur di Center, tanpa finishing tambahan pada produk paket.'
            );
        }
        $quantity = filter_var($input['quantity'] ?? 1, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1000000]
        ]);
        if (!$quantity) {
            throw new InvalidArgumentException('Jumlah paket tidak valid.');
        }
        if ($old) {
            $snapshot = $old;
        } else {
            if ($product['unit_type'] !== '~' || strtoupper(trim($product['category'])) !== 'PELENGKAP') {
                throw new InvalidArgumentException('Produk induk paket harus kategori PELENGKAP, satuan ~.');
            }
            $compiled = $this->compile($store, $definition);
            $stock = ['products' => [], 'finishings' => []];
            foreach ($compiled as $child) {
                $p = $child['product'];
                $item = $prepareChild([
                    'bundle_child' => 1,
                    'order_id' => $input['order_id'] ?? 0,
                    'product_id' => $p['product_id'],
                    'judul' => $p['name'],
                    'product_variant_id' => $child['variant']['id'] ?? 0,
                    'laser_product_id' => $child['laser']['laser_product_id'] ?? 0,
                    'size' => $child['size'],
                    'finishing' => $child['finishing'],
                    'quantity' => $child['quantity'],
                    'diskon' => 0
                ]);
                if (isset($item['error'])) {
                    throw new InvalidArgumentException($item['error']);
                }
                foreach (ProductPriceTierController::stock($item) as $table => $entries) {
                    foreach ($entries as $id => $factor) {
                        $stock[$table][$id] = ($stock[$table][$id] ?? 0) + $factor * $child['quantity'];
                    }
                }
            }
            $snapshot = [
                'id' => (int) $definition['id'],
                'price' => (float) $definition['price'],
                'items' => $compiled,
                'stock' => $stock
            ];
        }
        $discount = filter_var($input['diskon'] ?? 0, FILTER_VALIDATE_FLOAT);
        if ($discount === false || !is_finite($discount) || $discount < 0) {
            throw new InvalidArgumentException('Diskon paket tidak valid.');
        }
        $normal = round(max(0, $snapshot['price'] - $discount), 2);
        $data = [
            'rules' => [],
            'bundle' => $snapshot,
            'measure' => 'pcs',
            'factor' => 1,
            'normal' => $normal,
            'base' => $snapshot['price'],
            'discount' => $discount,
            'stock' => $snapshot['stock']
        ];
        return [
            'order_id' => (int) ($input['order_id'] ?? 0),
            'product_id' => (int) $product['product_id'],
            'product' => $product,
            'judul' => $product['name'],
            'size' => '-',
            'quantity' => $quantity,
            'unit' => $normal,
            'amount' => round($normal * $quantity, 2),
            'stok_butuh' => 0,
            'finishing_str' => '-',
            'finishing_to_reduce' => [],
            'product_variant_id' => null,
            'product_variant_data' => null,
            'variant' => null,
            'product_finishing_price_id' => null,
            'is_laser_variant' => false,
            'laser_product_id' => null,
            'bundle_stock' => $snapshot['stock'],
            'tier_price_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        ];
    }

    // Dipanggil di dalam transaksi pengosongan nota sebelum baris order_items dihapus.
    public function refundOrderStocks($store, $order) {
        if (!$this->isAvailable()) {
            return;
        }
        $rows = $this->bundleModel->refundItems($store, $order);
        $totals = ['products' => [], 'finishings' => []];
        foreach ($rows as $row) {
            $data = json_decode($row['tier_price_data'], true, 512, JSON_THROW_ON_ERROR);
            if (empty($data['bundle'])) {
                continue;
            }
            foreach ($totals as $table => $_) {
                foreach ($data['stock'][$table] ?? [] as $id => $factor) {
                    $totals[$table][$id] = ($totals[$table][$id] ?? 0) + (float) $factor * (int) $row['quantity'];
                }
            }
        }
        foreach ($totals as $table => $entries) {
            ksort($entries, SORT_NUMERIC);
            $column = $table === 'products' ? 'product_id' : 'finishing_id';
            foreach ($entries as $id => $quantity) {
                $this->bundleModel->restoreStock($table, $column, $quantity, $store, $id);
            }
        }
    }

    public function orderItems($store, $start, $end) {
        if (!$this->isAvailable()) {
            return [];
        }
        $rows = $this->bundleModel->orderItems($store, $start, $end);
        $items = [];
        foreach ($rows as $row) {
            $bundle = json_decode($row['tier_price_data'], true, 512, JSON_THROW_ON_ERROR)['bundle'] ?? null;
            foreach ($bundle['items'] ?? [] as $item) {
                $item['quantity'] *= (int) $row['quantity'];
                $items[] = $item;
            }
        }
        return $items;
    }

    public function quantityRows($store, $start, $end, $categories = null) {
        $totals = [];
        $masters = new ProductionComponentController($this->db);
        $names = array_column($masters->getByStore($store), 'name', 'id');
        $links = $masters->links($store);
        foreach ($this->orderItems($store, $start, $end) as $item) {
            if (
                $categories !== null &&
                !in_array(strtoupper(trim($item['product']['category'])), (array) $categories, true)
            ) {
                continue;
            }
            $variant = $item['variant'];
            $recipe = $variant['components'] ?? [];
            if (!$recipe) {
                $recipe = [
                    [
                        'name' => $item['product']['name'] . ($variant ? ' - ' . $variant['label'] : ''),
                        'quantity' => 1
                    ]
                ];
            }
            foreach ($recipe as $component) {
                $id = (int) ($component['component_id'] ?? 0);
                if (!$id && $variant) {
                    $id = (int) ($links[ProductionComponentController::legacyKey($variant, $component['name'])] ?? 0);
                }
                $id = ProductionComponentController::resolveId($id, $links);
                $name = $names[$id] ?? $component['name'];
                $totals[$name] = ($totals[$name] ?? 0) + (float) $component['quantity'] * $item['quantity'];
            }
        }
        $rows = [];
        foreach ($totals as $name => $qty) {
            if ($qty != 0) {
                $rows[] = ['name' => $name, 'total_qty' => round($qty, 4)];
            }
        }
        return $rows;
    }

    public function laserRows($store, $start, $end) {
        $totals = [];
        foreach ($this->orderItems($store, $start, $end) as $item) {
            if ($r = $item['laser']) {
                $totals[$r['laser_name']] =
                    ($totals[$r['laser_name']] ?? 0) + ProductLaserConversionController::printQuantity($item['quantity'], $r);
            }
        }
        return $totals;
    }
}
