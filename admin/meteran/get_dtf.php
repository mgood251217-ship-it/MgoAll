<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/controllers/MeterController.php';

$meterController = new MeterController($koneksi);

$dtf = $meterController->getDtf();
$product_data_dtf = $dtf['product_data'];
$max_rows_dtf = $dtf['max_rows'];
$total_panjang_dtf = $dtf['total_panjang_dtf'];
$total_panjang_dtf_uv = $dtf['total_panjang_dtf_uv'];
?>

<div class="excel-container" id="table-container-dtf">
    <?php foreach ($product_data_dtf as $product): ?>
    <?php 
        $rows = $product['rows'];
        $isA3 = $product['isA3'];
        $isUV_A3 = $product['isUV_A3'];
        $subtotal = array_sum(array_column($rows, 'total'));
    ?>
    <table class="excel-table">
        <thead>
        <tr><th colspan="3"><?= htmlspecialchars($product['name']) ?></th></tr>
        <tr>
            <th><?= ($isA3 || $isUV_A3) ? 'Qty' : 'Panjang' ?></th>
            <th>Qty</th>
            <th><?= ($isA3 || $isUV_A3) ? 'Total Qty' : 'Total Panjang' ?></th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <?php for ($i = 0; $i < $max_rows_dtf; $i++): ?>
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            <?php endfor; ?>
            <tr style="font-weight:bold;">
                <td colspan="2" style="text-align:right;">Total:</td>
                <td>0</td>
            </tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= ($isA3 || $isUV_A3) ? '-' : $row['p'] ?></td>
                <td><?= $row['qty'] ?></td>
                <td><?= round($row['total'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php for ($i = count($rows); $i < $max_rows_dtf; $i++): ?>
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            <?php endfor; ?>
            <tr style="font-weight:bold;">
                <td colspan="2" style="text-align:right;">Total:</td>
                <td><?= round($subtotal, 2) ?></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php endforeach; ?>
</div>

<div class="mt-3">
    <table class="table table-bordered" style="max-width:500px; font-weight:bold;">
        <tr style="background:#dff0d8;">
            <td class="text-end">Total Panjang DTF (Meteran):</td>
            <td class="text-center"><?= round($total_panjang_dtf, 2) ?></td>
        </tr>
        <tr>
            <td class="text-end">Total Panjang DTF UV:</td>
            <td class="text-center"><?= round($total_panjang_dtf_uv, 2) ?></td>
        </tr>
    </table>
</div>