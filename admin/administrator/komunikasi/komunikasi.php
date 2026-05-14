<?php
require_once '../../connect.php';
require_once BASE_PATH . '/global_functions.php';

if ($access == 'ALL') {
  $stores = $koneksi->query("SELECT store_id, name FROM stores");
}else {
  $stmtStore = $koneksi->prepare("SELECT store_id, name FROM stores WHERE administrator = ?");
  $stmtStore->bind_param("s", $access);
  $stmtStore->execute();
  $stores = $stmtStore->get_result();
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Komunikasi Internal</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/administrator/assets/css/content.css">
</head>
<body>

<?php include BASE_PATH . '/administrator/navbar.php'; ?>

<div id="mainWrapper">
  <?php include BASE_PATH . '/administrator/sidebar.php'; ?>

  <div id="contentWrapper">
    <main id="mainContent">
      <div class="container-fluid">
        <h1 class="mb-4">Komunikasi Internal</h1>

        <div class="mb-3">
          <label class="form-check-label fw-bold">
            <input type="checkbox" id="selectAll" class="form-check-input me-2">
            Pilih Semua Cabang
          </label>
        </div>

        <form id="formStores">
          <div class="list-group mb-4">
            <?php while ($store = $stores->fetch_assoc()): ?>
              <label class="list-group-item d-flex align-items-center">
                <input type="checkbox" class="form-check-input me-2 store-checkbox" name="stores[]" value="<?= $store['store_id'] ?>">
                <?= htmlspecialchars($store['name']) ?>
              </label>
            <?php endwhile; ?>
          </div>

          <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNotifikasi">
            Notifikasi Baru
          </button>
        </form>
      </div>
    </main>

    <?php include BASE_PATH . '/administrator/footer.php'; ?>
  </div>
</div>

<!-- Modal Notifikasi Baru -->
<div class="modal fade" id="modalNotifikasi" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="proses_notifikasi.php" class="modal-content" id="formNotifikasi">
      <div class="modal-header">
        <h5 class="modal-title">Kirim Notifikasi Baru</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="selected_stores" id="selectedStoresInput">

        <div class="mb-3">
          <label for="message" class="form-label">Judul</label>
          <input type="text" class="form-control" id="message" name="message" required>
        </div>
        <div class="mb-3">
          <label for="message_content" class="form-label">Pesan</label>
          <textarea class="form-control" id="message_content" name="message_content" rows="4" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Kirim Notifikasi</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Pilih Semua
document.getElementById('selectAll').addEventListener('change', function () {
  document.querySelectorAll('.store-checkbox').forEach(cb => cb.checked = this.checked);
});

// Saat modal dibuka, ambil store yang dicentang
document.getElementById('modalNotifikasi').addEventListener('show.bs.modal', function () {
  const checked = Array.from(document.querySelectorAll('.store-checkbox:checked'))
                      .map(cb => cb.value);
  document.getElementById('selectedStoresInput').value = checked.join(',');
});
</script>
</body>
</html>
