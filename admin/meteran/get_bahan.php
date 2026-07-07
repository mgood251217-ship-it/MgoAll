<?php
require_once 'get_order_ids.php';

$products_bahan = [];
$product_data_bahan_meteran = [];
$product_data_bahan_kiloan  = [];
$max_rows_bahan_meteran = 0;
$max_rows_bahan_kiloan  = 0;
$max_rows_bahan_asli = 0;

if (!empty($order_ids)) {
    $in = implode(',', array_fill(0, count($order_ids), '?'));
    $types = str_repeat('i', count($order_ids)) . 'i';
    $params = array_merge($order_ids, [$store_id]);

    $queryStr = "
        SELECT p.product_id, p.name, p.unit_type, oi.size, oi.quantity
        FROM products p
        JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN order_items oi ON p.product_id = oi.product_id AND oi.order_id IN ($in)
        WHERE c.name = 'SUBLIM' AND p.name LIKE '%BAHAN%' AND p.store_id = ?
    ";

    $stmt = $koneksi->prepare($queryStr);
    if (!$stmt) die("Query error: " . $koneksi->error);

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $all_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $products_map = [];
    $data_meteran_assoc = [];
    $data_kiloan_assoc = [];

    foreach ($all_data as $row) {
        $pid = $row['product_id'];
        $name = $row['name'];
        $unit = $row['unit_type'];

        if (!isset($products_map[$pid])) {
            $products_map[$pid] = [
                'product_id' => $pid,
                'name' => $name,
                'unit_type' => $unit
            ];
        }

        if (!empty($row['size']) && !empty($row['quantity'])) {
            $size = strtoupper(trim($row['size']));
            $qty = (int)$row['quantity'];

            if ($unit === 'M2') {
                if (!isset($data_meteran_assoc[$name])) {
                    $data_meteran_assoc[$name] = ['name' => $name, 'rows' => []];
                }
                if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $size, $m)) {
                    $p = (float)$m[1];
                    $l = (float)$m[2];
                    $m2 = $p * $l * $qty;
                    $data_meteran_assoc[$name]['rows'][] = ['p' => $p, 'l' => $l, 'qty' => $qty, 'm2' => $m2];
                }
            } elseif ($unit === 'PCS') {
                if (!isset($data_kiloan_assoc[$name])) {
                    $data_kiloan_assoc[$name] = ['name' => $name, 'rows' => []];
                }
                if (preg_match('/([\d.]+)\s*KG/', $size, $m)) {
                    $kg = (float)$m[1];
                    $total_kg = $kg * $qty;
                    $data_kiloan_assoc[$name]['rows'][] = ['kg' => $kg, 'qty' => $qty, 'kg_total' => $total_kg];
                }
            }
        }
    }

    $products_bahan = array_values($products_map);
    $product_data_bahan_meteran = array_values($data_meteran_assoc);
    $product_data_bahan_kiloan = array_values($data_kiloan_assoc);

    foreach ($product_data_bahan_meteran as $p) {
        $count = count($p['rows']);
        if ($count > $max_rows_bahan_meteran) {
            $max_rows_bahan_meteran = $count;
        }
    }

    foreach ($product_data_bahan_kiloan as $p) {
        $count = count($p['rows']);
        if ($count > $max_rows_bahan_kiloan) {
            $max_rows_bahan_kiloan = $count;
        }
    }

    if ($max_rows_bahan_kiloan >= $max_rows_bahan_meteran) {
        $max_rows_bahan_asli = $max_rows_bahan_kiloan;
    } else {
        $max_rows_bahan_asli = $max_rows_bahan_meteran;
    }
}
?>

<div class="excel-container" id="table-container-indoor">
    <?php foreach ($product_data_bahan_meteran as $product): ?>
    
    <table class="excel-table">
    <thead>
        <tr><th colspan="4"><?= htmlspecialchars($product['name']) ?></th></tr>
        <tr><th>P</th><th>L</th><th>Qty</th><th>M2</th></tr>
    </thead>
    <tbody>
        <?php if (empty($product['rows'])): ?>
        <?php for ($i = 0; $i < $max_rows_bahan_meteran; $i++): ?>
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        <?php endfor; ?>
        <tr style="font-weight:bold;">
            <td colspan="3" style="text-align:right;">Total m2:</td>
            <td>0</td>
        </tr>
        <?php else: ?>
        <?php 
            $total_m2_product = 0;
            foreach ($product['rows'] as $row):
            $total_m2_product += $row['m2'];
            $total_all_m2_sublim += $row['m2'];
        ?>
            <tr>
            <td><?= $row['p'] ?></td>
            <td><?= $row['l'] ?></td>
            <td><?= $row['qty'] ?></td>
            <td><?= $row['m2'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php for ($i = count($product['rows']); $i < $max_rows_bahan_asli; $i++): ?>
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        <?php endfor; ?>
        <tr style="font-weight:bold;">
            <td colspan="3" style="text-align:right;">Total m2:</td>
            <td><?= round($total_m2_product, 2) ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
    </table>
    <?php endforeach; ?>
    <?php foreach ($product_data_bahan_kiloan as $product): ?>
    <table class="excel-table">
    <thead>
        <tr><th colspan="4"><?= htmlspecialchars($product['name']) ?></th></tr>
        <tr><th>Kg</th><th>Qty</th><th>Kg Total</th></tr>
    </thead>
    <tbody>
        <?php if (empty($product['rows'])): ?>
        <?php for ($i = 0; $i < $max_rows_bahan_kiloan; $i++): ?>
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        <?php endfor; ?>
        <tr style="font-weight:bold;">
            <td colspan="3" style="text-align:right;">Total m2:</td>
            <td>0</td>
        </tr>
        <?php else: ?>
        <?php 
            $total_kg_product = 0;
            foreach ($product['rows'] as $row):
            $total_kg_product += $row['kg_total'];
        ?>
            <tr>
            <td><?= $row['kg'] ?></td>
            <td><?= $row['qty'] ?></td>
            <td><?= $row['kg_total'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php for ($i = count($product['rows']); $i < $max_rows_bahan_asli; $i++): ?>
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        <?php endfor; ?>
        <tr style="font-weight:bold;">
            <td colspan="2" style="text-align:right;">Total kg:</td>
            <td><?= round($total_kg_product, 2) ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
    </table>
    <?php endforeach; ?>
</div>
