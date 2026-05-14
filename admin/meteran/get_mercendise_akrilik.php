<?php
require_once 'get_order_ids.php';

// MERCENDISE AKRILIK
if (empty($order_ids)) {
    $products_mercendise_akrilik = [];
    $product_data_mercendise_akrilik = [];
} else {
    $stmt = $koneksi->prepare("SELECT product_id, name FROM products WHERE type = 'MERCENDISE AKRILIK' AND store_id = ?");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $products_mercendise_akrilik = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $product_data_mercendise_akrilik = [];
    foreach ($products_mercendise_akrilik as $product) {
        $pid = $product['product_id'];
        $in = implode(',', array_fill(0, count($order_ids), '?'));
        $types = str_repeat('i', count($order_ids) + 1);
        $params = array_merge([$pid], $order_ids);

        $queryStr = "SELECT quantity FROM order_items WHERE product_id = ? AND order_id IN ($in)";
        $query = $koneksi->prepare($queryStr);
        if (!$query) {
            die("Query error: " . $koneksi->error);
        }
        $query->bind_param($types, ...$params);
        $query->execute();
        $res = $query->get_result();

        $total_qty = 0;
        while ($row = $res->fetch_assoc()) {
            $qty = (int)$row['quantity'];
            $total_qty += $qty;
        }

        $product_data_mercendise_akrilik[] = [
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