<?php
require_once __DIR__ . '/../functions/helpers.php';
require_once __DIR__ . '/../../admin/functions/productPrices.php';
require_once __DIR__ . '/../../admin/functions/productStock.php';
require_once __DIR__ . '/../../admin/controllers/ProductPriceTierController.php';
require_once __DIR__ . '/ProductHistoryController.php';
class ProductController {
    private $koneksi;

    public function __construct($db) {
        $this->koneksi = $db;
    }

    public function getIndexData($access) {
        if ($access == 'ALL') {
            $storesResult = $this->koneksi->query("SELECT store_id, name FROM stores ORDER BY name ASC");
        } else {
            $stmt = $this->koneksi->prepare("SELECT store_id, name FROM stores WHERE administrator = ? ORDER BY name ASC");
            $stmt->bind_param("s", $access);
            $stmt->execute();
            $storesResult = $stmt->get_result();
        }

        $stores = [];
        if ($storesResult) {
            while ($row = $storesResult->fetch_assoc()) {
                $stores[] = $row;
            }
        }

        $store_id = isset($_GET['store_id']) && $_GET['store_id'] !== '' ? (int)$_GET['store_id'] : null;
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
        $category_id = max(0, (int)($_GET['category_id'] ?? 0));
        $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 25;

        if ($store_id === null && !empty($stores)) {
            $store_id = $stores[0]['store_id'];
        }
        if (!in_array($store_id, array_column($stores, 'store_id'))) $store_id = null;

        $productsData = [];
        $finishingsData = [];
        $categories = [];
        $migrationProducts = [];
        $migrationFinishings = [];
        $totalPages = 0;
        $total = 0;

        if ($store_id !== null) {
            $withVariants = (new ProductVariantController($this->koneksi))->isAvailable();
            $variantCount = $withVariants
                ? '(SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.product_id AND v.store_id = p.store_id)'
                : '0';
            $migrationStmt = $this->koneksi->prepare("SELECT p.product_id, p.category_id, p.name, p.price, $variantCount AS variant_count FROM products p WHERE p.store_id = ? ORDER BY p.name, p.product_id");
            $migrationStmt->bind_param('i', $store_id);
            $migrationStmt->execute();
            $migrationProducts = $migrationStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $migrationStmt->close();
            $migrationStmt = $this->koneksi->prepare('SELECT finishing_id, category_id, name FROM finishings WHERE store_id = ? ORDER BY name, finishing_id');
            $migrationStmt->bind_param('i', $store_id); $migrationStmt->execute();
            $migrationFinishings = $migrationStmt->get_result()->fetch_all(MYSQLI_ASSOC); $migrationStmt->close();
            $catStmt = $this->koneksi->prepare("
                SELECT c.category_id, c.name,
                    (SELECT COUNT(*) FROM products p
                     WHERE p.category_id = c.category_id AND p.store_id = c.store_id) AS product_count,
                    (SELECT COUNT(*) FROM finishings f
                     WHERE f.category_id = c.category_id AND f.store_id = c.store_id) AS finishing_count
                FROM categories c
                WHERE c.store_id = ? ORDER BY c.name ASC
            ");
            $catStmt->bind_param("i", $store_id);
            $catStmt->execute();
            $catResult = $catStmt->get_result();
            if ($catResult) {
                while ($c = $catResult->fetch_assoc()) {
                    $categories[] = $c;
                }
            }
            $catStmt->close();
            if (!in_array($category_id, array_column($categories, 'category_id'))) {
                $category_id = 0;
            }

            $search_param = "%" . $search . "%";
            [$productFilter, $searchParams] = $this->productSearch($store_id, $search, $withVariants);
            $productParams = array_merge([$store_id], $searchParams, [$category_id, $category_id]);
            $countStmt = $this->koneksi->prepare("
                SELECT COUNT(*) 
                FROM products p
                WHERE p.store_id = ? AND $productFilter
                    AND (? = 0 OR p.category_id = ?)
            ");
            $countStmt->bind_param(str_repeat('s', count($productParams)), ...$productParams);
            $countStmt->execute();
            $total = $countStmt->get_result()->fetch_row()[0];
            $countStmt->close();

            $totalPages = ceil($total / $limit);
            $offset = ($page - 1) * $limit;

            $stmt = $this->koneksi->prepare("
                SELECT p.*, c.name AS category,
                    $variantCount AS variant_count,
                    (SELECT COUNT(*) FROM order_items oi
                     WHERE oi.product_id = p.product_id AND oi.store_id = p.store_id) AS order_item_count
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.category_id
                WHERE p.store_id = ? AND $productFilter
                    AND (? = 0 OR p.category_id = ?)
                ORDER BY p.product_id DESC 
                LIMIT ? OFFSET ?
            ");
            $pageParams = array_merge($productParams, [$limit, $offset]);
            $stmt->bind_param(str_repeat('s', count($pageParams)), ...$pageParams);
            $stmt->execute();
            
            $result = $stmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $productsData[] = $row;
                }
            }
            $stmt->close();

            $fStmt = $this->koneksi->prepare("
                SELECT f.*, c.name AS category 
                FROM finishings f
                LEFT JOIN categories c ON f.category_id = c.category_id
                WHERE f.store_id = ? AND f.name LIKE ? 
                    AND (? = 0 OR f.category_id = ?)
                ORDER BY f.finishing_id DESC
            ");
            $fStmt->bind_param("isii", $store_id, $search_param, $category_id, $category_id);
            $fStmt->execute();
            
            $fResult = $fStmt->get_result();
            if ($fResult) {
                while ($row = $fResult->fetch_assoc()) {
                    $finishingsData[] = $row;
                }
            }
            $fStmt->close();
        }

        return [
            'stores' => $stores,
            'current_store_id' => $store_id,
            'categories' => $categories,
            'migration_products' => $migrationProducts,
            'migration_finishings' => $migrationFinishings,
            'current_category_id' => $category_id,
            'products' => $productsData,
            'finishings' => $finishingsData,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $total,
            'search' => $search,
            'limit' => $limit
        ];
    }

    private function productSearch($store_id, $search, $withVariants) {
        $words = preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY);
        if (!$words) return ['1 = 1', []];
        $names = $variants = $nameParams = $variantParams = [];
        foreach ($words as $word) {
            $pattern = '%' . $word . '%';
            $names[] = 'p.name LIKE ?';
            $nameParams[] = $pattern;
            $variants[] = "(p.name LIKE ? OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(v.options_json, '$.*'))) LIKE LOWER(?))";
            array_push($variantParams, $pattern, $pattern);
        }
        $filter = '(' . implode(' AND ', $names) . ')';
        if ($withVariants) {
            $filter = '(' . $filter . ' OR EXISTS (SELECT 1 FROM product_variants v
                WHERE v.product_id = p.product_id AND v.store_id = ? AND ' . implode(' AND ', $variants) . '))';
            $nameParams = array_merge($nameParams, [$store_id], $variantParams);
        }
        return [$filter, $nameParams];
    }

    private function productReturnUrl() {
        $filters = is_array($_POST['return_filters'] ?? null) ? $_POST['return_filters'] : [];
        return '/products?' . http_build_query([
            'store_id' => max(0, (int)($filters['store_id'] ?? $_POST['store_id'] ?? 0)),
            'category_id' => max(0, (int)($filters['category_id'] ?? 0)),
            'search' => is_string($filters['search'] ?? null) ? $filters['search'] : '',
            'limit' => max(1, (int)($filters['limit'] ?? 25)),
            'page' => max(1, (int)($filters['page'] ?? 1)),
        ]);
    }

    public function refreshCache() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $storeId = max(0, (int)($_POST['store_id'] ?? 0));
        try {
            if (empty($_SESSION['admin_logged_in']) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
                || !is_string($_POST['csrf'] ?? null) || empty($_SESSION['product_cache_csrf'])
                || !hash_equals($_SESSION['product_cache_csrf'], $_POST['csrf'])) {
                throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
            }
            $access = startEnk('dek', $_SESSION['admin_logged_in']['access'] ?? '');
            $stmt = $this->koneksi->prepare('SELECT name, administrator FROM stores WHERE store_id = ?');
            $stmt->bind_param('i', $storeId);
            $stmt->execute();
            $store = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$storeId || !$store || ($access !== 'ALL' && ((string)$access === '' || (string)$access !== (string)$store['administrator']))) {
                throw new InvalidArgumentException('Anda tidak memiliki akses ke toko ini.');
            }
            require_once __DIR__ . '/../functions/cacheHelper.php';
            set_error_handler(function ($severity, $message) { throw new RuntimeException($message); });
            try {
                foreach (['store_data', 'store_names', 'categories', 'products', 'finishings', 'product_variants', 'production_components', 'product_finishing_prices', 'product_laser_conversion', 'product_price_tiers', 'orders'] as $module) {
                    updateStoreCache($storeId, $module);
                }
            } finally {
                restore_error_handler();
            }
            $_SESSION['swal_success'] = 'Cache toko ' . $store['name'] . ' diperbarui: informasi toko, kategori, produk, finishing, harga paket, dan order. Buka ulang halaman terkait di MgoApp untuk mengambil data terbaru.';
        } catch (Throwable $e) {
            error_log('Store cache refresh: ' . $e->getMessage());
            $_SESSION['swal_error'] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Gagal memperbarui cache. Periksa izin folder temp/dataset.';
        }
        header('Location: ' . $this->productReturnUrl());
        exit;
    }

    private function selectedCatalogIds($ids, $label = 'produk') {
        if (!is_array($ids) || !$ids || count($ids) > 1000) {
            throw new InvalidArgumentException("Pilih 1 sampai 1.000 $label.");
        }
        foreach ($ids as $id) {
            if (!is_scalar($id) || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false) {
                throw new InvalidArgumentException("Pilihan $label tidak valid. Muat ulang halaman.");
            }
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        return $ids;
    }

    private function copyProductQuery($sql, $params = []) {
        $stmt = $this->koneksi->prepare($sql);
        if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        $stmt->execute();
        return $stmt;
    }

    private function copyProductVariants($sourceVariants, $targetStore, $targetProduct) {
        $model = new ProductVariantController($this->koneksi);
        $targetVariants = $model->getByProduct($targetStore, $targetProduct);
        $groups = $targetVariants
            ? array_keys($targetVariants[0]['options'])
            : array_keys($sourceVariants[0]['options']);
        $upper = function ($value) {
            return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
        };
        $components = new ProductionComponentController($this->koneksi);
        $useMasters = $components->isAvailable();
        $copyTiers = (new ProductPriceTierController($this->koneksi))->hasVariantTiers();
        $added = $skipped = 0;
        $variantMap = [];
        foreach ($sourceVariants as $variant) {
            if (array_map($upper, array_keys($variant['options'])) !== array_map($upper, $groups)) {
                throw new InvalidArgumentException(
                    'Nama dan urutan pilihan varian berbeda pada produk tujuan #' .
                        $targetProduct .
                        '. Penyalinan dibatalkan.'
                );
            }
            $options = [];
            foreach ($groups as $i => $group) {
                $value = array_values($variant['options'])[$i];
                foreach ($targetVariants as $existing) {
                    if ($upper($existing['options'][$group]) === $upper($value)) {
                        $value = $existing['options'][$group];
                        break;
                    }
                }
                $options[$group] = $value;
            }
            $options = ProductVariantController::options(array_keys($options), array_values($options));
            $key = json_encode(array_values($options), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $stmt = $this->copyProductQuery(
                'SELECT id FROM product_variants WHERE store_id = ? AND product_id = ? AND combination_key = SHA2(LOWER(?), 256)',
                [$targetStore, $targetProduct, $key]
            );
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($exists) {
                $variantMap[(int) $variant['id']] = (int) $exists['id'];
                $skipped++;
                continue;
            }
            $json = json_encode($options, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $stmt = $this->copyProductQuery(
                'INSERT INTO product_variants (store_id, product_id, options_json, combination_key, price) VALUES (?, ?, ?, SHA2(LOWER(?), 256), ?)',
                [$targetStore, $targetProduct, $json, $key, $variant['price']]
            );
            $newVariantId = (int) $this->koneksi->insert_id;
            $stmt->close();
            $variantMap[(int) $variant['id']] = $newVariantId;
            $recipe = [];
            foreach ($variant['components'] as $component) {
                $name = ProductionComponentController::name($component['name']);
                $componentId = null;
                if ($useMasters) {
                    $master = $components->findOrCreate($targetStore, $name);
                    $name = $master['name'];
                    $componentId = $master['id'];
                }
                $componentKey = $useMasters ? 'id:' . $componentId : 'name:' . $name;
                $quantity = (float) $component['quantity'];
                if ($quantity <= 0 || $quantity > 1000000) {
                    throw new InvalidArgumentException('Jumlah komponen produksi tidak valid. Penyalinan dibatalkan.');
                }
                if (!isset($recipe[$componentKey])) {
                    $recipe[$componentKey] = ['name' => $name, 'id' => $componentId, 'quantity' => 0];
                }
                $recipe[$componentKey]['quantity'] = round($recipe[$componentKey]['quantity'] + $quantity, 4);
                if ($recipe[$componentKey]['quantity'] > 1000000) {
                    throw new InvalidArgumentException(
                        'Jumlah komponen produksi terlalu besar. Penyalinan dibatalkan.'
                    );
                }
            }
            foreach ($recipe as $component) {
                $sql = $useMasters
                    ? 'INSERT INTO product_variant_components (variant_id, name, quantity, component_id) VALUES (?, ?, ?, ?)'
                    : 'INSERT INTO product_variant_components (variant_id, name, quantity) VALUES (?, ?, ?)';
                $params = [$newVariantId, $component['name'], $component['quantity']];
                if ($useMasters) {
                    $params[] = $component['id'];
                }
                $this->copyProductQuery($sql, $params)->close();
            }
            if ($copyTiers) {
                $this->copyProductQuery(
                    'INSERT INTO product_variant_price_tiers (store_id, product_id, product_variant_id, quantity_above, price) SELECT ?, ?, ?, quantity_above, price FROM product_variant_price_tiers WHERE store_id = ? AND product_id = ? AND product_variant_id = ?',
                    [
                        $targetStore,
                        $targetProduct,
                        $newVariantId,
                        $variant['store_id'],
                        $variant['product_id'],
                        $variant['id']
                    ]
                )->close();
            }
            $targetVariants[] = ['options' => $options];
            $added++;
        }
        return ['added' => $added, 'skipped' => $skipped, 'map' => $variantMap];
    }

    private function copyBundleFinishing($sourceId, $targetId, $finishingId, &$finishingMap) {
        if (isset($finishingMap[$finishingId])) {
            return $finishingMap[$finishingId];
        }
        $stmt = $this->copyProductQuery(
            'SELECT f.*, c.name AS category_name FROM finishings f JOIN categories c ON c.category_id=f.category_id AND c.store_id=? WHERE f.store_id=? AND f.finishing_id=?',
            [$sourceId, $sourceId, $finishingId]
        );
        $source = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$source) {
            throw new InvalidArgumentException('Finishing isi paket sumber tidak tersedia.');
        }
        $stmt = $this->copyProductQuery(
            'SELECT category_id FROM categories WHERE store_id=? AND LOWER(TRIM(name))=LOWER(TRIM(?)) FOR UPDATE',
            [$targetId, $source['category_name']]
        );
        $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if (count($categories) !== 1) {
            throw new InvalidArgumentException('Kategori finishing isi paket tujuan tidak tersedia atau duplikat.');
        }
        $categoryId = (int) $categories[0]['category_id'];
        $stmt = $this->copyProductQuery(
            'SELECT finishing_id, unit_type FROM finishings WHERE store_id=? AND category_id=? AND LOWER(TRIM(name))=LOWER(TRIM(?)) FOR UPDATE',
            [$targetId, $categoryId, $source['name']]
        );
        $matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if (count($matches) > 1) {
            throw new InvalidArgumentException('Finishing ' . $source['name'] . ' duplikat di cabang tujuan.');
        }
        if ($matches) {
            if (strtoupper(trim($matches[0]['unit_type'])) !== strtoupper(trim($source['unit_type']))) {
                throw new InvalidArgumentException(
                    'Satuan finishing ' . $source['name'] . ' berbeda di cabang tujuan.'
                );
            }
            return $finishingMap[$finishingId] = (int) $matches[0]['finishing_id'];
        }
        $this->copyProductQuery(
            'INSERT INTO finishings(category_id,unit_type,name,price,reasonable_price,failed_price,store_id,stock) VALUES(?,?,?,?,?,?,?,0)',
            [
                $categoryId,
                $source['unit_type'],
                $source['name'],
                $source['price'],
                $source['reasonable_price'],
                $source['failed_price'],
                $targetId
            ]
        )->close();
        return $finishingMap[$finishingId] = (int) $this->koneksi->insert_id;
    }

    private function copyBundleLaser($source, $sourceId, $targetId, $productMap, &$finishingMap) {
        $productId = $productMap[(int) $source['product_id']];
        $laserProductId = $productMap[(int) $source['laser_product_id']];
        $finishingIds = [];
        foreach (ProductLaserConversionController::finishingIds($source['finishing_ids']) as $id) {
            $finishingIds[] = $this->copyBundleFinishing($sourceId, $targetId, $id, $finishingMap);
        }
        sort($finishingIds, SORT_NUMERIC);
        $finishing = implode(',', $finishingIds);
        $model = new ProductLaserConversionController($this->koneksi);
        $matches = array_values(
            array_filter($model->getByStore($targetId), function ($rule) use (
                $source,
                $productId,
                $laserProductId,
                $finishingIds
            ) {
                return (int) $rule['product_id'] === $productId &&
                    (int) $rule['laser_product_id'] === $laserProductId &&
                    strcasecmp(
                        ProductLaserConversionController::normalizeSize($rule['size']),
                        ProductLaserConversionController::normalizeSize($source['size'])
                    ) === 0 &&
                    ProductLaserConversionController::finishingIds($rule['finishing_ids']) === $finishingIds;
            })
        );
        if (count($matches) > 1) {
            throw new InvalidArgumentException('Konversi print laser duplikat di cabang tujuan.');
        }
        if ($matches) {
            $match = $matches[0];
            if (
                !$match['valid'] ||
                (float) $match['product_quantity'] !== (float) $source['product_quantity'] ||
                (float) $match['print_quantity'] !== (float) $source['print_quantity']
            ) {
                throw new InvalidArgumentException(
                    'Rasio konversi print laser berbeda di cabang tujuan. Periksa pengaturannya sebelum menyalin.'
                );
            }
            return (int) $match['id'];
        }
        if ($model->hasVariants()) {
            $this->copyProductQuery(
                'INSERT INTO product_laser_conversion(store_id,product_id,laser_product_id,product_quantity,print_quantity,size,finishing_ids,price) VALUES(?,?,?,?,?,?,?,?)',
                [
                    $targetId,
                    $productId,
                    $laserProductId,
                    $source['product_quantity'],
                    $source['print_quantity'],
                    $source['size'],
                    $finishing,
                    $source['price']
                ]
            )->close();
        } else {
            $this->copyProductQuery(
                'INSERT INTO product_laser_conversion(store_id,product_id,laser_product_id,product_quantity,print_quantity) VALUES(?,?,?,?,?)',
                [$targetId, $productId, $laserProductId, $source['product_quantity'], $source['print_quantity']]
            )->close();
        }
        return (int) $this->koneksi->insert_id;
    }

    private function copyTargetStores($sourceId) {
        $targetIds = $this->selectedCatalogIds(
            $_POST['target_store_ids'] ?? [$_POST['target_store_id'] ?? 0], 'cabang tujuan'
        );
        if ($sourceId <= 0 || in_array($sourceId, $targetIds, true)) {
            throw new InvalidArgumentException('Pilih cabang tujuan yang berbeda dari cabang sumber.');
        }
        $adminId = (int) startEnk('dek', $_SESSION['admin_logged_in']['administrator_id'] ?? '');
        $stmt = $this->copyProductQuery('SELECT access FROM administrator WHERE administrator_id = ?', [$adminId]);
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$admin) throw new InvalidArgumentException('Akun tidak ditemukan. Silakan login kembali.');

        $storeIds = array_merge([$sourceId], $targetIds);
        sort($storeIds);
        $placeholders = implode(',', array_fill(0, count($storeIds), '?'));
        $stmt = $this->copyProductQuery(
            "SELECT store_id, name, administrator FROM stores WHERE store_id IN ($placeholders) ORDER BY store_id FOR UPDATE",
            $storeIds
        );
        $stores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if (count($stores) !== count($storeIds)) {
            throw new InvalidArgumentException('Cabang sumber atau tujuan tidak ditemukan.');
        }
        $targets = [];
        foreach ($stores as $store) {
            if ($admin['access'] !== 'ALL' && ($admin['access'] === '' || (string)$store['administrator'] !== (string)$admin['access'])) {
                throw new InvalidArgumentException('Anda tidak memiliki akses ke cabang sumber atau tujuan.');
            }
            if ((int)$store['store_id'] !== $sourceId) $targets[] = $store;
        }
        return $targets;
    }

    private function updateCopiedStoreCaches($targets, $modules, $label) {
        $failed = [];
        foreach ($targets as $target) {
            try {
                require_once __DIR__ . '/../functions/cacheHelper.php';
                foreach ($modules as $module) updateStoreCache((int)$target['store_id'], $module);
            } catch (Throwable $e) {
                error_log('Copy catalog cache: ' . $e->getMessage());
                $failed[] = $target['name'];
            }
        }
        if ($failed) {
            unset($_SESSION['swal_success']);
            $_SESSION['swal_error'] = $label . ' sudah disalin, tetapi cache gagal diperbarui pada cabang: '
                . implode(', ', $failed) . '. Gunakan Update Cache pada cabang tersebut.';
        }
    }

    public function copyFinishings() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $sourceId = filter_var($_POST['store_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
        $transaction = $saved = false;
        $targetName = "";
        $driver = new mysqli_driver();
        $report = $driver->report_mode;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            if (empty($_SESSION['admin_logged_in']) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new InvalidArgumentException('Silakan login kembali.');
            }
            if (empty($_SESSION['finishing_copy_csrf']) || !is_string($_POST['csrf'] ?? null)
                || !hash_equals($_SESSION['finishing_copy_csrf'], $_POST['csrf'])) {
                throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
            }
            $ids = $this->selectedCatalogIds($_POST['finishing_ids'] ?? [], 'finishing');
            $this->koneksi->begin_transaction();
            $transaction = true;
            $targets = $this->copyTargetStores($sourceId);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->copyProductQuery(
                "SELECT f.*, c.name AS category_name FROM finishings f
                 LEFT JOIN categories c ON c.category_id = f.category_id AND c.store_id = ?
                 WHERE f.store_id = ? AND f.finishing_id IN ($placeholders) ORDER BY f.finishing_id FOR UPDATE",
                array_merge([$sourceId, $sourceId], $ids)
            );
            $finishings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            if (count($finishings) !== count($ids)) {
                throw new InvalidArgumentException('Ada finishing yang tidak tersedia pada cabang sumber. Penyalinan dibatalkan.');
            }
            $added = $skipped = 0;
            foreach ($targets as $target) {
                $targetId = (int)$target['store_id'];
                $targetName = $target['name'];
                foreach ($finishings as $finishing) {
                    if (trim($finishing['category_name'] ?? '') === '') {
                        throw new InvalidArgumentException('Kategori finishing #' . $finishing['finishing_id'] . ' tidak valid.');
                    }
                    $stmt = $this->copyProductQuery(
                        'SELECT category_id FROM categories WHERE store_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?)) FOR UPDATE',
                        [$targetId, $finishing['category_name']]
                    );
                    $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    if (count($categories) > 1) {
                        throw new InvalidArgumentException('Kategori ' . $finishing['category_name'] . ' duplikat di cabang tujuan.');
                    }
                    if ($categories) {
                        $categoryId = (int)$categories[0]['category_id'];
                    } else {
                        $this->copyProductQuery(
                            'INSERT INTO categories (store_id, name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())',
                            [$targetId, $finishing['category_name']]
                        )->close();
                        $categoryId = (int)$this->koneksi->insert_id;
                    }
                    $stmt = $this->copyProductQuery(
                        'SELECT finishing_id FROM finishings WHERE store_id = ? AND category_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?)) FOR UPDATE',
                        [$targetId, $categoryId, $finishing['name']]
                    );
                    $exists = $stmt->get_result()->num_rows > 0;
                    $stmt->close();
                    if ($exists) {
                        $skipped++;
                        continue;
                    }
                    $this->copyProductQuery(
                        'INSERT INTO finishings (category_id, unit_type, name, price, reasonable_price, failed_price, store_id, stock) VALUES (?, ?, ?, ?, ?, ?, ?, 0)',
                        [$categoryId, $finishing['unit_type'], $finishing['name'], $finishing['price'],
                            $finishing['reasonable_price'], $finishing['failed_price'], $targetId]
                    )->close();
                    $added++;
                }
            }
            $this->koneksi->commit();
            $transaction = false;
            $saved = $added > 0;
            $targetCount = count($targets);
            $_SESSION['swal_success'] = "$added finishing berhasil disalin ke $targetCount cabang. $skipped finishing yang sudah ada dipertahankan.";
        } catch (Throwable $e) {
            if ($transaction) $this->koneksi->rollback();
            unset($_SESSION['swal_success']);
            $_SESSION['swal_error'] = $e instanceof InvalidArgumentException
                ? ($targetName ? $targetName . ': ' : '') . $e->getMessage() . ' Semua perubahan dibatalkan.'
                : 'Gagal menyalin finishing. Semua perubahan dibatalkan.';
            error_log('Copy finishings: ' . $e->getMessage());
        } finally {
            mysqli_report($report);
        }
        if ($saved) $this->updateCopiedStoreCaches($targets, ['categories', 'finishings'], 'Finishing');
        header('Location: ' . $this->productReturnUrl());
        exit;
    }

    public function copyProducts() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $sourceId = filter_var($_POST['store_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
        $transaction = $saved = false;
        $targetName = "";
        $driver = new mysqli_driver();
        $report = $driver->report_mode;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            if (empty($_SESSION['admin_logged_in']) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new InvalidArgumentException('Silakan login kembali.');
            }
            if (
                empty($_SESSION['product_copy_csrf']) ||
                !is_string($_POST['csrf'] ?? null) ||
                !hash_equals($_SESSION['product_copy_csrf'], $_POST['csrf'])
            ) {
                throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
            }
            $ids = $this->selectedCatalogIds($_POST['product_ids'] ?? []);
            $this->koneksi->begin_transaction();
            $transaction = true;
            $targets = $this->copyTargetStores($sourceId);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->copyProductQuery(
                "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.category_id = p.category_id AND c.store_id = ? WHERE p.store_id = ? AND p.product_id IN ($placeholders) ORDER BY p.product_id FOR UPDATE",
                array_merge([$sourceId, $sourceId], $ids)
            );
            $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            if (count($products) !== count($ids)) {
                throw new InvalidArgumentException(
                    'Ada produk yang tidak tersedia pada cabang sumber. Penyalinan dibatalkan.'
                );
            }
            $bundleModel = new ProductBundleController($this->koneksi);
            $bundleDefinitions = [];
            $sourceLaserRules = (new ProductLaserConversionController($this->koneksi))->getByStore($sourceId);
            $dependencyIds = $ids;
            foreach ($ids as $id) {
                $bundle = $bundleModel->find($sourceId, $id);
                if (!$bundle) {
                    continue;
                }
                $bundleDefinitions[$id] = $bundle;
                foreach ($bundleModel->compile($sourceId, $bundle) as $item) {
                    $dependencyIds[] = (int) $item['product']['product_id'];
                    if ($item['laser']) {
                        $dependencyIds[] = (int) $item['laser']['laser_product_id'];
                    }
                }
            }
            $conversionProducts = array_fill_keys($dependencyIds, true);
            $laserRulesByProduct = [];
            foreach ($sourceLaserRules as $rule) {
                $productId = (int) $rule['product_id'];
                if (!isset($conversionProducts[$productId])) {
                    continue;
                }
                if (!$rule['valid']) {
                    throw new InvalidArgumentException(
                        'Konversi print laser produk ' . $rule['product_name'] . ' tidak valid. Periksa bahan dan finishing sumber.'
                    );
                }
                $laserRulesByProduct[$productId][] = $rule;
                $dependencyIds[] = (int) $rule['laser_product_id'];
            }
            $dependencyIds = array_values(array_unique($dependencyIds));
            if (count($dependencyIds) !== count($ids)) {
                $placeholders = implode(',', array_fill(0, count($dependencyIds), '?'));
                $stmt = $this->copyProductQuery(
                    "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.category_id = p.category_id AND c.store_id = ? WHERE p.store_id = ? AND p.product_id IN ($placeholders) ORDER BY p.product_id FOR UPDATE",
                    array_merge([$sourceId, $sourceId], $dependencyIds)
                );
                $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                if (count($products) !== count($dependencyIds)) {
                    throw new InvalidArgumentException('Produk pendukung sumber tidak lengkap. Penyalinan dibatalkan.');
                }
            }
            $newProducts = $existingProducts = $newVariants = $skippedVariants = 0;
            $newBundles = $existingBundles = 0;
            foreach ($targets as $target) {
                $targetId = (int)$target['store_id'];
                $targetName = $target['name'];
                $productMap = $variantMap = $laserMap = $finishingMap = [];
                $model = new ProductVariantController($this->koneksi);
                $configured = [];
                $packageProducts = [];
                foreach ((new ProductFinishingPriceController($this->koneksi))->getByStore($targetId, 0, false) as $rule) {
                    $configured[(int) $rule['product_id']] = true;
                    $packageProducts[(int) $rule['product_id']] = true;
                }
                foreach ((new ProductLaserConversionController($this->koneksi))->getByStore($targetId) as $rule) {
                    $configured[(int) $rule['product_id']] = true;
                }
                foreach ($products as $product) {
                    if (empty($product['category_name'])) {
                        throw new InvalidArgumentException('Kategori produk #' . $product['product_id'] . ' tidak valid.');
                    }
                    $stmt = $this->copyProductQuery(
                        'SELECT category_id FROM categories WHERE store_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?)) FOR UPDATE',
                        [$targetId, $product['category_name']]
                    );
                    $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    if (count($categories) > 1) {
                        throw new InvalidArgumentException(
                            'Kategori ' . $product['category_name'] . ' muncul lebih dari satu kali di cabang tujuan.'
                        );
                    }
                    if ($categories) {
                        $categoryId = (int) $categories[0]['category_id'];
                    } else {
                        $this->copyProductQuery(
                            'INSERT INTO categories (store_id, name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())',
                            [$targetId, $product['category_name']]
                        )->close();
                        $categoryId = (int) $this->koneksi->insert_id;
                    }
                    $stmt = $this->copyProductQuery(
                        'SELECT product_id, unit_type FROM products WHERE store_id = ? AND category_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?)) FOR UPDATE',
                        [$targetId, $categoryId, $product['name']]
                    );
                    $matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    if (count($matches) > 1) {
                        throw new InvalidArgumentException(
                            'Produk ' . $product['name'] . ' muncul lebih dari satu kali pada kategori tujuan.'
                        );
                    }
                    if ($matches) {
                        $match = $matches[0];
                        if (strtoupper(trim($match['unit_type'] ?? '')) !== strtoupper(trim($product['unit_type'] ?? ''))) {
                            throw new InvalidArgumentException(
                                'Satuan produk ' . $product['name'] . ' berbeda: sumber '
                                . $product['unit_type'] . ', tujuan ' . $match['unit_type'] . '.'
                            );
                        }
                        $targetProduct = (int) $match['product_id'];
                        $existingProducts++;
                    } else {
                        $this->copyProductQuery(
                            'INSERT INTO products (store_id, category_id, name, unit_type, price, reasonable_price, failed_price, stock) VALUES (?, ?, ?, ?, ?, ?, ?, 0)',
                            [
                                $targetId,
                                $categoryId,
                                $product['name'],
                                $product['unit_type'],
                                $product['price'],
                                $product['reasonable_price'],
                                $product['failed_price']
                            ]
                        )->close();
                        $targetProduct = (int) $this->koneksi->insert_id;
                        $newProducts++;
                    }
                    $productMap[(int) $product['product_id']] = $targetProduct;
                    $variants = $model->getByProduct($sourceId, (int) $product['product_id']);
                    if ($variants) {
                        if (isset($configured[$targetProduct]) || $bundleModel->find($targetId, $targetProduct)) {
                            throw new InvalidArgumentException(
                                'Produk ' .
                                    $product['name'] .
                                    ' di tujuan memakai paket atau konversi laser. Varian tidak dapat ditambahkan.'
                            );
                        }
                        $count = $this->copyProductVariants($variants, $targetId, $targetProduct);
                        $newVariants += $count['added'];
                        $skippedVariants += $count['skipped'];
                        $variantMap += $count['map'];
                    }
                }
                foreach ($laserRulesByProduct as $sourceProduct => $rules) {
                    $targetProduct = $productMap[$sourceProduct];
                    if (
                        isset($packageProducts[$targetProduct]) ||
                        $model->getByProduct($targetId, $targetProduct) ||
                        $bundleModel->find($targetId, $targetProduct)
                    ) {
                        throw new InvalidArgumentException(
                            'Produk ' . $rules[0]['product_name'] . ' di tujuan sudah memakai varian atau paket. Konversi laser tidak dapat ditambahkan.'
                        );
                    }
                    foreach ($rules as $rule) {
                        $laserMap[(int) $rule['id']] = $this->copyBundleLaser(
                            $rule,
                            $sourceId,
                            $targetId,
                            $productMap,
                            $finishingMap
                        );
                    }
                }
                foreach ($bundleDefinitions as $sourceProduct => $bundle) {
                    $targetProduct = $productMap[$sourceProduct];
                    if ($bundleModel->find($targetId, $targetProduct)) {
                        $existingBundles++;
                        continue;
                    }
                    if (isset($configured[$targetProduct])) {
                        throw new InvalidArgumentException(
                            'Produk paket tujuan sudah memakai harga paket atau konversi laser.'
                        );
                    }
                    $bundleModel->copyToStore(
                        $sourceId,
                        $sourceProduct,
                        $targetId,
                        $targetProduct,
                        $productMap,
                        $variantMap,
                        $laserMap
                    );
                    $newBundles++;
                }
            }
            $this->koneksi->commit();
            $transaction = false;
            $saved = true;
            $targetCount = count($targets);
            $_SESSION[
                'swal_success'
            ] = "Disalin ke $targetCount cabang: $newProducts produk baru dan $newVariants varian berhasil disalin. $existingProducts produk dan $skippedVariants varian yang sudah ada dipertahankan.";
            if ($laserRulesByProduct) {
                $_SESSION['swal_success'] .= ' Konversi print laser ikut disalin.';
            }
            if ($bundleDefinitions) {
                $_SESSION[
                    'swal_success'
                ] .= " $newBundles paket gabungan disalin, $existingBundles paket yang sudah ada dipertahankan.";
            }
        } catch (Throwable $e) {
            if ($transaction) {
                $this->koneksi->rollback();
            }
            unset($_SESSION['swal_success']);
            $_SESSION['swal_error'] =
                $e instanceof InvalidArgumentException
                    ? ($targetName ? $targetName . ': ' : '') . $e->getMessage() . ' Semua perubahan dibatalkan.'
                    : 'Gagal menyalin produk. Semua perubahan dibatalkan.';
            error_log('Copy products: ' . $e->getMessage());
        } finally {
            mysqli_report($report);
        }
        if ($saved) $this->updateCopiedStoreCaches($targets, [
            'categories', 'products', 'finishings', 'product_variants', 'production_components',
            'product_price_tiers', 'product_laser_conversion', 'product_bundles'
        ], 'Produk');
        header('Location: ' . $this->productReturnUrl());
        exit();
    }

    public function migrateProduct() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $storeId = (int)($_POST['store_id'] ?? 0);
        $targetId = (int)($_POST['target_product_id'] ?? 0);
        $transaction = false;
        $migrated = false;
        $driver = new mysqli_driver();
        $previousMode = $driver->report_mode;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            if (empty($_SESSION['admin_logged_in']) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new InvalidArgumentException('Silakan login kembali.');
            }
            if (!is_string($_POST['csrf'] ?? null) || empty($_SESSION['product_migration_csrf']) || !hash_equals($_SESSION['product_migration_csrf'], $_POST['csrf'])) {
                throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
            }
            $sourceIds = $this->selectedCatalogIds($_POST['source_product_ids'] ?? [$_POST['source_product_id'] ?? 0]);
            $deleteSource = $_POST['delete_source_products'] ?? '0';
            if (!is_string($deleteSource) || !in_array($deleteSource, ['0', '1'], true)) throw new InvalidArgumentException('Pilihan hapus produk asal tidak valid.');
            $deleteSource = $deleteSource === '1';
            $variantId = $_POST['target_variant_id'] ?? '0';
            if ($variantId === '') $variantId = '0';
            if (!is_scalar($variantId) || !preg_match('/^\d+$/D', (string)$variantId) || (float)$variantId > 2147483647) throw new InvalidArgumentException('Pilihan varian tujuan tidak valid.');
            $variantId = (int)$variantId;
            $sourceVariantIds = $_POST['source_variant_ids'] ?? [];
            if (!is_array($sourceVariantIds) || ($sourceVariantIds && count($sourceVariantIds) !== count($sourceIds))) throw new InvalidArgumentException('Pilihan varian tiap produk asal belum lengkap.');
            if ($sourceVariantIds) foreach ($sourceIds as $sourceId) {
                $id = filter_var($sourceVariantIds[$sourceId] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
                if (!$id) throw new InvalidArgumentException('Pilih varian tujuan untuk setiap produk asal.');
                $sourceVariantIds[$sourceId] = $id;
            }
            $finishingIds = $_POST['target_finishing_ids'] ?? [];
            if (!array_key_exists('target_finishing_ids', $_POST) && isset($_POST['target_finishing_id']) && !in_array($_POST['target_finishing_id'], ['', '0', 0], true)) $finishingIds = [$_POST['target_finishing_id']];
            if (!is_array($finishingIds) || count($finishingIds) > 1000) throw new InvalidArgumentException('Pilihan finishing tambahan tidak valid.');
            foreach ($finishingIds as $finishingId) if (!is_scalar($finishingId) || filter_var($finishingId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false) throw new InvalidArgumentException('Pilihan finishing tambahan tidak valid.');
            $finishingIds = array_values(array_unique(array_map('intval', $finishingIds))); sort($finishingIds, SORT_NUMERIC);
            if ($targetId <= 0 || in_array($targetId, $sourceIds, true) || $storeId <= 0) {
                throw new InvalidArgumentException('Produk pengganti harus berbeda dari semua produk yang dipilih.');
            }
            $adminId = (int)startEnk('dek', $_SESSION['admin_logged_in']['administrator_id'] ?? '');
            $stmt = $this->koneksi->prepare('SELECT access FROM administrator WHERE administrator_id = ?');
            $stmt->bind_param('i', $adminId);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$admin) throw new InvalidArgumentException('Akun tidak ditemukan. Silakan login kembali.');

            $this->koneksi->begin_transaction();
            $transaction = true;
            $stmt = $this->koneksi->prepare('SELECT administrator FROM stores WHERE store_id = ? FOR UPDATE');
            $stmt->bind_param('i', $storeId);
            $stmt->execute();
            $store = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$store || ($admin['access'] !== 'ALL' && ((string)$admin['access'] === '' || (string)$store['administrator'] !== (string)$admin['access']))) {
                throw new InvalidArgumentException('Anda tidak memiliki akses ke toko ini.');
            }
            $allIds = array_merge($sourceIds, [$targetId]);
            sort($allIds);
            $placeholders = implode(',', array_fill(0, count($allIds), '?'));
            $params = array_merge([$storeId], $allIds);
            $types = str_repeat('i', count($params));
            $stmt = $this->koneksi->prepare("SELECT p.*, c.name AS category FROM products p JOIN categories c ON c.category_id = p.category_id AND c.store_id = p.store_id WHERE p.store_id = ? AND p.product_id IN ($placeholders) ORDER BY p.product_id FOR UPDATE");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            if (count($products) !== count($allIds) || count(array_unique(array_column($products, 'category_id'))) !== 1) {
                throw new InvalidArgumentException('Produk sumber dan tujuan harus berada di toko dan kategori yang sama.');
            }
            $products = array_column($products, null, 'product_id');
            $target = $products[$targetId];
            $bundleModel = new ProductBundleController($this->koneksi);
            foreach ($allIds as $id) if ($bundleModel->find($storeId,$id)) throw new InvalidArgumentException('Produk paket gabungan dikelola melalui Harga Paket; tidak dimigrasikan sebagai produk biasa.');
            $finishings = [];
            if ($finishingIds) {
                $finishingPlaceholders = implode(',', array_fill(0, count($finishingIds), '?'));
                $finishingParams = array_merge([$storeId, $target['category_id']], $finishingIds);
                $stmt = $this->koneksi->prepare("SELECT finishing_id, name, unit_type FROM finishings WHERE store_id = ? AND category_id = ? AND finishing_id IN ($finishingPlaceholders) ORDER BY finishing_id FOR UPDATE");
                $stmt->bind_param(str_repeat('i', count($finishingParams)), ...$finishingParams); $stmt->execute();
                $finishings = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), null, 'finishing_id'); $stmt->close();
                if (count($finishings) !== count($finishingIds)) throw new InvalidArgumentException('Pilih finishing yang tersedia pada toko dan kategori produk tujuan.');
            }
            $variantModel = new ProductVariantController($this->koneksi);
            $variants = $variantModel->getByProduct($storeId, $targetId);
            $variantsById = array_column($variants, null, 'id');
            $sourceVariants = [];
            foreach ($sourceIds as $sourceId) {
                $selectedId = $sourceVariantIds[$sourceId] ?? $variantId;
                if (($variants && !isset($variantsById[$selectedId])) || ($selectedId && !isset($variantsById[$selectedId]))) throw new InvalidArgumentException('Pilih varian yang tersedia pada produk tujuan untuk setiap produk asal. Muat ulang jika pilihan sudah berubah.');
                if ($selectedId) $sourceVariants[$sourceId] = $variantsById[$selectedId];
            }
            $hasVariant = (bool)$sourceVariants;
            $pricing = new ProductPriceTierController($this->koneksi);
            if ($hasVariant && !$pricing->isAvailable()) throw new InvalidArgumentException('Pengaturan harga varian belum tersedia.');

            // Kunci nota bersama itemnya; migrasi tidak menjalankan perhitungan ulang harga/stok.
            $placeholders = implode(',', array_fill(0, count($sourceIds), '?'));
            $params = array_merge([$storeId], $sourceIds);
            $types = str_repeat('i', count($params));
            $stmt = $this->koneksi->prepare("SELECT DISTINCT o.order_id FROM orders o JOIN order_items oi ON oi.order_id = o.order_id AND oi.store_id = o.store_id WHERE oi.store_id = ? AND oi.product_id IN ($placeholders) ORDER BY o.order_id FOR UPDATE");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            if ($hasVariant || $finishings) {
                $hasPricing = $pricing->isAvailable();
                $templates = [];
                foreach ($sourceVariants as $sourceId => $selected) $templates[$sourceId] = ProductVariantController::orderData($selected, $target);
                $discountField = $hasVariant ? ', COALESCE(d.diskon, 0) AS migration_discount' : '';
                $discountJoin = $hasVariant ? ' LEFT JOIN diskon_order_items d ON d.order_id = oi.order_id AND d.product_id = oi.product_id' : '';
                $stmt = $this->koneksi->prepare("SELECT oi.*$discountField FROM order_items oi$discountJoin WHERE oi.store_id = ? AND oi.product_id IN ($placeholders) ORDER BY oi.order_id, oi.order_item_id FOR UPDATE");
                $stmt->bind_param($types, ...$params); $stmt->execute(); $items = $stmt->get_result();
                $variantFields = $hasVariant ? ', product_variant_id = ?, product_variant_data = ?' : '';
                $pricingField = $hasPricing ? ', tier_price_data = ?' : '';
                $update = $this->koneksi->prepare("UPDATE order_items SET product_id = ?, finishing = ?$variantFields$pricingField WHERE order_item_id = ? AND store_id = ?");
                $count = 0;
                while ($row = $items->fetch_assoc()) {
                    if (!empty($row['product_finishing_price_id']) || !empty($row['laser_product_id'])) throw new InvalidArgumentException('Item harga paket atau konversi print laser perlu dimigrasikan melalui pengaturannya sendiri.');
                    $finalFinishing = $row['finishing']; $addedIds = [];
                    if ($finishings) {
                        $previous = trim((string)$row['finishing']);
                        if ($previous !== '' && $previous !== '-' && !preg_match('/^\d+(?:\s*,\s*\d+)*$/D', $previous)) throw new InvalidArgumentException('Finishing lama pada item #' . $row['order_item_id'] . ' tidak valid. Periksa sebelum migrasi.');
                        $ids = ProductLaserConversionController::finishingIds($previous);
                        $addedIds = array_values(array_diff($finishingIds, $ids));
                        $ids = array_merge($ids, $addedIds);
                        sort($ids, SORT_NUMERIC); $finalFinishing = implode(',', $ids);
                    }
                    $pricingData = $hasPricing && !empty($row['tier_price_data']) ? json_decode($row['tier_price_data'], true, 512, JSON_THROW_ON_ERROR) : null;
                    if (!empty($pricingData['bundle'])) throw new InvalidArgumentException('Item paket gabungan tidak dimigrasikan sebagai varian biasa.');
                    $values = [$targetId, $finalFinishing]; $updateTypes = 'is';
                    if ($hasVariant) {
                        $snapshot = $templates[$row['product_id']];
                        $oldVariant = ProductVariantController::snapshot($row);
                        $snapshot['price'] = (float)($oldVariant['price'] ?? $pricingData['base'] ?? $products[$row['product_id']]['price']);
                        $variantJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                        $pricingData = $pricing->variantMigrationData($row, $target, $snapshot, (float)$row['migration_discount']);
                        array_push($values, $snapshot['id'], $variantJson); $updateTypes .= 'is';
                    }
                    if ($addedIds && $pricingData) {
                        $factor = 1;
                        if (strtoupper(trim($target['category'])) !== 'ANEKA CETAK' && preg_match('/^([\d.]+)x([\d.]+)$/i', $row['size'], $size)) $factor = max(0, (float)$size[1] * (float)$size[2]) ?: 1;
                        foreach ($addedIds as $finishingId) if ($finishings[$finishingId]['unit_type'] !== '~') $pricingData['stock']['finishings'][$finishingId] = $factor;
                    }
                    if ($hasPricing) { $values[] = $pricingData ? json_encode($pricingData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null; $updateTypes .= 's'; }
                    $itemId = (int)$row['order_item_id'];
                    array_push($values, $itemId, $storeId); $updateTypes .= 'ii';
                    $update->bind_param($updateTypes, ...$values); $update->execute(); $count += $update->affected_rows;
                }
                $update->close(); $stmt->close();
            } else {
                $params = array_merge([$targetId, $storeId], $sourceIds);
                $types = str_repeat('i', count($params));
                $stmt = $this->koneksi->prepare("UPDATE order_items SET product_id = ? WHERE store_id = ? AND product_id IN ($placeholders)");
                $stmt->bind_param($types, ...$params); $stmt->execute(); $count = $stmt->affected_rows; $stmt->close();
            }
            if ($deleteSource) {
                // Hapus hanya setelah semua item berpindah; kegagalan membatalkan seluruh migrasi.
                $stmt = $this->koneksi->prepare("DELETE p FROM products p WHERE p.store_id = ? AND p.product_id IN ($placeholders)
                    AND NOT EXISTS(SELECT 1 FROM order_items oi WHERE oi.product_id = p.product_id)");
                $params = array_merge([$storeId], $sourceIds);
                $stmt->bind_param(str_repeat('i', count($params)), ...$params);
                $stmt->execute(); $deletedCount = $stmt->affected_rows; $stmt->close();
                if ($deletedCount !== count($sourceIds)) throw new InvalidArgumentException('Produk asal masih dipakai atau datanya berubah. Migrasi dan penghapusan dibatalkan; muat ulang halaman.');
            }
            $this->koneksi->commit();
            $transaction = false;
            $migrated = true;
            $sourceCount = count($sourceIds);
            $variantLabel = $sourceVariantIds ? ' / varian sesuai masing-masing produk asal' : ($hasVariant ? ' / ' . reset($sourceVariants)['label'] : '');
            $sourceMessage = $deleteSource ? "$sourceCount produk asal berhasil dihapus." : 'Produk asal tetap tersedia.';
            $_SESSION['swal_success'] = "$count item order dari $sourceCount produk berhasil dimigrasikan ke produk #$targetId$variantLabel. Harga, diskon, dan quantity tetap. $sourceMessage";
        } catch (Throwable $e) {
            if ($transaction) $this->koneksi->rollback();
            $_SESSION['swal_error'] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Migrasi gagal. Perubahan dibatalkan.';
            error_log('Product migration failed: ' . $e->getMessage());
        } finally {
            mysqli_report($previousMode);
        }
        if ($migrated) {
            try {
                require_once __DIR__ . '/../functions/cacheHelper.php';
                updateStoreCache($storeId, 'orders');
                if ($deleteSource) foreach (['products', 'categories', 'product_variants', 'product_finishing_prices', 'product_laser_conversion', 'product_price_tiers'] as $module) updateStoreCache($storeId, $module);
                foreach ($orders as $order) updateOrderTrigger($storeId, $order['order_id']);
            } catch (Throwable $e) {
                error_log('Product migration cache refresh failed: ' . $e->getMessage());
            }
        }
        header('Location: ' . $this->productReturnUrl());
        exit;
    }

    public function bulkDeleteProducts() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $storeId = (int)($_POST['store_id'] ?? 0);
        $transaction = false;
        $deleted = false;
        $driver = new mysqli_driver();
        $previousMode = $driver->report_mode;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            if (empty($_SESSION['admin_logged_in']) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new InvalidArgumentException('Silakan login kembali.');
            }
            if (!is_string($_POST['csrf'] ?? null) || empty($_SESSION['product_category_csrf'])
                || !hash_equals($_SESSION['product_category_csrf'], $_POST['csrf'])) {
                throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
            }
            $ids = $this->selectedCatalogIds($_POST['product_ids'] ?? []);
            $access = startEnk('dek', $_SESSION['admin_logged_in']['access'] ?? '');
            $this->koneksi->begin_transaction();
            $transaction = true;
            $stmt = $this->koneksi->prepare('SELECT administrator FROM stores WHERE store_id = ? FOR UPDATE');
            $stmt->bind_param('i', $storeId);
            $stmt->execute();
            $store = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$store || ($access !== 'ALL' && ((string)$access === '' || (string)$store['administrator'] !== (string)$access))) {
                throw new InvalidArgumentException('Anda tidak memiliki akses ke toko ini.');
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$storeId], $ids);
            $types = str_repeat('i', count($params));
            $stmt = $this->koneksi->prepare("SELECT product_id FROM products WHERE store_id = ? AND product_id IN ($placeholders) ORDER BY product_id FOR UPDATE");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $count = $stmt->get_result()->num_rows;
            $stmt->close();
            if ($count !== count($ids)) throw new InvalidArgumentException('Ada produk yang tidak tersedia atau bukan milik toko ini. Penghapusan dibatalkan.');
            $types = str_repeat('i', count($params));
            $stmt = $this->koneksi->prepare("DELETE p FROM products p WHERE p.store_id = ? AND p.product_id IN ($placeholders)
                AND NOT EXISTS(SELECT 1 FROM order_items oi WHERE oi.product_id = p.product_id)");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $count = $stmt->affected_rows;
            $stmt->close();
            $skipped = count($ids) - $count;
            $this->koneksi->commit();
            $transaction = false;
            $deleted = $count > 0;
            $_SESSION['swal_success'] = "$count produk berhasil dihapus."
                . ($skipped > 0 ? " $skipped produk dilewati karena masih memiliki item order." : '');
        } catch (Throwable $e) {
            if ($transaction) $this->koneksi->rollback();
            $_SESSION['swal_error'] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Gagal menghapus produk. Perubahan dibatalkan.';
            error_log('Bulk product delete: ' . $e->getMessage());
        } finally {
            mysqli_report($previousMode);
        }
        if ($deleted) {
            set_error_handler(function ($severity, $message) { throw new RuntimeException($message); });
            try {
                require_once __DIR__ . '/../functions/cacheHelper.php';
                foreach (['products', 'categories', 'product_finishing_prices', 'product_laser_conversion', 'product_price_tiers', 'orders'] as $module) updateStoreCache($storeId, $module);
            } catch (Throwable $e) {
                error_log('Bulk product delete cache: ' . $e->getMessage());
                unset($_SESSION['swal_success']);
                $_SESSION['swal_error'] = 'Produk sudah dihapus, tetapi cache gagal diperbarui. Gunakan tombol Update Cache.';
            } finally { restore_error_handler(); }
        }
        header('Location: ' . $this->productReturnUrl());
        exit;
    }

    public function bulkUpdateCategory() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $storeId = (int)($_POST['store_id'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $transaction = false;
        $saved = false;
        $driver = new mysqli_driver();
        $previousMode = $driver->report_mode;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            if (empty($_SESSION['admin_logged_in']) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new InvalidArgumentException('Silakan login kembali.');
            }
            if (!is_string($_POST['csrf'] ?? null) || empty($_SESSION['product_category_csrf'])
                || !hash_equals($_SESSION['product_category_csrf'], $_POST['csrf'])) {
                throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
            }
            $ids = $_POST['product_ids'] ?? [];
            if (!is_array($ids) || !$ids || count($ids) > 1000) {
                throw new InvalidArgumentException('Pilih 1 sampai 1.000 produk untuk diganti kategorinya.');
            }
            foreach ($ids as $id) {
                if (!is_scalar($id) || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false) {
                    throw new InvalidArgumentException('Pilihan produk tidak valid. Muat ulang halaman.');
                }
            }
            $ids = array_values(array_unique(array_map('intval', $ids)));
            sort($ids);
            $access = startEnk('dek', $_SESSION['admin_logged_in']['access'] ?? '');
            $this->koneksi->begin_transaction();
            $transaction = true;
            $stmt = $this->koneksi->prepare('SELECT administrator FROM stores WHERE store_id = ? FOR UPDATE');
            $stmt->bind_param('i', $storeId);
            $stmt->execute();
            $store = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$store || ($access !== 'ALL' && ((string)$access === '' || (string)$store['administrator'] !== (string)$access))) {
                throw new InvalidArgumentException('Anda tidak memiliki akses ke toko ini.');
            }
            $stmt = $this->koneksi->prepare('SELECT category_id FROM categories WHERE category_id = ? AND store_id = ? FOR UPDATE');
            $stmt->bind_param('ii', $categoryId, $storeId);
            $stmt->execute();
            $category = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$category) throw new InvalidArgumentException('Pilih kategori tujuan dari toko yang sama.');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$storeId], $ids);
            $types = str_repeat('i', count($params));
            $stmt = $this->koneksi->prepare("SELECT product_id FROM products WHERE store_id = ? AND product_id IN ($placeholders) ORDER BY product_id FOR UPDATE");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $count = $stmt->get_result()->num_rows;
            $stmt->close();
            if ($count !== count($ids)) throw new InvalidArgumentException('Ada produk yang sudah tidak tersedia atau bukan milik toko ini. Perubahan dibatalkan.');
            $params = array_merge([$categoryId, $storeId], $ids);
            $types = str_repeat('i', count($params));
            $stmt = $this->koneksi->prepare("UPDATE products SET category_id = ? WHERE store_id = ? AND product_id IN ($placeholders)");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $changed = $stmt->affected_rows;
            $stmt->close();
            $this->koneksi->commit();
            $transaction = false;
            $saved = $changed > 0;
            $_SESSION['swal_success'] = $changed > 0 ? "$changed produk berhasil diganti kategorinya." : 'Semua produk yang dipilih sudah berada di kategori tujuan.';
        } catch (Throwable $e) {
            if ($transaction) $this->koneksi->rollback();
            $_SESSION['swal_error'] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Gagal mengganti kategori. Perubahan dibatalkan.';
            error_log('Bulk product category: ' . $e->getMessage());
        } finally {
            mysqli_report($previousMode);
        }
        if ($saved) {
            set_error_handler(function ($severity, $message) { throw new RuntimeException($message); });
            try {
                require_once __DIR__ . '/../functions/cacheHelper.php';
                foreach (['products', 'categories', 'product_finishing_prices', 'product_laser_conversion', 'product_price_tiers', 'orders'] as $module) updateStoreCache($storeId, $module);
            } catch (Throwable $e) {
                error_log('Bulk product category cache: ' . $e->getMessage());
                unset($_SESSION['swal_success']);
                $_SESSION['swal_error'] = 'Kategori produk sudah diganti, tetapi cache gagal diperbarui. Gunakan tombol Update Cache.';
            } finally {
                restore_error_handler();
            }
        }
        header('Location: ' . $this->productReturnUrl());
        exit;
    }

    public function addCategory() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $storeId = (int)($_POST['store_id'] ?? 0);
        $name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
        $transaction = false;
        $saved = false;
        $driver = new mysqli_driver();
        $previousMode = $driver->report_mode;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            if (empty($_SESSION['admin_logged_in']) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new InvalidArgumentException('Silakan login kembali.');
            }
            if (!is_string($_POST['csrf'] ?? null) || empty($_SESSION['category_csrf']) || !hash_equals($_SESSION['category_csrf'], $_POST['csrf'])) {
                throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
            }
            if ($storeId <= 0 || $name === '') throw new InvalidArgumentException('Toko dan nama kategori wajib diisi.');
            $access = startEnk('dek', $_SESSION['admin_logged_in']['access'] ?? '');
            $this->koneksi->begin_transaction();
            $transaction = true;
            $stmt = $this->koneksi->prepare('SELECT administrator FROM stores WHERE store_id = ? FOR UPDATE');
            $stmt->bind_param('i', $storeId);
            $stmt->execute();
            $store = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$store || ($access !== 'ALL' && ((string)$access === '' || (string)$store['administrator'] !== (string)$access))) {
                throw new InvalidArgumentException('Anda tidak memiliki akses ke toko ini.');
            }
            $stmt = $this->koneksi->prepare('SELECT category_id FROM categories WHERE store_id = ? AND LOWER(TRIM(name)) = LOWER(?) LIMIT 1');
            $stmt->bind_param('is', $storeId, $name);
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            if ($exists) throw new InvalidArgumentException('Nama kategori sudah ada di toko ini.');

            $stmt = $this->koneksi->prepare('INSERT INTO categories (name, store_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
            $stmt->bind_param('si', $name, $storeId);
            $stmt->execute();
            $stmt->close();
            $this->koneksi->commit();
            $transaction = false;
            $saved = true;
            $_SESSION['swal_success'] = 'Kategori berhasil ditambahkan.';
        } catch (Throwable $e) {
            if ($transaction) $this->koneksi->rollback();
            $_SESSION['swal_error'] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Gagal menambahkan kategori.';
            error_log('Add category failed: ' . $e->getMessage());
        } finally {
            mysqli_report($previousMode);
        }
        if ($saved) {
            try {
                require_once __DIR__ . '/../functions/cacheHelper.php';
                updateStoreCache($storeId, 'categories');
            } catch (Throwable $e) {
                error_log('Category cache refresh failed: ' . $e->getMessage());
            }
        }
        header('Location: ' . $this->productReturnUrl());
        exit;
    }

    public function deleteCategory() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $storeId = (int)($_POST['store_id'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $transaction = false;
        $deleted = false;
        $driver = new mysqli_driver();
        $previousMode = $driver->report_mode;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            if (empty($_SESSION['admin_logged_in']) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new InvalidArgumentException('Silakan login kembali.');
            }
            if (!is_string($_POST['csrf'] ?? null) || empty($_SESSION['category_csrf']) || !hash_equals($_SESSION['category_csrf'], $_POST['csrf'])) {
                throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
            }
            if ($storeId <= 0 || $categoryId <= 0) throw new InvalidArgumentException('Toko dan kategori tidak valid.');
            $access = startEnk('dek', $_SESSION['admin_logged_in']['access'] ?? '');
            $this->koneksi->begin_transaction();
            $transaction = true;
            $stmt = $this->koneksi->prepare('SELECT administrator FROM stores WHERE store_id = ? FOR UPDATE');
            $stmt->bind_param('i', $storeId);
            $stmt->execute();
            $store = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$store || ($access !== 'ALL' && ((string)$access === '' || (string)$store['administrator'] !== (string)$access))) {
                throw new InvalidArgumentException('Anda tidak memiliki akses ke toko ini.');
            }
            $stmt = $this->koneksi->prepare('SELECT category_id FROM categories WHERE category_id = ? AND store_id = ? FOR UPDATE');
            $stmt->bind_param('ii', $categoryId, $storeId);
            $stmt->execute();
            $category = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$category) throw new InvalidArgumentException('Kategori tidak ditemukan pada toko ini.');
            $stmt = $this->koneksi->prepare('DELETE c FROM categories c WHERE c.category_id = ? AND c.store_id = ?
                AND NOT EXISTS(SELECT 1 FROM products p WHERE p.category_id = c.category_id)
                AND NOT EXISTS(SELECT 1 FROM finishings f WHERE f.category_id = c.category_id)');
            $stmt->bind_param('ii', $categoryId, $storeId);
            $stmt->execute();
            $count = $stmt->affected_rows;
            $stmt->close();
            if (!$count) throw new InvalidArgumentException('Kategori masih memiliki produk atau finishing. Kosongkan keduanya terlebih dahulu.');
            $this->koneksi->commit();
            $transaction = false;
            $deleted = true;
            $_SESSION['swal_success'] = 'Kategori berhasil dihapus.';
            if ((int)($_POST['return_filters']['category_id'] ?? 0) === $categoryId) {
                $_POST['return_filters']['category_id'] = 0;
                $_POST['return_filters']['page'] = 1;
            }
        } catch (Throwable $e) {
            if ($transaction) $this->koneksi->rollback();
            $_SESSION['swal_error'] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Gagal menghapus kategori. Perubahan dibatalkan.';
            error_log('Delete category: ' . $e->getMessage());
        } finally {
            mysqli_report($previousMode);
        }
        if ($deleted) {
            set_error_handler(function ($severity, $message) { throw new RuntimeException($message); });
            try {
                require_once __DIR__ . '/../functions/cacheHelper.php';
                updateStoreCache($storeId, 'categories');
            } catch (Throwable $e) {
                error_log('Delete category cache: ' . $e->getMessage());
                unset($_SESSION['swal_success']);
                $_SESSION['swal_error'] = 'Kategori sudah dihapus, tetapi cache gagal diperbarui. Gunakan tombol Update Cache.';
            } finally { restore_error_handler(); }
        }
        header('Location: ' . $this->productReturnUrl());
        exit;
    }

    public function addProduct() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $storeId = filter_var($_POST['store_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
        $transaction = $saved = false; $driver = new mysqli_driver(); $report = $driver->report_mode;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_SESSION['product_add_csrf']) || !is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['product_add_csrf'], $_POST['csrf'])) throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
            $history = new ProductHistoryController($this->koneksi); $admin = $history->getAdministrator();
            if (!$admin) throw new InvalidArgumentException('Silakan login kembali.');
            if (!$history->isAvailable()) throw new InvalidArgumentException('Pencatatan produk belum disiapkan. Hubungi administrator.');
            $name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
            $categoryId = $_POST['category_id'] ?? '';
            $categoryId = $categoryId === '' ? null : filter_var($categoryId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $unit = $_POST['unit_type'] ?? '~';
            if ($storeId <= 0 || $name === '' || $categoryId === false || !in_array($unit, ['~', 'PCS', 'M2', 'CM2'], true)) throw new InvalidArgumentException('Periksa toko, nama, kategori, dan satuan produk.');
            [$price, $reasonablePrice, $failedPrice] = $this->requestPrices();
            $stock = $this->requestStock();
            $this->koneksi->begin_transaction(); $transaction = true;
            $stmt = $this->koneksi->prepare('SELECT administrator FROM stores WHERE store_id = ? FOR UPDATE');
            $stmt->bind_param('i', $storeId); $stmt->execute(); $store = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$store || ($admin['access'] !== 'ALL' && ($admin['access'] === '' || (string)$store['administrator'] !== (string)$admin['access']))) throw new InvalidArgumentException('Anda tidak memiliki akses ke toko ini.');
            if ($categoryId !== null) {
                $stmt = $this->koneksi->prepare('SELECT category_id FROM categories WHERE category_id = ? AND store_id = ? FOR UPDATE');
                $stmt->bind_param('ii', $categoryId, $storeId); $stmt->execute(); $category = $stmt->get_result()->fetch_assoc(); $stmt->close();
                if (!$category) throw new InvalidArgumentException('Kategori tidak tersedia di toko ini.');
            }
            $stmt = $this->koneksi->prepare('INSERT INTO products (store_id, category_id, name, price, reasonable_price, failed_price, stock, unit_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('iissssss', $storeId, $categoryId, $name, $price, $reasonablePrice, $failedPrice, $stock, $unit);
            $stmt->execute(); $productId = (int)$this->koneksi->insert_id; $stmt->close();
            $history->record($productId, (int)$admin['administrator_id']);
            $this->koneksi->commit(); $transaction = false; $saved = true;
            $_SESSION['swal_success'] = 'Produk berhasil ditambahkan.';
        } catch (Throwable $e) {
            if ($transaction) $this->koneksi->rollback();
            unset($_SESSION['swal_success']);
            $_SESSION['swal_error'] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Gagal menambahkan produk. Semua perubahan dibatalkan.';
            error_log('Add product: ' . $e->getMessage());
        } finally { mysqli_report($report); }
        if ($saved) try {
            require_once __DIR__ . '/../functions/cacheHelper.php'; updateStoreCache($storeId, 'products');
        } catch (Throwable $e) {
            error_log('Add product cache: ' . $e->getMessage());
            unset($_SESSION['swal_success']); $_SESSION['swal_error'] = 'Produk sudah ditambahkan, tetapi cache gagal diperbarui. Gunakan Update Cache.';
        }
        header('Location: ' . $this->productReturnUrl()); exit;
    }

    public function editProduct() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->updateData('products', 'product_id', 'produk');
    }

    public function deleteProduct() {
        $this->deleteData('products', 'product_id');
    }

    public function addFinishing() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->saveData('finishings', 'finishing');
    }

    public function editFinishing() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->updateData('finishings', 'finishing_id', 'finishing');
    }

    public function deleteFinishing() {
        $this->deleteData('finishings', 'finishing_id');
    }

    private function requestStock() {
        $stock = productStockValue($_POST['stock'] ?? 0);
        if ($stock === null) {
            $_SESSION['swal_error'] = 'Stok harus berupa angka, maksimal 11 digit dan 4 angka desimal.';
            header('Location: ' . $this->productReturnUrl());
            exit;
        }
        return $stock;
    }

    private function requestPrices() {
        $prices = productPriceValues($_POST);
        if ($prices === null) {
            $_SESSION['swal_error'] = 'Harga harus berupa angka positif atau nol, maksimal 13 digit dan 2 angka desimal.';
            header('Location: ' . $this->productReturnUrl());
            exit;
        }
        return $prices;
    }

    private function saveData($table, $label) {
        $store_id = $_POST['store_id'] ?? null;
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $name = trim($_POST['name'] ?? '');
        [$price, $reasonable_price, $failed_price] = $this->requestPrices();
        $stock = $this->requestStock();
        $unit_type = trim($_POST['unit_type'] ?? '');

        if ($store_id && $name) {
            $stmt = $this->koneksi->prepare("INSERT INTO $table (store_id, category_id, name, price, reasonable_price, failed_price, stock, unit_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissssss", $store_id, $category_id, $name, $price, $reasonable_price, $failed_price, $stock, $unit_type);

            if ($stmt->execute()) {
                require_once __DIR__ . '/../functions/cacheHelper.php';
                updateStoreCache($store_id, $table);
                
                $_SESSION['swal_success'] = ucfirst($label) . " berhasil ditambahkan.";
            } else {
                $_SESSION['swal_error'] = "Gagal menyimpan data: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['swal_error'] = "Toko dan nama wajib diisi.";
        }

        header('Location: ' . $this->productReturnUrl());
        exit;
    }

    private function updateData($table, $id_column, $label) {
        $id = $_POST[$id_column] ?? null;
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $name = trim($_POST['name'] ?? '');
        [$price, $reasonable_price, $failed_price] = $this->requestPrices();
        $stock = $this->requestStock();
        $unit_type = trim($_POST['unit_type'] ?? '');

        if ($id && $name) {
            $stmtGet = $this->koneksi->prepare("SELECT store_id FROM $table WHERE $id_column = ?");
            $stmtGet->bind_param("i", $id);
            $stmtGet->execute();
            $store_id = $stmtGet->get_result()->fetch_assoc()['store_id'] ?? null;
            $stmtGet->close();

            $stmt = $this->koneksi->prepare("UPDATE $table SET category_id=?, name=?, price=?, reasonable_price=?, failed_price=?, stock=?, unit_type=? WHERE $id_column=?");
            $stmt->bind_param("issssssi", $category_id, $name, $price, $reasonable_price, $failed_price, $stock, $unit_type, $id);

            if ($stmt->execute()) {
                if ($store_id) {
                    require_once __DIR__ . '/../functions/cacheHelper.php';
                    updateStoreCache($store_id, $table);
                }
                
                $_SESSION['swal_success'] = ucfirst($label) . " berhasil diperbarui.";
            } else {
                $_SESSION['swal_error'] = "Gagal memperbarui data: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['swal_error'] = "Nama wajib diisi.";
        }

        header('Location: ' . $this->productReturnUrl());
        exit;
    }

    private function deleteData($table, $id_column) {
        $id = $_POST[$id_column] ?? null;
        if ($id) {
            $stmtGet = $this->koneksi->prepare("SELECT store_id FROM $table WHERE $id_column = ?");
            $stmtGet->bind_param("i", $id);
            $stmtGet->execute();
            $store_id = $stmtGet->get_result()->fetch_assoc()['store_id'] ?? null;
            $stmtGet->close();

            $stmt = $this->koneksi->prepare("DELETE FROM $table WHERE $id_column = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                if ($store_id) {
                    require_once __DIR__ . '/../functions/cacheHelper.php';
                    updateStoreCache($store_id, $table);
                }
                
                echo "OK";
            } else {
                echo "Error: " . $this->koneksi->error;
            }
            $stmt->close();
        } else {
            echo "ID tidak ditemukan";
        }
        exit;
    }

    public function getCategoryByStoreId($store_id){
        $stmt = $this->koneksi->prepare("SELECT * FROM categories WHERE store_id = ?");
        $stmt->bind_param('i', $store_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $caregories = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $caregories;
    }

    public function getProductByCategoryId(){
        $category_id = $_GET['category_id'] ?? '';
        $stmt = $this->koneksi->prepare("SELECT * FROM products WHERE category_id = ?");
        $stmt->bind_param('i', $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $products = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // return $products;
        send_json_response(true, "success", $products);
    }

    public function getFinishingByCategoryId(){
        $category_id = $_GET['category_id'] ?? '';
        $stmt = $this->koneksi->prepare("SELECT * FROM finishings WHERE category_id = ?");
        $stmt->bind_param('i', $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $finishings = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // return $finishings;
        send_json_response(true, "success", $finishings);
    }

    public function getProductById($id) {
        $stmt = $this->koneksi->prepare("
            SELECT
                p.*,
                c.name AS category
            FROM products p
            LEFT JOIN categories c
                ON c.category_id = p.category_id
            WHERE p.product_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();

        return $data;
    }

    public function getStockByProductId($product_id) {
        $stmt = $this->koneksi->prepare("SELECT stock FROM products WHERE product_id = ? LIMIT 1");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? (float)$result['stock'] : 0;
    }

    public function getFinishingStockByProductId($finishing_id) {
        $stmt = $this->koneksi->prepare("SELECT stock FROM finishings WHERE finishing_id = ? LIMIT 1");
        $stmt->bind_param("i", $finishing_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? (float)$result['stock'] : 0;
    }

    public function addStock($quantity, $product_id) {
        $stmt = $this->koneksi->prepare("UPDATE products SET stock = stock + CAST(? AS DECIMAL(30,12)) WHERE product_id = ?");
        $stmt->bind_param("si", $quantity, $product_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function addFinishingStock($quantity, $finishing_id) {
        $stmt = $this->koneksi->prepare("UPDATE finishings SET stock = stock + CAST(? AS DECIMAL(30,12)) WHERE finishing_id = ?");
        $stmt->bind_param("si", $quantity, $finishing_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getProductByNameAndStore($name, $store_id) {
        $stmt = $this->koneksi->prepare("SELECT p.*, c.name AS category FROM products p LEFT JOIN categories c ON c.category_id = p.category_id WHERE p.name = ? AND p.store_id = ? LIMIT 1");
        $stmt->bind_param("si", $name, $store_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function reduceStock($quantity, $product_id) {
        $stmt = $this->koneksi->prepare("UPDATE products SET stock = stock - CAST(? AS DECIMAL(30,12)) WHERE product_id = ?");
        $stmt->bind_param("si", $quantity, $product_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function reduceFinishingStock($quantity, $finishing_id) {
        $stmt = $this->koneksi->prepare("UPDATE finishings SET stock = stock - CAST(? AS DECIMAL(30,12)) WHERE finishing_id = ?");
        $stmt->bind_param("si", $quantity, $finishing_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

}
