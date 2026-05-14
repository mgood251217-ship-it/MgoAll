<?php
require_once 'get_order_ids.php';

// BAHAN
if (empty($order_ids)) {

    $products_bahan = [];
    $product_data_bahan_meteran = [];
    $product_data_bahan_kiloan  = [];
    $max_rows_bahan_meteran = 0;
    $max_rows_bahan_kiloan  = 0;

} else {

    $stmt = $koneksi->prepare("
        SELECT product_id, name, unit_type
        FROM products
        WHERE type = 'SUBLIM'
          AND store_id = ?
          AND name LIKE '%BAHAN%'
    ");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $products_bahan = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $product_data_bahan_meteran = [];
    $product_data_bahan_kiloan  = [];

    foreach ($products_bahan as $product) {

        $pid = $product['product_id'];

        $in     = implode(',', array_fill(0, count($order_ids), '?'));
        $types  = str_repeat('i', count($order_ids) + 1);
        $params = array_merge([$pid], $order_ids);

        $queryStr = "
            SELECT size, quantity
            FROM order_items
            WHERE product_id = ?
              AND order_id IN ($in)
        ";

        $query = $koneksi->prepare($queryStr);
        if (!$query) {
            die("Query error: " . $koneksi->error);
        }

        $query->bind_param($types, ...$params);
        $query->execute();
        $res = $query->get_result();

        $rows_meteran = [];
        $rows_kiloan  = [];

        while ($row = $res->fetch_assoc()) {

            $size = strtoupper(trim($row['size']));
            $qty  = (int)$row['quantity'];

            if ($product['unit_type'] === 'M2') {

                if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $size, $m)) {

                    $p  = (float)$m[1];
                    $l  = (float)$m[2];
                    $m2 = $p * $l * $qty;

                    $rows_meteran[] = [
                        'p'   => $p,
                        'l'   => $l,
                        'qty' => $qty,
                        'm2'  => $m2
                    ];
                }
            }

            elseif ($product['unit_type'] === 'PCS') {

                // contoh: 23KG
                if (preg_match('/([\d.]+)\s*KG/', $size, $m)) {

                    $kg       = (float)$m[1];
                    $total_kg = $kg * $qty;

                    $rows_kiloan[] = [
                        'kg'  => $kg,
                        'qty' => $qty,
                        'kg_total' => $total_kg
                    ];
                }
            }
        }

        $query->close();

        if (!empty($rows_meteran)) {

            $found = false;
            foreach ($product_data_bahan_meteran as &$pd) {
                if ($pd['name'] === $product['name']) {
                    $pd['rows'] = array_merge($pd['rows'], $rows_meteran);
                    $found = true;
                    break;
                }
            }
            unset($pd);

            if (!$found) {
                $product_data_bahan_meteran[] = [
                    'name' => $product['name'],
                    'rows' => $rows_meteran
                ];
            }
        }

        if (!empty($rows_kiloan)) {

            $found = false;
            foreach ($product_data_bahan_kiloan as &$pd) {
                if ($pd['name'] === $product['name']) {
                    $pd['rows'] = array_merge($pd['rows'], $rows_kiloan);
                    $found = true;
                    break;
                }
            }
            unset($pd);

            if (!$found) {
                $product_data_bahan_kiloan[] = [
                    'name' => $product['name'],
                    'rows' => $rows_kiloan
                ];
            }
        }
    }

    $max_rows_bahan_meteran = 0;
    foreach ($product_data_bahan_meteran as $p) {
        $max_rows_bahan_meteran = max($max_rows_bahan_meteran, count($p['rows']));
    }

    $max_rows_bahan_kiloan = 0;
    foreach ($product_data_bahan_kiloan as $p) {
        $max_rows_bahan_kiloan = max($max_rows_bahan_kiloan, count($p['rows']));
    }
    $max_rows_bahan_asli = 0;
    if ($max_rows_bahan_kiloan >= $max_rows_bahan_meteran) {
      $max_rows_bahan_asli = $max_rows_bahan_kiloan;
    }else {
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
