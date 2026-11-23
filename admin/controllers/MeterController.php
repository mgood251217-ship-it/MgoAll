<?php
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Project.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/ProductLaserConversionController.php';
require_once __DIR__ . '/ProductVariantController.php';
require_once __DIR__ . '/ProductBundleController.php';
require_once __DIR__ . '/../functions/helpers.php';

class MeterController {
    private $koneksi;
    private $orderModel;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
        $this->orderModel = new Order($koneksi);
    }

    public function getOrderIds() {
        global $store_id;

        [$start_date_full, $end_date_full] = $this->getDateRange();

        return $this->orderModel->getOrderIdsByIntervalDate($store_id, $start_date_full, $end_date_full);
    }

    public function getOutdoor() {
        global $store_id;

        [$start_date_full, $end_date_full] = $this->getDateRange();

        $product_data = [];
        $max_rows = 0;
        $total_all_m2_outdoor = 0;
        $total_m2_product = [];

        $stmt = $this->koneksi->prepare("
            SELECT p.product_id, p.name
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            WHERE c.name = 'OUTDOOR' AND p.store_id = ?
        ");
        $stmt->bind_param("i", $store_id);
        $stmt->execute();
        $all_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $productId_to_name = [];
        $product_data_assoc = [];

        foreach ($all_products as $outdoor) {
            $productId_to_name[$outdoor['product_id']] = $outdoor['name'];
            $product_data_assoc[$outdoor['name']] = [
                'name' => $outdoor['name'],
                'rows' => []
            ];
            $total_m2_product[$outdoor['name']] = 0;
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
        $max_rows = $this->getMaxRows($product_data);

        return [
            'product_data' => $product_data,
            'max_rows' => $max_rows,
            'total_all_m2' => $total_all_m2_outdoor,
            'total_m2_product' => $total_m2_product
        ];
    }

    public function getIndoor() {
        global $store_id;

        [$start_date_full, $end_date_full] = $this->getDateRange();

        $max_rows_indoor = 0;
        $total_all_m2_indoor = 0;
        $total_m2_product_indoor = [];

        $stmt = $this->koneksi->prepare("
            SELECT p.product_id, p.name, oi_filtered.size, oi_filtered.quantity
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN (
                SELECT oi.product_id, oi.size, oi.quantity
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                WHERE o.store_id = ? AND o.date BETWEEN ? AND ?
            ) oi_filtered ON p.product_id = oi_filtered.product_id
            WHERE c.name = 'INDOOR'
              AND p.store_id = ?
        ");

        $stmt->bind_param("issi", $store_id, $start_date_full, $end_date_full, $store_id);
        $stmt->execute();

        $all_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $product_data_assoc = [];

        foreach ($all_data as $row) {
            $name = $row['name'];

            if (!isset($product_data_assoc[$name])) {
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
        }

        $product_data_indoor = array_values($product_data_assoc);

        $max_rows_indoor = $this->getMaxRows($product_data_indoor);

        return [
            'product_data' => $product_data_indoor,
            'max_rows' => $max_rows_indoor,
            'total_all_m2' => $total_all_m2_indoor,
            'total_m2_product' => $total_m2_product_indoor
        ];
    }

    public function getAkrilik() {
        global $store_id;

        [$start_date_full, $end_date_full] = $this->getDateRange();

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
            if (!$query) {
                die("Query error: " . $this->koneksi->error);
            }

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
        $max_rows = $this->getMaxRows($product_data);

        return [
            'product_data' => $product_data,
            'max_rows' => $max_rows,
            'total_all_m2' => $total_all_m2_akrilik,
            'total_m2_product' => $total_m2_product
        ];
    }

    public function getJersey() {
        return $this->getPakaian();
    }

    public function getPakaian() {
        global $store_id;

        [$start_date_full, $end_date_full] = $this->getDateRange();

        $product_data_pakaian = [];
        $total_all_qty_pakaian = 0;
        $variantModel = new ProductVariantController($this->koneksi);
        $legacyOnly = $variantModel->isAvailable() ? ' AND NOT (' . ProductVariant::productionSnapshotFilter() . ')' : '';
        if ((new ProductBundleController($this->koneksi))->isAvailable()) {
            $legacyOnly .= " AND JSON_EXTRACT(IF(JSON_VALID(oi.tier_price_data), oi.tier_price_data, '{}'), '$.bundle') IS NULL";
        }

        $queryStr = "
            SELECT p.name, COALESCE(SUM(oi_filtered.quantity), 0) AS total_qty
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN (
                SELECT oi.product_id, oi.quantity
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                WHERE o.store_id = ? AND o.date BETWEEN ? AND ? $legacyOnly
            ) oi_filtered ON p.product_id = oi_filtered.product_id
            WHERE UPPER(TRIM(c.name)) = 'TEKSTIL' AND p.store_id = ?
            GROUP BY p.product_id, p.name
        ";

        $stmt = $this->koneksi->prepare($queryStr);
        if (!$stmt) {
            die("Query error: " . $this->koneksi->error);
        }

        $stmt->bind_param("issi", $store_id, $start_date_full, $end_date_full, $store_id);
        $stmt->execute();

        $res = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($rows as $row) {
            $qty = (int)$row['total_qty'];
            if ($qty === 0) {
                continue;
            }
            $product_data_pakaian[] = [
                'name' => $row['name'],
                'total_qty' => $qty
            ];
            $total_all_qty_pakaian += $qty;
        }

        foreach ($variantModel->productionRows($store_id, $start_date_full, $end_date_full, ['TEKSTIL'], true) as $component) {
            if ((float)$component['total_qty'] === 0.0) {
                continue;
            }
            $product_data_pakaian[] = $component;
            $total_all_qty_pakaian += $component['total_qty'];
        }
        foreach ((new ProductBundleController($this->koneksi))->quantityRows($store_id, $start_date_full, $end_date_full, ['TEKSTIL']) as $row) {
            $product_data_pakaian[] = $row;
            $total_all_qty_pakaian += $row['total_qty'];
        }
        $merged = [];
        foreach ($product_data_pakaian as $row) {
            $merged[$row['name']] = ($merged[$row['name']] ?? 0) + $row['total_qty'];
        }
        ksort($merged, SORT_NATURAL | SORT_FLAG_CASE);
        $product_data_pakaian = [];
        foreach ($merged as $name => $qty) {
            if ($qty != 0) {
                $product_data_pakaian[] = ['name' => $name, 'total_qty' => $qty];
            }
        }
        return [
            'product_data' => $product_data_pakaian,
            'total_all_qty' => $total_all_qty_pakaian
        ];
    }

    public function getComponents() {
        global $store_id;
        [$start, $end] = $this->getDateRange();
        $rows = (new ProductVariantController($this->koneksi))->productionRows($store_id, $start, $end);
        $rows = array_merge($rows, (new ProductBundleController($this->koneksi))->quantityRows($store_id, $start, $end));
        $merged = [];
        foreach ($rows as $row) {
            $name = $row['name'];
            if (!isset($merged[$name])) {
                $merged[$name] = array_merge(['component_id' => null], $row, ['total_qty' => 0]);
            }
            $merged[$name]['total_qty'] += $row['total_qty'];
        }
        $rows = array_values(array_filter($merged, function ($row) {
            return $row['total_qty'] != 0;
        }));

        return [
            'product_data' => $rows,
            'total_all_qty' => array_sum(array_column($rows, 'total_qty'))
        ];
    }

    public function getLaser() {
        global $store_id;

        [$start_date_full, $end_date_full] = $this->getDateRange();

        $product_data_laser_a3 = [];
        $total_all_qty_laser = 0;
        $conversionModel = new ProductLaserConversionController($this->koneksi);
        $conversions = [];
        foreach ($conversionModel->getByStore($store_id, true) as $rule) {
            $conversions[(int)$rule['product_id']][] = $rule;
        }

        $query1 = "
            SELECT p.name, COALESCE(SUM(oi_filtered.quantity), 0) AS total_qty
            FROM products p
            JOIN categories c ON p.category_id = c.category_id AND c.store_id = p.store_id
            LEFT JOIN (
                SELECT oi.product_id, oi.quantity
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                WHERE o.store_id = ? AND o.date BETWEEN ? AND ?
            ) oi_filtered ON p.product_id = oi_filtered.product_id
            WHERE UPPER(TRIM(c.name)) = 'LASER A3' AND p.store_id = ?
            GROUP BY p.product_id, p.name
        ";

        $stmt1 = $this->koneksi->prepare($query1);
        if (!$stmt1) {
            die("Query error: " . $this->koneksi->error);
        }

        $stmt1->bind_param("issi", $store_id, $start_date_full, $end_date_full, $store_id);
        $stmt1->execute();
        $res1 = $stmt1->get_result();
        $rows1 = $res1->fetch_all(MYSQLI_ASSOC);
        $stmt1->close();

        foreach ($rows1 as $row) {
            $product_data_laser_a3[$row['name']] = ($product_data_laser_a3[$row['name']] ?? 0) + (int)$row['total_qty'];
        }

        $conversionIds = array_keys($conversions);
        if ($conversionIds) {
            $materialFields = $conversionModel->hasOrderMaterials()
                ? 'oi.laser_product_id, laser.name AS laser_material_name'
                : 'NULL AS laser_product_id, NULL AS laser_material_name';
            $materialJoin = $conversionModel->hasOrderMaterials()
                ? 'LEFT JOIN products laser ON laser.product_id = oi.laser_product_id AND laser.store_id = oi.store_id' : '';
            $placeholders = implode(',', array_fill(0, count($conversionIds), '?'));
            $query2 = "
                SELECT p.product_id, oi.quantity AS total_qty, oi.size, oi.finishing, $materialFields
                FROM order_items oi
                $materialJoin
                JOIN orders o ON oi.order_id = o.order_id
                JOIN products p ON oi.product_id = p.product_id
                JOIN categories c ON p.category_id = c.category_id AND c.store_id = p.store_id
                WHERE o.store_id = ? AND o.date BETWEEN ? AND ?
                  AND p.store_id = ? AND p.product_id IN ($placeholders)
            ";

            $stmt2 = $this->koneksi->prepare($query2);
            if (!$stmt2) {
                die("Query error: " . $this->koneksi->error);
            }

            $params = array_merge([$store_id, $start_date_full, $end_date_full, $store_id], $conversionIds);
            $types = 'issi' . str_repeat('i', count($conversionIds));
            $stmt2->bind_param($types, ...$params);
            $stmt2->execute();
            $rows2 = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt2->close();

            foreach ($rows2 as $row) {
                $rule = ProductLaserConversionController::matchRule(
                    $conversions[(int)$row['product_id']],
                    $row['size'],
                    $row['finishing'],
                    true,
                    $row['laser_product_id'] ?? 0
                );
                if ($rule) {
                    $name = $row['laser_material_name'] ?: $rule['laser_name'];
                    $product_data_laser_a3[$name] = ($product_data_laser_a3[$name] ?? 0)
                        + ProductLaserConversionController::printQuantity($row['total_qty'], $rule);
                }
            }
        }

        foreach ((new ProductBundleController($this->koneksi))->laserRows($store_id, $start_date_full, $end_date_full) as $name => $qty) {
            $product_data_laser_a3[$name] = ($product_data_laser_a3[$name] ?? 0) + $qty;
        }
        $product_data_laser_a3 = array_filter($product_data_laser_a3, function ($qty) {
            return (float)$qty !== 0.0;
        });
        foreach ($product_data_laser_a3 as $qty) {
            $total_all_qty_laser += $qty;
        }

        return [
            'product_data' => $product_data_laser_a3,
            'total_all_qty' => $total_all_qty_laser
        ];
    }

    public function getSublim() {
        global $store_id;

        [$start_date_full, $end_date_full] = $this->getDateRange();

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
                if (empty($rows_lebar)) {
                    continue;
                }

                $label = ($lebar === 'LAINNYA') ? 'LAINNYA' : $lebar . 'm';
                $product_data_sublim[] = [
                    'name' => $pdata['name'] . " (" . $label . ")",
                    'rows' => $rows_lebar
                ];
            }
        }

        $max_rows_sublim = $this->getMaxRows($product_data_sublim);

        return [
            'product_data' => $product_data_sublim,
            'max_rows' => $max_rows_sublim,
            'total_all_m2' => $total_all_m2_sublim
        ];
    }

    public function getMercendiseAkrilik() {
        global $store_id;

        [$start_date_full, $end_date_full] = $this->getDateRange();

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
        if (!$stmt) {
            die("Query error: " . $this->koneksi->error);
        }

        $stmt->bind_param("issi", $store_id, $start_date_full, $end_date_full, $store_id);
        $stmt->execute();

        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($rows as $row) {
            if ((int)$row['total_qty'] === 0) {
                continue;
            }
            $product_data_mercendise_akrilik[] = [
                'name' => $row['name'],
                'total_qty' => (int)$row['total_qty']
            ];
        }

        return $product_data_mercendise_akrilik;
    }

    public function getDtf() {
        global $store_id;

        [$start_date_full, $end_date_full] = $this->getDateRange();

        $data_assoc = [];
        $total_panjang_dtf = 0;
        $total_panjang_dtf_uv = 0;

        $queryStr = "
            SELECT p.product_id, p.name, p.unit_type, c.name AS category_name, oi_filtered.size, oi_filtered.quantity
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN (
                SELECT oi.product_id, oi.size, oi.quantity
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                WHERE o.store_id = ? AND o.date BETWEEN ? AND ?
            ) oi_filtered ON p.product_id = oi_filtered.product_id
            WHERE c.name IN ('DTF', 'DTF UV') AND p.store_id = ?
        ";

        $stmt = $this->koneksi->prepare($queryStr);
        $stmt->bind_param("issi", $store_id, $start_date_full, $end_date_full, $store_id);
        $stmt->execute();
        $all_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($all_data as $row) {
            $name = $row['name'];
            $isUV = strtoupper(trim($row['category_name'])) === 'DTF UV';
            $isPcs = strtoupper(trim($row['unit_type'])) === 'PCS';
            $isA3 = !$isUV && $isPcs;
            $isUV_A3 = $isUV && $isPcs;
            $groupKey = $isPcs ? ($isUV ? 'pcs:uv' : 'pcs:dtf') : 'product:' . $row['product_id'];
            $groupName = $isPcs ? ($isUV ? 'DTF UV PCS' : 'DTF PCS') : $name;

            if (!isset($data_assoc[$groupKey])) {
                $data_assoc[$groupKey] = [
                    'name' => $groupName,
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
                    if ($isA3) {
                        $total_panjang_dtf += ($qty * 0.2);
                    }
                    if ($isUV_A3) {
                        $total_panjang_dtf_uv += ($qty * 0.2);
                    }
                } else {
                    if (!empty($row['size']) && preg_match('/^([\d.]+)[xX]([\d.]+)$/', $row['size'], $m)) {
                        $p = floatval($m[1]);
                        $total_val = $p * $qty;

                        if ($isUV) {
                            $total_panjang_dtf_uv += $total_val;
                        } else {
                            $total_panjang_dtf += $total_val;
                        }
                    }
                }

                $data_assoc[$groupKey]['rows'][] = [
                    'p' => $p,
                    'qty' => $qty,
                    'total' => $total_val
                ];
            }
        }

        $product_data_dtf = array_values($data_assoc);
        $max_rows_dtf = 0;
        $max_rows_dtf = $this->getMaxRows($product_data_dtf);

        return [
            'product_data' => $product_data_dtf,
            'max_rows' => $max_rows_dtf,
            'total_panjang_dtf' => round($total_panjang_dtf, 2),
            'total_panjang_dtf_uv' => round($total_panjang_dtf_uv, 2)
        ];
    }

    public function getCetakan() {
        global $store_id;

        [$start_date_full, $end_date_full] = $this->getDateRange();

        $variantModel = new ProductVariantController($this->koneksi);
        $legacyOnly = $variantModel->isAvailable() ? ' AND NOT (' . ProductVariant::productionSnapshotFilter() . ')' : '';

        $query = "
            SELECT p.name, COALESCE(SUM(oi_filtered.quantity), 0) AS total_qty
            FROM products p
            JOIN categories c ON p.category_id = c.category_id AND c.store_id = p.store_id
            LEFT JOIN (
                SELECT oi.product_id, oi.quantity
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id AND oi.store_id = o.store_id
                WHERE o.store_id = ? AND o.date BETWEEN ? AND ? $legacyOnly
            ) oi_filtered ON p.product_id = oi_filtered.product_id
            WHERE UPPER(TRIM(c.name)) = 'CETAKAN' AND p.store_id = ?
            GROUP BY p.product_id, p.name
            ORDER BY p.name ASC
        ";

        $stmt = $this->koneksi->prepare($query);
        $stmt->bind_param("issi", $store_id, $start_date_full, $end_date_full, $store_id);
        $stmt->execute();

        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $totals = [];
        foreach ($rows as $row) {
            if ((float)$row['total_qty'] === 0.0) {
                continue;
            }
            $totals[$row['name']] = ($totals[$row['name']] ?? 0) + (float)$row['total_qty'];
        }
        // Nota menyimpan komponen saat dibuat; varian lama tanpa komponen memakai nama + label.
        foreach ($variantModel->productionRows($store_id, $start_date_full, $end_date_full, ['CETAKAN'], true) as $component) {
            $name = $component['name'];
            $totals[$name] = ($totals[$name] ?? 0) + (float)$component['total_qty'];
        }
        ksort($totals, SORT_NATURAL | SORT_FLAG_CASE);
        $product_data_cetakan = [];
        foreach ($totals as $name => $quantity) {
            $quantity = round($quantity, 4);
            if ($quantity == 0) {
                continue;
            }
            $product_data_cetakan[] = ['name' => $name, 'total_qty' => $quantity];
        }
        return $product_data_cetakan;
    }

    public function getPelengkap() {
        global $store_id;
        [$start, $end] = $this->getDateRange();
        $variants = new ProductVariantController($this->koneksi);
        $legacyOnly = $variants->isAvailable() ? ' AND NOT (' . ProductVariant::productionSnapshotFilter() . ')' : '';
        if ((new ProductBundleController($this->koneksi))->isAvailable()) {
            $legacyOnly .= " AND JSON_EXTRACT(IF(JSON_VALID(oi.tier_price_data), oi.tier_price_data, '{}'), '$.bundle') IS NULL";
        }
        $stmt = $this->koneksi->prepare("SELECT p.name, SUM(oi.quantity) AS total_qty
            FROM order_items oi
            JOIN orders o ON o.order_id = oi.order_id AND o.store_id = oi.store_id
            JOIN products p ON p.product_id = oi.product_id AND p.store_id = oi.store_id
            JOIN categories c ON c.category_id = p.category_id AND c.store_id = p.store_id
            WHERE oi.store_id = ? AND o.date BETWEEN ? AND ?
                AND UPPER(TRIM(c.name)) = 'PELENGKAP' AND UPPER(TRIM(p.unit_type)) IN ('PCS', '~')$legacyOnly
            GROUP BY p.product_id, p.name ORDER BY p.name");
        $stmt->bind_param('iss', $store_id, $start, $end);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $totals = [];
        foreach ($rows as $row) {
            $totals[$row['name']] = ($totals[$row['name']] ?? 0) + (float)$row['total_qty'];
        }
        foreach ($variants->productionRows($store_id, $start, $end, ['PELENGKAP'], true, ['PCS', '~']) as $row) {
            $totals[$row['name']] = ($totals[$row['name']] ?? 0) + (float)$row['total_qty'];
        }
        foreach ((new ProductBundleController($this->koneksi))->quantityRows($store_id, $start, $end, ['PELENGKAP']) as $row) {
            $totals[$row['name']] = ($totals[$row['name']] ?? 0) + $row['total_qty'];
        }
        ksort($totals, SORT_NATURAL | SORT_FLAG_CASE);
        $result = [];
        foreach ($totals as $name => $quantity) {
            $quantity = round($quantity, 4);
            if ($quantity != 0) {
                $result[] = ['name' => $name, 'total_qty' => $quantity];
            }
        }
        return $result;
    }

    public function getBahanSublim() {
        global $store_id;
        [$start, $end] = $this->getDateRange();
        $variantFields = ProductVariant::orderFields(); $variantJoins = ProductVariant::orderJoins();
        $stmt = $this->koneksi->prepare("SELECT oi.product_id, oi.product_variant_id, oi.size, oi.quantity, oi.product_variant_data, $variantFields
            FROM order_items oi
            $variantJoins
            JOIN orders o ON o.order_id = oi.order_id AND o.store_id = oi.store_id
            WHERE oi.store_id = ? AND o.date BETWEEN ? AND ?
                AND oi.product_variant_data IS NOT NULL
            ORDER BY oi.order_item_id");
        $stmt->bind_param('iss', $store_id, $start, $end);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $masters = new ProductionComponentController($this->koneksi);
        $masterNames = array_column($masters->getByStore($store_id), 'name', 'id');
        $meteran = $kiloan = [];
        foreach ($rows as $row) {
            $saved = ProductVariantController::snapshot($row);
            if (!$saved) {
                continue;
            }
            $category = strtoupper(trim($saved['category'] ?? ''));
            $productName = $saved['product_name'] ?? '';
            if ($category !== 'SUBLIM' || !str_contains(strtoupper($productName), 'BAHAN')) {
                continue;
            }
            $qty = (int)$row['quantity'];
            if ($qty <= 0) {
                continue;
            }
            $size = strtoupper(trim($row['size'] ?? ''));
            $weight = preg_match('/^(\d+(?:\.\d+)?)\s*KG$/D', $size, $kg);
            $area = preg_match('/^(\d+(?:\.\d+)?)[X](\d+(?:\.\d+)?)$/D', $size, $dimensions);
            if (!$weight && !$area) {
                continue;
            }
            if (($weight && (float)$kg[1] <= 0) || ($area && ((float)$dimensions[1] <= 0 || (float)$dimensions[2] <= 0))) {
                continue;
            }
            $components = $saved['components'];
            if (!$components) {
                $components = [[
                    'name' => $productName . ' - ' . $saved['label'],
                    'quantity' => 1
                ]];
            }
            foreach ($components as $component) {
                $factor = (float)$component['quantity'];
                if ($factor <= 0) {
                    continue;
                }
                $id = (int)($component['component_id'] ?? 0);
                $name = $masterNames[$id] ?? $component['name'];
                if ($weight) {
                    if (!isset($kiloan[$name])) {
                        $kiloan[$name] = ['name' => $name, 'rows' => []];
                    }
                    $perItem = round((float)$kg[1] * $factor, 4);
                    $kiloan[$name]['rows'][] = [
                        'kg' => $perItem,
                        'qty' => $qty,
                        'kg_total' => round($perItem * $qty, 4)
                    ];
                } else {
                    if (!isset($meteran[$name])) {
                        $meteran[$name] = ['name' => $name, 'rows' => []];
                    }
                    $length = round((float)$dimensions[1] * $factor, 4);
                    $meteran[$name]['rows'][] = [
                        'p' => $length,
                        'l' => (float)$dimensions[2],
                        'qty' => $qty,
                        'm2' => round($length * (float)$dimensions[2] * $qty, 4)
                    ];
                }
            }
        }
        ksort($meteran, SORT_NATURAL | SORT_FLAG_CASE);
        ksort($kiloan, SORT_NATURAL | SORT_FLAG_CASE);
        $max = $this->getMaxRows(array_merge(array_values($meteran), array_values($kiloan)));

        return [
            'meteran' => array_values($meteran),
            'kiloan' => array_values($kiloan),
            'max_rows' => $max
        ];
    }

    public function getFinishingJersey() {
        return $this->getFinishingPakaian();
    }

    public function getFinishingPakaian() {
        global $store_id;

        [$start_date_full, $end_date_full] = $this->getDateRange();

        $stmt = $this->koneksi->prepare("
            SELECT finishing_id, name
            FROM finishings
            WHERE category_id IN (SELECT category_id FROM categories WHERE UPPER(TRIM(name)) = 'TEKSTIL')
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
                if ($fid === '') {
                    continue;
                }
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
            if ($qty === 0) {
                continue;
            }
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

    private function getDateRange() {
        $start = isset($_GET['start_date']) ? $_GET['start_date'] . " 00:00:00" : date('Y-m-d 00:00:00');
        $end = isset($_GET['end_date']) ? $_GET['end_date'] . " 23:59:59" : date('Y-m-d 23:59:59');

        return [$start, $end];
    }

    private function getMaxRows($products) {
        $maxRows = 0;
        foreach ($products as $product) {
            $maxRows = max($maxRows, count($product['rows']));
        }

        return $maxRows;
    }

}
