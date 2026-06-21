<?php
require_once 'get_order_ids.php';

$product_data_mercendise_akrilik = [];

if (!empty($order_ids)) {
    $in = implode(',', array_fill(0, count($order_ids), '?'));
    $types = str_repeat('i', count($order_ids)) . 'i';
    $params = array_merge($order_ids, [$store_id]);

    $queryStr = "
        SELECT p.name, COALESCE(SUM(oi.quantity), 0) AS total_qty
        FROM products p
        LEFT JOIN order_items oi ON p.product_id = oi.product_id AND oi.order_id IN ($in)
        WHERE p.type = 'MERCENDISE AKRILIK' AND p.store_id = ?
        GROUP BY p.product_id, p.name
    ";

    $stmt = $koneksi->prepare($queryStr);
    if (!$stmt) die("Query error: " . $koneksi->error);

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $product_data_mercendise_akrilik[] = [
            'name' => $row['name'],
            'total_qty' => (int)$row['total_qty']
        ];
    }
    $stmt->close();
}
?>

<div class="table-responsive mb-3">
  <table class="table table-bordered">
    <thead class="table-primary">
      <tr><th>Nama Produk</th><th>Total Qty</th></tr>
    </thead>
    <tbody>
      <?php if (empty($product_data_mercendise_akrilik)): ?>
        <tr><td colspan="2" class="text-center">Data kosong</td></tr>
      <?php else: ?>
        <?php foreach ($product_data_mercendise_akrilik as $product): ?>
          <tr><td><?= htmlspecialchars($product['name']) ?></td><td><?= $product['total_qty'] ?></td></tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>