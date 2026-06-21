<?php
require_once 'get_order_ids.php';

$products = [];
$product_data = [];
$max_rows = 0;
$total_all_m2_outdoor = 0;

if (!empty($order_ids)) {
    $stmt = $koneksi->prepare("SELECT product_id, name, type FROM products WHERE type IN ('OUTDOOR', 'PAKET INDOOR OUTDOOR') AND store_id = ?");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $all_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $outdoor_products = [];
    $paket_products = [];
    
    foreach ($all_products as $p) {
        if ($p['type'] === 'OUTDOOR') {
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
        $in_orders = implode(',', array_fill(0, count($order_ids), '?'));
        $in_products = implode(',', array_fill(0, count($valid_product_ids), '?'));

        $types = str_repeat('i', count($order_ids)) . str_repeat('i', count($valid_product_ids));
        $params = array_merge($order_ids, $valid_product_ids);

        $queryStr = "SELECT product_id, size, quantity FROM order_items WHERE order_id IN ($in_orders) AND product_id IN ($in_products)";
        $query = $koneksi->prepare($queryStr);
        if (!$query) die("Query error: " . $koneksi->error);
        
        $query->bind_param($types, ...$params);
        $query->execute();
        $res = $query->get_result();

        while ($row = $res->fetch_assoc()) {
            $pid = $row['product_id'];
            $display_name = $productId_to_name[$pid];

            $size = $row['size'];
            $qty = (int)$row['quantity'];
            
            if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $size, $match)) {
                $p = floatval($match[1]);
                $l = floatval($match[2]);
                $m2 = $p * $l * $qty;

                $product_data_assoc[$display_name]['rows'][] = ['p' => $p, 'l' => $l, 'qty' => $qty, 'm2' => $m2];
            }
        }
        $query->close();
    }

    $product_data = array_values($product_data_assoc);
    foreach ($product_data as $product) {
        $count_rows = count($product['rows']);
        if ($count_rows > $max_rows) {
            $max_rows = $count_rows;
        }
    }
}
?>

<div class="excel-container" id="table-container" >
    <?php foreach ($product_data as $product): ?>
    <table class="excel-table" class="tableOutdoor">
    <thead >
        <tr><th colspan="4"><?= htmlspecialchars($product['name']) ?></th></tr>
        <tr><th>P</th><th>L</th><th>Qty</th><th>M2</th></tr>
    </thead>
    <tbody>
        <?php if (empty($product['rows'])): ?>
        <?php for ($i = 0; $i < $max_rows; $i++): ?>
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
            $total_all_m2_outdoor += $row['m2'];
        ?>
            <tr>
            <td><?= $row['p'] ?></td>
            <td><?= $row['l'] ?></td>
            <td><?= $row['qty'] ?></td>
            <td><?= $row['m2'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php for ($i = count($product['rows']); $i < $max_rows; $i++): ?>
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        <?php endfor; ?>
        <tr style="font-weight:bold; ">
            <td colspan="3" style="text-align:right;">Total m2:</td>
            <td><?= round($total_m2_product, 2) ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
    </table>
    <?php endforeach; ?>
</div>

<div class="mt-3 d-flex align-items-center justify-content-between">
    <table class="table table-bordered" style="max-width:500px; font-weight:bold;">
    <tr style="background:#dff0d8;">
        <td class="text-end">Total Meteran Outdoor:</td>
        <td class="text-center"><?= round($total_all_m2_outdoor, 2) ?></td>
    </tr>
    </table>
</div>