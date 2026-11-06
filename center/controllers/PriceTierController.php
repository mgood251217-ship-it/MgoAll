<?php
require_once __DIR__ . '/../../admin/models/ProductPriceTier.php';
require_once __DIR__ . '/../functions/helpers.php';
require_once __DIR__ . '/../functions/cacheHelper.php';

class PriceTierController {
    private $db;
    private $tiers;
    public function __construct($db) { $this->db = $db; $this->tiers = new ProductPriceTier($db); }

    private function stores() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['admin_logged_in'])) throw new InvalidArgumentException('Silakan login kembali.');
        $access = startEnk('dek', $_SESSION['admin_logged_in']['access'] ?? '');
        if ($access === 'ALL') return $this->db->query('SELECT store_id, name FROM stores ORDER BY name')->fetch_all(MYSQLI_ASSOC);
        if (!$access) return [];
        $stmt = $this->db->prepare('SELECT store_id, name FROM stores WHERE administrator = ? ORDER BY name');
        $stmt->bind_param('s', $access); $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); return $rows;
    }

    private function checkStore($store) {
        if (!$store || !in_array($store, array_column($this->stores(), 'store_id'))) throw new InvalidArgumentException('Anda tidak memiliki akses ke toko ini.');
    }

    public function getData() {
        $stores = $this->stores();
        $store = (int)($_GET['store_id'] ?? ($stores[0]['store_id'] ?? 0));
        if ($store) $this->checkStore($store);
        $selected = (int)($_GET['product_id'] ?? 0);
        $category = (int)($_GET['category_id'] ?? 0);
        $variantId = (int)($_GET['product_variant_id'] ?? 0); $variants = []; $variant = null; $variantMissing = false;
        $variantReady = $this->tiers->hasVariantTiers();
        $ready = $this->tiers->isAvailable(); $categories = $products = $groups = $rules = []; $product = null;
        if ($store) {
            $stmt = $this->db->prepare('SELECT category_id, name FROM categories WHERE store_id = ? ORDER BY name, category_id');
            $stmt->bind_param('i', $store); $stmt->execute();
            $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
            $stmt = $this->db->prepare('SELECT p.*, c.name AS category FROM products p JOIN categories c ON c.category_id = p.category_id AND c.store_id = p.store_id WHERE p.store_id = ? ORDER BY c.name, p.name, p.product_id');
            $stmt->bind_param('i', $store); $stmt->execute();
            $allProducts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            foreach ($allProducts as $p) if ((int)$p['product_id'] === $selected) $product = $p;
            if (!array_key_exists('category_id', $_GET) && $product) $category = (int)$product['category_id'];
            if (!in_array($category, array_column($categories, 'category_id'))) $category = 0;
            $products = array_values(array_filter($allProducts, function ($p) use ($category) { return (int)$p['category_id'] === $category; }));
            if (!$product || (int)$product['category_id'] !== $category) { $selected = 0; $product = null; }
            if ($ready) {
                $variants = (new ProductVariant($this->db))->getByProduct($store, $selected);
                if ($variants && (!isset($_GET['product_variant_id']) || $_GET['product_variant_id'] === '')) $variantId = (int)$variants[0]['id'];
                foreach ($variants as $v) if ((int)$v['id'] === $variantId) $variant = $v;
                $variantMissing = $variantId > 0 && !$variant;
                $rules = $this->tiers->rules($store, $selected, $variantId);
                $stmt = $this->db->prepare("SELECT r.*, 0 AS product_variant_id, p.name AS product_name, p.unit_type, p.category_id, c.name AS category
                    FROM product_price_tiers r LEFT JOIN products p ON p.product_id = r.product_id AND p.store_id = r.store_id
                    LEFT JOIN categories c ON c.category_id = p.category_id AND c.store_id = p.store_id
                    WHERE r.store_id = ? AND (? = 0 OR p.category_id = ?) ORDER BY c.name, p.name, r.product_id, r.quantity_above");
                $stmt->bind_param('iii', $store, $category, $category); $stmt->execute();
                foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $groups[$r['product_id'] . ':0'][] = $r;
                $stmt->close();
                if ($variantReady) {
                    $stmt = $this->db->prepare('SELECT r.*, p.name AS product_name, p.unit_type, p.category_id, c.name AS category, v.options_json
                        FROM product_variant_price_tiers r LEFT JOIN products p ON p.product_id = r.product_id AND p.store_id = r.store_id
                        LEFT JOIN categories c ON c.category_id = p.category_id AND c.store_id = p.store_id
                        LEFT JOIN product_variants v ON v.id = r.product_variant_id AND v.product_id = r.product_id AND v.store_id = r.store_id
                        WHERE r.store_id = ? AND (? = 0 OR p.category_id = ?) ORDER BY c.name, p.name, r.product_id, r.product_variant_id, r.quantity_above');
                    $stmt->bind_param('iii', $store, $category, $category); $stmt->execute();
                    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
                        $r['variant_label'] = $r['options_json'] ? implode(' / ', array_values(json_decode($r['options_json'], true, 512, JSON_THROW_ON_ERROR))) : 'Varian #' . $r['product_variant_id'] . ' tidak tersedia';
                        $groups[$r['product_id'] . ':' . $r['product_variant_id']][] = $r;
                    }
                    $stmt->close();
                }
            }
        }
        return compact('stores', 'store', 'category', 'categories', 'selected', 'ready', 'products', 'product', 'rules', 'groups', 'variantReady', 'variantId', 'variants', 'variant', 'variantMissing');
    }

    public function save() {
        $store = (int)($_POST['store_id'] ?? 0); $productId = (int)($_POST['product_id'] ?? 0);
        $category = (int)($_POST['category_id'] ?? 0);
        $variantId = $_POST['product_variant_id'] ?? '0';
        $transaction = false; $saved = false;
        $driver = new mysqli_driver(); $report = $driver->report_mode;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $this->checkStore($store);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['price_tier_csrf'])
                || !is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['price_tier_csrf'], $_POST['csrf_token'])) throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
            if (!$this->tiers->isAvailable()) throw new InvalidArgumentException('Jalankan SQL product_price_tiers.sql terlebih dahulu.');
            if ($variantId === '') $variantId = '0';
            if (!is_scalar($variantId) || !preg_match('/^\d+$/D', (string)$variantId) || (float)$variantId > 2147483647) throw new InvalidArgumentException('Varian tidak valid.');
            $variantId = (int)$variantId;
            if ($variantId && !$this->tiers->hasVariantTiers()) throw new InvalidArgumentException('Jalankan SQL product_variant_price_tiers.sql terlebih dahulu.');
            if ($productId < 1) throw new InvalidArgumentException('Pilih produk terlebih dahulu.');
            $command = $_POST['command'] ?? '';
            if (!in_array($command, ['save', 'delete'], true)) throw new InvalidArgumentException('Aksi tidak valid.');
            $this->db->begin_transaction(); $transaction = true;
            $stmt = $this->db->prepare('SELECT store_id FROM stores WHERE store_id = ? FOR UPDATE');
            $stmt->bind_param('i', $store); $stmt->execute(); $stmt->close();
            $stmt = $this->db->prepare('SELECT p.*, c.name AS category FROM products p JOIN categories c ON c.category_id = p.category_id AND c.store_id = p.store_id WHERE p.product_id = ? AND p.store_id = ? FOR UPDATE');
            $stmt->bind_param('ii', $productId, $store); $stmt->execute(); $product = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$product && $command === 'save') throw new InvalidArgumentException('Produk tidak ditemukan pada toko ini.');
            if ($product) $category = (int)$product['category_id'];
            $rows = [];
            if ($command === 'save') {
                if ($variantId) {
                    $stmt = $this->db->prepare('SELECT id FROM product_variants WHERE id = ? AND product_id = ? AND store_id = ? FOR UPDATE');
                    $stmt->bind_param('iii', $variantId, $productId, $store); $stmt->execute(); $variant = $stmt->get_result()->fetch_assoc(); $stmt->close();
                    if (!$variant) throw new InvalidArgumentException('Pilih varian pada produk dan toko yang sama.');
                }
                $measure = $product ? ProductPriceTier::measure($product) : null;
                if (!$measure) throw new InvalidArgumentException('Pilih produk satuan PCS, ~, M2, atau CM2 yang tersedia di toko ini dengan perhitungan jumlah atau luas.');
                $category = (int)$product['category_id'];
                $quantities = $_POST['quantity_above'] ?? []; $prices = $_POST['price'] ?? [];
                if (!is_array($quantities) || !is_array($prices) || count($quantities) !== count($prices) || count($prices) > 500) throw new InvalidArgumentException('Daftar tingkatan tidak valid.');
                $quantities = array_values($quantities); $prices = array_values($prices);
                foreach ($quantities as $i => $value) {
                    $price = $prices[$i];
                    if (!is_scalar($value) || !preg_match('/^\d+(?:\.\d{1,4})?$/D', (string)$value) || (float)$value > 999999999999.9999
                        || ($measure === 'pcs' && floor((float)$value) != (float)$value)) throw new InvalidArgumentException('Batas PCS/~ harus bilangan bulat; batas luas boleh sampai 4 angka desimal.');
                    if (!is_scalar($price) || !preg_match('/^\d+(?:\.\d{1,2})?$/D', (string)$price) || (float)$price <= 0 || (float)$price > 9999999999999.99) throw new InvalidArgumentException('Harga setiap tingkat harus lebih dari 0.');
                    $key = number_format((float)$value, 4, '.', '');
                    if (isset($rows[$key])) throw new InvalidArgumentException('Batas jumlah yang sama tidak boleh ditulis dua kali.');
                    $rows[$key] = (float)$price;
                }
            }
            $table = $variantId ? 'product_variant_price_tiers' : 'product_price_tiers';
            $whereVariant = $variantId ? ' AND product_variant_id = ?' : '';
            $stmt = $this->db->prepare("DELETE FROM $table WHERE store_id = ? AND product_id = ?$whereVariant");
            if ($variantId) $stmt->bind_param('iii', $store, $productId, $variantId); else $stmt->bind_param('ii', $store, $productId);
            $stmt->execute(); $stmt->close();
            $sql = $variantId ? 'INSERT INTO product_variant_price_tiers (store_id, product_id, product_variant_id, quantity_above, price) VALUES (?, ?, ?, ?, ?)' : 'INSERT INTO product_price_tiers (store_id, product_id, quantity_above, price) VALUES (?, ?, ?, ?)';
            $stmt = $this->db->prepare($sql);
            foreach ($rows as $above => $price) {
                if ($variantId) $stmt->bind_param('iiisd', $store, $productId, $variantId, $above, $price); else $stmt->bind_param('iisd', $store, $productId, $above, $price);
                $stmt->execute();
            }
            $stmt->close(); $this->db->commit(); $transaction = false; $saved = true;
            $_SESSION['swal_success'] = $rows ? 'Harga bertingkat tersimpan. Tingkatan otomatis dipakai saat jumlah melewati batas.' : 'Harga bertingkat dihapus. Item nota yang sudah tersimpan tetap memakai aturan sebelumnya.';
        } catch (Throwable $e) {
            if ($transaction) $this->db->rollback();
            $_SESSION['swal_error'] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Gagal menyimpan harga bertingkat.';
            error_log('Price tier: ' . $e->getMessage());
        } finally { mysqli_report($report); }
        if ($saved) try { updateStoreCache($store, 'product_price_tiers'); } catch (Throwable $e) { error_log('Tier settings cache: ' . $e->getMessage()); }
        header('Location: /price-tiers?' . http_build_query(['store_id' => $store, 'category_id' => $category, 'product_id' => $productId, 'product_variant_id' => is_scalar($variantId) ? (int)$variantId : 0])); exit;
    }
}
