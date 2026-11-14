<?php
require_once __DIR__ . '/ProductVariant.php';
require_once __DIR__ . '/ProductLaserConversion.php';

class ProductBundle {
    private $db;
    private $ready;

    public function __construct($db) {
        $this->db = $db;
    }

    public function isAvailable() {
        if ($this->ready === null) {
            $r = $this->db
                ->query(
                    "SELECT
                (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('product_bundles','product_bundle_items','product_price_tiers')) AS tables_ready,
                EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_items' AND COLUMN_NAME='tier_price_data') AS orders_ready"
                )
                ->fetch_assoc();
            $this->ready = (int) $r['tables_ready'] === 3 && (bool) $r['orders_ready'];
        }
        return $this->ready;
    }

    private function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        if ($params) {
            $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        }
        $stmt->execute();
        return $stmt;
    }

    public function getByStore($store) {
        if (!$this->isAvailable()) {
            return [];
        }
        $stmt = $this->query(
            'SELECT b.*, p.name, p.category_id FROM product_bundles b LEFT JOIN products p ON p.product_id=b.product_id AND p.store_id=? WHERE b.store_id=? ORDER BY b.id',
            [$store, $store]
        );
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($rows as &$row) {
            $stmt = $this->query(
                'SELECT i.*, p.name, c.name AS category FROM product_bundle_items i LEFT JOIN products p ON p.product_id=i.product_id AND p.store_id=? LEFT JOIN categories c ON c.category_id=p.category_id AND c.store_id=? WHERE i.bundle_id=? ORDER BY i.id',
                [$store, $store, $row['id']]
            );
            $row['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
        unset($row);
        return $rows;
    }

    public function find($store, $product) {
        if (!$this->isAvailable()) {
            return null;
        }
        $stmt = $this->query('SELECT * FROM product_bundles WHERE store_id=? AND product_id=?', [$store, $product]);
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        $stmt = $this->query('SELECT * FROM product_bundle_items WHERE bundle_id=? ORDER BY id', [$row['id']]);
        $row['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $row;
    }

    public function saved($store, $input) {
        if (!$this->isAvailable() || empty($input['order_item_id'])) {
            return null;
        }
        $stmt = $this->query(
            'SELECT tier_price_data FROM order_items WHERE store_id=? AND order_id=? AND order_item_id=? AND product_id=?',
            [$store, $input['order_id'] ?? 0, $input['order_item_id'], $input['product_id'] ?? 0]
        );
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (empty($row['tier_price_data'])) {
            return null;
        }
        return json_decode($row['tier_price_data'], true, 512, JSON_THROW_ON_ERROR)['bundle'] ?? null;
    }

    public function compile($store, $bundle) {
        $items = $bundle['items'];
        if (count($items) < 2 || count($items) > 30) {
            throw new InvalidArgumentException('Isi paket harus 2 sampai 30 baris produk.');
        }
        $result = [];
        $seen = [];
        $variants = new ProductVariant($this->db);
        $lasers = new ProductLaserConversion($this->db);
        $rules = $lasers->getByStore($store, true);
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
            $stmt = $this->query(
                'SELECT p.*, c.name AS category FROM products p JOIN categories c ON c.category_id=p.category_id AND c.store_id=? WHERE p.store_id=? AND p.product_id=?',
                [$store, $store, $id]
            );
            $p = $stmt->get_result()->fetch_assoc();
            $stmt->close();
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
            if ($this->find($store, $id)) {
                throw new InvalidArgumentException('Paket tidak boleh berisi paket lain.');
            }
            $variant = $variants->resolve($store, $p, [
                'product_variant_id' => $item['product_variant_id'] ?? 0
            ]);
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
            !empty(ProductLaserConversion::finishingIds($input['finishing'] ?? ''))
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
                foreach (ProductPriceTier::stock($item) as $table => $entries) {
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
            'laser_material_name' => null,
            'bundle_stock' => $snapshot['stock'],
            'tier_price_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        ];
    }
    // Dipanggil di dalam transaksi pengosongan nota sebelum baris order_items dihapus.

    public function refundOrderStocks($store, $order) {
        if (!$this->isAvailable()) {
            return;
        }
        $stmt = $this->query(
            "SELECT quantity,tier_price_data FROM order_items WHERE store_id=? AND order_id=? AND JSON_EXTRACT(IF(JSON_VALID(tier_price_data),tier_price_data,'{}'),'$.bundle') IS NOT NULL FOR UPDATE",
            [$store, $order]
        );
        $totals = ['products' => [], 'finishings' => []];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
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
        $stmt->close();
        foreach ($totals as $table => $entries) {
            ksort($entries, SORT_NUMERIC);
            $column = $table === 'products' ? 'product_id' : 'finishing_id';
            foreach ($entries as $id => $quantity) {
                $stmt = $this->query("UPDATE $table SET stock=stock+? WHERE store_id=? AND $column=?", [
                    $quantity,
                    $store,
                    $id
                ]);
                $stmt->close();
            }
        }
    }

    public function orderItems($store, $start, $end) {
        if (!$this->isAvailable()) {
            return [];
        }
        $stmt = $this->query(
            "SELECT oi.quantity,oi.tier_price_data FROM order_items oi JOIN orders o ON o.order_id=oi.order_id AND o.store_id=? WHERE oi.store_id=? AND o.date BETWEEN ? AND ? AND JSON_EXTRACT(IF(JSON_VALID(oi.tier_price_data),oi.tier_price_data,'{}'),'$.bundle') IS NOT NULL",
            [$store, $store, $start, $end]
        );
        $items = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $bundle = json_decode($row['tier_price_data'], true, 512, JSON_THROW_ON_ERROR)['bundle'] ?? null;
            foreach ($bundle['items'] ?? [] as $item) {
                $item['quantity'] *= (int) $row['quantity'];
                $items[] = $item;
            }
        }
        $stmt->close();
        return $items;
    }

    public function quantityRows($store, $start, $end, $categories = null) {
        $totals = [];
        $masters = new ProductionComponent($this->db);
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
                    $id = (int) ($links[ProductionComponent::legacyKey($variant, $component['name'])] ?? 0);
                }
                $id = ProductionComponent::resolveId($id, $links);
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
                    ($totals[$r['laser_name']] ?? 0) + ProductLaserConversion::printQuantity($item['quantity'], $r);
            }
        }
        return $totals;
    }
}
