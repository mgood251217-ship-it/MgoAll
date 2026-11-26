<?php
require_once __DIR__ . '/../functions/productStock.php';
require_once __DIR__ . '/../models/Failure.php';
require_once __DIR__ . '/FailureReasonController.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../controllers/OrderController.php';
require_once __DIR__ . '/../functions/helpers.php';
require_once __DIR__ . '/../functions/cacheHelpers.php';

class FailureController {
    private $koneksi;
    private $productModel;
    private $orderController;
    private $failureModel;
    private $failureReasons;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
        $this->failureModel = new Failure($koneksi);
        $this->failureReasons = new FailureReasonController($koneksi);
        $this->orderController = new OrderController($koneksi);
        $this->productModel = new Product($koneksi);
    }

    public function index() {
        global $store_id;
        $start_date = $_GET['start_date'] ?? date('Y-m-d');
        $end_date = $_GET['end_date'] ?? date('Y-m-d');

        $stmt = $this->koneksi->prepare("
            SELECT f.*, m.name AS nama_mesin, m.type AS machine_type, u.name AS operator_name
            FROM failure f
            LEFT JOIN machine m ON f.machine_id = m.machine_id
            LEFT JOIN users u ON f.user_id = u.user_id
            WHERE f.store_id = ? AND f.date BETWEEN ? AND ?
            ORDER BY f.date DESC
        ");
        $stmt->bind_param('iss', $store_id, $start_date, $end_date);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $items = $this->failureReasons->attach($items);
        $productIds = array_values(array_unique(array_filter(array_column($items, 'product_id'))));
        $products = $this->getProducts($productIds, $store_id);
        $finishingIds = [];
        foreach ($items as $item) {
            $finishingIds = array_merge($finishingIds, ProductLaserConversionController::finishingIds($item['finishing'] ?? ''));
        }
        $finishings = $this->getFinishings(array_values(array_unique($finishingIds)), $store_id);

        foreach ($items as &$item) {
            $finishingNames = [];
            $finishingPrice = 0;
            foreach (ProductLaserConversionController::finishingIds($item['finishing'] ?? '') as $id) {
                if (isset($finishings[$id])) {
                    $finishingNames[] = $finishings[$id]['name'];
                    $finishingPrice += (float)$finishings[$id]['failed_price'];
                }
            }
            $product = $products[$item['product_id']] ?? null;
            $item['finishing_names_str'] = $finishingNames ? implode(', ', $finishingNames) : '-';
            $item['formatted_date'] = date('d M Y', strtotime($item['date']));
            $item['total_loss'] = $product
                ? $this->calculateLoss($product, $finishingPrice, $item['quantity'], $item['size'])->amount
                : 0;
            $item['detail_gagal'] = $this->failureDetails($item);
        }
        unset($item);

        return $items;
    }

    public function create() {
        global $store_id;
        $transaction = $saved = false;

        try {
            $data = $this->requestData($this->readRequest(), $store_id);
            if ($data['product_id'] <= 0 || $data['quantity'] <= 0) {
                throw new InvalidArgumentException('Pilih produk dan isi jumlah lebih dari 0.');
            }

            if (!$this->koneksi->begin_transaction()) {
                throw new RuntimeException('Gagal memulai transaksi kegagalan.');
            }
            $transaction = true;
            $product = $this->lockProduct($data['product_id'], $store_id);
            if (!$product) {
                throw new InvalidArgumentException('Produk tidak ditemukan di toko ini.');
            }
            $item = $this->prepareFailureItem($product, $data, $store_id);
            $data['size'] = $item['size'];
            $data['finishing_str'] = $item['finishing_str'];

            $data['failure_reason_ids'] = json_encode($this->failureReasons->resolve($data));
            $failureId = $this->failureModel->createFailure($data);
            if (!$failureId) {
                throw new RuntimeException('Gagal menyimpan kegagalan.');
            }
            if (tracksStock($product)) {
                if ((float)$product['stock'] < $item['stock']) {
                    throw new InvalidArgumentException('Stok produk tidak mencukupi.');
                }
                if (!$this->productModel->reduceStock($item['stock'], $data['product_id'])) {
                    throw new RuntimeException('Gagal mengurangi stok produk.');
                }
            }
            foreach ($item['finishing_stocks'] as $finishing) {
                if (!$this->productModel->reduceFinishingStock($finishing['quantity'], $finishing['id'])) {
                    throw new RuntimeException('Gagal mengurangi stok finishing.');
                }
            }
            if (!$this->koneksi->commit()) {
                throw new RuntimeException('Gagal menyimpan transaksi kegagalan.');
            }
            $transaction = false;
            $saved = true;
            foreach (['failures', 'products', 'finishings'] as $module) {
                updateStoreCache($store_id, $module);
            }
        } catch (Throwable $e) {
            if ($transaction) {
                $this->koneksi->rollback();
            }
            if ($saved) {
                error_log('Failure cache: ' . $e->getMessage());
                send_json_response(true, 'Kegagalan tersimpan. Cache belum berhasil diperbarui, muat ulang data.');
            }
            http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
            if (!($e instanceof InvalidArgumentException)) {
                error_log('Create failure: ' . $e->getMessage());
            }
            send_json_response(false, $e instanceof InvalidArgumentException ? $e->getMessage() : 'Gagal menyimpan kegagalan.');
        }

        send_json_response(true, 'Item berhasil ditambahkan.');
    }

    public function delete() {
        global $store_id;
        $failure_id = $_POST['failure_id'] ?? '';
        if ($this->failureModel->deleteFailure($failure_id, $store_id)) {
            updateStoreCache($store_id, 'failures');
            send_json_response(true, 'Berhasil menghapus kegagalan', $failure_id);
        } else {
            send_json_response(false, 'Gagal menghapus kegagalan');
        }
    }

    public function updateInfo() {
        global $store_id;
        $data = (object)[
            'failure_id' => (int)($_POST['failure_id'] ?? 0),
            'info' => trim($_POST['info'] ?? ''),
            'store_id' => $store_id
        ];
        if ($this->failureModel->updateFailureInfo($data)) {
            updateStoreCache($store_id, 'failures');
            send_json_response(true, 'Kegagalan berhasil diperbaharui');
        } else {
            send_json_response(false, 'Gagal memperbaharui kegagalan');
        }
    }

    private function readRequest() {
        if ($_POST) {
            return $_POST;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Data kegagalan tidak valid.');
        }

        return $data;
    }

    private function requestValue(array $request, string $key, $default = null) {
        $value = array_key_exists($key, $request) ? $request[$key] : $default;
        if ($value !== null && !is_scalar($value)) {
            throw new InvalidArgumentException('Data ' . $key . ' tidak valid.');
        }

        return $value;
    }

    private function requestArray(array $request, string $key): array {
        $value = $request[$key] ?? [];
        if (!is_array($value)) {
            $value = $value === '' ? [] : explode(',', (string)$value);
        }
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                throw new InvalidArgumentException('Data ' . $key . ' tidak valid.');
            }
        }

        return $value;
    }

    private function requestData($request, $store_id) {
        $date = trim((string)$this->requestValue($request, 'date', ''));
        if ($date !== '' && strtotime($date) === false) {
            throw new InvalidArgumentException('Tanggal kegagalan tidak valid.');
        }
        $data = [
            'store_id' => $store_id,
            'user_id_fail' => (int)$this->requestValue($request, 'user_id', 0),
            'product_id' => (int)$this->requestValue($request, 'product_id', 0),
            'machine_id' => (int)$this->requestValue($request, 'machine_id', 0),
            'quantity' => (int)$this->requestValue($request, 'quantity', 1),
            'date' => $date === '' ? date('Y-m-d') : date('Y-m-d', strtotime($date)),
        ];
        foreach (['judul', 'nomorator', 'customer_name', 'loss_burden', 'info', 'failure_cause_other'] as $field) {
            $data[$field] = trim((string)$this->requestValue($request, $field, ''));
        }
        $data['size'] = trim((string)$this->requestValue($request, 'size', '-'));
        $data['finishing_str'] = trim((string)$this->requestValue($request, 'finishing', '-'));
        foreach (['panjang', 'lebar', 'kiloan', 'waktu'] as $field) {
            $data[$field] = (float)$this->requestValue($request, $field, 0);
            if (!is_finite($data[$field]) || $data[$field] < 0) {
                throw new InvalidArgumentException('Ukuran, berat, dan waktu harus berupa angka positif.');
            }
        }
        foreach (['failure_design', 'failure_print', 'failure_finishing', 'failure_cause'] as $field) {
            $data[$field] = implode(',', $this->requestArray($request, $field));
        }

        $ids = [];
        foreach ($this->requestArray($request, 'failure_reason_ids') as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]]);
            if ($id === false) throw new InvalidArgumentException('ID alasan kegagalan tidak valid.');
            $ids[] = $id;
        }
        $data['failure_reason_ids'] = array_values(array_unique($ids));
        return $data;
    }

    private function getProducts($ids, $store_id) {
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->koneksi->prepare("SELECT p.*, c.name AS category
            FROM products p LEFT JOIN categories c ON p.category_id = c.category_id AND c.store_id = p.store_id
            WHERE p.store_id = ? AND p.product_id IN ($placeholders)");
        $params = array_merge([$store_id], $ids);
        $stmt->bind_param(str_repeat('i', count($params)), ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return array_column($rows, null, 'product_id');
    }

    private function getFinishings($ids, $store_id, $lock = false) {
        if (!$ids) {
            return [];
        }
        sort($ids, SORT_NUMERIC);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT * FROM finishings
            WHERE store_id = ? AND finishing_id IN ($placeholders) ORDER BY finishing_id";
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->koneksi->prepare($sql);
        $params = array_merge([$store_id], $ids);
        $stmt->bind_param(str_repeat('i', count($params)), ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return array_column($rows, null, 'finishing_id');
    }

    private function lockProduct($product_id, $store_id) {
        $stmt = $this->koneksi->prepare("SELECT p.*, c.name AS category
            FROM products p LEFT JOIN categories c ON p.category_id = c.category_id AND c.store_id = p.store_id
            WHERE p.product_id = ? AND p.store_id = ? FOR UPDATE");
        $stmt->bind_param('ii', $product_id, $store_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $product;
    }

    private function prepareFailureItem($product, $data, $store_id) {
        $size = $data['size'];
        [$panjang, $lebar, $waktu, $kiloan] = $this->parseSize($product, $size);
        $panjang = $data['panjang'] ?: $panjang;
        $lebar = $data['lebar'] ?: $lebar;
        $kiloan = $data['kiloan'] ?: $kiloan;
        $waktu = $data['waktu'] ?: $waktu;
        if ($panjang > 0 && $lebar > 0) {
            $size = "{$panjang}x{$lebar}";
        } elseif ($kiloan > 0) {
            $size = "{$kiloan} KG";
        }
        $isAnekaCetak = strtoupper(trim($product['category'] ?? '')) === 'ANEKA CETAK' && !ProductPriceTierController::usesTime($product);
        $package = null;
        if ($isAnekaCetak) {
            $panjang = $lebar = $kiloan = $waktu = 0;
            $size = ProductLaserConversionController::normalizeSize($data['size']) ?: '-';
        } else {
            $package = (new ProductFinishingPriceController($this->koneksi))->resolve(
                $store_id, $data['product_id'], $data['finishing_str'], $panjang, $lebar
            );
            if ($package) {
                $kiloan = $waktu = 0;
                if (empty($package['requires_size'])) {
                    $panjang = $lebar = 0;
                    $size = '-';
                }
            }
        }
        $ids = ProductLaserConversionController::finishingIds($data['finishing_str']);
        $finishings = $this->getFinishings($ids, $store_id, true);
        if (count($finishings) !== count($ids)) {
            throw new InvalidArgumentException('Finishing tidak ditemukan di toko ini.');
        }
        $finishingPrice = 0;
        $finishingStocks = [];
        $finishingUsage = $panjang > 0 && $lebar > 0 && !$package ? $panjang * $lebar : 1;
        foreach ($finishings as $id => $finishing) {
            $finishingPrice += (float)$finishing['failed_price'];
            if (!tracksStock($finishing)) {
                continue;
            }
            $quantity = $finishingUsage * $data['quantity'];
            if ((float)$finishing['stock'] < $quantity) {
                throw new InvalidArgumentException('Stok finishing tidak mencukupi.');
            }
            $finishingStocks[] = ['id' => $id, 'quantity' => $quantity];
        }
        $pricing = $this->orderController->calculatePricingDetails(
            $product, (float)$product['failed_price'], $finishingPrice,
            $data['quantity'], $panjang, $lebar, $waktu, $kiloan, $size
        );

        return [
            'size' => $pricing->size,
            'finishing_str' => $ids ? implode(',', $ids) : '-',
            'stock' => $this->orderController->requiredProductStock(
                $product, $data['quantity'], $panjang, $lebar, $kiloan, (bool)$package
            ),
            'finishing_stocks' => $finishingStocks
        ];
    }

    private function calculateLoss($product, $finishingPrice, $quantity, $size) {
        [$panjang, $lebar, $waktu, $kiloan] = $this->parseSize($product, $size);

        return $this->orderController->calculatePricingDetails(
            $product, (float)$product['failed_price'], $finishingPrice,
            (int)$quantity, $panjang, $lebar, $waktu, $kiloan, $size
        );
    }

    private function parseSize($product, $size) {
        $panjang = $lebar = $waktu = $kiloan = 0;
        if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $size, $matches)) {
            $panjang = (float)$matches[1];
            $lebar = (float)$matches[2];
        }
        if (ProductPriceTierController::isWeightMaterial($product)) {
            $kiloan = ProductPriceTierController::weightFromSize($size);
            if (!$kiloan && preg_match('/^([\d.]+)(?:\s|$)/', $size, $matches)) {
                $kiloan = (float)$matches[1];
            }
        }
        $waktu = ProductPriceTierController::timeFromSize($size);

        return [$panjang, $lebar, $waktu, $kiloan];
    }

    private function failureDetails($item) {
        $labels = [
            'failure_design' => 'Desain',
            'failure_print' => 'Cetak',
            'failure_finishing' => 'Finishing',
            'failure_cause' => 'Penyebab',
            'failure_cause_other' => 'Lainnya'
        ];
        $details = [];
        foreach ($labels as $field => $label) {
            if (!empty($item[$field])) {
                $details[] = $label . ': ' . htmlspecialchars((string)$item[$field], ENT_QUOTES, 'UTF-8');
            }
        }

        return $details ? implode('<br>', $details) : '-';
    }
}
