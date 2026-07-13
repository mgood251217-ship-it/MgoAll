<?php

require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/controllers/MeterController.php';

$meterController = new MeterController($koneksi);

$outdoor = $meterController->getOutdoor();
$product_data = $outdoor['product_data'];
$total_m2_product = $outdoor['total_m2_product'];
$total_all_m2_outdoor = $outdoor['total_all_m2_outdoor'];
$max_rows = $outdoor['max_rows'];

?>

<div class="excel-container" id="table-container" >
    <?php foreach ($product_data as $product): ?>
    <table class="excel-table" class="tableOutdoor">
    <thead >
        <tr><th colspan="4"><?= htmlspecialchars($product['name']) ?></th></tr>
        <tr><th>P</th><th>L</th><th>Qty</th><th>M2</th></tr>
    </thead>
    <tbody>
        <?php if (empty($product['rows'])): ?>
        <?php for ($i = 0; $i < $max_rows; $i++): ?>
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        <?php endfor; ?>
        <tr style="font-weight:bold;">
            <td colspan="3" style="text-align:right;">Total m2:</td>
            <td>0</td>
        </tr>
        <?php else: ?>
        <?php 
            $total_m2_product = 0;
            foreach ($product['rows'] as $row):
            $total_m2_product += $row['m2'];
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
        <tr style="font-weight:bold; ">
            <td colspan="3" style="text-align:right;">Total m2:</td>
            <td><?= round($total_m2_product, 2) ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
    </table>
    <?php endforeach; ?>
</div>

<div class="mt-3 d-flex align-items-center justify-content-between">
    <table class="table table-bordered" style="max-width:500px; font-weight:bold;">
    <tr style="background:#dff0d8;">
        <td class="text-end">Total Meteran Outdoor:</td>
        <td class="text-center"><?= round($total_all_m2_outdoor, 2) ?></td>
    </tr>
    </table>
</div>