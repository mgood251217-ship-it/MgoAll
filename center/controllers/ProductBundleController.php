<?php
require_once __DIR__ . '/../../admin/models/ProductBundle.php';
require_once __DIR__ . '/ProductHistoryController.php';
require_once __DIR__ . '/AdministratorAccessController.php';
require_once __DIR__ . '/../functions/cacheHelper.php';

class ProductBundleController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    private function administrator() {
        $administrator = (new ProductHistoryController($this->db))->getAdministrator();
        if (!$administrator) {
            throw new InvalidArgumentException('Silakan login kembali.');
        }
        return $administrator;
    }

    private function stores() {
        $administrator = $this->administrator();
        if ((string) $administrator['access'] === '') {
            return [];
        }
        if ($administrator['access'] === 'ALL') {
            return $this->db->query('SELECT store_id,name FROM stores ORDER BY name')->fetch_all(MYSQLI_ASSOC);
        }
        $stmt = $this->db->prepare('SELECT store_id,name FROM stores WHERE administrator=? ORDER BY name');
        $stmt->bind_param('s', $administrator['access']);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getData() {
        $stores = $this->stores();
        $store = (int) ($_GET['store_id'] ?? ($stores[0]['store_id'] ?? 0));
        if ($store && !in_array($store, array_column($stores, 'store_id'))) {
            throw new InvalidArgumentException('Anda tidak memiliki akses ke toko ini.');
        }
        $model = new ProductBundle($this->db);
        $ready = $model->isAvailable();
        $products = $variants = $rules = $bundles = $warnings = [];
        if ($store && $ready) {
            // Batasi tiap tabel ke toko yang sama, tanpa membandingkan store_id teks antar-collation.
            $stmt = $this->db->prepare(
                "SELECT p.product_id,p.name,p.unit_type,c.name AS category FROM products p JOIN categories c ON c.category_id=p.category_id AND c.store_id=? WHERE p.store_id=? AND p.unit_type IN ('PCS','~') AND UPPER(TRIM(c.name)) IN ('TEKSTIL','PAKAIAN','JERSEY','PELENGKAP','ANEKA CETAK') ORDER BY c.name,p.name"
            );
            $stmt->bind_param('ii', $store, $store);
            $stmt->execute();
            $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            if ((new ProductVariant($this->db))->isAvailable()) {
                $stmt = $this->db->prepare(
                    'SELECT id,product_id,options_json FROM product_variants WHERE store_id=? ORDER BY id'
                );
                $stmt->bind_param('i', $store);
                $stmt->execute();
                foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $variant) {
                    $options = json_decode($variant['options_json'] ?? '', true);
                    if (
                        !is_array($options) ||
                        !$options ||
                        count(array_filter($options, 'is_scalar')) !== count($options)
                    ) {
                        $warnings[] =
                            'Varian #' .
                            (int) $variant['id'] .
                            ' tidak dapat dimuat. Perbaiki pilihan varian pada produk #' .
                            (int) $variant['product_id'] .
                            '.';
                        continue;
                    }
                    $variant['label'] = implode(' / ', array_values($options));
                    unset($variant['options_json']);
                    $variants[] = $variant;
                }
                $stmt->close();
            }
            $rules = (new ProductLaserConversion($this->db))->getByStore($store, true);
            $bundles = $model->getByStore($store);
        }
        foreach ($bundles as &$bundle) {
            $bundle['display_items'] = [];
            try {
                foreach ($model->compile($store, $bundle) as $item) {
                    $bundle['display_items'][] = [
                        'quantity' => $item['quantity'],
                        'label' =>
                            $item['product']['name'] .
                            ($item['variant'] ? ' - ' . $item['variant']['label'] : '') .
                            ($item['laser']
                                ? ' - ' .
                                    implode(
                                        ' / ',
                                        array_filter([
                                            $item['laser']['laser_name'],
                                            $item['laser']['size'],
                                            $item['laser']['finishing_names'] ?? ''
                                        ])
                                    )
                                : '')
                    ];
                }
            } catch (InvalidArgumentException $e) {
                $bundle['error'] = $e->getMessage();
            } catch (Throwable $e) {
                $bundle['error'] = 'Isi paket tidak dapat dimuat. Periksa varian dan konversi laser.';
                error_log('Bundle #' . (int) $bundle['id'] . ': ' . $e->getMessage());
            }
        }
        unset($bundle);
        return compact('stores', 'store', 'ready', 'products', 'variants', 'rules', 'bundles', 'warnings');
    }

    public function save() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $transaction = $saved = false;
        $store = (int) ($_POST['store_id'] ?? 0);
        $driver = new mysqli_driver();
        $reportMode = $driver->report_mode;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $administrator = $this->administrator();
            if (!(new AdministratorAccessController($this->db))->can('save_product_bundle')) {
                throw new InvalidArgumentException('Anda tidak memiliki izin mengatur harga paket.');
            }
            if (
                ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
                !is_string($_POST['csrf'] ?? null) ||
                empty($_SESSION['product_bundle_csrf']) ||
                !hash_equals($_SESSION['product_bundle_csrf'], $_POST['csrf'])
            ) {
                throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
            }
            $model = new ProductBundle($this->db);
            if (!$model->isAvailable()) {
                throw new InvalidArgumentException('Pengaturan paket gabungan belum tersedia.');
            }
            $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1]
            ]);
            $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
            if (!$productId || $price === false || !is_finite($price) || $price <= 0 || $price > 9999999999999.99) {
                throw new InvalidArgumentException('Produk paket dan harga harus valid.');
            }
            $items = $_POST['items'] ?? null;
            if (!is_array($items) || count($items) < 2 || count($items) > 30) {
                throw new InvalidArgumentException('Isi 2 sampai 30 baris produk.');
            }
            $this->db->begin_transaction();
            $transaction = true;
            $stmt = $this->db->prepare('SELECT administrator FROM stores WHERE store_id=? FOR UPDATE');
            $stmt->bind_param('i', $store);
            $stmt->execute();
            $owner = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (
                !$owner ||
                ($administrator['access'] !== 'ALL' &&
                    ($administrator['access'] === '' || $administrator['access'] !== $owner['administrator']))
            ) {
                throw new InvalidArgumentException('Anda tidak memiliki akses ke toko ini.');
            }
            $stmt = $this->db->prepare(
                'SELECT p.unit_type,c.name AS category FROM products p JOIN categories c ON c.category_id=p.category_id AND c.store_id=? WHERE p.store_id=? AND p.product_id=? FOR UPDATE'
            );
            $stmt->bind_param('iii', $store, $store, $productId);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$product || $product['unit_type'] !== '~' || strtoupper(trim($product['category'])) !== 'PELENGKAP') {
                throw new InvalidArgumentException('Buat/pilih produk induk paket dari PELENGKAP dengan satuan ~.');
            }
            if ((new ProductVariant($this->db))->getByProduct($store, $productId)) {
                throw new InvalidArgumentException(
                    'Produk induk paket tidak memakai varian sendiri; pilih varian pada isi paket.'
                );
            }
            $bundle = ['product_id' => $productId, 'items' => $items];
            $model->compile($store, $bundle);
            $stmt = $this->db->prepare(
                'INSERT INTO product_bundles(store_id,product_id,price) VALUES(?,?,?) ON DUPLICATE KEY UPDATE price=VALUES(price)'
            );
            $stmt->bind_param('iid', $store, $productId, $price);
            $stmt->execute();
            $stmt->close();
            $bundle = $model->find($store, $productId);
            $bundleId = (int) $bundle['id'];
            $stmt = $this->db->prepare('DELETE FROM product_bundle_items WHERE bundle_id=?');
            $stmt->bind_param('i', $bundleId);
            $stmt->execute();
            $stmt->close();
            $stmt = $this->db->prepare(
                'INSERT INTO product_bundle_items(bundle_id,product_id,product_variant_id,laser_conversion_id,quantity,size) VALUES(?,?,?,?,?,?)'
            );
            foreach ($items as $item) {
                $itemProductId = (int) $item['product_id'];
                $variantId = (int) ($item['product_variant_id'] ?? 0) ?: null;
                $laserId = (int) ($item['laser_conversion_id'] ?? 0) ?: null;
                $quantity = (int) $item['quantity'];
                $size = trim((string) ($item['size'] ?? ''));
                $stmt->bind_param('iiiiis', $bundleId, $itemProductId, $variantId, $laserId, $quantity, $size);
                $stmt->execute();
            }
            $stmt->close();
            $stmt = $this->db->prepare('UPDATE products SET price=? WHERE store_id=? AND product_id=?');
            $stmt->bind_param('dii', $price, $store, $productId);
            $stmt->execute();
            $stmt->close();
            $this->db->commit();
            $transaction = false;
            $saved = true;
            $_SESSION['swal_success'] = 'Paket gabungan tersimpan.';
        } catch (Throwable $e) {
            if ($transaction) {
                $this->db->rollback();
            }
            $_SESSION['swal_error'] =
                $e instanceof InvalidArgumentException
                    ? $e->getMessage()
                    : 'Paket gagal disimpan. Perubahan dibatalkan.';
            error_log('Product bundle: ' . $e->getMessage());
        } finally {
            mysqli_report($reportMode);
        }
        if ($saved) {
            set_error_handler(function ($severity, $message) {
                throw new RuntimeException($message);
            });
            try {
                foreach (['product_bundles', 'products'] as $module) {
                    updateStoreCache($store, $module);
                }
            } catch (Throwable $e) {
                unset($_SESSION['swal_success']);
                $_SESSION['swal_error'] = 'Paket tersimpan, tetapi cache belum diperbarui. Gunakan Update Cache.';
                error_log('Bundle cache: ' . $e->getMessage());
            } finally {
                restore_error_handler();
            }
        }
        header('Location: /package-prices?tab=bundles&store_id=' . $store);
        exit();
    }
}
