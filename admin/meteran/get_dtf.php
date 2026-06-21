<?php
require_once 'get_order_ids.php';

$products_dtf = [];
$product_data_dtf = [];
$max_rows_dtf = 0;

if (!empty($order_ids)) {
    $in = implode(',', array_fill(0, count($order_ids), '?'));
    $types = str_repeat('i', count($order_ids)) . 'i';
    $params = array_merge($order_ids, [$store_id]);

    $queryStr = "
        SELECT p.product_id, p.name, oi.size, oi.quantity 
        FROM products p 
        LEFT JOIN order_items oi ON p.product_id = oi.product_id AND oi.order_id IN ($in)
        WHERE p.type = 'DTF' AND p.store_id = ?
    ";

    $stmt = $koneksi->prepare($queryStr);
    if (!$stmt) die("Query error: " . $koneksi->error);

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $all_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $data_assoc = [];

    foreach ($all_data as $row) {
        $name = $row['name'];
        $product_name_lower = strtolower($name);

        $isDTFA3 = $product_name_lower === 'dtf a3' || str_contains($product_name_lower, 'kaos');
        $isDTFUV_A3 = $product_name_lower === 'dtf uv a3';

        if (!isset($data_assoc[$name])) {
            $data_assoc[$name] = [
                'name' => $name,
                'rows' => []
            ];
        }

        if (!empty($row['quantity'])) {
            $qty = (int)$row['quantity'];

            if ($isDTFA3 || $isDTFUV_A3) {
                $data_assoc[$name]['rows'][] = ['qty' => $qty];
            } else {
                if (!empty($row['size']) && preg_match('/^([\d.]+)[xX]([\d.]+)$/', $row['size'], $match)) {
                    $p = floatval($match[1]);
                    $data_assoc[$name]['rows'][] = ['p' => $p, 'qty' => $qty, 'total_panjang' => $p * $qty];
                }
            }
        }
    }

    $product_data_dtf = array_values($data_assoc);

    foreach ($product_data_dtf as $product) {
        $count_rows = count($product['rows']);
        if ($count_rows > $max_rows_dtf) {
            $max_rows_dtf = $count_rows;
        }
    }
}
?>

<div class="excel-container" id="table-container-dtf">
    <?php 
    $total_panjang_dtf = 0;
    $total_panjang_dtf_uv = 0;

    $dtf_biasa = ['DTF', 'DTF A3', 'DTF TEBAL', 'DTF 28'];
    $dtf_uv = ['DTF UV GLOSSY', 'DTF UV DOFF', 'DTF UV A3'];
    ?>

    <?php foreach ($product_data_dtf as $product): ?>
    <?php 
        $name = $product['name'];
        $name_upper = strtoupper($name);
        $rows = $product['rows'] ?? [];

        $isA3 = $name_upper === 'DTF A3' || str_contains($name_upper, 'KAOS');
        $isUV = in_array($name_upper, $dtf_uv);
        $isUV_A3 = $name_upper === 'DTF UV A3';
        $isDTFBiasa = in_array($name_upper, $dtf_biasa) || (strtok($name_upper, ' ') == 'DTF' && str_contains($name_upper, 'UV') === false);
        
        if (!$isDTFBiasa && !$isUV) continue;

        $subtotal = 0;
    ?>
    <table class="excel-table">
        <thead>
        <tr><th colspan="3"><?= htmlspecialchars($name) ?></th></tr>
        <tr>
            <th><?= ($isA3 || $isUV_A3) ? 'Qty' : 'Panjang' ?></th>
            <th>Qty</th>
            <th><?= ($isA3 || $isUV_A3) ? 'Total Qty' : 'Total Panjang' ?></th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <?php for ($i = 0; $i < $max_rows_dtf; $i++): ?>
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            <?php endfor; ?>
            <tr style="font-weight:bold;">
            <td colspan="2" style="text-align:right;">Total:</td>
            <td>0</td>
            </tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <?php 
                $total = ($isA3 || $isUV_A3) ? $row['qty'] : $row['total_panjang'];
                $subtotal += $total;

                if ($isA3) {
                    $total_panjang_dtf += ($row['qty'] * 0.2);
                } elseif ($isUV_A3) {
                    $total_panjang_dtf_uv += ($row['qty'] * 0.2);
                } elseif ($isUV) {
                    $total_panjang_dtf_uv += $row['total_panjang'];
                } else {
                    $total_panjang_dtf += $row['total_panjang'];
                }
            ?>
            <tr>
                <td><?= ($isA3 || $isUV_A3) ? '-' : $row['p'] ?></td>
                <td><?= $row['qty'] ?></td>
                <td><?= round($total, 2) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php for ($i = count($rows); $i < $max_rows_dtf; $i++): ?>
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            <?php endfor; ?>
            <tr style="font-weight:bold;">
            <td colspan="2" style="text-align:right;">Total:</td>
            <td><?= round($subtotal, 2) ?></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php endforeach; ?>
</div>

<div class="mt-3">
    <table class="table table-bordered" style="max-width:500px; font-weight:bold;">
    <tr style="background:#dff0d8;">
        <td class="text-end">Total Panjang DTF (Meteran):</td>
        <td class="text-center"><?= round($total_panjang_dtf, 2) ?></td>
    </tr>
    <tr>
        <td class="text-end">Total Panjang DTF UV:</td>
        <td class="text-center"><?= round($total_panjang_dtf_uv, 2) ?></td>
    </tr>
    </table>
</div>