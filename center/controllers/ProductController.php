<?php
require_once __DIR__ . '/../functions/helpers.php';
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
        $search = $_GET['search'] ?? '';
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
        $totalPages = 0;
        $total = 0;

        if ($store_id !== null) {
            $migrationStmt = $this->koneksi->prepare('SELECT product_id, category_id, name FROM products WHERE store_id = ? ORDER BY name, product_id');
            $migrationStmt->bind_param('i', $store_id);
            $migrationStmt->execute();
            $migrationProducts = $migrationStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $migrationStmt->close();
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
            $countStmt = $this->koneksi->prepare("
                SELECT COUNT(*) 
                FROM products 
                WHERE store_id = ? AND name LIKE ?
                    AND (? = 0 OR category_id = ?)
            ");
            $countStmt->bind_param("isii", $store_id, $search_param, $category_id, $category_id);
            $countStmt->execute();
            $total = $countStmt->get_result()->fetch_row()[0];
            $countStmt->close();

            $totalPages = ceil($total / $limit);
            $offset = ($page - 1) * $limit;

            $stmt = $this->koneksi->prepare("
                SELECT p.*, c.name AS category,
                    (SELECT COUNT(*) FROM order_items oi
                     WHERE oi.product_id = p.product_id AND oi.store_id = p.store_id) AS order_item_count
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.category_id
                WHERE p.store_id = ? AND p.name LIKE ? 
                    AND (? = 0 OR p.category_id = ?)
                ORDER BY p.product_id DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param("isiiii", $store_id, $search_param, $category_id, $category_id, $limit, $offset);
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
                foreach (['store_data', 'store_names', 'categories', 'products', 'finishings', 'product_finishing_prices', 'product_laser_conversion', 'product_price_tiers', 'orders'] as $module) {
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

    private function selectedProductIds($ids) {
        if (!is_array($ids) || !$ids || count($ids) > 1000) {
            throw new InvalidArgumentException('Pilih 1 sampai 1.000 produk.');
        }
        foreach ($ids as $id) {
            if (!is_scalar($id) || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false) {
                throw new InvalidArgumentException('Pilihan produk tidak valid. Muat ulang halaman.');
            }
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        return $ids;
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
            $sourceIds = $this->selectedProductIds($_POST['source_product_ids'] ?? [$_POST['source_product_id'] ?? 0]);
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
            $stmt = $this->koneksi->prepare("SELECT product_id, category_id FROM products WHERE store_id = ? AND product_id IN ($placeholders) ORDER BY product_id FOR UPDATE");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            if (count($products) !== count($allIds) || count(array_unique(array_column($products, 'category_id'))) !== 1) {
                throw new InvalidArgumentException('Produk sumber dan tujuan harus berada di toko dan kategori yang sama.');
            }

            // Hanya ganti referensi produk, tanpa mengubah quantity, harga, atau total nota.
            $placeholders = implode(',', array_fill(0, count($sourceIds), '?'));
            $params = array_merge([$storeId], $sourceIds);
            $types = str_repeat('i', count($params));
            $stmt = $this->koneksi->prepare("SELECT DISTINCT order_id FROM order_items WHERE store_id = ? AND product_id IN ($placeholders) FOR UPDATE");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            $params = array_merge([$targetId, $storeId], $sourceIds);
            $types = str_repeat('i', count($params));
            $stmt = $this->koneksi->prepare("UPDATE order_items SET product_id = ? WHERE store_id = ? AND product_id IN ($placeholders)");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $count = $stmt->affected_rows;
            $stmt->close();
            $this->koneksi->commit();
            $transaction = false;
            $migrated = true;
            $sourceCount = count($sourceIds);
            $_SESSION['swal_success'] = "$count item order dari $sourceCount produk berhasil dimigrasikan ke produk #$targetId. Produk lama tetap tersedia.";
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
            $ids = $this->selectedProductIds($_POST['product_ids'] ?? []);
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
            $types = str_repeat('i', count($ids));
            $stmt = $this->koneksi->prepare("SELECT DISTINCT product_id FROM order_items WHERE product_id IN ($placeholders) FOR UPDATE");
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $used = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'product_id');
            $stmt->close();
            if ($used) throw new InvalidArgumentException('Produk #' . implode(', #', $used) . ' masih dipakai pada nota. Migrasikan item order-nya terlebih dahulu.');
            $types = str_repeat('i', count($params));
            $stmt = $this->koneksi->prepare("DELETE p FROM products p WHERE p.store_id = ? AND p.product_id IN ($placeholders)
                AND NOT EXISTS(SELECT 1 FROM order_items oi WHERE oi.product_id = p.product_id)");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $count = $stmt->affected_rows;
            $stmt->close();
            if ($count !== count($ids)) throw new InvalidArgumentException('Data produk berubah saat diproses. Penghapusan dibatalkan; muat ulang halaman.');
            $this->koneksi->commit();
            $transaction = false;
            $deleted = true;
            $_SESSION['swal_success'] = "$count produk berhasil dihapus.";
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
        $this->saveData('products', 'product');
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

    private function saveData($table, $label) {
        $store_id = $_POST['store_id'] ?? null;
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $name = trim($_POST['name'] ?? '');
        $price = isset($_POST['price']) ? (int)$_POST['price'] : 0;
        $reasonable_price = isset($_POST['reasonable_price']) ? (int)$_POST['reasonable_price'] : 0;
        $failed_price = isset($_POST['failed_price']) ? (int)$_POST['failed_price'] : 0;
        $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
        $unit_type = trim($_POST['unit_type'] ?? '');

        if ($store_id && $name) {
            $stmt = $this->koneksi->prepare("INSERT INTO $table (store_id, category_id, name, price, reasonable_price, failed_price, stock, unit_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisiiiis", $store_id, $category_id, $name, $price, $reasonable_price, $failed_price, $stock, $unit_type);

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
        $price = isset($_POST['price']) ? (int)$_POST['price'] : 0;
        $reasonable_price = isset($_POST['reasonable_price']) ? (int)$_POST['reasonable_price'] : 0;
        $failed_price = isset($_POST['failed_price']) ? (int)$_POST['failed_price'] : 0;
        $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
        $unit_type = trim($_POST['unit_type'] ?? '');

        if ($id && $name) {
            $stmtGet = $this->koneksi->prepare("SELECT store_id FROM $table WHERE $id_column = ?");
            $stmtGet->bind_param("i", $id);
            $stmtGet->execute();
            $store_id = $stmtGet->get_result()->fetch_assoc()['store_id'] ?? null;
            $stmtGet->close();

            $stmt = $this->koneksi->prepare("UPDATE $table SET category_id=?, name=?, price=?, reasonable_price=?, failed_price=?, stock=?, unit_type=? WHERE $id_column=?");
            $stmt->bind_param("isiiiisi", $category_id, $name, $price, $reasonable_price, $failed_price, $stock, $unit_type, $id);

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
        $stmt = $this->koneksi->prepare("UPDATE products SET stock = stock + ? WHERE product_id = ?");
        $stmt->bind_param("di", $quantity, $product_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function addFinishingStock($quantity, $finishing_id) {
        $stmt = $this->koneksi->prepare("UPDATE finishings SET stock = stock + ? WHERE finishing_id = ?");
        $stmt->bind_param("di", $quantity, $finishing_id);
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
        $stmt = $this->koneksi->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ?");
        $stmt->bind_param("di", $quantity, $product_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function reduceFinishingStock($quantity, $finishing_id) {
        $stmt = $this->koneksi->prepare("UPDATE finishings SET stock = stock - ? WHERE finishing_id = ?");
        $stmt->bind_param("di", $quantity, $finishing_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

}
