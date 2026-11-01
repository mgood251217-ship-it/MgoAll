<?php
require_once __DIR__ . '/../../admin/models/ProductFinishingPrice.php';
require_once __DIR__ . '/../functions/helpers.php';
require_once __DIR__ . '/../functions/cacheHelper.php';

class PackagePriceController {
    private $koneksi;
    private $prices;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
        $this->prices = new ProductFinishingPrice($koneksi);
    }

    private function getStores() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['admin_logged_in'])) throw new InvalidArgumentException('Silakan login kembali.');
        $access = startEnk('dek', $_SESSION['admin_logged_in']['access'] ?? '');
        if ($access === 'ALL') {
            return $this->koneksi->query('SELECT store_id, name FROM stores ORDER BY name')->fetch_all(MYSQLI_ASSOC);
        }
        if (!$access) return [];
        $stmt = $this->koneksi->prepare('SELECT store_id, name FROM stores WHERE administrator = ? ORDER BY name');
        $stmt->bind_param('s', $access);
        $stmt->execute();
        $stores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $stores;
    }

    private function checkStore($store_id) {
        if (!$store_id || !in_array($store_id, array_column($this->getStores(), 'store_id'))) {
            throw new InvalidArgumentException('Anda tidak memiliki akses ke toko ini.');
        }
    }

    public function getData() {
        $stores = $this->getStores();
        $store_id = (int)($_GET['store_id'] ?? ($stores[0]['store_id'] ?? 0));
        if ($store_id) $this->checkStore($store_id);
        $products = $rules = $finishings = [];
        $ready = $this->prices->hasCurrentSchema();
        if ($store_id) {
            $stmt = $this->koneksi->prepare("SELECT p.product_id, p.name, p.price, p.unit_type, c.name AS category
                FROM products p JOIN categories c ON c.category_id = p.category_id AND c.store_id = p.store_id
                WHERE p.store_id = ? AND (
                    (c.name IN ('INDOOR', 'OUTDOOR') AND p.unit_type = 'M2')
                    OR (c.name IN ('LASER', 'LASER A3') AND p.unit_type IN ('PCS', '~')))
                ORDER BY c.name, p.name, p.product_id");
            $stmt->bind_param('i', $store_id);
            $stmt->execute();
            $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            $stmt = $this->koneksi->prepare('SELECT finishing_id, category_id, name FROM finishings WHERE store_id = ? ORDER BY name, finishing_id');
            $stmt->bind_param('i', $store_id);
            $stmt->execute();
            $finishings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            $rules = $this->prices->getByStore($store_id, 0, false);
            if ($ready && $rules) {
                $stmt = $this->koneksi->prepare('SELECT product_id, product_finishing_price_id, COUNT(*) AS item_count
                    FROM order_items WHERE store_id = ? AND product_finishing_price_id IS NOT NULL
                    GROUP BY product_id, product_finishing_price_id');
                $stmt->bind_param('i', $store_id);
                $stmt->execute();
                $counts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                $packageCounts = [];
                foreach ($counts as $count) {
                    $product_id = (int)$count['product_id'];
                    $packageCounts[$product_id . ':' . (int)$count['product_finishing_price_id']] = (int)$count['item_count'];
                }
                foreach ($rules as &$rule) {
                    $rule['order_item_count'] = $packageCounts[(int)$rule['product_id'] . ':' . (int)$rule['id']] ?? 0;
                }
                unset($rule);
            }
        }
        return compact('stores', 'store_id', 'products', 'finishings', 'rules', 'ready');
    }

    public function getRules() {
        $store_id = (int)($_GET['store_id'] ?? 0);
        try {
            $this->checkStore($store_id);
        } catch (InvalidArgumentException $e) {
            http_response_code(403);
            send_json_response(false, $e->getMessage());
            return;
        }
        $rows = $this->prices->getByStore($store_id, (int)($_GET['category_id'] ?? 0), false);
        // Respons untuk halaman lama yang masih memeriksa flag active; tidak disimpan di database.
        foreach ($rows as &$row) $row['active'] = 1;
        unset($row);
        send_json_response(true, 'Harga paket', $rows);
    }

    private function normalizeFinishingName($name) {
        if (!is_string($name)) return '';
        $name = trim(preg_replace('/[\s\p{Z}]+/u', ' ', $name) ?? '');
        return function_exists('mb_strtoupper') ? mb_strtoupper($name, 'UTF-8') : strtoupper($name);
    }

    private function decimalInput($field, $label, $scale = 2, $max = 9999999999999.99) {
        $value = $_POST[$field] ?? '';
        if (!is_scalar($value) || !preg_match('/^\d+(?:\.\d{1,' . $scale . '})?$/D', (string)$value)
            || (float)$value <= 0 || (float)$value > $max) {
            throw new InvalidArgumentException($label . ' harus diisi dengan angka lebih dari 0.');
        }
        return (float)$value;
    }

    public function save() {
        $store_id = max(0, (int)($_POST['store_id'] ?? 0));
        $transaction = false;
        $saved = false;
        $driver = new mysqli_driver();
        $reportMode = $driver->report_mode;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $this->checkStore($store_id);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST'
                || !is_string($_POST['csrf_token'] ?? null)
                || !hash_equals($_SESSION['package_price_csrf'] ?? '', $_POST['csrf_token'])
                || empty($_SESSION['package_price_csrf'])) {
                throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
            }
            if (!$this->prices->hasCurrentSchema()) {
                throw new InvalidArgumentException('Jalankan migrasi tabel product_package terlebih dahulu.');
            }
            $this->koneksi->begin_transaction();
            $transaction = true;
            // Kunci per toko agar dua admin tidak membuat finishing paket bersamaan.
            $stmt = $this->koneksi->prepare('SELECT store_id FROM stores WHERE store_id = ? FOR UPDATE');
            $stmt->bind_param('i', $store_id);
            $stmt->execute();
            $stmt->close();
            $command = $_POST['command'] ?? '';
            if ($command === 'add') {
                $product_id = (int)($_POST['product_id'] ?? 0);
                $packageType = $this->normalizeFinishingName($_POST['finishing_name'] ?? '');
                if ($packageType === '' || strlen($packageType) > 100) {
                    throw new InvalidArgumentException('Isi nama finishing paket, maksimal 100 karakter.');
                }
                $price = $this->decimalInput('price', 'Harga paket');
                $stmt = $this->koneksi->prepare("SELECT p.category_id, p.unit_type, c.name AS category FROM products p
                    JOIN categories c ON c.category_id = p.category_id AND c.store_id = p.store_id
                    WHERE p.product_id = ? AND p.store_id = ? AND (
                        (c.name IN ('INDOOR', 'OUTDOOR') AND p.unit_type = 'M2')
                        OR (c.name IN ('LASER', 'LASER A3') AND p.unit_type IN ('PCS', '~')))
                    FOR UPDATE");
                $stmt->bind_param('ii', $product_id, $store_id);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$product) throw new InvalidArgumentException('Pilih bahan INDOOR, OUTDOOR, atau LASER dari toko yang sama.');
                $requiresSize = ProductFinishingPrice::requiresDimensions($product);
                $panjang = $requiresSize ? $this->decimalInput('panjang', 'Panjang', 4, 999999.9999) : 0;
                $lebar = $requiresSize ? $this->decimalInput('lebar', 'Lebar', 4, 999999.9999) : 0;
                $category_id = (int)$product['category_id'];
                $stmt = $this->koneksi->prepare('SELECT finishing_id, name FROM finishings
                    WHERE store_id = ? AND category_id = ? FOR UPDATE');
                $stmt->bind_param('ii', $store_id, $category_id);
                $stmt->execute();
                $finishings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                $finishings = array_values(array_filter($finishings, function ($finishing) use ($packageType) {
                    return $this->normalizeFinishingName($finishing['name']) === $packageType;
                }));
                if (count($finishings) > 1) throw new InvalidArgumentException('Ada finishing ' . $packageType . ' ganda pada kategori ini. Rapikan dahulu.');
                if ($finishings) {
                    $finishing_id = (int)$finishings[0]['finishing_id'];
                } else {
                    $stmt = $this->koneksi->prepare("INSERT INTO finishings
                        (store_id, category_id, name, unit_type, price, reasonable_price, failed_price, stock, created_at, updated_at)
                        VALUES (?, ?, ?, '~', 0, 0, 0, 0, NOW(), NOW())");
                    $stmt->bind_param('iis', $store_id, $category_id, $packageType);
                    $stmt->execute();
                    $finishing_id = $this->koneksi->insert_id;
                    $stmt->close();
                }
                $stmt = $this->koneksi->prepare('SELECT id FROM product_package
                    WHERE store_id = ? AND product_id = ? AND finishing_id = ? AND panjang = ? AND lebar = ?');
                $stmt->bind_param('iiidd', $store_id, $product_id, $finishing_id, $panjang, $lebar);
                $stmt->execute();
                $duplicate = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($duplicate) throw new InvalidArgumentException('Kombinasi bahan, finishing, dan ukuran sudah dipetakan.');
                $stmt = $this->koneksi->prepare('INSERT INTO product_package
                    (store_id, product_id, finishing_id, panjang, lebar, price)
                    VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('iiiddd', $store_id, $product_id, $finishing_id, $panjang, $lebar, $price);
                $stmt->execute();
                $stmt->close();
                $message = 'Paket tersimpan dan langsung dapat digunakan.';
            } elseif ($command === 'update_price') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $this->koneksi->prepare('SELECT id FROM product_package WHERE id = ? AND store_id = ? FOR UPDATE');
                $stmt->bind_param('ii', $id, $store_id);
                $stmt->execute();
                $rule = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$rule) throw new InvalidArgumentException('Pemetaan tidak ditemukan.');
                $price = $this->decimalInput('price', 'Harga paket');
                $message = 'Harga paket diperbarui. Harga item pada nota lama tetap tersimpan.';
                $stmt = $this->koneksi->prepare('UPDATE product_package SET price = ?, updated_at = NOW() WHERE id = ? AND store_id = ?');
                $stmt->bind_param('dii', $price, $id, $store_id);
                $stmt->execute();
                $stmt->close();
            } else {
                throw new InvalidArgumentException('Aksi tidak valid.');
            }
            $this->koneksi->commit();
            $transaction = false;
            $saved = true;
            $_SESSION['swal_success'] = $message;
        } catch (Throwable $e) {
            if ($transaction) $this->koneksi->rollback();
            error_log('PackagePrice: ' . $e->getMessage());
            $_SESSION['swal_error'] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Gagal menyimpan pemetaan paket.';
        } finally {
            mysqli_report($reportMode);
        }
        if ($saved) {
            set_error_handler(function ($severity, $message) { throw new RuntimeException($message); });
            try {
                updateStoreCache($store_id, 'product_finishing_prices');
                updateStoreCache($store_id, 'finishings');
            } catch (Throwable $e) {
                error_log('PackagePrice cache: ' . $e->getMessage());
                $_SESSION['swal_error'] = 'Pemetaan tersimpan, tetapi cache gagal diperbarui. Periksa izin folder temp/dataset lalu simpan kembali.';
                unset($_SESSION['swal_success']);
            } finally {
                restore_error_handler();
            }
        }
        header('Location: /package-prices?' . http_build_query(['store_id' => $store_id]));
        exit;
    }
}
