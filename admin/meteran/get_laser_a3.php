<?php
require_once 'get_order_ids.php';

// LASER A3
if (empty($order_ids)) {
    $products_laser_a3 = [];
    $product_data_laser_a3 = [];
} else {
    $stmt = $koneksi->prepare("SELECT product_id, name FROM products WHERE type = 'LASER A3' AND store_id = ?");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $products_laser_a3 = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $product_data_laser_a3 = [];
    foreach ($products_laser_a3 as $product) {
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

        $product_data_laser_a3[] = [
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
                    <?php if (empty($product_data_laser_a3)): ?>
                      <tr><td colspan="2" class="text-center">Data kosong</td></tr>
                    <?php else: ?>
                      <?php foreach ($product_data_laser_a3 as $product): ?>
                        <tr><td><?= htmlspecialchars($product['name']) ?></td><td><?= $product['total_qty'] ?></td></tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
              <?php
                $total_qty_all_laser_a3 = 0;
                foreach ($product_data_laser_a3 as $product) {
                  $total_qty_all_laser_a3 += $product['total_qty'];
                }
              ?>
              <!-- TOTAL LASER A3 -->
              <div class="mt-3">
                <table class="table table-bordered" style="max-width:500px; font-weight:bold;">
                  <tr style="background:#dff0d8;">
                    <td class="text-end">Total Qty Laser A3:</td>
                    <td class="text-center"><?= $total_qty_all_laser_a3 ?></td>
                  </tr>
                </table>
              </div>