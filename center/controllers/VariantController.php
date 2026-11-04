<?php
require_once __DIR__ . '/../../admin/models/ProductPriceTier.php';
require_once __DIR__ . '/../functions/helpers.php';
require_once __DIR__ . '/../functions/cacheHelper.php';

class VariantController {
    private $db;
    private $variants;
    public function __construct($db) { $this->db = $db; $this->variants = new ProductVariant($db); }

    private function stores() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['admin_logged_in'])) throw new InvalidArgumentException('Silakan login kembali.');
        $access = startEnk('dek', $_SESSION['admin_logged_in']['access'] ?? '');
        if ($access === 'ALL') return $this->db->query('SELECT store_id, name FROM stores ORDER BY name')->fetch_all(MYSQLI_ASSOC);
        if (!$access) return [];
        $stmt = $this->db->prepare('SELECT store_id, name FROM stores WHERE administrator = ? ORDER BY name');
        $stmt->bind_param('s', $access); $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); return $rows;
    }
    private function checkStore($store) {
        if (!$store || !in_array($store, array_column($this->stores(), 'store_id'))) throw new InvalidArgumentException('Anda tidak memiliki akses ke toko ini.');
    }
    public function getRules() {
        try {
            $store = (int)($_GET['store_id'] ?? 0); $this->checkStore($store);
            send_json_response(true, 'Varian produk', $this->variants->getByProduct($store, (int)($_GET['product_id'] ?? 0)));
        } catch (InvalidArgumentException $e) { http_response_code(403); send_json_response(false, $e->getMessage()); }
    }
    public function getData() {
        $stores = $this->stores();
        $store = (int)($_GET['store_id'] ?? ($stores[0]['store_id'] ?? 0));
        if ($store) $this->checkStore($store);
        $category = (int)($_GET['category_id'] ?? 0); $selected = (int)($_GET['product_id'] ?? 0);
        $ready = $this->variants->isAvailable() && (new ProductPriceTier($this->db))->isAvailable();
        $categories = $products = $variants = []; $product = $editing = null;
        if ($store) {
            $stmt = $this->db->prepare('SELECT category_id, name FROM categories WHERE store_id = ? ORDER BY name, category_id');
            $stmt->bind_param('i', $store); $stmt->execute(); $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
            $stmt = $this->db->prepare('SELECT p.*, c.name AS category FROM products p JOIN categories c ON c.category_id = p.category_id AND c.store_id = p.store_id WHERE p.store_id = ? ORDER BY p.name, p.product_id');
            $stmt->bind_param('i', $store); $stmt->execute(); $all = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
            foreach ($all as $p) if ((int)$p['product_id'] === $selected) $product = $p;
            if (!array_key_exists('category_id', $_GET) && $product) $category = (int)$product['category_id'];
            if (!in_array($category, array_column($categories, 'category_id'))) $category = 0;
            $products = array_values(array_filter($all, function ($p) use ($category) { return (int)$p['category_id'] === $category; }));
            if (!$product || (int)$product['category_id'] !== $category) { $selected = 0; $product = null; }
            if ($ready) {
                $variants = $this->variants->getByProduct($store, $selected);
                foreach ($variants as $v) if ((int)$v['id'] === (int)($_GET['variant_id'] ?? 0)) $editing = $v;
            }
        }
        return compact('stores', 'store', 'category', 'selected', 'categories', 'products', 'product', 'variants', 'editing', 'ready');
    }
    public function save() {
        $store = (int)($_POST['store_id'] ?? 0); $product = (int)($_POST['product_id'] ?? 0); $category = (int)($_POST['category_id'] ?? 0); $id = (int)($_POST['variant_id'] ?? 0);
        $transaction = $saved = false; $driver = new mysqli_driver(); $report = $driver->report_mode;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $this->checkStore($store);
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_SESSION['variant_csrf']) || !is_string($_POST['csrf_token'] ?? null)
                || !hash_equals($_SESSION['variant_csrf'], $_POST['csrf_token'])) throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
            if (!$this->variants->isAvailable() || !(new ProductPriceTier($this->db))->isAvailable()) throw new InvalidArgumentException('Jalankan SQL varian dan pastikan fitur harga bertingkat sudah terpasang.');
            $command = $_POST['command'] ?? '';
            if ($product < 1 || !in_array($command, ['save', 'delete'], true)) throw new InvalidArgumentException('Pilih produk dan aksi yang valid.');
            $this->db->begin_transaction(); $transaction = true;
            $stmt = $this->db->prepare('SELECT store_id FROM stores WHERE store_id = ? FOR UPDATE'); $stmt->bind_param('i', $store); $stmt->execute(); $stmt->close();
            $stmt = $this->db->prepare('SELECT id FROM product_variants WHERE id = ? AND store_id = ? AND product_id = ? FOR UPDATE');
            $stmt->bind_param('iii', $id, $store, $product); $stmt->execute(); $existing = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (($id || $command === 'delete') && !$existing) throw new InvalidArgumentException('Varian tidak ditemukan pada produk dan toko ini.');
            if ($command === 'delete') {
                $stmt = $this->db->prepare('DELETE FROM product_variant_components WHERE variant_id = ?'); $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
                $stmt = $this->db->prepare('DELETE FROM product_variants WHERE id = ? AND store_id = ? AND product_id = ?'); $stmt->bind_param('iii', $id, $store, $product); $stmt->execute(); $stmt->close();
            } else {
                $stmt = $this->db->prepare('SELECT p.*, c.name AS category FROM products p JOIN categories c ON c.category_id = p.category_id AND c.store_id = p.store_id WHERE p.product_id = ? AND p.store_id = ? FOR UPDATE');
                $stmt->bind_param('ii', $product, $store); $stmt->execute(); $p = $stmt->get_result()->fetch_assoc(); $stmt->close();
                if (!$p) throw new InvalidArgumentException('Produk tidak ditemukan pada toko ini.');
                $category = (int)$p['category_id'];
                foreach ((new ProductFinishingPrice($this->db))->getByStore($store, 0, false) as $r) if ((int)$r['product_id'] === $product) throw new InvalidArgumentException('Produk ini memakai harga paket. Kelola melalui Harga Paket.');
                foreach ((new ProductLaserConversion($this->db))->getByStore($store) as $r) if ((int)$r['product_id'] === $product) throw new InvalidArgumentException('Produk ini memakai konversi print laser. Kelola melalui Konversi Print Laser.');
                $options = ProductVariant::options($_POST['option_name'] ?? [], $_POST['option_value'] ?? []);
                $current = $this->variants->getByProduct($store, $product);
                if ($current && array_keys($options) !== array_keys($current[0]['options'])) throw new InvalidArgumentException('Nama dan urutan pilihan harus sama dengan varian produk yang sudah ada.');
                $price = $_POST['price'] ?? '';
                if (!is_scalar($price) || !preg_match('/^\d+(?:\.\d{1,2})?$/D', (string)$price) || (float)$price <= 0 || (float)$price > 9999999999999.99) throw new InvalidArgumentException('Harga varian harus lebih dari 0, maksimal 2 angka desimal.');
                $names = $_POST['component_name'] ?? []; $quantities = $_POST['component_quantity'] ?? [];
                if (!is_array($names) || !is_array($quantities) || count($names) !== count($quantities) || count($names) > 100) throw new InvalidArgumentException('Daftar komponen tidak valid.');
                $components = [];
                foreach (array_values($names) as $i => $name) {
                    $quantity = array_values($quantities)[$i];
                    if (!is_scalar($name) || !is_scalar($quantity)) throw new InvalidArgumentException('Komponen tidak valid.');
                    $name = preg_replace('/\s+/u', ' ', trim((string)$name));
                    if ($name === '' && (string)$quantity === '') continue;
                    if (!preg_match('/^.{1,150}$/us', $name) || !preg_match('/^\d+(?:\.\d{1,4})?$/D', (string)$quantity) || (float)$quantity <= 0 || (float)$quantity > 1000000) throw new InvalidArgumentException('Isi nama komponen dan jumlah per satuan jual yang lebih dari 0.');
                    $components[] = ['name' => $name, 'quantity' => (float)$quantity];
                }
                $json = json_encode($options, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $key = json_encode(array_values($options), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); $price = (float)$price;
                if ($id) {
                    $stmt = $this->db->prepare('UPDATE product_variants SET options_json = ?, combination_key = SHA2(LOWER(?), 256), price = ? WHERE id = ? AND store_id = ? AND product_id = ?');
                    $stmt->bind_param('ssdiii', $json, $key, $price, $id, $store, $product);
                } else {
                    $stmt = $this->db->prepare('INSERT INTO product_variants (store_id, product_id, options_json, combination_key, price) VALUES (?, ?, ?, SHA2(LOWER(?), 256), ?)');
                    $stmt->bind_param('iissd', $store, $product, $json, $key, $price);
                }
                $stmt->execute(); if (!$id) $id = (int)$this->db->insert_id; $stmt->close();
                $stmt = $this->db->prepare('DELETE FROM product_variant_components WHERE variant_id = ?'); $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
                $stmt = $this->db->prepare('INSERT INTO product_variant_components (variant_id, name, quantity) VALUES (?, ?, ?)');
                foreach ($components as $c) { $stmt->bind_param('isd', $id, $c['name'], $c['quantity']); $stmt->execute(); } $stmt->close();
            }
            $this->db->commit(); $transaction = false; $saved = true;
            $_SESSION['swal_success'] = $command === 'delete' ? 'Varian dihapus. Data varian pada nota lama tetap tersimpan.' : 'Varian tersimpan. Perubahan berlaku untuk item baru.';
        } catch (Throwable $e) {
            if ($transaction) $this->db->rollback();
            $_SESSION['swal_error'] = $e instanceof InvalidArgumentException ? $e->getMessage() : ($e instanceof mysqli_sql_exception && $e->getCode() === 1062 ? 'Kombinasi varian atau nama komponen yang sama sudah ada.' : 'Gagal menyimpan varian.');
            error_log('Product variant: ' . $e->getMessage());
        } finally { mysqli_report($report); }
        if ($saved) try { updateStoreCache($store, 'product_variants'); updateStoreCache($store, 'products'); } catch (Throwable $e) { error_log('Variant cache: ' . $e->getMessage()); }
        header('Location: /product-variants?' . http_build_query(['store_id' => $store, 'category_id' => $category, 'product_id' => $product])); exit;
    }
}
