<?php
require_once BASE_PATH . '/models/Order.php';
require_once BASE_PATH . '/models/User.php';
require_once BASE_PATH . '/models/Project.php';
require_once BASE_PATH . '/models/Product.php';
require_once BASE_PATH . '/models/Stock.php';
require_once BASE_PATH . '/models/Activity.php';
require_once BASE_PATH . '/models/Payment.php';
require_once BASE_PATH . '/functions/helpers.php';

class OrderController {
    private $koneksi;
    private $orderModel;
    private $userModel;
    private $projectModel;
    private $productModel;
    private $stockModel;
    private $paymentModel;
    private $activityModel;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
        $this->orderModel = new Order($koneksi);
        $this->userModel = new User($koneksi);
        $this->projectModel = new Project($koneksi);
        $this->productModel = new Product($koneksi);
        $this->stockModel = new Stock($koneksi);
        $this->paymentModel = new Payment($koneksi);
        $this->activityModel = new Activity($koneksi);
    }

    public function getOrderIds(){
        global $store_id;

        $start_date_full = isset($_GET['start_date']) ? $_GET['start_date'] . " 00:00:00" : date('Y-m-d 00:00:00');
        $end_date_full   = isset($_GET['end_date']) ? $_GET['end_date'] . " 23:59:59" : date('Y-m-d 23:59:59');

        return $this->orderModel->getOrderIdsByIntervalDate($store_id, $start_date_full, $end_date_full);
    }

    public function getOutdoor(){
        global $store_id;
        $order_ids = $this->getOrderIds();

        $product_data = [];
        $max_rows = 0;
        $total_all_m2_outdoor = 0;
        $total_m2_product = 0;

        if (!empty($order_ids)) {
            $stmt = $this->koneksi->prepare("SELECT product_id, name, type FROM products WHERE type IN ('OUTDOOR', 'PAKET INDOOR OUTDOOR') AND store_id = ?");
            $stmt->bind_param("i", $store_id);
            $stmt->execute();
            $all_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $outdoor_products = [];
            $paket_products = [];
            
            foreach ($all_products as $p) {
                if ($p['type'] === 'OUTDOOR') {
                    $outdoor_products[] = $p;
                } else {
                    $paket_products[] = $p;
                }
            }

            $productId_to_name = [];
            $product_data_assoc = [];

            foreach ($outdoor_products as $outdoor) {
                $productId_to_name[$outdoor['product_id']] = $outdoor['name'];
                $product_data_assoc[$outdoor['name']] = [
                    'name' => $outdoor['name'],
                    'rows' => []
                ];
            }

            foreach ($paket_products as $paket) {
                foreach ($outdoor_products as $outdoor) {
                    if (stripos($paket['name'], $outdoor['name']) !== false) {
                        $productId_to_name[$paket['product_id']] = $outdoor['name'];
                        break;
                    }
                }
            }

            $valid_product_ids = array_keys($productId_to_name);

            if (!empty($valid_product_ids)) {
                $in_orders = implode(',', array_fill(0, count($order_ids), '?'));
                $in_products = implode(',', array_fill(0, count($valid_product_ids), '?'));

                $types = str_repeat('i', count($order_ids)) . str_repeat('i', count($valid_product_ids));
                $params = array_merge($order_ids, $valid_product_ids);

                $query = $this->koneksi->prepare("SELECT product_id, size, quantity FROM order_items WHERE order_id IN ($in_orders) AND product_id IN ($in_products)");
                $query->bind_param($types, ...$params);
                $query->execute();
                $res = $query->get_result();

                while ($row = $res->fetch_assoc()) {
                    $pid = $row['product_id'];
                    $display_name = $productId_to_name[$pid];

                    $size = $row['size'];
                    $qty = (int)$row['quantity'];
                    
                    if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $size, $match)) {
                        $p = floatval($match[1]);
                        $l = floatval($match[2]);
                        $m2 = $p * $l * $qty;

                        $product_data_assoc[$display_name]['rows'][] = ['p' => $p, 'l' => $l, 'qty' => $qty, 'm2' => $m2];
                        $total_all_m2_outdoor += $m2;
                        
                    }
                }
                $query->close();
            }

            $product_data = array_values($product_data_assoc);
            foreach ($product_data as $product) {
                $count_rows = count($product['rows']);
                if ($count_rows > $max_rows) {
                    $max_rows = $count_rows;
                }
            }
        }

        return [
            'productData' => $product_data,
            'maxRows' => $max_rows,
            'totalAllM2Outdoor' => $total_all_m2_outdoor,
            'totalM2PerProduct' => $total_m2_product
        ];

    }

}