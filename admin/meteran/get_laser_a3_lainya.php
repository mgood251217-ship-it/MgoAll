<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/controllers/MeterController.php';

$meterController = new MeterController($koneksi);

$merchandise = $meterController->getMerchandise();
?>

<div class="table-responsive mb-3">
<table class="table table-bordered">
    <thead class="table-primary">
    <tr><th>Nama Produk</th><th>Total Qty</th></tr>
    </thead>
    <tbody>
    <?php foreach ($merchandise as $name => $qty): ?>
        <tr><td><?= htmlspecialchars($name) ?></td><td><?= $qty ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>