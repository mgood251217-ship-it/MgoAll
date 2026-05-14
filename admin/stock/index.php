<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

// Ambil semua produk milik store dan tampilkan stok (termasuk 0)
$query = "
    SELECT 
        p.product_id, 
        p.type, 
        p.name, 
        p.unit_type, 
        COALESCE(s.quantity, 0) AS quantity
    FROM products p
    LEFT JOIN stock s ON p.product_id = s.product_id AND s.store_id = ?
    WHERE p.store_id = ?
    AND name != 'KISS CUT'
    AND name != 'DIE CUT'
    AND unit_type != '~'
";

$stmt = $koneksi->prepare($query);
$stmt->bind_param("ii", $store_id, $store_id);
$stmt->execute();
$result = $stmt->get_result();

$stocks = [];
while ($row = $result->fetch_assoc()) {
    $stocks[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Stok Barang</title>
  <?php include BASE_PATH . '/header.php'; ?>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/content.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dark_mode.css">
    <?php if (isset($username) && ($username == 'zannia' || $username == 'vikialvian')) { ?>
      <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/pink_mode.css">
    <?php } ?>
</head>

<body>
<div id="main-wrapper" <?= ($mode ?? 0) === 1 ? 'class="dark-mode"' : '' ?>>
  <?php include BASE_PATH . '/navbar.php'; ?>

  <div id="main-content" <?= ($mode ?? 0) === 1 ? 'class="dark-mode"' : '' ?>>
    <?php include BASE_PATH . '/sidebar.php'; ?>

    <div id="page-content-wrapper">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Data Stok Barang</h1>
        <input type="text" id="searchInput" class="form-control" placeholder="Cari Nama Barang..." style="max-width: 250px;">
      </div>
      <?php if (empty($stocks)): ?>
        <div class="alert alert-warning">Tidak ada data stok barang untuk toko ini.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-bordered table-striped">
            <thead class="table-primary">
              <tr>
                <th>No</th>
                <th>Jenis Barang</th>
                <th>Nama Barang</th>
                <th>Jumlah Stok</th>
                <th>Satuan</th>
                <?php
                if ($role == 'ADMIN' || $role == 'MANAGER') { ?>
                <th class="text-nowrap" style="width: 180px;">Aksi</th>
                <?php } ?>
              </tr>
            </thead>
            <tbody>
              <?php $no = 1; ?>
              <?php foreach ($stocks as $s): ?>
                <tr>
                  <td><?= $no++ ?></td>
                  <td><?= htmlspecialchars($s['type']) ?></td>
                  <td><?= htmlspecialchars($s['name']) ?></td>
                  <td>
                    <?php
                      $unitType = strtoupper($s['unit_type']);
                      echo ($unitType === 'M2' || $unitType === 'CM2')
                        ? number_format((float)$s['quantity'], 2)
                        : (int)$s['quantity'];
                    ?>
                  </td>
                  <td><?= htmlspecialchars($s['unit_type'] ?? '-') ?></td>
                  <?php
                  if ($role == 'ADMIN' || $role == 'MANAGER') { ?>
                  <td class="text-nowrap">
                    <form method="POST" action="update_stock.php" class="d-inline-flex me-1">
                      <input type="hidden" name="product_id" value="<?= $s['product_id'] ?>">
                      <input type="number" name="add_quantity" step="0.01" class="form-control form-control-sm me-1" placeholder="+Qty" style="width: 70px;" required>
                      <button type="submit" class="btn btn-success btn-sm">Tambah</button>
                    </form>
                    <form method="POST" action="edit_stock.php" class="d-inline-flex">
                      <input type="hidden" name="product_id" value="<?= $s['product_id'] ?>">
                      <input type="number" name="new_quantity" step="0.01" value="<?= $s['quantity'] ?>" class="form-control form-control-sm me-1" style="width: 70px;" required>
                      <button type="submit" class="btn btn-primary btn-sm">Edit</button>
                    </form>
                  </td>
                  <?php } ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php include BASE_PATH . '/footer.php'; ?>
</div>

<script>
document.getElementById('searchInput').addEventListener('keyup', function () {
  const keyword = this.value.toLowerCase();
  const rows = document.querySelectorAll("table tbody tr");

  rows.forEach(row => {
    const namaBarang = row.children[2]?.textContent.toLowerCase();
    row.style.display = namaBarang && namaBarang.includes(keyword) ? '' : 'none';
  });
});
</script>

</body>
</html>
