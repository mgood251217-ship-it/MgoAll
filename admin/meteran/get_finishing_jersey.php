<?php
require_once 'get_order_ids.php';

if (empty($order_ids)) {
    $product_data_finishing_jersey = [];
} else {

    $stmt = $koneksi->prepare("
        SELECT p.product_id, p.name
        FROM products p
        JOIN categories c ON p.category_id = c.category_id
        WHERE c.name = 'FINISHING JERSEY'
        AND p.store_id = ?
        ORDER BY p.name
    ");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $finishings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $finishing_totals = [];
    foreach ($finishings as $f) {
        $finishing_totals[$f['product_id']] = 0;
    }

    $in    = implode(',', array_fill(0, count($order_ids), '?'));
    $types = str_repeat('i', count($order_ids));

    $stmt = $koneksi->prepare("
        SELECT finishing, quantity
        FROM order_items
        WHERE finishing IS NOT NULL
        AND finishing != ''
        AND order_id IN ($in)
    ");
    $stmt->bind_param($types, ...$order_ids);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {


        $qty = (int)$row['quantity'];
        if ($qty <= 0) {
            $qty = 1;
        }

        $fin_ids = array_map('trim', explode(',', $row['finishing']));

        foreach ($fin_ids as $fid) {
            if ($fid === '') continue;

            if (isset($finishing_totals[$fid])) {
                $finishing_totals[$fid] += $qty;
            }
        }
    }
    $stmt->close();
    $product_data_finishing_jersey = [];

    foreach ($finishings as $fin) {
        $fid = $fin['product_id'];

        $product_data_finishing_jersey[] = [
            'name'      => $fin['name'],
            'total_qty' => $finishing_totals[$fid]
        ];
    }
}

?>

<div class="table-responsive mb-3">
<table class="table table-bordered">
    <thead class="table-primary">
    <tr><th>Nama Produk</th><th>Total Qty</th></tr>
    </thead>
    <tbody>
    <?php if (empty($product_data_finishing_jersey)): ?>
        <tr><td colspan="2" class="text-center">Data kosong</td></tr>
    <?php else: ?>
        <?php foreach ($product_data_finishing_jersey as $product): ?>
        <tr><td><?= htmlspecialchars($product['name']) ?></td><td><?= $product['total_qty'] ?></td></tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
</div>
<?php
$total_qty_all_finishing_jersey = 0;
foreach ($product_data_finishing_jersey as $product) {
    $total_qty_all_finishing_jersey += $product['total_qty'];
}
?>

<div class="mt-3">
<table class="table table-bordered" style="max-width:500px; font-weight:bold;">
    <tr style="background:#dff0d8;">
    <td class="text-end">Total Qty Finishing Jersey:</td>
    <td class="text-center"><?= $total_qty_all_finishing_jersey ?></td>
    </tr>
</table>
</div>