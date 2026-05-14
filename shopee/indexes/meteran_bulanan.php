<?php
require_once '../connect.php';
require_once BASE_PATH . '/functions/user_validation.php';

$user_id = $_SESSION['shopee_users']['user_id'] ?? 0;

date_default_timezone_set('Asia/Jakarta');

$date = $_GET['d'] ?? date('Y-m');
$start_date = date('Y-m-01', strtotime($date . '-01'));
$end_date = date('Y-m-t', strtotime($start_date));

$stmtProducts = $koneksi->prepare("
    SELECT product_id, name, unit_type, type
    FROM products
    WHERE user_id = ?
    ORDER BY type DESC");
$stmtProducts->bind_param("i", $user_id);
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
            <?php $no = 1; ?>
            <?php while ($product = $resultProducts->fetch_assoc()) { ?>
            <tr>
                <td><?= $no ?></td>
                <td><?= htmlspecialchars($product['type']) ?></td>
                <td><?= htmlspecialchars($product['name']) ?></td>
                <td>
                <?php 
                    $total = 0;
                    $meters = [];
                    $stmtMeter = $koneksi->prepare("
                        SELECT total, date, meter_id
                        FROM meters
                        WHERE product_id = ?
                        AND date BETWEEN ? AND ?");
                    $stmtMeter->bind_param("iss", $product['product_id'], $start_date, $end_date);
                    $stmtMeter->execute();
                    $resultMeter = $stmtMeter->get_result();  
                    while ($rsMeter = $resultMeter->fetch_assoc()) {
                        $total += $rsMeter['total'] ?? 0;
                        $meters[] = $rsMeter;
                    }
                    echo htmlspecialchars($total);  
                ?>
                </td>
                <td><?= htmlspecialchars($product['unit_type']) ?></td>
                <td>
                    <?php
                    if ($meters) {
                        $listData = [];
                        while ($meterData = array_shift($meters)) {
                            $meterId = $meterData['meter_id'];
                            $stmtListMeter = $koneksi->prepare("
                                SELECT list_meter_id, value
                                FROM list_meters
                                WHERE meter_id = ?");
                            $stmtListMeter->bind_param("i", $meterId);
                            $stmtListMeter->execute();
                            $resultListMeter = $stmtListMeter->get_result();
                            if ($resultListMeter->num_rows > 0) {
                                while ($listMeter = $resultListMeter->fetch_assoc()) {
                                    $listData[] = $listMeter;
                                }
                            }
                        }

                        foreach ($listData as $listMeter) {
                            ?>
                            <span style="margin-right: 8px; display: inline-block; cursor: pointer;"
                            id="deleteList" data-id="<?= $listMeter['list_meter_id'] ?>">
                                <?= htmlspecialchars($listMeter['value']) ?>
                            </span>

                            <?php
                        }
                        ?>
                    
                    <?php

                    }else {
                        echo "No Data";
                    }
                    
                    ?>
                </td>
                <td><?= $start_date . ' s/d ' . $end_date ?></td>
            </tr>
            <?php $no++ ?>
            <?php } ?>
        </tbody>
    </table>

  <?php include BASE_PATH . '/elements/footer.php'; ?>
</div>
</body>
</html>
