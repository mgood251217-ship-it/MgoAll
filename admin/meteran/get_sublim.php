<?php
require_once 'get_order_ids.php';

// SUBLIM
if (empty($order_ids)) {
    $products_sublim = [];
    $product_data_sublim = [];
    $max_rows_sublim = 0;
} else {
    // Ambil semua produk bertipe SUBLIM
    $stmt = $koneksi->prepare("SELECT product_id, name FROM products
      WHERE type = 'SUBLIM' AND store_id = ? AND (name LIKE '%TRANSFERPAPER%' OR name LIKE '%PRINT PRES%')");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $products_sublim = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Proses data per produk
    $product_data_sublim = [];
    foreach ($products_sublim as $product) {
        $pid = $product['product_id'];
        $in = implode(',', array_fill(0, count($order_ids), '?'));
        $types = str_repeat('i', count($order_ids) + 1);
        $params = array_merge([$pid], $order_ids);

        $queryStr = "SELECT size, quantity FROM order_items WHERE product_id = ? AND order_id IN ($in)";
        $query = $koneksi->prepare($queryStr);
        if (!$query) die("Query error: " . $koneksi->error);
        $query->bind_param($types, ...$params);
        $query->execute();
        $res = $query->get_result();

        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $size = $row['size'];
            $qty  = (int)$row['quantity'];

            if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $size, $m)) {
                $p = floatval($m[1]);
                $l = floatval($m[2]);
                $m2 = $p * $l * $qty;

                if (in_array($p, [1.1, 1.2, 1.5, 1.8])) {
                    $rows[] = ['p' => $l, 'l' => $p, 'qty' => $qty, 'm2' => $m2];
                } else {
                    $rows[] = ['p' => $p, 'l' => $l, 'qty' => $qty, 'm2' => $m2];
                }
            }
        }

        $name = strtoupper($product['name']);
        $is_transfer = (strpos($name, 'TRANSFERPAPER') !== false || strpos($name, 'PRINT PRESS') !== false);

        if ($is_transfer) {
            $lebar_list = [1.1, 1.2, 1.5, 1.8];
            $grouped_by_lebar = [
                '1.1' => [],
                '1.2' => [],
                '1.5' => [],
                '1.8' => [],
                'LAINNYA' => []
            ];

            foreach ($rows as $r) {
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
                    'name' => $product['name'] . " (" . $label . ")",
                    'rows' => $rows_lebar
                ];
            }

        } else {
            $found = false;
            foreach ($product_data_sublim as &$pd) {
                if ($pd['name'] === $product['name']) {
                    $pd['rows'] = array_merge($pd['rows'], $rows);
                    $found = true;
                    break;
                }
            }
            unset($pd);

            if (!$found) {
                $product_data_sublim[] = [
                    'name' => $product['name'],
                    'rows' => $rows
                ];
            }
        }
    }

    $max_rows_sublim = 0;
    foreach ($product_data_sublim as $product) {
        $row_count = count($product['rows']);
        if ($row_count > $max_rows_sublim) $max_rows_sublim = $row_count;
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
            $total_all_m2_sublim += $row['m2']; // Akumulasi total keseluruhan indoor
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

<!-- TOTAL M2 SUBLIM -->
<div class="mt-3">
    <table class="table table-bordered" style="max-width:500px; font-weight:bold;">
    <tr style="background:#dff0d8;">
        <td class="text-end">Total Meteran Sublim:</td>
        <td class="text-center"><?= round($total_all_m2_sublim, 2) ?></td>
    </tr>
    </table>
</div>