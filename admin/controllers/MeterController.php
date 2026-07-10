<?php
require_once BASE_PATH . '/models/Order.php';
require_once BASE_PATH . '/models/User.php';
require_once BASE_PATH . '/models/Project.php';
require_once BASE_PATH . '/models/Product.php';
require_once BASE_PATH . '/models/Activity.php';
require_once BASE_PATH . '/models/Payment.php';
require_once BASE_PATH . '/functions/helpers.php';

class MeterController {
    private $koneksi;
    private $orderModel;
    private $userModel;
    private $projectModel;
    private $productModel;
    private $paymentModel;
    private $activityModel;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
        $this->orderModel = new Order($koneksi);
        $this->userModel = new User($koneksi);
        $this->projectModel = new Project($koneksi);
        $this->productModel = new Product($koneksi);
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

        $start_date_full = isset($_GET['start_date']) ? $_GET['start_date'] . " 00:00:00" : date('Y-m-d 00:00:00');
        $end_date_full   = isset($_GET['end_date']) ? $_GET['end_date'] . " 23:59:59" : date('Y-m-d 23:59:59');

        $product_data = [];
        $max_rows = 0;
        $total_all_m2_outdoor = 0;
        $total_m2_product = [];

        $stmt = $this->koneksi->prepare("
            SELECT p.product_id, p.name, c.name AS category 
            FROM products p 
            JOIN categories c ON p.category_id = c.category_id 
            WHERE c.name IN ('OUTDOOR', 'PAKET INDOOR OUTDOOR') AND p.store_id = ?
        ");
        $stmt->bind_param("i", $store_id);
        $stmt->execute();
        $all_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $outdoor_products = [];
        $paket_products = [];
        
        foreach ($all_products as $p) {
            if ($p['category'] === 'OUTDOOR') {
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
            $total_m2_product[$outdoor['name']] = 0;
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
            $in_products = implode(',', array_fill(0, count($valid_product_ids), '?'));
            
            $query = $this->koneksi->prepare("
                SELECT oi.product_id, oi.size, oi.quantity 
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                WHERE o.store_id = ? 
                  AND o.date BETWEEN ? AND ?
                  AND oi.product_id IN ($in_products)
            ");
            
            $types = "iss" . str_repeat('i', count($valid_product_ids));
            $params = array_merge([$store_id, $start_date_full, $end_date_full], $valid_product_ids);
            
            $query->bind_param($types, ...$params);
            $query->execute();
            $res = $query->get_result();
            
            $rows = $res->fetch_all(MYSQLI_ASSOC);
            $query->close();

            foreach ($rows as $row) {
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
                    $total_m2_product[$display_name] += $m2;
                }
            }
        }

        $product_data = array_values($product_data_assoc);
        foreach ($product_data as $product) {
            $count_rows = count($product['rows']);
            if ($count_rows > $max_rows) {
                $max_rows = $count_rows;
            }
        }

        return [
            'product_data' => $product_data,
            'max_rows' => $max_rows,
            'total_all_m2_outdoor' => $total_all_m2_outdoor,
            'total_m2_product' => $total_m2_product
        ];
    }

    public function getIndoor() {
        global $store_id;
        
        $start_date_full = isset($_GET['start_date']) ? $_GET['start_date'] . " 00:00:00" : date('Y-m-d 00:00:00');
        $end_date_full   = isset($_GET['end_date']) ? $_GET['end_date'] . " 23:59:59" : date('Y-m-d 23:59:59');

        $max_rows_indoor = 0;
        $total_all_m2_indoor = 0;
        $total_m2_product_indoor = []; 

        $stmt = $this->koneksi->prepare("
            SELECT p.product_id, p.name, c.name AS category, oi_filtered.size, oi_filtered.quantity 
            FROM products p 
            JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN (
                SELECT oi.product_id, oi.size, oi.quantity
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                WHERE o.store_id = ? AND o.date BETWEEN ? AND ?
            ) oi_filtered ON p.product_id = oi_filtered.product_id
            WHERE c.name IN ('INDOOR', 'PAKET INDOOR OUTDOOR') 
              AND p.store_id = ?
        ");
        
        $stmt->bind_param("issi", $store_id, $start_date_full, $end_date_full, $store_id);
        $stmt->execute();
        
        $all_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $indoor_names = [];
        $product_data_assoc = [];
        $paket_rows = [];

        foreach ($all_data as $row) {
            $name = $row['name'];
            $type = $row['category'];
            
            if ($type === 'INDOOR') {
                if (!isset($product_data_assoc[$name])) {
                    $indoor_names[] = $name;
                    $product_data_assoc[$name] = [
                        'name' => $name,
                        'rows' => []
                    ];
                    $total_m2_product_indoor[$name] = 0;
                }
                
                if (!empty($row['size']) && !empty($row['quantity'])) {
                    $size = $row['size'];
                    $qty = (int)$row['quantity'];
                    if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $size, $match)) {
                        $p = floatval($match[1]);
                        $l = floatval($match[2]);
                        $m2 = $p * $l * $qty;
                        $product_data_assoc[$name]['rows'][] = ['p' => $p, 'l' => $l, 'qty' => $qty, 'm2' => $m2];
                        
                        $total_all_m2_indoor += $m2;
                        $total_m2_product_indoor[$name] += $m2;
                    }
                }
            } else {
                if (!empty($row['size']) && !empty($row['quantity'])) {
                    $paket_rows[] = $row;
                }
            }
        }

        foreach ($paket_rows as $row) {
            $mapped_name = null;
            foreach ($indoor_names as $in_name) {
                if (stripos($row['name'], $in_name) !== false) {
                    $mapped_name = $in_name;
                    break;
                }
            }

            if ($mapped_name) {
                $size = $row['size'];
                $qty = (int)$row['quantity'];
                if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $size, $match)) {
                    $p = floatval($match[1]);
                    $l = floatval($match[2]);
                    $m2 = $p * $l * $qty;
                    $product_data_assoc[$mapped_name]['rows'][] = ['p' => $p, 'l' => $l, 'qty' => $qty, 'm2' => $m2];
                    
                    $total_all_m2_indoor += $m2;
                    $total_m2_product_indoor[$mapped_name] += $m2;
                }
            }
        }

        $product_data_indoor = array_values($product_data_assoc);
        
        foreach ($product_data_indoor as $product) {
            $count_rows = count($product['rows']);
            if ($count_rows > $max_rows_indoor) {
                $max_rows_indoor = $count_rows;
            }
        }

        return [
            'product_data' => $product_data_indoor,
            'max_rows' => $max_rows_indoor,
            'total_all_m2' => $total_all_m2_indoor,
            'total_m2_product' => $total_m2_product_indoor
        ];
    }

    public function getAkrilik() {
        global $store_id;

        $start_date_full = isset($_GET['start_date']) ? $_GET['start_date'] . " 00:00:00" : date('Y-m-d 00:00:00');
        $end_date_full   = isset($_GET['end_date']) ? $_GET['end_date'] . " 23:59:59" : date('Y-m-d 23:59:59');

        $product_data = [];
        $max_rows = 0;
        $total_all_m2_akrilik = 0;
        $total_m2_product = [];

        $stmt = $this->koneksi->prepare("
            SELECT p.product_id, p.name 
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            WHERE c.name = 'AKRILIK' AND p.store_id = ?
        ");
        $stmt->bind_param("i", $store_id);
        $stmt->execute();
        $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $productId_to_name = [];
        $product_data_assoc = [];

        foreach ($products as $akrilik) {
            $productId_to_name[$akrilik['product_id']] = $akrilik['name'];
            $product_data_assoc[$akrilik['name']] = [
                'name' => $akrilik['name'],
                'rows' => []
            ];
            $total_m2_product[$akrilik['name']] = 0;
        }

        $valid_product_ids = array_keys($productId_to_name);

        if (!empty($valid_product_ids)) {
            $in_products = implode(',', array_fill(0, count($valid_product_ids), '?'));

            $queryStr = "
                SELECT oi.product_id, oi.size, oi.quantity 
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                WHERE o.store_id = ? 
                  AND o.date BETWEEN ? AND ?
                  AND oi.product_id IN ($in_products)
            ";
            
            $query = $this->koneksi->prepare($queryStr);
            if (!$query) die("Query error: " . $this->koneksi->error);
            
            $types = "iss" . str_repeat('i', count($valid_product_ids));
            $params = array_merge([$store_id, $start_date_full, $end_date_full], $valid_product_ids);
            
            $query->bind_param($types, ...$params);
            $query->execute();
            $res = $query->get_result();
            
            $rows = $res->fetch_all(MYSQLI_ASSOC);
            $query->close();

            foreach ($rows as $row) {
                $pid = $row['product_id'];
                $display_name = $productId_to_name[$pid];

                $size = $row['size'];
                $qty = (int)$row['quantity'];
                
                if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $size, $match)) {
                    $p = floatval($match[1]);
                    $l = floatval($match[2]);
                    $m2 = $p * $l * $qty;

                    $product_data_assoc[$display_name]['rows'][] = ['p' => $p, 'l' => $l, 'qty' => $qty, 'm2' => $m2];
                    
                    $total_all_m2_akrilik += $m2;
                    $total_m2_product[$display_name] += $m2;
                }
            }
        }

        $product_data = array_values($product_data_assoc);
        foreach ($product_data as $product) {
            $count_rows = count($product['rows']);
            if ($count_rows > $max_rows) {
                $max_rows = $count_rows;
            }
        }

        return [
            'product_data' => $product_data,
            'max_rows' => $max_rows,
            'total_all_m2' => $total_all_m2_akrilik,
            'total_m2_product' => $total_m2_product
        ];
    }

    public function getJersey() {
        global $store_id;

        $start_date_full = isset($_GET['start_date']) ? $_GET['start_date'] . " 00:00:00" : date('Y-m-d 00:00:00');
        $end_date_full   = isset($_GET['end_date']) ? $_GET['end_date'] . " 23:59:59" : date('Y-m-d 23:59:59');

        $product_data_jersey = [];
        $total_all_qty_jersey = 0;

        $queryStr = "
            SELECT p.name, COALESCE(SUM(oi_filtered.quantity), 0) AS total_qty
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN (
                SELECT oi.product_id, oi.quantity
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                WHERE o.store_id = ? AND o.date BETWEEN ? AND ?
            ) oi_filtered ON p.product_id = oi_filtered.product_id
            WHERE c.name = 'JERSEY' AND p.store_id = ?
            GROUP BY p.product_id, p.name
        ";

        $stmt = $this->koneksi->prepare($queryStr);
        if (!$stmt) die("Query error: " . $this->koneksi->error);

        $stmt->bind_param("issi", $store_id, $start_date_full, $end_date_full, $store_id);
        $stmt->execute();
        
        $res = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($rows as $row) {
            $qty = (int)$row['total_qty'];
            $product_data_jersey[] = [
                'name' => $row['name'],
                'total_qty' => $qty
            ];
            $total_all_qty_jersey += $qty;
        }

        return [
            'product_data' => $product_data_jersey,
            'total_all_qty' => $total_all_qty_jersey
        ];
    }

    public function getLaser() {
        global $store_id;

        $start_date_full = isset($_GET['start_date']) ? $_GET['start_date'] . " 00:00:00" : date('Y-m-d 00:00:00');
        $end_date_full   = isset($_GET['end_date']) ? $_GET['end_date'] . " 23:59:59" : date('Y-m-d 23:59:59');

        $product_data_laser_a3 = [];
        $total_all_qty_laser = 0;

        $query1 = "
            SELECT p.name, COALESCE(SUM(oi_filtered.quantity), 0) AS total_qty
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN (
                SELECT oi.product_id, oi.quantity
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                WHERE o.store_id = ? AND o.date BETWEEN ? AND ?
            ) oi_filtered ON p.product_id = oi_filtered.product_id
            WHERE c.name = 'LASER A3' AND p.store_id = ?
            GROUP BY p.product_id, p.name
        ";

        $stmt1 = $this->koneksi->prepare($query1);
        if (!$stmt1) die("Query error: " . $this->koneksi->error);
        
        $stmt1->bind_param("issi", $store_id, $start_date_full, $end_date_full, $store_id);
        $stmt1->execute();
        $res1 = $stmt1->get_result();
        $rows1 = $res1->fetch_all(MYSQLI_ASSOC);
        $stmt1->close();

        foreach ($rows1 as $row) {
            $product_data_laser_a3[$row['name']] = (int)$row['total_qty'];
        }

        $query2 = "
            SELECT p.name, SUM(oi.quantity) AS total_qty
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            JOIN products p ON oi.product_id = p.product_id
            JOIN categories c ON p.category_id = c.category_id
            WHERE o.store_id = ? AND o.date BETWEEN ? AND ?
              AND p.store_id = ?
              AND (
                  (c.name = 'KARTU NAMA' AND p.name LIKE '%KN%') OR 
                  (c.name = 'MERCENDISE' AND p.name LIKE '%JAM%')
              )
            GROUP BY p.product_id, p.name
        ";

        $stmt2 = $this->koneksi->prepare($query2);
        if (!$stmt2) die("Query error: " . $this->koneksi->error);

        $stmt2->bind_param("issi", $store_id, $start_date_full, $end_date_full, $store_id);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $rows2 = $res2->fetch_all(MYSQLI_ASSOC);
        $stmt2->close();

        $qty_kn = 0;
        $qty_kn_bb = 0;
        $qty_jam = 0;

        foreach ($rows2 as $row) {
            $name_upper = strtoupper($row['name']);
            $qty = (int)$row['total_qty'];
            
            if (strpos($name_upper, 'JAM') !== false) {
                $qty_jam += $qty;
            } elseif (strpos($name_upper, 'KN') !== false && strpos($name_upper, 'BB') !== false) {
                $qty_kn_bb += $qty;
            } elseif (strpos($name_upper, 'KN') !== false) {
                $qty_kn += $qty;
            }
        }

        $tambahan_ap260 = ($qty_kn * 4) + ($qty_kn_bb * 8) + ($qty_jam * 1);

        if ($tambahan_ap260 > 0) {
            $ap260_found = false;
            foreach ($product_data_laser_a3 as $lname => $lqty) {
                $lname_upper = strtoupper($lname);
                if (strpos($lname_upper, 'AP260') !== false || strpos($lname_upper, 'AP 260') !== false) {
                    $product_data_laser_a3[$lname] += $tambahan_ap260;
                    $ap260_found = true;
                    break;
                }
            }
            
            if (!$ap260_found) {
                $product_data_laser_a3['AP260'] = $tambahan_ap260;
            }
        }

        foreach ($product_data_laser_a3 as $qty) {
            $total_all_qty_laser += $qty;
        }

        return [
            'product_data' => $product_data_laser_a3,
            'total_all_qty' => $total_all_qty_laser
        ];
    }

    public function getMerchandise() {
        global $store_id;

        $start_date_full = isset($_GET['start_date']) ? $_GET['start_date'] . " 00:00:00" : date('Y-m-d 00:00:00');
        $end_date_full   = isset($_GET['end_date']) ? $_GET['end_date'] . " 23:59:59" : date('Y-m-d 23:59:59');

        $merch_keywords = ['ID CARD', 'PIN', 'GANCI', 'JAM', 'THUMBLER', 'FRAME A4', 'FRAME A3'];
        $product_data_merch = [];

        foreach ($merch_keywords as $keyword) {
            $product_data_merch[$keyword] = 0;
        }

        $like_conditions = [];
        foreach ($merch_keywords as $k) {
            $like_conditions[] = "p.name LIKE '%$k%'";
        }
        $like_sql = implode(' OR ', $like_conditions);

        $query = "
            SELECT p.name, COALESCE(SUM(oi_filtered.quantity), 0) AS total_qty
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN (
                SELECT oi.product_id, oi.quantity
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                WHERE o.store_id = ? AND o.date BETWEEN ? AND ?
            ) oi_filtered ON p.product_id = oi_filtered.product_id
            WHERE c.name = 'MERCENDISE' AND p.store_id = ? AND ($like_sql)
            GROUP BY p.name
            ORDER BY p.name ASC
        ";

        $stmt = $this->koneksi->prepare($query);
        $stmt->bind_param("issi", $store_id, $start_date_full, $end_date_full, $store_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($rows as $row) {
            $name = $row['name'];
            $qty = (int)$row['total_qty'];
            
            foreach ($merch_keywords as $keyword) {
                if (stripos($name, $keyword) !== false) {
                    $product_data_merch[$keyword] += $qty;
                    break;
                }
            }
        }

        return $product_data_merch;
    }

    public function getSublim() {
        global $store_id;

        $start_date_full = isset($_GET['start_date']) ? $_GET['start_date'] . " 00:00:00" : date('Y-m-d 00:00:00');
        $end_date_full   = isset($_GET['end_date']) ? $_GET['end_date'] . " 23:59:59" : date('Y-m-d 23:59:59');

        $product_data_sublim = [];
        $max_rows_sublim = 0;
        $total_all_m2_sublim = 0;

        $queryStr = "
            SELECT p.product_id, p.name, oi_filtered.size, oi_filtered.quantity 
            FROM products p 
            JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN (
                SELECT oi.product_id, oi.size, oi.quantity
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                WHERE o.store_id = ? AND o.date BETWEEN ? AND ?
            ) oi_filtered ON p.product_id = oi_filtered.product_id
            WHERE c.name = 'SUBLIM' 
              AND p.store_id = ?
              AND (p.name LIKE '%TRANSFERPAPER%' OR p.name LIKE '%PRINT PRES%')
        ";

        $stmt = $this->koneksi->prepare($queryStr);
        $stmt->bind_param("issi", $store_id, $start_date_full, $end_date_full, $store_id);
        $stmt->execute();
        $all_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $products_map = [];
        foreach ($all_data as $row) {
            $pid = $row['product_id'];
            if (!isset($products_map[$pid])) {
                $products_map[$pid] = ['name' => $row['name'], 'rows' => []];
            }

            if (!empty($row['size']) && !empty($row['quantity'])) {
                if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $row['size'], $m)) {
                    $p = floatval($m[1]);
                    $l = floatval($m[2]);
                    $m2 = $p * $l * (int)$row['quantity'];

                    if (in_array($p, [1.1, 1.2, 1.5, 1.8])) {
                        $products_map[$pid]['rows'][] = ['p' => $l, 'l' => $p, 'qty' => (int)$row['quantity'], 'm2' => $m2];
                    } else {
                        $products_map[$pid]['rows'][] = ['p' => $p, 'l' => $l, 'qty' => (int)$row['quantity'], 'm2' => $m2];
                    }
                    $total_all_m2_sublim += $m2;
                }
            }
        }

        $lebar_list = [1.1, 1.2, 1.5, 1.8];
        foreach ($products_map as $pid => $pdata) {
            $grouped_by_lebar = [
                '1.1' => [], '1.2' => [], '1.5' => [], '1.8' => [], 'LAINNYA' => []
            ];

            foreach ($pdata['rows'] as $r) {
                $lebar = strval($r['l']);
                $key = in_array($r['l'], $lebar_list) ? $lebar : 'LAINNYA';
                $grouped_by_lebar[$key][] = $r;
            }

            foreach ($grouped_by_lebar as $lebar => $rows_lebar) {
                if (empty($rows_lebar)) continue;

                $label = ($lebar === 'LAINNYA') ? 'LAINNYA' : $lebar . 'm';
                $product_data_sublim[] = [
                    'name' => $pdata['name'] . " (" . $label . ")",
                    'rows' => $rows_lebar
                ];
            }
        }

        foreach ($product_data_sublim as $product) {
            $row_count = count($product['rows']);
            if ($row_count > $max_rows_sublim) $max_rows_sublim = $row_count;
        }

        return [
            'product_data' => $product_data_sublim,
            'max_rows' => $max_rows_sublim,
            'total_all_m2' => $total_all_m2_sublim
        ];
    }

    public function getMercendiseAkrilik() {
        global $store_id;

        $start_date_full = isset($_GET['start_date']) ? $_GET['start_date'] . " 00:00:00" : date('Y-m-d 00:00:00');
        $end_date_full   = isset($_GET['end_date']) ? $_GET['end_date'] . " 23:59:59" : date('Y-m-d 23:59:59');

        $product_data_mercendise_akrilik = [];

        $queryStr = "
            SELECT p.name, COALESCE(SUM(oi_filtered.quantity), 0) AS total_qty
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN (
                SELECT oi.product_id, oi.quantity
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                WHERE o.store_id = ? AND o.date BETWEEN ? AND ?
            ) oi_filtered ON p.product_id = oi_filtered.product_id
            WHERE c.name = 'MERCENDISE AKRILIK' AND p.store_id = ?
            GROUP BY p.product_id, p.name
        ";

        $stmt = $this->koneksi->prepare($queryStr);
        if (!$stmt) die("Query error: " . $this->koneksi->error);

        $stmt->bind_param("issi", $store_id, $start_date_full, $end_date_full, $store_id);
        $stmt->execute();
        
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($rows as $row) {
            $product_data_mercendise_akrilik[] = [
                'name' => $row['name'],
                'total_qty' => (int)$row['total_qty']
            ];
        }

        return $product_data_mercendise_akrilik;
    }

    public function getDtf() {
        global $store_id;

        $start_date_full = isset($_GET['start_date']) ? $_GET['start_date'] . " 00:00:00" : date('Y-m-d 00:00:00');
        $end_date_full   = isset($_GET['end_date']) ? $_GET['end_date'] . " 23:59:59" : date('Y-m-d 23:59:59');

        $dtf_biasa_names = ['DTF', 'DTF A3', 'DTF TEBAL', 'DTF 28'];
        $dtf_uv_names = ['DTF UV GLOSSY', 'DTF UV DOFF', 'DTF UV A3'];

        $data_assoc = [];
        $total_panjang_dtf = 0;
        $total_panjang_dtf_uv = 0;

        $queryStr = "
            SELECT p.product_id, p.name, oi_filtered.size, oi_filtered.quantity 
            FROM products p 
            JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN (
                SELECT oi.product_id, oi.size, oi.quantity
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                WHERE o.store_id = ? AND o.date BETWEEN ? AND ?
            ) oi_filtered ON p.product_id = oi_filtered.product_id
            WHERE c.name = 'DTF' AND p.store_id = ?
        ";

        $stmt = $this->koneksi->prepare($queryStr);
        $stmt->bind_param("issi", $store_id, $start_date_full, $end_date_full, $store_id);
        $stmt->execute();
        $all_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($all_data as $row) {
            $name = $row['name'];
            $name_upper = strtoupper($name);
            
            $isA3 = ($name_upper === 'DTF A3' || str_contains($name_upper, 'KAOS'));
            $isUV = in_array($name_upper, $dtf_uv_names);
            $isUV_A3 = ($name_upper === 'DTF UV A3');
            $isDTFBiasa = (in_array($name_upper, $dtf_biasa_names) || (strtok($name_upper, ' ') == 'DTF' && !str_contains($name_upper, 'UV')));

            if (!$isDTFBiasa && !$isUV) continue;

            if (!isset($data_assoc[$name])) {
                $data_assoc[$name] = [
                    'name' => $name,
                    'isA3' => $isA3,
                    'isUV' => $isUV,
                    'isUV_A3' => $isUV_A3,
                    'rows' => []
                ];
            }

            if (!empty($row['quantity'])) {
                $qty = (int)$row['quantity'];
                
                $total_val = 0;
                $p = 0;
                
                if ($isA3 || $isUV_A3) {
                    $total_val = $qty;
                    if ($isA3) $total_panjang_dtf += ($qty * 0.2);
                    if ($isUV_A3) $total_panjang_dtf_uv += ($qty * 0.2);
                } else {
                    if (!empty($row['size']) && preg_match('/^([\d.]+)[xX]([\d.]+)$/', $row['size'], $m)) {
                        $p = floatval($m[1]);
                        $total_val = $p * $qty;
                        
                        if ($isUV) $total_panjang_dtf_uv += $total_val;
                        else $total_panjang_dtf += $total_val;
                    }
                }

                $data_assoc[$name]['rows'][] = [
                    'p' => $p, 
                    'qty' => $qty, 
                    'total' => $total_val
                ];
            }
        }

        $product_data_dtf = array_values($data_assoc);
        $max_rows_dtf = 0;
        foreach ($product_data_dtf as $p) {
            $count = count($p['rows']);
            if ($count > $max_rows_dtf) $max_rows_dtf = $count;
        }

        return [
            'product_data' => $product_data_dtf,
            'max_rows' => $max_rows_dtf,
            'total_panjang_dtf' => $total_panjang_dtf,
            'total_panjang_dtf_uv' => $total_panjang_dtf_uv
        ];
    }

    public function getCetakan() {
        global $store_id;

        $start_date_full = isset($_GET['start_date']) ? $_GET['start_date'] . " 00:00:00" : date('Y-m-d 00:00:00');
        $end_date_full   = isset($_GET['end_date']) ? $_GET['end_date'] . " 23:59:59" : date('Y-m-d 23:59:59');

        $query = "
            SELECT p.name, COALESCE(SUM(oi_filtered.quantity), 0) AS total_qty
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN (
                SELECT oi.product_id, oi.quantity
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                WHERE o.store_id = ? AND o.date BETWEEN ? AND ?
            ) oi_filtered ON p.product_id = oi_filtered.product_id
            WHERE c.name = 'CETAKAN' AND p.store_id = ?
            GROUP BY p.product_id, p.name
            ORDER BY p.name ASC
        ";

        $stmt = $this->koneksi->prepare($query);
        $stmt->bind_param("issi", $store_id, $start_date_full, $end_date_full, $store_id);
        $stmt->execute();
        
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $product_data_cetakan = [];
        foreach ($rows as $row) {
            $product_data_cetakan[] = [
                'name' => $row['name'],
                'total_qty' => (int)$row['total_qty']
            ];
        }

        return $product_data_cetakan;
    }

    public function getBahanSublim() {
        global $store_id;

        $start_date_full = isset($_GET['start_date']) ? $_GET['start_date'] . " 00:00:00" : date('Y-m-d 00:00:00');
        $end_date_full   = isset($_GET['end_date']) ? $_GET['end_date'] . " 23:59:59" : date('Y-m-d 23:59:59');

        $data_meteran_assoc = [];
        $data_kiloan_assoc = [];
        
        $queryStr = "
            SELECT p.product_id, p.name, p.unit_type, oi_filtered.size, oi_filtered.quantity
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN (
                SELECT oi.product_id, oi.size, oi.quantity
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                WHERE o.store_id = ? AND o.date BETWEEN ? AND ?
            ) oi_filtered ON p.product_id = oi_filtered.product_id
            WHERE c.name = 'SUBLIM' AND p.name LIKE '%BAHAN%' AND p.store_id = ?
        ";

        $stmt = $this->koneksi->prepare($queryStr);
        $stmt->bind_param("issi", $store_id, $start_date_full, $end_date_full, $store_id);
        $stmt->execute();
        $all_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($all_data as $row) {
            $name = $row['name'];
            $unit = $row['unit_type'];

            if ($unit === 'M2' && !isset($data_meteran_assoc[$name])) {
                $data_meteran_assoc[$name] = ['name' => $name, 'rows' => []];
            } elseif ($unit === 'PCS' && !isset($data_kiloan_assoc[$name])) {
                $data_kiloan_assoc[$name] = ['name' => $name, 'rows' => []];
            }

            if (!empty($row['size']) && !empty($row['quantity'])) {
                $size = strtoupper(trim($row['size']));
                $qty = (int)$row['quantity'];

                if ($unit === 'M2' && preg_match('/^([\d.]+)[xX]([\d.]+)$/', $size, $m)) {
                    $p = (float)$m[1];
                    $l = (float)$m[2];
                    $m2 = $p * $l * $qty;
                    $data_meteran_assoc[$name]['rows'][] = ['p' => $p, 'l' => $l, 'qty' => $qty, 'm2' => $m2];
                } elseif ($unit === 'PCS' && preg_match('/([\d.]+)\s*KG/', $size, $m)) {
                    $kg = (float)$m[1];
                    $total_kg = $kg * $qty;
                    $data_kiloan_assoc[$name]['rows'][] = ['kg' => $kg, 'qty' => $qty, 'kg_total' => $total_kg];
                }
            }
        }

        $max_meteran = 0;
        foreach ($data_meteran_assoc as $p) $max_meteran = max($max_meteran, count($p['rows']));
        
        $max_kiloan = 0;
        foreach ($data_kiloan_assoc as $p) $max_kiloan = max($max_kiloan, count($p['rows']));

        return [
            'meteran' => array_values($data_meteran_assoc),
            'kiloan' => array_values($data_kiloan_assoc),
            'max_rows' => max($max_meteran, $max_kiloan)
        ];
    }

    public function getFinishingJersey() {
        global $store_id;

        $start_date_full = isset($_GET['start_date']) ? $_GET['start_date'] . " 00:00:00" : date('Y-m-d 00:00:00');
        $end_date_full   = isset($_GET['end_date']) ? $_GET['end_date'] . " 23:59:59" : date('Y-m-d 23:59:59');

        $stmt = $this->koneksi->prepare("
            SELECT finishing_id, name 
            FROM finishings 
            WHERE category_id IN (SELECT category_id FROM categories WHERE name = 'JERSEY')
            AND store_id = ?
            ORDER BY name
        ");
        $stmt->bind_param("i", $store_id);
        $stmt->execute();
        $finishings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $finishing_totals = [];
        foreach ($finishings as $f) {
            $finishing_totals[$f['finishing_id']] = 0;
        }

        $query = "
            SELECT oi.finishing, oi.quantity
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            WHERE o.store_id = ? 
            AND o.date BETWEEN ? AND ?
            AND oi.finishing IS NOT NULL 
            AND oi.finishing != ''
        ";
        
        $stmt = $this->koneksi->prepare($query);
        $stmt->bind_param("iss", $store_id, $start_date_full, $end_date_full);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($rows as $row) {
            $qty = (int)($row['quantity'] > 0 ? $row['quantity'] : 1);
            $fin_ids = array_map('trim', explode(',', $row['finishing']));

            foreach ($fin_ids as $fid) {
                if ($fid === '') continue;
                if (isset($finishing_totals[$fid])) {
                    $finishing_totals[$fid] += $qty;
                }
            }
        }

        $result = [];
        $total_all = 0;
        foreach ($finishings as $fin) {
            $fid = $fin['finishing_id'];
            $qty = $finishing_totals[$fid];
            $result[] = [
                'name' => $fin['name'],
                'total_qty' => $qty
            ];
            $total_all += $qty;
        }

        return [
            'data' => $result,
            'total_all' => $total_all
        ];
    }

}