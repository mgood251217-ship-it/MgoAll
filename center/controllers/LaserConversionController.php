<?php
require_once __DIR__ . '/../../admin/models/ProductLaserConversion.php';
require_once __DIR__ . '/../functions/helpers.php';
require_once __DIR__ . '/../functions/cacheHelper.php';

class LaserConversionController {
    private $koneksi;
    private $conversions;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
        $this->conversions = new ProductLaserConversion($koneksi);
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
        $products = $laserProducts = $rules = $finishings = [];
        $ready = $this->conversions->hasVariants();
        if ($store_id) {
            $stmt = $this->koneksi->prepare("SELECT p.product_id, p.name, p.price, c.name AS category
                FROM products p JOIN categories c ON c.category_id = p.category_id AND c.store_id = p.store_id
                WHERE p.store_id = ? AND p.unit_type IN ('PCS', '~')
                  AND UPPER(TRIM(c.name)) IN ('ANEKA CETAK', 'LASER A3') ORDER BY c.name, p.name, p.product_id");
            $stmt->bind_param('i', $store_id);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $product) {
                if (strtoupper(trim($product['category'])) === 'LASER A3') $laserProducts[] = $product;
                else $products[] = $product;
            }
            $stmt->close();
            $stmt = $this->koneksi->prepare("SELECT f.finishing_id, f.name, f.price FROM finishings f
                JOIN categories c ON c.category_id = f.category_id AND c.store_id = f.store_id
                WHERE f.store_id = ? AND UPPER(TRIM(c.name)) = 'ANEKA CETAK' ORDER BY f.name, f.finishing_id");
            $stmt->bind_param('i', $store_id);
            $stmt->execute();
            $finishings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            $rules = $this->conversions->getByStore($store_id);
        }
        return compact('stores', 'store_id', 'products', 'laserProducts', 'rules', 'finishings', 'ready');
    }

    public function getRules() {
        $store_id = (int)($_GET['store_id'] ?? 0);
        try { $this->checkStore($store_id); }
        catch (InvalidArgumentException $e) { http_response_code(403); send_json_response(false, $e->getMessage()); }
        $rows = $this->conversions->getByStore($store_id);
        $category_id = (int)($_GET['category_id'] ?? 0);
        if ($category_id) $rows = array_values(array_filter($rows, function ($row) use ($category_id) { return (int)$row['category_id'] === $category_id; }));
        send_json_response(true, 'Varian print laser', $rows);
    }

    private function quantityInput($field) {
        $value = $_POST[$field] ?? '';
        if (!is_scalar($value) || !preg_match('/^\d+$/D', (string)$value)
            || (float)$value < 1 || (float)$value > 1000000) {
            throw new InvalidArgumentException('Jumlah produk dan print harus bilangan bulat antara 1 sampai 1.000.000.');
        }
        return (int)$value;
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
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['laser_conversion_csrf'])
                || !is_string($_POST['csrf_token'] ?? null)
                || !hash_equals($_SESSION['laser_conversion_csrf'], $_POST['csrf_token'])) {
                throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
            }
            if (!$this->conversions->hasVariants()) {
                throw new InvalidArgumentException('Jalankan SQL product_laser_variants terlebih dahulu.');
            }
            $product_id = (int)($_POST['product_id'] ?? 0);
            $id = (int)($_POST['id'] ?? 0);
            $command = $_POST['command'] ?? '';
            $this->koneksi->begin_transaction();
            $transaction = true;
            $stmt = $this->koneksi->prepare('SELECT store_id FROM stores WHERE store_id = ? FOR UPDATE');
            $stmt->bind_param('i', $store_id);
            $stmt->execute();
            $stmt->close();
            if ($id) {
                $stmt = $this->koneksi->prepare('SELECT product_id FROM product_laser_conversion WHERE id = ? AND store_id = ? FOR UPDATE');
                $stmt->bind_param('ii', $id, $store_id);
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$existing) throw new InvalidArgumentException('Varian tidak ditemukan. Muat ulang halaman.');
                if (!array_key_exists('product_id', $_POST)) $product_id = (int)$existing['product_id'];
            }
            if ($command === 'delete') {
                if (!$id) throw new InvalidArgumentException('Pilih varian yang akan dihapus. Muat ulang halaman.');
                $stmt = $this->koneksi->prepare('DELETE FROM product_laser_conversion WHERE store_id = ? AND id = ?');
                $stmt->bind_param('ii', $store_id, $id);
                $stmt->execute();
                $stmt->close();
                $message = 'Konversi dihapus. Perhitungan bawaan produk berlaku kembali jika tersedia.';
            } elseif ($command === 'save') {
                $laser_product_id = (int)($_POST['laser_product_id'] ?? 0);
                $product_quantity = $this->quantityInput('product_quantity');
                $print_quantity = $this->quantityInput('print_quantity');
                if (!is_string($_POST['size'] ?? '')) throw new InvalidArgumentException('Ukuran tidak valid.');
                $size = ProductLaserConversion::normalizeSize($_POST['size'] ?? '');
                if (!preg_match('/^.{0,64}$/usD', $size)) throw new InvalidArgumentException('Ukuran maksimal 64 karakter.');
                $priceInput = $_POST['price'] ?? '';
                if (!is_scalar($priceInput) || !preg_match('/^\d+(?:\.\d{1,2})?$/D', (string)$priceInput)
                    || (float)$priceInput <= 0 || (float)$priceInput > 9999999999999.99) throw new InvalidArgumentException('Isi harga final lebih dari 0.');
                $price = (float)$priceInput;
                $selectedFinishings = $_POST['finishings'] ?? [];
                if (!is_array($selectedFinishings)) throw new InvalidArgumentException('Pilihan finishing tidak valid.');
                foreach ($selectedFinishings as $fid) {
                    if (!is_scalar($fid) || !ctype_digit((string)$fid) || (int)$fid <= 0) throw new InvalidArgumentException('Pilihan finishing tidak valid.');
                }
                $ids = ProductLaserConversion::finishingIds(implode(',', $selectedFinishings));
                $finishing_ids = implode(',', $ids);
                if (strlen($finishing_ids) > 255) throw new InvalidArgumentException('Terlalu banyak finishing dipilih.');
                $stmt = $this->koneksi->prepare("SELECT p.product_id FROM products p
                    JOIN categories c ON c.category_id = p.category_id AND c.store_id = p.store_id
                    JOIN products lp ON lp.product_id = ? AND lp.store_id = p.store_id
                    JOIN categories lc ON lc.category_id = lp.category_id AND lc.store_id = lp.store_id
                    WHERE p.product_id = ? AND p.store_id = ? AND UPPER(TRIM(c.name)) = 'ANEKA CETAK'
                      AND p.unit_type IN ('PCS', '~') AND UPPER(TRIM(lc.name)) = 'LASER A3'
                      AND lp.unit_type IN ('PCS', '~') FOR UPDATE");
                $stmt->bind_param('iii', $laser_product_id, $product_id, $store_id);
                $stmt->execute();
                $valid = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$valid) throw new InvalidArgumentException('Pilih produk satuan dari ANEKA CETAK dan bahan print LASER A3 dari toko yang sama.');
                if ($ids) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stmt = $this->koneksi->prepare("SELECT f.finishing_id FROM finishings f
                        JOIN products p ON p.product_id = ? AND p.store_id = f.store_id AND p.category_id = f.category_id
                        WHERE f.store_id = ? AND f.finishing_id IN ($placeholders) FOR UPDATE");
                    $params = array_merge([$product_id, $store_id], $ids);
                    $types = str_repeat('i', count($params));
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $found = $stmt->get_result()->num_rows;
                    $stmt->close();
                    if ($found !== count($ids)) throw new InvalidArgumentException('Pilih finishing dari kategori dan toko produk yang sama.');
                }
                $stmt = $this->koneksi->prepare('SELECT id FROM product_laser_conversion WHERE store_id = ? AND product_id = ? AND size = ? AND finishing_ids = ? AND id <> ?');
                $stmt->bind_param('iissi', $store_id, $product_id, $size, $finishing_ids, $id);
                $stmt->execute();
                $duplicate = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($duplicate) throw new InvalidArgumentException('Kombinasi produk, ukuran, dan finishing sudah ada. Edit baris tersebut.');
                if ($id) {
                    $stmt = $this->koneksi->prepare('UPDATE product_laser_conversion SET product_id = ?, laser_product_id = ?, product_quantity = ?, print_quantity = ?, size = ?, finishing_ids = ?, price = ? WHERE id = ? AND store_id = ?');
                    $stmt->bind_param('iiiissdii', $product_id, $laser_product_id, $product_quantity, $print_quantity, $size, $finishing_ids, $price, $id, $store_id);
                } else {
                    $stmt = $this->koneksi->prepare('INSERT INTO product_laser_conversion (store_id, product_id, laser_product_id, product_quantity, print_quantity, size, finishing_ids, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->bind_param('iiiiissd', $store_id, $product_id, $laser_product_id, $product_quantity, $print_quantity, $size, $finishing_ids, $price);
                }
                $stmt->execute();
                $stmt->close();
                $message = 'Konversi tersimpan dan langsung dipakai pada meteran print laser.';
            } else {
                throw new InvalidArgumentException('Aksi tidak valid.');
            }
            $this->koneksi->commit();
            $transaction = false;
            $saved = true;
            $_SESSION['swal_success'] = $message;
        } catch (Throwable $e) {
            if ($transaction) $this->koneksi->rollback();
            error_log('LaserConversion: ' . $e->getMessage());
            $_SESSION['swal_error'] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Gagal menyimpan konversi print laser.';
        } finally {
            mysqli_report($reportMode);
        }
        if ($saved) {
            set_error_handler(function ($severity, $message) { throw new RuntimeException($message); });
            try { updateStoreCache($store_id, 'product_laser_conversion'); }
            catch (Throwable $e) {
                error_log('LaserConversion cache: ' . $e->getMessage());
                unset($_SESSION['swal_success']);
                $_SESSION['swal_error'] = 'Varian tersimpan, tetapi cache gagal diperbarui. Gunakan Update Cache di halaman Produk.';
            } finally { restore_error_handler(); }
        }
        header('Location: /laser-conversions?' . http_build_query(['store_id' => $store_id]));
        exit;
    }
}
