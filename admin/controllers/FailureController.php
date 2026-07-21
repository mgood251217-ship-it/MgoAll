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