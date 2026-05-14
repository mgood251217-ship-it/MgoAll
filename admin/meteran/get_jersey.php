<?php
require_once 'get_order_ids.php';

// JERSEY
if (empty($order_ids)) {
    $product_data_jersey = [];
} else {
    $stmt = $koneksi->prepare("
        SELECT product_id, name 
        FROM products 
        WHERE type = 'JERSEY' AND store_id = ?
    ");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $products_jersey = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $product_data_jersey = [];

    foreach ($products_jersey as $product) {
        $pid = $product['product_id'];

        $in     = implode(',', array_fill(0, count($order_ids), '?'));
        $types  = str_repeat('i', count($order_ids) + 1);
        $params = array_merge([$pid], $order_ids);

        $query = $koneksi->prepare("
            SELECT quantity 
            FROM order_items 
            WHERE product_id = ? AND order_id IN ($in)
        ");
        $query->bind_param($types, ...$params);
        $query->execute();
        $res = $query->get_result();

        $total_qty = 0;
        while ($row = $res->fetch_assoc()) {
            $total_qty += (int)$row['quantity'];
        }

        $product_data_jersey[] = [
            'name' => $product['name'],
            'total_qty' => $total_qty
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
    <?php if (empty($product_data_jersey)): ?>
        <tr><td colspan="2" class="text-center">Data kosong</td></tr>
    <?php else: ?>
        <?php foreach ($product_data_jersey as $product): ?>
        <tr><td><?= htmlspecialchars($product['name']) ?></td><td><?= $product['total_qty'] ?></td></tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
</div>
<?php
$total_qty_all_jersey = 0;
foreach ($product_data_jersey as $product) {
    $total_qty_all_jersey += $product['total_qty'];
}
?>
<!-- JERSEY -->
<div class="mt-3">
<table class="table table-bordered" style="max-width:500px; font-weight:bold;">
    <tr style="background:#dff0d8;">
    <td class="text-end">Total Qty Jersey:</td>
    <td class="text-center"><?= $total_qty_all_jersey ?></td>
    </tr>
</table>
</div>