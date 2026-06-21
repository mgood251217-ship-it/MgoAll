<?php
require_once 'get_order_ids.php';

$product_data_laser_a3 = [];

if (!empty($order_ids)) {
    $in = implode(',', array_fill(0, count($order_ids), '?'));
    $types = str_repeat('i', count($order_ids)) . 'i';
    $params = array_merge($order_ids, [$store_id]);

    $queryStr = "
        SELECT p.name, COALESCE(SUM(oi.quantity), 0) AS total_qty
        FROM products p
        LEFT JOIN order_items oi ON p.product_id = oi.product_id AND oi.order_id IN ($in)
        WHERE p.type = 'LASER A3' AND p.store_id = ?
        GROUP BY p.product_id, p.name
    ";
    
    $stmt = $koneksi->prepare($queryStr);
    if (!$stmt) die("Query error: " . $koneksi->error);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while ($row = $res->fetch_assoc()) {
        $product_data_laser_a3[$row['name']] = (int)$row['total_qty'];
    }
    $stmt->close();

    $queryTambahan = "
        SELECT p.name, COALESCE(SUM(oi.quantity), 0) AS total_qty
        FROM products p
        LEFT JOIN order_items oi ON p.product_id = oi.product_id AND oi.order_id IN ($in)
        WHERE p.store_id = ? AND (
            (p.type = 'KARTU NAMA' AND p.name LIKE '%KN%') OR 
            (p.type = 'MERCENDISE' AND p.name LIKE '%JAM%')
        )
        GROUP BY p.product_id, p.name
    ";
    
    $stmt2 = $koneksi->prepare($queryTambahan);
    $stmt2->bind_param($types, ...$params);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    
    $qty_kn = 0;
    $qty_kn_bb = 0;
    $qty_jam = 0;

    while ($row = $res2->fetch_assoc()) {
        $name_upper = strtoupper($row['name']);
        $qty = (int)$row['total_qty'];
        
        if (strpos($name_upper, 'JAM') !== false) {
            $qty_jam += $qty;
        } elseif (strpos($name_upper, 'KN') !== false && strpos($name_upper, 'BB') !== false) {
            $qty_kn_bb += $qty;
        } elseif (strpos($name_upper, 'KN') !== false) {
            $qty_kn += $qty;
        }
    }
    $stmt2->close();

    $tambahan_ap260 = ($qty_kn * 4) + ($qty_kn_bb * 8) + ($qty_jam * 1);

    if ($tambahan_ap260 > 0) {
        $ap260_found = false;
        foreach ($product_data_laser_a3 as $lname => $lqty) {
            $lname_upper = strtoupper($lname);
            if (strpos($lname_upper, 'AP260') !== false || strpos($lname_upper, 'AP 260') !== false) {
                $product_data_laser_a3[$lname] += $tambahan_ap260;
                $ap260_found = true;
                break;
            }
        }
        
        if (!$ap260_found) {
            $product_data_laser_a3['AP260'] = $tambahan_ap260;
        }
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
        <?php foreach ($product_data_laser_a3 as $name => $qty): ?>
          <tr><td><?= htmlspecialchars($name) ?></td><td><?= $qty ?></td></tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
  $total_qty_all_laser_a3 = 0;
  foreach ($product_data_laser_a3 as $qty) {
    $total_qty_all_laser_a3 += $qty;
  }
?>

<div class="mt-3">
  <table class="table table-bordered" style="max-width:500px; font-weight:bold;">
    <tr style="background:#dff0d8;">
      <td class="text-end">Total Qty Laser A3:</td>
      <td class="text-center"><?= $total_qty_all_laser_a3 ?></td>
    </tr>
  </table>
</div>