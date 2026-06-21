<?php
require_once 'get_order_ids.php';

$product_data_sublim = [];
$max_rows_sublim = 0;
$total_all_m2_sublim = 0;

if (!empty($order_ids)) {
    $in = implode(',', array_fill(0, count($order_ids), '?'));
    $types = str_repeat('i', count($order_ids)) . 'i';
    $params = array_merge($order_ids, [$store_id]);

    $queryStr = "
        SELECT p.product_id, p.name, oi.size, oi.quantity 
        FROM products p 
        LEFT JOIN order_items oi ON p.product_id = oi.product_id AND oi.order_id IN ($in)
        WHERE p.type = 'SUBLIM' AND p.store_id = ? 
        AND (p.name LIKE '%TRANSFERPAPER%' OR p.name LIKE '%PRINT PRES%')
    ";

    $stmt = $koneksi->prepare($queryStr);
    if (!$stmt) die("Query error: " . $koneksi->error);

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $all_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $products_map = [];

    foreach ($all_data as $row) {
        $pid = $row['product_id'];
        $name = $row['name'];

        if (!isset($products_map[$pid])) {
            $products_map[$pid] = [
                'name' => $name,
                'rows' => []
            ];
        }

        if (!empty($row['size']) && !empty($row['quantity'])) {
            $size = $row['size'];
            $qty = (int)$row['quantity'];

            if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $size, $m)) {
                $p = floatval($m[1]);
                $l = floatval($m[2]);
                $m2 = $p * $l * $qty;

                if (in_array($p, [1.1, 1.2, 1.5, 1.8])) {
                    $products_map[$pid]['rows'][] = ['p' => $l, 'l' => $p, 'qty' => $qty, 'm2' => $m2];
                } else {
                    $products_map[$pid]['rows'][] = ['p' => $p, 'l' => $l, 'qty' => $qty, 'm2' => $m2];
                }
            }
        }
    }

    $lebar_list = [1.1, 1.2, 1.5, 1.8];

    foreach ($products_map as $pid => $pdata) {
        $name_upper = strtoupper($pdata['name']);
        $is_transfer = (strpos($name_upper, 'TRANSFERPAPER') !== false || strpos($name_upper, 'PRINT PRES') !== false);

        if ($is_transfer) {
            $grouped_by_lebar = [
                '1.1' => [],
                '1.2' => [],
                '1.5' => [],
                '1.8' => [],
                'LAINNYA' => []
            ];

            foreach ($pdata['rows'] as $r) {
                $lebar = $r['l'];
                if (in_array($lebar, $lebar_list)) {
                    $key = strval($lebar);
                } else {
                    $key = 'LAINNYA';
                }
                $grouped_by_lebar[$key][] = $r;
            }

            foreach ($grouped_by_lebar as $lebar => $rows_lebar) {
                $label = ($lebar === 'LAINNYA') ? 'LAINNYA' : $lebar . 'm';
                $product_data_sublim[] = [
                    'name' => $pdata['name'] . " (" . $label . ")",
                    'rows' => $rows_lebar
                ];
            }
        } else {
            $product_data_sublim[] = [
                'name' => $pdata['name'],
                'rows' => $pdata['rows']
            ];
        }
    }

    foreach ($product_data_sublim as $product) {
        $row_count = count($product['rows']);
        if ($row_count > $max_rows_sublim) {
            $max_rows_sublim = $row_count;
        }
    }
}
?>

<div class="excel-container" id="table-container-indoor">
    <?php foreach ($product_data_sublim as $product): ?>
    <table class="excel-table">
    <thead>
        <tr><th colspan="4"><?= htmlspecialchars($product['name']) ?></th></tr>
        <tr><th>P</th><th>L</th><th>Qty</th><th>M2</th></tr>
    </thead>
    <tbody>
        <?php if (empty($product['rows'])): ?>
        <?php for ($i = 0; $i < $max_rows_sublim; $i++): ?>
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
        <?php for ($i = count($product['rows']); $i < $max_rows_sublim; $i++): ?>
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
        <td class="text-end">Total Meteran Sublim:</td>
        <td class="text-center"><?= round($total_all_m2_sublim, 2) ?></td>
    </tr>
    </table>
</div>