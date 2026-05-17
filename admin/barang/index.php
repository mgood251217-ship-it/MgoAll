<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/models/Product.php';

$productModel = new Product($koneksi);

$products = [];

$result = $productModel->getProductByStoreId($store_id);
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
$result->close();

$jenisList = ['OUTDOOR', 'FINISHING OUTDOOR','INDOOR','FINISHING INDOOR', 'PAKET INDOOR OUTDOOR','LASER A3','FINISHING LASER A3','SUBLIM','FINISHING SUBLIM','DTF','STAMP', 'MERCENDISE', 'MERCENDISE AKRILIK', 'JERSEY', 'FINISHING JERSEY', 'AKRILIK', 'FINISHING AKRILIK', 'KARTU NAMA', 'CETAKAN', 'FINISHING CETAKAN', 'JASA'];
$unitList = ['M2', 'CM2', 'PCS', 'RIM', '~'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Data Barang</title>
  <?php include BASE_PATH . '/header.php'; ?>
</head>
<body>
<div id="main-wrapper" <?= ($mode === 1) ? 'class="dark-mode"' : '' ?>>
  <?php include '../navbar.php'; ?>
  <div id="main-content" <?= (isset($mode) && $mode === 1) ? 'class="dark-mode"' : '' ?>>
    <?php include '../sidebar.php'; ?>

    <div id="page-content-wrapper">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Data Barang</h1>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-primary" id="btn-export" style="display: none;">📤 Export Barang</button>

          <!-- Tombol Import (trigger input file tersembunyi) -->
          <form id="importForm" action="import_products.php" method="POST" enctype="multipart/form-data" style="display: none;">
            <input type="file" id="importFile" name="file" accept=".csv,.xls,.xlsx" onchange="document.getElementById('importForm').submit();">
          </form>
          <button class="btn btn-outline-secondary" onclick="document.getElementById('importFile').click();">📥 Import Barang</button>
          <?php
          if ($role == 'ADMIN' || $role == 'MANAGER') { ?>
          <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addProductModal">+ Tambah Barang</button>
          <?php } ?>
        </div>
      </div>

      <!-- Tabel Data Barang -->
      <?php if (empty($products)): ?>
        <div class="alert alert-warning">Tidak ada data barang untuk toko ini.</div>
      <?php else: ?>
        <div class="table-responsive mb-5">
          <table class="table table-bordered table-striped">
            <thead class="table-primary">
              <tr>
                <th>No</th>
                <th>Jenis</th>
                <th>Nama</th>
                <th>Harga</th>
                <th>Satuan</th>
                <th>Maklun <br> Cabang</th>
                <th>Harga <br> kegagalan</th>
                <?php
                if ($role == 'ADMIN' || $role == 'MANAGER') { ?>
                <th>Aksi</th>
                <th>
                  <input class="form-check-input" id="selectAllCheckbox" type="checkbox" title="Pilih Semua">
                </th>
                <?php } ?>
                
              </tr>
            </thead>
            <tbody>
              <?php foreach ($products as $no => $p): ?>
                <tr class="barang-rows">
                  <td><?= $no + 1 ?></td>
                  <td><?= htmlspecialchars($p['type']) ?></td>
                  <td><?= htmlspecialchars($p['name']) ?></td>
                  <td><?= number_format($p['price'], 0, ',', '.') ?></td>
                  <td><?= htmlspecialchars($p['unit_type']) ?></td>
                  <td><?= htmlspecialchars($p['reasonable_price']) ?></td>
                  <td><?= htmlspecialchars($p['failed_price']) ?></td>
                  <?php
                  if ($role == 'ADMIN' || $role == 'MANAGER') { ?>
                  <td>
                    <button type="button" class="btn btn-warning btn-sm" style="line-height: 0;" data-bs-toggle="modal" data-bs-target="#editProductModal"
                      data-id="<?= $p['product_id'] ?>"
                      data-type="<?= htmlspecialchars($p['type']) ?>"
                      data-name="<?= htmlspecialchars($p['name']) ?>"
                      data-price="<?= $p['price'] ?>"
                      data-unit="<?= $p['unit_type'] ?>"
                      data-reasonable="<?= $p['reasonable_price'] ?>"
                      data-failed="<?= $p['failed_price'] ?>">
                      <svg style="width: 16px; height: 16px; stroke-width: 2; stroke: white; fill: none;" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    </button>
                      <form action="product_action.php" method="POST" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus barang ini?')">
                        <input type="hidden" name="product" value="delete_product">
                        <input type="hidden" name="product_id" value="<?= $p['product_id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" style="line-height: 0; padding: .25rem .5rem;">
                          <svg style="width: 16px; height: 16px; stroke-width: 2; stroke: white; fill: none;" viewBox="0 0 24 24">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            <line x1="10" y1="11" x2="10" y2="17"></line>
                            <line x1="14" y1="11" x2="14" y2="17"></line>
                          </svg>
                        </button>
                      </form>
                  </td>
                  <th><input class="form-check-input check-barang" data-id="<?= htmlspecialchars($p['product_id']) ?>" type="checkbox" value="" id="flexCheckDefault"></th>
                  <?php } ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <!-- Modal Edit Barang -->
      <div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <form method="POST" action="product_action.php">
              <input type="hidden" name="product" value="update_product">
              <div class="modal-header bg-warning">
                <h5 class="modal-title" id="editProductModalLabel">Edit Barang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
              </div>
              <div class="modal-body row ">
                <input type="hidden" name="product_id" id="edit_product_id">
                <div class="col-md-3">
                  <label for="edit_type" class="form-label">Jenis</label>
                  <select name="type" id="edit_type" class="form-select" required>
                    <?php foreach ($jenisList as $j): ?>
                      <option value="<?= $j ?>"><?= $j ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label for="edit_name" class="form-label">Nama Barang</label>
                  <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="col-md-3">
                  <label for="edit_price" class="form-label">Harga</label>
                  <input type="number" name="price" id="edit_price" class="form-control" required>
                </div>
                <div class="col-md-3">
                  <label for="edit_reasonable_price" class="form-label">Harga Maklun</label>
                  <input type="number" name="reasonable_price" id="edit_reasonable_price" class="form-control" >
                </div>
                <div class="col-md-3">
                  <label for="edit_failed_price" class="form-label">Harga Kegagalan</label>
                  <input type="number" name="failed_price" id="edit_failed_price" class="form-control">
                </div>
                <div class="col-md-3">
                  <label for="edit_unit_type" class="form-label">Satuan</label>
                  <select name="unit_type" id="edit_unit_type" class="form-select" required>
                    <?php foreach ($unitList as $u): ?>
                      <option value="<?= $u ?>"><?= $u ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
                <div class="alert alert-warning mb-0 ms-3 me-3" role="alert">
                  !Mohon konfirmasi sebelum edit barang
                </div>
              <div class="modal-footer">
                <button type="submit" class="btn btn-warning">Update</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Form Tambah Barang -->
    <!-- Modal Tambah Barang -->
    <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <form action="product_action.php" method="POST" class="row g-3">
            <input type="hidden" name="product" value="create_product">
            <div class="modal-header bg-success text-white">
              <h5 class="modal-title" id="addProductModalLabel">Tambah Barang Baru</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body row g-3">
              <div class="col-md-3">
                <label for="add_type" class="form-label">Jenis</label>
                <select name="type" id="add_type" class="form-select" required>
                  <option value="">- Pilih Jenis -</option>
                  <?php foreach ($jenisList as $j): ?>
                    <option value="<?= $j ?>"><?= $j ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-5">
                <label for="add_name" class="form-label">Nama Barang</label>
                <input type="text" name="name" id="add_name" class="form-control" required>
              </div>
              <div class="col-md-2">
                <label for="add_price" class="form-label">Harga</label>
                <input type="number" name="price" id="add_price" class="form-control" required>
              </div>
              <div class="col-md-2">
                <label for="add_reasonable_price" class="form-label">Harga Maklun</label>
                <input type="number" name="reasonable_price" id="add_reasonable_price" class="form-control" required>
              </div>
              <div class="col-md-2">
                <label for="add_failed_price" class="form-label">Harga Kegagalan</label>
                <input type="number" name="failed_price" id="add_failed_price" class="form-control" required>
              </div>
              <div class="col-md-2">
                <label for="add_unit_type" class="form-label">Satuan</label>
                <select name="unit_type" id="add_unit_type" class="form-select" required>
                  <option value="">- Pilih Satuan -</option>
                  <?php foreach ($unitList as $u): ?>
                    <option value="<?= $u ?>"><?= $u ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="alert alert-warning mb-0" role="alert">
                !Mohon konfirmasi sebelum tambah barang baru
              </div>
            </div>
            <div class="modal-footer">
              <button type="submit" class="btn btn-success">Simpan</button>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    </div>
  </div>

  <?php include '../footer.php'; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const editModal = document.getElementById('editProductModal');
  editModal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    document.getElementById('edit_product_id').value = button.getAttribute('data-id');
    document.getElementById('edit_type').value = button.getAttribute('data-type');
    document.getElementById('edit_name').value = button.getAttribute('data-name');
    document.getElementById('edit_price').value = button.getAttribute('data-price');
    document.getElementById('edit_unit_type').value = button.getAttribute('data-unit');
    document.getElementById('edit_reasonable_price').value = button.getAttribute('data-reasonable');
    document.getElementById('edit_failed_price').value = button.getAttribute('data-failed');
  });

  const btnExport = document.querySelector('#btn-export');
  let checkbox = document.querySelectorAll('.check-barang');
  const selectAllCheckbox = document.getElementById('selectAllCheckbox');

  // Handle Select All checkbox
  if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', (e) => {
      checkbox.forEach(cb => {
        cb.checked = e.target.checked;
      });
      displayExport();
    });
  }

  checkbox.forEach(e => {
    
    e.addEventListener('change', f => {
      // Update Select All checkbox state
      if (selectAllCheckbox) {
        const allChecked = Array.from(checkbox).every(cb => cb.checked);
        const someChecked = Array.from(checkbox).some(cb => cb.checked);
        selectAllCheckbox.checked = allChecked;
        selectAllCheckbox.indeterminate = someChecked && !allChecked;
      }
      displayExport();
    });
  })

  function displayExport(){
    const checked = document.querySelectorAll('.check-barang:checked');
    if (checked.length === 0) {
      btnExport.style.display = 'none';
    }else{
      btnExport.style.display = 'block';
    }
  }

  btnExport.addEventListener('click', () => {
    const checked = document.querySelectorAll('.check-barang:checked');
    const barangIds = Array.from(checked).map(cb => cb.dataset.id);

    const formData = new URLSearchParams();
    barangIds.forEach(id => formData. append('barangIds[]', id));

    fetch('export_products.php', {
      method: 'POST',
      body: formData
    })
    .then(res => {
      if (!res.ok) throw new Error('Gagal export');
      return res.blob();
    })
    .then(blob => {
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'export-barang.csv';
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.URL.revokeObjectURL(url);
    })
    .catch(err => {
      console.error(err);
      alert('Gagal export data');
    });
  });


});

</script>
</body>
</html>
