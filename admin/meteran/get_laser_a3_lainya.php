<?php
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../middleware/init_auth.php';
require_once __DIR__ . '/../controllers/MeterController.php';

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