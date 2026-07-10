<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/controllers/MeterController.php';

$meterController = new MeterController($koneksi);

$bahan = $meterController->getBahanSublim();
$product_data_bahan_meteran = $bahan['meteran'];
$product_data_bahan_kiloan  = $bahan['kiloan'];
$max_rows = $bahan['max_rows'];
?>

<div class="excel-container" id="table-container-bahan">
    <?php foreach ($product_data_bahan_meteran as $product): ?>
    <table class="excel-table">
        <thead>
            <tr><th colspan="4"><?= htmlspecialchars($product['name']) ?></th></tr>
            <tr><th>P</th><th>L</th><th>Qty</th><th>M2</th></tr>
        </thead>
        <tbody>
            <?php 
            $subtotal_m2 = 0;
            foreach ($product['rows'] as $row): 
                $subtotal_m2 += $row['m2'];
            ?>
                <tr>
                    <td><?= $row['p'] ?></td>
                    <td><?= $row['l'] ?></td>
                    <td><?= $row['qty'] ?></td>
                    <td><?= $row['m2'] ?></td>
                </tr>
            <?php endforeach; ?>
            <?php for ($i = count($product['rows']); $i < $max_rows; $i++): ?>
                <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            <?php endfor; ?>
            <tr style="font-weight:bold;">
                <td colspan="3" style="text-align:right;">Total m2:</td>
                <td><?= round($subtotal_m2, 2) ?></td>
            </tr>
        </tbody>
    </table>
    <?php endforeach; ?>

    <?php foreach ($product_data_bahan_kiloan as $product): ?>
    <table class="excel-table">
        <thead>
            <tr><th colspan="4"><?= htmlspecialchars($product['name']) ?></th></tr>
            <tr><th>Kg</th><th>Qty</th><th>Kg Total</th></tr>
        </thead>
        <tbody>
            <?php 
            $subtotal_kg = 0;
            foreach ($product['rows'] as $row): 
                $subtotal_kg += $row['kg_total'];
            ?>
                <tr>
                    <td><?= $row['kg'] ?></td>
                    <td><?= $row['qty'] ?></td>
                    <td><?= $row['kg_total'] ?></td>
                </tr>
            <?php endforeach; ?>
            <?php for ($i = count($product['rows']); $i < $max_rows; $i++): ?>
                <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            <?php endfor; ?>
            <tr style="font-weight:bold;">
                <td colspan="2" style="text-align:right;">Total kg:</td>
                <td><?= round($subtotal_kg, 2) ?></td>
            </tr>
        </tbody>
    </table>
    <?php endforeach; ?>
</div>