<?php
require_once BASE_PATH . '/models/Failure.php';
require_once BASE_PATH . '/functions/helpers.php';
require_once BASE_PATH . '/models/Product.php'; 
require_once BASE_PATH . '/controllers/OrderController.php';

class FailureController{
    private $koneksi;
    private $productModel;
    private $orderController;
    private $failureModel;

    public function __construct($koneksi){
        $this->koneksi = $koneksi;
        $this->failureModel = new Failure($koneksi);
        $this->orderController = new OrderController($koneksi);
        $this->productModel = new Product($koneksi);
    }

    public function index(){
        global $store_id;
        $start_date = $_GET['start_date'] ?? date('Y-m-d');
        $end_date = $_GET['end_date'] ?? date('Y-m-d');

        $sql = "SELECT f.*, m.name AS nama_mesin, m.type AS machine_type, u.name AS operator_name
                FROM failure f 
                LEFT JOIN machine m ON f.machine_id = m.machine_id
                LEFT JOIN users u ON f.user_id = u.user_id
                WHERE f.store_id = ? AND f.date BETWEEN ? AND ?
                ORDER BY f.date DESC";

        $stmt2 = $this->koneksi->prepare($sql);
        $stmt2->bind_param("iss", $store_id, $start_date, $end_date);
        $stmt2->execute();  
        $items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt2->close();

        $product_ids = [];
        $finishing_ids = [];

        foreach ($items as $item) {
            if (!empty($item['product_id'])) {
                $product_ids[$item['product_id']] = true;
            }
            if (!empty($item['finishing']) && $item['finishing'] !== '-') {
                $fids = explode(',', $item['finishing']);
                foreach ($fids as $fid) {
                    $fid = trim($fid);
                    if (ctype_digit($fid)) {
                        $finishing_ids[$fid] = true;
                    }
                }
            }
        }

        $products_map = [];
        if (!empty($product_ids)) {
            $ids_keys = array_keys($product_ids);
            $placeholders = implode(',', array_fill(0, count($ids_keys), '?'));
            $stmt_prod = $this->koneksi->prepare("SELECT p.product_id, p.name, unit_type, c.name AS category, p.failed_price FROM products p LEFT JOIN categories c ON p.category_id = c.category_id WHERE p.product_id IN ($placeholders)");
            $stmt_prod->bind_param(str_repeat('i', count($ids_keys)), ...$ids_keys);
            $stmt_prod->execute();
            $res_prod = $stmt_prod->get_result();
            while ($row = $res_prod->fetch_assoc()) {
                $products_map[$row['product_id']] = $row;
            }
            $stmt_prod->close();
        }

        $finishings_map = [];
        if (!empty($finishing_ids)) {
            $fids_keys = array_keys($finishing_ids);
            $placeholders = implode(',', array_fill(0, count($fids_keys), '?'));
            $stmt_finish = $this->koneksi->prepare("SELECT finishing_id, name, failed_price FROM finishings WHERE finishing_id IN ($placeholders)");
            $stmt_finish->bind_param(str_repeat('i', count($fids_keys)), ...$fids_keys);
            $stmt_finish->execute();
            $res_finish = $stmt_finish->get_result();
            while ($row = $res_finish->fetch_assoc()) {
                $finishings_map[$row['finishing_id']] = $row;
            }
            $stmt_finish->close();
        }

        foreach ($items as &$item) {
            $finishing_names = [];
            $finishing_price = 0;
            
            if (!empty($item['finishing']) && $item['finishing'] !== '-') {
                $fids = explode(',', $item['finishing']);
                foreach ($fids as $fid) {
                    $fid = trim($fid);
                    if (ctype_digit($fid) && isset($finishings_map[$fid])) {
                        $finishing_names[] = $finishings_map[$fid]['name'];
                        $finishing_price += (int)($finishings_map[$fid]['failed_price'] ?? 0);
                    }
                }
            }
            
            $item['finishing_names_str'] = empty($finishing_names) ? '-' : implode(', ', $finishing_names);
            
            $hargaSatuan = 0;
            $pid = $item['product_id'];
            if ($pid != 0 && isset($products_map[$pid])) {
                $prod = $products_map[$pid];
                $unit_type = $prod['unit_type'];
                $type = $prod['category'];
                $reasonable_price = (float)$prod['failed_price'];
                $product_name = $prod['name'];
                $size = $item['size'];
                
                $reasonable_price += $finishing_price;
                
                if ($unit_type == 'M2') {
                    if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $size, $match)) {
                        $p = floatval($match[1]);
                        $l = floatval($match[2]);
                        if ($type == 'DTF') {
                            $hargaSatuan = $p * $reasonable_price;
                        } else {
                            $hargaSatuan = $p * $l * $reasonable_price;
                        }
                    }
                } elseif ($unit_type == 'PCS') {
                    $hargaSatuan = $reasonable_price;
                    if ($type == 'JERSEY') {
                        $harga_jersey = 0;
                        if ($size === '5XL') {
                            $harga_jersey += 40000;
                        } elseif ($size === '4XL') {
                            $harga_jersey += 30000;
                        } elseif ($size === '3XL') {
                            $harga_jersey += 20000;
                        } elseif ($size === '2XL') {
                            $harga_jersey += 10000;
                        }
                        $hargaSatuan += $harga_jersey;
                    } elseif ($type == 'SUBLIM' && str_contains($product_name, 'BAHAN')) {
                        $kata = explode(" ", $size);
                        if (isset($kata[0]) && is_numeric($kata[0])) {
                            $hargaSatuan *= (float)$kata[0];
                        }
                    }
                }
            }
            
            $item['formatted_date'] = date('d M Y', strtotime($item['date']));
            $item['total_loss'] = $hargaSatuan * (int)$item['quantity'];
            
            $details = [];
            if (!empty($item['failure_design'])) $details[] = "Desain: " . $item['failure_design'];
            if (!empty($item['failure_print'])) $details[] = "Cetak: " . $item['failure_print'];
            if (!empty($item['failure_finishing'])) $details[] = "Finishing: " . $item['failure_finishing'];
            if (!empty($item['failure_cause'])) $details[] = "Penyebab: " . $item['failure_cause'];
            if (!empty($item['failure_cause_other'])) $details[] = "Lainnya: " . $item['failure_cause_other'];
            $item['detail_gagal'] = empty($details) ? '-' : implode('<br>', $details);
        }
        unset($item);

        return $items;
    }

    public function create(){
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        global $store_id;

        header('Content-Type: application/json');

        $request = $_POST;
        if (empty($request)) {
            $rawInput = trim(file_get_contents('php://input'));
            if ($rawInput !== '') {
                $decodedInput = json_decode($rawInput, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedInput)) {
                    $request = $decodedInput;
                }
            }
        }

        function requestValue(array $request, string $key, $default = null) {
            return array_key_exists($key, $request) ? $request[$key] : $default;
        }

        function requestArray(array $request, string $key): array {
            $value = requestValue($request, $key, []);
            if (is_array($value)) {
                return $value;
            }
            if ($value === '' || $value === null) {
                return [];
            }
            return explode(',', (string) $value);
        }

        $user_id_fail  = (int) requestValue($request, 'user_id', 0);
        $product_id    = (int) requestValue($request, 'product_id', 0);
        $judul         = trim((string) requestValue($request, 'judul', ''));
        $size          = trim((string) requestValue($request, 'size', '-'));
        $quantity      = (int) requestValue($request, 'quantity', 1);
        $finishing     = trim((string) requestValue($request, 'finishing', '-'));
        $panjang       = (float) requestValue($request, 'panjang', 0);
        $lebar         = (float) requestValue($request, 'lebar', 0);
        $kiloan        = (float) requestValue($request, 'kiloan', 0);
        $nomorator     = trim((string) requestValue($request, 'nomorator', ''));
        $customer_name = trim((string) requestValue($request, 'customer_name', ''));
        $machine_id    = (int) requestValue($request, 'machine_id', 0);
        $loss_burden   = trim((string) requestValue($request, 'loss_burden', ''));
        $info          = trim((string) requestValue($request, 'info', ''));
        $dateRaw       = trim((string) requestValue($request, 'date', ''));
        $date          = $dateRaw !== '' ? date('Y-m-d', strtotime($dateRaw)) : date('Y-m-d');

        $failure_design = implode(',', requestArray($request, 'failure_design'));
        $failure_print = implode(',', requestArray($request, 'failure_print'));
        $failure_finishing = implode(',', requestArray($request, 'failure_finishing'));
        $failure_cause = implode(',', requestArray($request, 'failure_cause'));
        $failure_cause_other = trim((string) requestValue($request, 'failure_cause_other', ''));

        if ($panjang > 0 && $lebar > 0) {
            $size = "{$panjang}x{$lebar}";
        }

        $product = $this->productModel->getProductById($product_id);

        $unit_type = $product['unit_type'];

        $stok_butuh = 0;
        if ($panjang > 0 && $lebar > 0) {
            $stok_butuh = $panjang * $lebar * $quantity;
        } elseif ($kiloan > 0) {
            $stok_butuh = $kiloan * $quantity;
        } else {
            $stok_butuh = $quantity;
        }

        $fData = $this->orderController->finishingData($finishing, $panjang, $lebar);
        $finishing_ids = $fData['ids'] ?? [];
        $finishing_str = count($finishing_ids) ? implode(',', $finishing_ids) : '-';

        $data = [
            'user_id_fail' => $user_id_fail,
            'store_id' => $store_id,
            'nomorator' => $nomorator,
            'customer_name' => $customer_name,
            'machine_id' => $machine_id,
            'product_id' => $product_id,
            'judul' => $judul,
            'size' => $size,
            'quantity' => $quantity,
            'finishing_str' => $finishing_str,
            'date' => $date,
            'failure_design' => $failure_design,
            'failure_print' => $failure_print,
            'failure_finishing' => $failure_finishing,
            'failure_cause' => $failure_cause,
            'failure_cause_other' => $failure_cause_other,
            'loss_burden' => $loss_burden,
            'info' => $info
        ];

        if ($this->failureModel->createFailure($data)) {
            if ($unit_type !== '~') {
                $this->productModel->updateStock($product_id, $stok_butuh);
            }
            echo json_encode(['success' => true, 'message' => 'Item berhasil ditambahkan.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal Insert Data: ']);
        }
    }

    public function delete(){
        $failure_id   = isset($_POST['failure_id']) ? $_POST['failure_id'] : '';
        if($this->failureModel->deleteFailure($failure_id)){
            send_json_response(true, "Berhasil menghapus kegagalan", $failure_id);
        }else {
            send_json_response(false, "Gagal menghapus kegagalan");
        }
    }

    public function updateInfo(){
        global $store_id;
        $failure_id = (int)($_POST['failure_id'] ?? 0);
        $info = trim($_POST['info'] ?? '');

        $data = (object)[
            'failure_id' => $failure_id,
            'info' => $info,
            'store_id' => $store_id
        ];

        if ($this->failureModel->updateFailureInfo($data)) {
            send_json_response(true, "Kegagalan berhasil diperbaharui");
        }else {
            send_json_response(false, "Gagal memperbaharui kegagalan");
        }
    }
}




?>