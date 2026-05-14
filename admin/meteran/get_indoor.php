<?php
require_once 'get_order_ids.php';

if (empty($order_ids)) {
    $products_indoor = [];
    $product_data_indoor = [];
    $max_rows_indoor = 0;
} else {
    // Ambil semua produk bertipe INDOOR
    $stmt = $koneksi->prepare("SELECT product_id, name FROM products WHERE type = 'INDOOR' AND store_id = ?");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $indoor_products = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Ambil juga produk bertipe PAKET INDOOR OUTDOOR
    $stmt = $koneksi->prepare("SELECT product_id, name FROM products WHERE type = 'PAKET INDOOR OUTDOOR' AND store_id = ?");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $paket_products = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Gabungkan indoor + paket yang namanya mirip
    $products_indoor = $indoor_products;
    foreach ($paket_products as $paket) {
        foreach ($indoor_products as $indoor) {
            if (stripos($paket['name'], $indoor['name']) !== false) {
                $products_indoor[] = [
                    'product_id' => $paket['product_id'],
                    'name' => $indoor['name'] // gunakan label nama indoor
                ];
                break;
            }
        }
    }

    // Proses data per produk
    $product_data_indoor = [];
    foreach ($products_indoor as $product) {
        $pid = $product['product_id'];
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
            $size = $row['size'];
            $qty = (int)$row['quantity'];
            if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $size, $match)) {
                $p = floatval($match[1]);
                $l = floatval($match[2]);
                $m2 = $p * $l * $qty;
                $rows[] = ['p' => $p, 'l' => $l, 'qty' => $qty, 'm2' => $m2];
            }
        }

        // Gabungkan ke entri existing jika nama sama
        $found = false;
        foreach ($product_data_indoor as &$pd) {
            if ($pd['name'] === $product['name']) {
                $pd['rows'] = array_merge($pd['rows'], $rows);
                $found = true;
                break;
            }
        }
        unset($pd);

        if (!$found) {
            $product_data_indoor[] = [
                'name' => $product['name'],
                'rows' => $rows
            ];
        }
    }

    // Hitung baris maksimal
    $max_rows_indoor = 0;
    foreach ($product_data_indoor as $product) {
        $count_rows = count($product['rows']);
        if ($count_rows > $max_rows_indoor) $max_rows_indoor = $count_rows;
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
            $total_all_m2_indoor += $row['m2']; // Akumulasi total keseluruhan indoor
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
<!-- TOTAL M2 INDOOR -->
<div class="mt-3">
    <table class="table table-bordered" style="max-width:500px; font-weight:bold;">
    <tr style="background:#dff0d8;">
        <td class="text-end">Total Meteran Indoor:</td>
        <td class="text-center"><?= round($total_all_m2_indoor, 2) ?></td>
    </tr>
    </table>
</div>