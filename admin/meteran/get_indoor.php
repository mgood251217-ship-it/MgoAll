<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/controllers/MeterController.php';

$meterController = new MeterController($koneksi);

$indoor = $meterController->getIndoor();
$product_data = $indoor['product_data'];
$max_rows = $indoor['max_rows'];
$total_all_m2_indoor = $indoor['total_all_m2']; 
?>

<div class="excel-container" id="table-container-indoor">
    <?php foreach ($product_data as $product): ?>
    <table class="excel-table">
    <thead>
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
                $current_table_m2 = 0; 
                foreach ($product['rows'] as $row):
                    $current_table_m2 += $row['m2'];
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
                <td><?= round($current_table_m2, 2) ?></td>
            </tr>
        <?php endif; ?>
    </tbody>
    </table>
    <?php endforeach; ?>
</div>

<div class="mt-3">
    <table class="table table-bordered" style="max-width:500px; font-weight:bold;">
    <tr style="background:#dff0d8;">
        <td class="text-end">Total Meteran Indoor:</td>
        <td class="text-center"><?= round($total_all_m2_indoor, 2) ?></td>
    </tr>
    </table>
</div>