<?php
require_once '../connect.php';
require_once BASE_PATH . '/functions/user_validation.php';

$user_id = $_SESSION['shopee_users']['user_id'] ?? 0;

date_default_timezone_set('Asia/Jakarta');

$date = $_GET['d'] ?? date('Y-m');
$start_date = date('Y-m-01', strtotime($date . '-01'));
$end_date = date('Y-m-t', strtotime($start_date));

$stmtProducts = $koneksi->prepare("
    SELECT 
        p.product_id, 
        p.name, 
        p.unit_type, 
        p.type,
        IFNULL(SUM(lm.value), 0) AS total_meteran,
        GROUP_CONCAT(CONCAT(lm.list_meter_id, ':', lm.value) SEPARATOR ',') AS list_meteran_raw
    FROM products p
    LEFT JOIN orders o ON o.user_id = p.user_id 
        AND o.date BETWEEN ? AND ?
    LEFT JOIN list_meters lm ON o.id = lm.order_id 
        AND lm.product_id = p.product_id
    WHERE p.user_id = ?
    GROUP BY p.product_id
    ORDER BY p.type DESC
");

$stmtProducts->bind_param("ssi", $start_date, $end_date, $user_id);
$stmtProducts->execute();
$resultProducts = $stmtProducts->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard</title>
  <?php include BASE_PATH . '/elements/header.php'; ?>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/content.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dark_mode.css">
</head>

<body>
<div id="main-wrapper" <?= ($mode ?? 0) === 1 ? 'class="dark-mode"' : '' ?>>
  <?php include BASE_PATH . '/elements/navbar.php'; ?>
  <div id="page-content-wrapper" <?= ($mode ?? 0) === 1 ? 'class="dark-mode"' : '' ?>>

    <form class="shopee-form" id="formTanggal" method="get">
        <input type="month" name="d" id="d" value="<?= htmlspecialchars($date) ?>" onchange="this.form.submit()">
    </form>
    <table class="shopee-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Type Produk</th>
                <th>Nama Produk</th>
                <th>Total Meteran</th>
                <th>Satuan</th>
                <th>List Meteran</th>
                <th>Tanggal</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1; 
            while ($product = $resultProducts->fetch_assoc()) { 
            ?>
            <tr>
                <td><?= $no ?></td>
                <td><?= htmlspecialchars($product['type']) ?></td>
                <td><?= htmlspecialchars($product['name']) ?></td>
                <td><?= htmlspecialchars($product['total_meteran']) ?></td>
                <td><?= htmlspecialchars($product['unit_type']) ?></td>
                <td>
                    <?php
                    if (!empty($product['list_meteran_raw'])) {
                        // Memecah string GROUP_CONCAT menjadi array data meteran
                        $metersArray = explode(',', $product['list_meteran_raw']);
                        foreach ($metersArray as $meterItem) {
                            list($list_meter_id, $value) = explode(':', $meterItem);
                            ?>
                            <span style="margin-right: 8px; display: inline-block; cursor: pointer;"
                                  class="deleteList" data-id="<?= htmlspecialchars($list_meter_id) ?>">
                                <?= htmlspecialchars($value) ?>
                            </span>
                            <?php
                        }
                    } else {
                        echo "No Data";
                    }
                    ?>
                </td>
                <td><?= $start_date . ' s/d ' . $end_date ?></td>
            </tr>
            <?php 
            $no++;
            } 
            ?>
        </tbody>
    </table>

  <?php include BASE_PATH . '/elements/footer.php'; ?>
</div>
</div>
</body>
</html>