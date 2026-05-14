<?php
require_once 'get_order_ids.php';

// DTF dan DTF UV
if (empty($order_ids)) {
    $products_dtf = [];
    $product_data_dtf = [];
    $max_rows_dtf = 0;
} else {
    $stmt = $koneksi->prepare("SELECT product_id, name FROM products WHERE type = 'DTF' AND store_id = ?");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $products_dtf = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $product_data_dtf = [];
    foreach ($products_dtf as $product) {
        $pid = $product['product_id'];
        $product_name = strtolower($product['name']);

        $isDTFA3 = $product_name === 'dtf a3' || str_contains($name, 'KAOS');
        $isDTFUV_A3 = $product_name === 'dtf uv a3';

        $in = implode(',', array_fill(0, count($order_ids), '?'));
        $types = str_repeat('i', count($order_ids) + 1);
        $params = array_merge([$pid], $order_ids);

        $queryStr = "SELECT size, quantity FROM order_items WHERE product_id = ? AND order_id IN ($in)";
        $query = $koneksi->prepare($queryStr);
        if (!$query) {
            die("Query error: " . $koneksi->error);
        }
        $query->bind_param($types, ...$params);
        $query->execute();
        $res = $query->get_result();

        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $qty = (int)$row['quantity'];

            if ($isDTFA3 || $isDTFUV_A3) {

                $rows[] = ['qty' => $qty];
            } else {
                if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $row['size'], $match)) {
                    $p = floatval($match[1]);
                    $rows[] = ['p' => $p, 'qty' => $qty, 'total_panjang' => $p * $qty];
                }
            }
        }

        $product_data_dtf[] = [
            'name' => $product['name'],
            'rows' => $rows
        ];
    }

    $max_rows_dtf = 0;
    foreach ($product_data_dtf as $product) {
        $count_rows = count($product['rows']);
        if ($count_rows > $max_rows_dtf) $max_rows_dtf = $count_rows;
    }
}

?>

<div class="excel-container" id="table-container-dtf">
    <?php 
    $total_panjang_dtf = 0;
    $total_qty_dtf_a3 = 0;
    $total_panjang_dtf_uv = 0;
    $total_qty_dtf_uv_a3 = 0;

    $dtf_biasa = ['DTF', 'DTF A3', 'DTF TEBAL', 'DTF 28'];
    $dtf_uv = ['DTF UV GLOSSY', 'DTF UV DOFF', 'DTF UV A3'];
    ?>

    <?php foreach ($product_data_dtf as $product): ?>
    <?php 
        $name = $product['name'];
        $rows = $product['rows'] ?? [];

        $isA3 = $name === 'DTF A3' || str_contains($name, 'KAOS');
        $isUV = in_array($name, $dtf_uv);
        $isUV_A3 = $name === 'DTF UV A3';
        $isDTFBiasa = in_array($name, $dtf_biasa) || (strtok($name, ' ') == 'DTF') && (str_contains($name, 'UV') === false);
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

                // Akumulasi
                if ($isA3) {
                $total_qty_dtf_a3 += $row['qty'];
                } elseif ($isUV_A3) {
                $total_qty_dtf_uv_a3 += $row['qty'];
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
<!-- RINGKASAN DTF -->
<div class="mt-3">
    <table class="table table-bordered" style="max-width:500px; font-weight:bold;">
    <tr style="background:#dff0d8;">
        <td class="text-end">Total Panjang DTF (Meteran):</td>
        <td class="text-center"><?= round($total_panjang_dtf, 2) ?></td>
    </tr>
    <tr>
        <td class="text-end">Total Qty DTF A3:</td>
        <td class="text-center"><?= (int)$total_qty_dtf_a3 ?></td>
    </tr>
    <tr>
        <td class="text-end">Total Panjang DTF UV:</td>
        <td class="text-center"><?= round($total_panjang_dtf_uv, 2) ?></td>
    </tr>
    <tr>
        <td class="text-end">Total Qty DTF UV A3:</td>
        <td class="text-center"><?= (int)$total_qty_dtf_uv_a3 ?></td>
    </tr>
    </table>
</div>