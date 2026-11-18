<?php
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/ProductFinishingPrice.php';
require_once __DIR__ . '/../models/ProductLaserConversion.php';
require_once __DIR__ . '/ProductVariantController.php';
require_once __DIR__ . '/../models/ProductBundle.php';
require_once __DIR__ . '/ProductPriceTierController.php';
require_once __DIR__ . '/../models/ProductPromotion.php';
require_once __DIR__ . '/../functions/helpers.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../functions/cacheHelpers.php';

class ProductController {
    private $productModel;
    private $db;
    private $authMiddleware;
    private $packagePrices;
    private $laserConversions;
    private $variants;

    public function __construct($koneksi) {
        $this->db = $koneksi;
        $this->productModel = new Product($koneksi);
        $this->packagePrices = new ProductFinishingPrice($koneksi);
        $this->laserConversions = new ProductLaserConversion($koneksi);
        $this->variants = new ProductVariantController($koneksi);
        $this->authMiddleware = new AuthMiddleware($koneksi);
    }

    public function index() {
        global $store_id;
        return $this->productModel->getProductByStoreId($store_id);
    }

    public function getProductByCategory() {
        $category_id = $_GET['category_id'] ?? '';
        $products = $this->productModel->getProductByCategoryId($category_id);
        send_json_response(true, 'Products retrieved successfully.', $products);
    }

    public function getFinishingByCategory() {
        $category_id = $_GET['category_id'] ?? '';
        $finishings = $this->productModel->getFinishingByCategoryId($category_id);
        send_json_response(true, 'Products retrieved successfully.', $finishings);
    }

    public function getFinishing() {
        global $store_id;
        $finishings = $this->productModel->getFinishingByStoreId($store_id);
        send_json_response(true, 'Finishings retrieved successfully.', $finishings);
    }

    public function getProductFinishingPrices() {
        global $store_id;
        $category_id = max(0, (int)($_GET['category_id'] ?? 0));
        $rows = $this->packagePrices->getByStore($store_id, $category_id, false);
        // Kompatibilitas MgoApp lama: semua paket langsung aktif, tanpa kolom active di tabel.
        foreach ($rows as &$row) {
            $row['active'] = 1;
        }
        unset($row);
        send_json_response(true, 'Harga paket', $rows);
    }

    public function getProductLaserConversions() {
        global $store_id;
        $rows = $this->laserConversions->getByStore($store_id);
        $category_id = (int)($_GET['category_id'] ?? 0);
        if ($category_id) {
            $rows = array_values(array_filter($rows, function ($row) use ($category_id) {
                return (int)$row['category_id'] === $category_id;
            }));
        }
        send_json_response(true, 'Varian print laser', $rows);
    }

    public function getCategory() {
        global $store_id;
        $categories = $this->productModel->getCategoryByStoreId($store_id);
        send_json_response(true, 'Categories retrieved successfully.', $categories);
    }

    public function getProductBundles() {
        global $store_id;
        $rows = (new ProductBundle($this->db))->getCatalog($store_id);
        send_json_response(true, 'Paket gabungan produk', $rows);
    }

    public function getProductVariants() {
        global $store_id;
        $productId = (int)($_GET['product_id'] ?? 0);
        $variants = $this->variants->getByProduct($store_id, $productId);
        $tiersByVariant = [];
        foreach ((new ProductPriceTierController($this->db))->rulesForVariants($store_id, $productId) as $rule) {
            $tiersByVariant[$rule['product_variant_id']][] = $rule;
        }
        foreach ($variants as &$variant) {
            $variant['price_tiers'] = $tiersByVariant[$variant['id']] ?? [];
        }
        unset($variant);
        send_json_response(true, 'Varian produk', $variants);
    }

    public function getProductByPagination() {
        global $store_id;

        $page = max(1, (int)($_GET['page'] ?? 1));
        $search = $_GET['search'] ?? '';
        $limit = max(1, (int)($_GET['limit'] ?? 25));
        $categoryId = max(0, (int)($_GET['category_id'] ?? 0));

        $data = $this->productModel->getProductByPagination(
            $store_id,
            $page,
            $search,
            $limit,
            $categoryId
        );

        $data = $this->addProductDetails($data, $store_id);

        $total = $this->productModel->countProducts($store_id, $search, $categoryId);
        $totalPages = ceil($total / $limit);

        return [
            "data" => $data,
            "total_pages" => $totalPages,
            "total" => $total
        ];
    }

    public function createProduct() {
        if ($this->authMiddleware->isAdminOrManager() == false) {
            return [];
        }
        header('Content-Type: application/json');
        $data = $this->requestData();

        $this->validateProductData($data);

        if ($this->productModel->createProduct($data)) {
            updateStoreCache($data->store_id, 'products');
            send_json_response(true, 'Produk berhasil ditambahkan.');
        } else {
            send_json_response(false, 'Gagal menambahkan produk.');
        }
        exit;
    }

    public function createFinishing() {
        if ($this->authMiddleware->isAdminOrManager() == false) {
            return [];
        }
        header('Content-Type: application/json');
        $data = $this->requestData();

        $this->validateProductData($data);

        if ($this->productModel->createFinishing($data)) {
            updateStoreCache($data->store_id, 'finishings');
            send_json_response(true, 'Finishing berhasil ditambahkan.');
        } else {
            send_json_response(false, 'Gagal menambahkan finishing.');
        }
        exit;
    }

    public function updateProduct() {
        if ($this->authMiddleware->isAdminOrManager() == false) {
            return [];
        }
        header('Content-Type: application/json');
        $data = $this->requestData();

        $this->validateProductData($data);

        if ($this->productModel->updateProduct($data)) {
            updateStoreCache($data->store_id, 'products');
            send_json_response(true, 'Produk berhasil diperbarui.');
        } else {
            send_json_response(false, 'Gagal memperbarui produk.');
        }
        exit;
    }

    public function updateFinishing() {
        if ($this->authMiddleware->isAdminOrManager() == false) {
            return [];
        }
        header('Content-Type: application/json');
        $data = $this->requestData();

        $this->validateProductData($data);

        if ($this->productModel->updateFinishing($data)) {
            updateStoreCache($data->store_id, 'finishings');
            send_json_response(true, 'Finishing berhasil diperbarui.');
        } else {
            send_json_response(false, 'Gagal memperbarui finishing.');
        }
        exit;
    }

    public function deleteProduct() {
        global $store_id;

        if ($this->authMiddleware->isAdminOrManager() == false) {
            return [];
        }
        header('Content-Type: application/json');
        $data = new stdClass();
        $data->id = $_POST['product_id'] ?? 0;

        if (empty($data->id)) {
            send_json_response(false, 'ID produk tidak ditemukan.');
            exit;
        }

        if ($this->productModel->deleteProductById($data)) {
            updateStoreCache($store_id, 'products');
            send_json_response(true, 'Produk berhasil dihapus.');
        } else {
            send_json_response(false, 'Gagal menghapus produk.');
        }
        exit;
    }

    public function deleteFinishing() {
        global $store_id;

        if ($this->authMiddleware->isAdminOrManager() == false) {
            return [];
        }
        header('Content-Type: application/json');
        $data = new stdClass();
        $data->id = $_POST['finishing_id'] ?? 0;

        if (empty($data->id)) {
            send_json_response(false, 'ID finishing tidak ditemukan.');
            exit;
        }

        if ($this->productModel->deleteFinishingById($data)) {
            updateStoreCache($store_id, 'finishings');
            send_json_response(true, 'Finishing berhasil dihapus.');
        } else {
            send_json_response(false, 'Gagal menghapus finishing.');
        }
        exit;
    }

    public function updateStock() {
        global $store_id;
        header('Content-Type: application/json');
        $id       = $_POST['product_id'] ?? 0;
        $quantity = $_POST['quantity'] ?? 0;

        if (empty($id)) {
            send_json_response(false, 'ID produk tidak ditemukan.');
            exit;
        }

        if ($this->productModel->updateStock($id, $quantity)) {
            updateStoreCache($store_id, 'products');
            send_json_response(true, 'Stok berhasil diperbarui.');
        } else {
            send_json_response(false, 'Gagal memperbarui stok.');
        }
        exit;
    }

    public function updateStockFinishing() {
        global $store_id;
        header('Content-Type: application/json');
        $id       = $_POST['finishing_id'] ?? 0;
        $quantity = $_POST['quantity'] ?? 0;

        if (empty($id)) {
            send_json_response(false, 'ID finishing tidak ditemukan.');
            exit;
        }

        if ($this->productModel->updateStockFinishing($id, $quantity)) {
            updateStoreCache($store_id, 'finishings');
            send_json_response(true, 'Stok berhasil diperbarui.');
        } else {
            send_json_response(false, 'Gagal memperbarui stok.');
        }
        exit;
    }

    private function requestData() {
        global $store_id;

        $data = new stdClass();
        $data->id               = $_POST['product_id'] ?? 0;
        $data->finishing_id     = $_POST['finishing_id'] ?? 0;
        $data->store_id         = $store_id ?? 0;
        $data->category_id      = $_POST['category_id'] ?? 0;
        $data->name             = $_POST['name'] ?? '';
        $data->price            = $_POST['price'] ?? '';
        $data->unit             = $_POST['unit_type'] ?? '';
        $data->reasonable_price = $_POST['reasonable_price'] ?? '';
        $data->failed_price     = $_POST['failed_price'] ?? '';

        return $data;
    }

    private function validateProductData($data) {
        if (empty($data->name) || empty($data->price) || empty($data->unit)) {
            send_json_response(false, 'Validasi gagal: Nama, harga, dan satuan harus diisi.');
            exit;
        }
    }

    private function getVariantCounts($products, $store_id) {
        if (!$products || !$this->variants->isAvailable()) {
            return [];
        }

        $ids = array_map('intval', array_column($products, 'product_id'));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("
            SELECT product_id, COUNT(*) AS variant_count
            FROM product_variants
            WHERE store_id=? AND product_id IN ($placeholders)
            GROUP BY product_id
        ");
        $params = array_merge([(int)$store_id], $ids);
        $stmt->bind_param(str_repeat('i', count($params)), ...$params);
        $stmt->execute();
        $counts = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'variant_count', 'product_id');
        $stmt->close();

        return $counts;
    }

    private function addProductDetails($data, $store_id) {
        $variantCounts = $this->getVariantCounts($data, $store_id);
        foreach ($data as &$product) {
            $product['variant_count'] = (int)($variantCounts[$product['product_id']] ?? 0);
        }
        unset($product);

        $tiersByProduct = [];
        foreach ((new ProductPriceTierController($this->db))->rulesForProducts($store_id, array_column($data, 'product_id')) as $rule) {
            $tiersByProduct[$rule['product_id']][] = $rule;
        }
        foreach ($data as &$product) {
            $product['price_tiers'] = $tiersByProduct[$product['product_id']] ?? [];
        }
        unset($product);

        $promotions = (new ProductPromotion($this->db))->rulesForProducts($store_id, array_column($data, 'product_id'));
        $promotionsByProduct = array_column($promotions, null, 'product_id');
        foreach ($data as &$product) {
            $product['promotion'] = ProductPromotion::eligible($product) && !ProductPriceTierController::isWeightMaterial($product)
                ? ($promotionsByProduct[$product['product_id']] ?? null) : null;
        }
        unset($product);

        $productIds = array_column($data, 'product_id');
        $packageCounts = $this->packagePrices->countsForProducts($store_id, $productIds);
        $laserCounts = $this->laserConversions->countsForProducts($store_id, $productIds);
        $bundleCounts = (new ProductBundle($this->db))->countsForProducts($store_id, $productIds);
        foreach ($data as &$product) {
            $id = $product['product_id'];
            $product['package_count'] = (int)($packageCounts[$id] ?? 0);
            $product['laser_conversion_count'] = (int)($laserCounts[$id] ?? 0);
            $product['has_bundle'] = !empty($bundleCounts[$id]);
        }
        unset($product);

        return $data;
    }
}
