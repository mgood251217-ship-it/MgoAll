<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/controllers/MeterController.php';

$meterController = new MeterController($koneksi);

$cetakan = $meterController->getCetakan();
?>

<div class="table-responsive mb-3">
  <table class="table table-bordered">
    <thead class="table-primary">
      <tr><th>Nama Produk</th><th>Total Qty</th></tr>
    </thead>
    <tbody>
      <?php if (empty($cetakan)): ?>
        <tr><td colspan="2" class="text-center">Data kosong</td></tr>
      <?php else: ?>
        <?php foreach ($cetakan as $product): ?>
          <tr><td><?= htmlspecialchars($product['name']) ?></td><td><?= $product['total_qty'] ?></td></tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>