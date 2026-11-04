<?php
require_once __DIR__ . '/../../admin/models/ProductPriceTier.php';
require_once __DIR__ . '/../functions/helpers.php';
require_once __DIR__ . '/../functions/cacheHelper.php';

class PromotionController {
    private $db;
    private $promotions;
    public function __construct($db) { $this->db = $db; $this->promotions = new ProductPromotion($db); }

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
        $category = (int)($_GET['category_id'] ?? 0); $selected = (int)($_GET['product_id'] ?? 0);
        $ready = $this->promotions->isAvailable() && (new ProductPriceTier($this->db))->isAvailable();
        $categories = $products = $rules = []; $product = $rule = null;
        if ($store) {
            $stmt = $this->db->prepare('SELECT category_id, name FROM categories WHERE store_id = ? ORDER BY name, category_id');
            $stmt->bind_param('i', $store); $stmt->execute();
            $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
            $stmt = $this->db->prepare('SELECT p.*, c.name AS category FROM products p JOIN categories c ON c.category_id = p.category_id AND c.store_id = p.store_id WHERE p.store_id = ? ORDER BY p.name, p.product_id');
            $stmt->bind_param('i', $store); $stmt->execute();
            $all = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
            foreach ($all as $p) if ((int)$p['product_id'] === $selected) $product = $p;
            if (!array_key_exists('category_id', $_GET) && $product) $category = (int)$product['category_id'];
            if (!in_array($category, array_column($categories, 'category_id'))) $category = 0;
            $products = array_values(array_filter($all, function ($p) use ($category) { return (int)$p['category_id'] === $category; }));
            if (!$product || (int)$product['category_id'] !== $category) { $selected = 0; $product = null; }
            if ($ready) {
                $rule = $this->promotions->rule($store, $selected);
                $stmt = $this->db->prepare('SELECT r.*, p.name AS product_name, p.category_id, p.unit_type, c.name AS category
                    FROM product_promotions r LEFT JOIN products p ON p.product_id = r.product_id AND p.store_id = r.store_id
                    LEFT JOIN categories c ON c.category_id = p.category_id AND c.store_id = p.store_id
                    WHERE r.store_id = ? AND (? = 0 OR p.category_id = ?) ORDER BY c.name, p.name, r.product_id');
                $stmt->bind_param('iii', $store, $category, $category); $stmt->execute();
                $rules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
            }
        }
        return compact('stores', 'store', 'category', 'selected', 'categories', 'products', 'product', 'rule', 'rules', 'ready');
    }

    public function save() {
        $store = (int)($_POST['store_id'] ?? 0); $productId = (int)($_POST['product_id'] ?? 0); $category = (int)($_POST['category_id'] ?? 0);
        $transaction = $saved = false;
        $driver = new mysqli_driver(); $report = $driver->report_mode;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $this->checkStore($store);
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_SESSION['promotion_csrf'])
                || !is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['promotion_csrf'], $_POST['csrf_token'])) throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
            if (!$this->promotions->isAvailable() || !(new ProductPriceTier($this->db))->isAvailable()) throw new InvalidArgumentException('Jalankan SQL promo dan pastikan fitur harga bertingkat sudah terpasang.');
            $command = $_POST['command'] ?? '';
            if ($productId < 1 || !in_array($command, ['save', 'delete'], true)) throw new InvalidArgumentException('Pilih produk dan aksi yang valid.');
            $this->db->begin_transaction(); $transaction = true;
            $stmt = $this->db->prepare('SELECT store_id FROM stores WHERE store_id = ? FOR UPDATE');
            $stmt->bind_param('i', $store); $stmt->execute(); $stmt->close();
            if ($command === 'save') {
                $stmt = $this->db->prepare('SELECT p.*, c.name AS category FROM products p JOIN categories c ON c.category_id = p.category_id AND c.store_id = p.store_id WHERE p.product_id = ? AND p.store_id = ? FOR UPDATE');
                $stmt->bind_param('ii', $productId, $store); $stmt->execute(); $product = $stmt->get_result()->fetch_assoc(); $stmt->close();
                if (!$product || !ProductPromotion::eligible($product)) throw new InvalidArgumentException('Pilih produk PCS atau ~ dengan perhitungan per pcs.');
                $category = (int)$product['category_id'];
                foreach (['buy_quantity', 'free_quantity'] as $field) {
                    $number = $_POST[$field] ?? '';
                    if (!is_scalar($number) || !preg_match('/^[1-9][0-9]{0,6}$/D', (string)$number) || (int)$number > 1000000) throw new InvalidArgumentException('Jumlah beli dan gratis harus bilangan bulat 1 sampai 1.000.000.');
                }
                $buy = (int)$_POST['buy_quantity']; $free = (int)$_POST['free_quantity'];
                $stmt = $this->db->prepare('INSERT INTO product_promotions (store_id, product_id, buy_quantity, free_quantity) VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE buy_quantity = VALUES(buy_quantity), free_quantity = VALUES(free_quantity)');
                $stmt->bind_param('iiii', $store, $productId, $buy, $free); $stmt->execute(); $stmt->close();
            } else {
                $stmt = $this->db->prepare('DELETE FROM product_promotions WHERE store_id = ? AND product_id = ?');
                $stmt->bind_param('ii', $store, $productId); $stmt->execute(); $stmt->close();
            }
            $this->db->commit(); $transaction = false; $saved = true;
            $_SESSION['swal_success'] = $command === 'save' ? 'Promo tersimpan dan langsung berlaku untuk nota baru.' : 'Promo dihapus. Aturan pada nota yang sudah tersimpan tetap dipertahankan.';
        } catch (Throwable $e) {
            if ($transaction) $this->db->rollback();
            $_SESSION['swal_error'] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Gagal menyimpan promo.';
            error_log('Product promotion: ' . $e->getMessage());
        } finally { mysqli_report($report); }
        if ($saved) try { updateStoreCache($store, 'product_promotions'); } catch (Throwable $e) { error_log('Promotion cache: ' . $e->getMessage()); }
        header('Location: /promotions?' . http_build_query(['store_id' => $store, 'category_id' => $category, 'product_id' => $productId])); exit;
    }
}
