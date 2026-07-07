<?php
require_once 'get_order_ids.php';

$merch_keywords = ['ID CARD', 'PIN', 'GANCI', 'JAM', 'THUMBLER', 'FRAME A4', 'FRAME A3'];
$product_data_merch = [];

if (!empty($order_ids)) {
    $order_placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $types = 'i' . str_repeat('i', count($order_ids));
    $params = array_merge([$store_id], $order_ids);
    $like_conditions = [];
    foreach ($merch_keywords as $k) {
        $like_conditions[] = "p.name LIKE '%$k%'";
    }
    $like_sql = implode(' OR ', $like_conditions);

    $query = "
        SELECT p.name, COALESCE(SUM(oi.quantity), 0) AS total_qty
        FROM products p
        JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN order_items oi 
          ON oi.product_id = p.product_id AND oi.order_id IN ($order_placeholders)
        WHERE c.name = 'MERCENDISE'
          AND p.store_id = ?
          AND ($like_sql)
        GROUP BY p.name
        ORDER BY p.name ASC
    ";

    $stmt = $koneksi->prepare($query);
    if (!$stmt) {
        die("Query error: " . $koneksi->error);
    }

    $bind_params = array_merge($order_ids, [$store_id]);
    $bind_names = [];
    $bind_types = str_repeat('i', count($bind_params));
    foreach ($bind_params as $key => $value) {
        $bind_names[$key] = &$bind_params[$key];
    }
    array_unshift($bind_names, $bind_types);
    call_user_func_array([$stmt, 'bind_param'], $bind_names);

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $product_data_merch[$row['name']] = (int)$row['total_qty'];
    }

    $stmt->close();
} else {
    foreach ($merch_keywords as $keyword) {
        $product_data_merch[$keyword] = 0;
    }
}

foreach ($merch_keywords as $keyword) {
    $found = false;
    foreach ($product_data_merch as $name => $qty) {
        if (stripos($name, $keyword) !== false) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $product_data_merch[$keyword] = 0;
    }
}

foreach ($merch_keywords as $keyword) {
    $is_exist = false;
    foreach ($product_data_merch as $name => $qty) {
        if (stripos($name, $keyword) !== false) {
            $is_exist = true;
            break;
        }
    }

    if (!$is_exist) {
        $product_data_merch[$keyword] = 0;
    }
}


if (empty($order_ids)) {
    $total_qty_stamp = 0;
} else {
    $in = implode(',', array_fill(0, count($order_ids), '?'));
    $types = str_repeat('i', count($order_ids));

    $queryStr = "
        SELECT COALESCE(SUM(oi.quantity),0) AS total_qty_stamp
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        JOIN categories c ON p.category_id = c.category_id
        WHERE c.name = 'STAMP' AND oi.order_id IN ($in)
    ";

    $stmt = $koneksi->prepare($queryStr);
    if (!$stmt) {
        die("Query error: " . $koneksi->error);
    }

    $stmt->bind_param($types, ...$order_ids);
    $stmt->execute();
    $stmt->bind_result($total_qty_stamp);
    $stmt->fetch();
    $stmt->close();
}

?>

<div class="table-responsive mb-3">
<table class="table table-bordered">
    <thead class="table-primary">
    <tr><th>Nama Produk</th><th>Total Qty</th></tr>
    </thead>
    <tbody>
    <?php foreach ($product_data_merch as $name => $qty): ?>
        <tr><td><?= htmlspecialchars($name) ?></td><td><?= $qty ?></td></tr>
    <?php endforeach; ?>

    <tr><td>STAMP</td><td><?= $total_qty_stamp ?? 0 ?></td></tr>
    
    </tbody>
</table>
</div>