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

    public function migrateProduct() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $storeId = (int)($_POST['store_id'] ?? 0);
        $sourceId = (int)($_POST['source_product_id'] ?? 0);
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
            if (($_POST['confirmed'] ?? '') !== '1' || $sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId || $storeId <= 0) {
                throw new InvalidArgumentException('Pilih produk pengganti dan konfirmasi migrasi.');
            }
            $adminId = (int)startEnk('dek', $_SESSION['admin_logged_in']['administrator_id'] ?? '');
            $stmt = $this->koneksi->prepare('SELECT password, access FROM administrator WHERE administrator_id = ?');
            $stmt->bind_param('i', $adminId);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $password = $_POST['password'] ?? '';
            if (!$admin || !is_string($password) || !password_verify($password, $admin['password'])) {
                throw new InvalidArgumentException('Sandi salah. Migrasi dibatalkan.');
            }

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
            $stmt = $this->koneksi->prepare('SELECT product_id, category_id FROM products WHERE store_id = ? AND product_id IN (?, ?) ORDER BY product_id FOR UPDATE');
            $stmt->bind_param('iii', $storeId, $sourceId, $targetId);
            $stmt->execute();
            $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            if (count($products) !== 2 || $products[0]['category_id'] !== $products[1]['category_id']) {
                throw new InvalidArgumentException('Produk sumber dan tujuan harus berada di toko dan kategori yang sama.');
            }

            // Hanya ganti referensi produk, tanpa mengubah quantity, harga, atau total nota.
            $stmt = $this->koneksi->prepare('SELECT DISTINCT order_id FROM order_items WHERE store_id = ? AND product_id = ? FOR UPDATE');
            $stmt->bind_param('ii', $storeId, $sourceId);
            $stmt->execute();
            $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            $stmt = $this->koneksi->prepare('UPDATE order_items SET product_id = ? WHERE store_id = ? AND product_id = ?');
            $stmt->bind_param('iii', $targetId, $storeId, $sourceId);
            $stmt->execute();
            $count = $stmt->affected_rows;
            $stmt->close();
            $this->koneksi->commit();
            $transaction = false;
            $migrated = true;
            $_SESSION['swal_success'] = "$count item order berhasil dimigrasikan dari produk #$sourceId ke #$targetId. Produk lama tetap tersedia.";
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
