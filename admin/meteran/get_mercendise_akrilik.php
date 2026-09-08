<?php
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../middleware/init_auth.php';
require_once __DIR__ . '/../controllers/MeterController.php';

$meterController = new MeterController($koneksi);

$merchandise_akrilik = $meterController->getMercendiseAkrilik();
?>

<div class="table-responsive mb-3">
  <table class="table table-bordered">
    <thead class="table-primary">
      <tr><th>Nama Produk</th><th>Total Qty</th></tr>
    </thead>
    <tbody>
      <?php if (empty($merchandise_akrilik)): ?>
        <tr><td colspan="2" class="text-center">Data kosong</td></tr>
      <?php else: ?>
        <?php foreach ($merchandise_akrilik as $product): ?>
          <tr><td><?= htmlspecialchars($product['name']) ?></td><td><?= $product['total_qty'] ?></td></tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>