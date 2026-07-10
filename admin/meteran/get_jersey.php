<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/controllers/MeterController.php';

$meterController = new MeterController($koneksi);

$jersey = $meterController->getJersey();
$product_data_jersey = $jersey['product_data'];
$total_qty_all_jersey = $jersey['total_all_qty']; 
?>

<div class="table-responsive mb-3">
<table class="table table-bordered">
    <thead class="table-primary">
    <tr><th>Nama Produk</th><th>Total Qty</th></tr>
    </thead>
    <tbody>
    <?php if (empty($product_data_jersey)): ?>
        <tr><td colspan="2" class="text-center">Data kosong</td></tr>
    <?php else: ?>
        <?php foreach ($product_data_jersey as $product): ?>
        <tr><td><?= htmlspecialchars($product['name']) ?></td><td><?= $product['total_qty'] ?></td></tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
</div>

<div class="mt-3">
<table class="table table-bordered" style="max-width:500px; font-weight:bold;">
    <tr style="background:#dff0d8;">
    <td class="text-end">Total Qty Jersey:</td>
    <td class="text-center"><?= $total_qty_all_jersey ?></td>
    </tr>
</table>
</div>