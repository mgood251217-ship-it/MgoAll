<?php
require_once 'get_order_ids.php';

$products_cetakan = [];
$product_data_cetakan = [];

if (!empty($order_ids)) {
    $in = implode(',', array_fill(0, count($order_ids), '?'));
    $types = str_repeat('i', count($order_ids)) . 'i';
    $params = array_merge($order_ids, [$store_id]);

    $queryStr = "
        SELECT p.product_id, p.name, oi.quantity 
        FROM products p 
        JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN order_items oi ON p.product_id = oi.product_id AND oi.order_id IN ($in)
        WHERE c.name = 'CETAKAN' AND p.store_id = ?
    ";

    $stmt = $koneksi->prepare($queryStr);
    if (!$stmt) die("Query error: " . $koneksi->error);

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $all_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $products_map = [];
    $data_assoc = [];

    foreach ($all_data as $row) {
        $pid = $row['product_id'];
        $name = $row['name'];

        if (!isset($products_map[$pid])) {
            $products_map[$pid] = [
                'product_id' => $pid,
                'name' => $name
            ];
        }

        if (!isset($data_assoc[$name])) {
            $data_assoc[$name] = [
                'name' => $name,
                'total_qty' => 0
            ];
        }

        if (!empty($row['quantity'])) {
            $data_assoc[$name]['total_qty'] += (int)$row['quantity'];
        }
    }

    $products_cetakan = array_values($products_map);
    $product_data_cetakan = array_values($data_assoc);
}
?>

<div class="table-responsive mb-3">
  <table class="table table-bordered">
    <thead class="table-primary">
      <tr><th>Nama Produk</th><th>Total Qty</th></tr>
    </thead>
    <tbody>
      <?php if (empty($product_data_cetakan)): ?>
        <tr><td colspan="2" class="text-center">Data kosong</td></tr>
      <?php else: ?>
        <?php foreach ($product_data_cetakan as $product): ?>
          <tr><td><?= htmlspecialchars($product['name']) ?></td><td><?= $product['total_qty'] ?></td></tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>