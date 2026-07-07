<?php
require_once 'get_order_ids.php';

$products_indoor = [];
$product_data_indoor = [];
$max_rows_indoor = 0;
$total_all_m2_indoor = 0;

if (!empty($order_ids)) {
    $in = implode(',', array_fill(0, count($order_ids), '?'));
    $types = str_repeat('i', count($order_ids)) . 'i';
    $params = array_merge($order_ids, [$store_id]);

    $queryStr = "
        SELECT p.product_id, p.name, c.name AS category, oi.size, oi.quantity 
        FROM products p 
        JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN order_items oi ON p.product_id = oi.product_id AND oi.order_id IN ($in)
        WHERE c.name IN ('INDOOR', 'PAKET INDOOR OUTDOOR') AND p.store_id = ?
    ";

    $stmt = $koneksi->prepare($queryStr);
    if (!$stmt) die("Query error: " . $koneksi->error);
    
    $stmt->bind_param($types, ...$params);
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
            }
            if (!empty($row['size']) && !empty($row['quantity'])) {
                $size = $row['size'];
                $qty = (int)$row['quantity'];
                if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $size, $match)) {
                    $p = floatval($match[1]);
                    $l = floatval($match[2]);
                    $m2 = $p * $l * $qty;
                    $product_data_assoc[$name]['rows'][] = ['p' => $p, 'l' => $l, 'qty' => $qty, 'm2' => $m2];
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
}
?>

<div class="excel-container" id="table-container-indoor">
    <?php foreach ($product_data_indoor as $product): ?>
    <table class="excel-table">
    <thead>
        <tr><th colspan="4"><?= htmlspecialchars($product['name']) ?></th></tr>
        <tr><th>P</th><th>L</th><th>Qty</th><th>M2</th></tr>
    </thead>
    <tbody>
        <?php if (empty($product['rows'])): ?>
        <?php for ($i = 0; $i < $max_rows_indoor; $i++): ?>
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
            $total_all_m2_indoor += $row['m2'];
        ?>
            <tr>
            <td><?= $row['p'] ?></td>
            <td><?= $row['l'] ?></td>
            <td><?= $row['qty'] ?></td>
            <td><?= $row['m2'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php for ($i = count($product['rows']); $i < $max_rows_indoor; $i++): ?>
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
</div>

<div class="mt-3">
    <table class="table table-bordered" style="max-width:500px; font-weight:bold;">
    <tr style="background:#dff0d8;">
        <td class="text-end">Total Meteran Indoor:</td>
        <td class="text-center"><?= round($total_all_m2_indoor, 2) ?></td>
    </tr>
    </table>
</div>