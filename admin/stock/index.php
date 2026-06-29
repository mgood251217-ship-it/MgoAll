<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/controllers/StockController.php';
require_once BASE_PATH . '/components/Modal.php';
require_once BASE_PATH . '/components/Alert.php';
require_once BASE_PATH . '/components/Table.php';
require_once BASE_PATH . '/components/Loading.php';
require_once BASE_PATH . '/components/Icon.php';
require_once BASE_PATH . '/functions/helpers.php';

$stockController = new StockController($koneksi);
$stocks = $stockController->index();
$isAdmin = ($role == 'ADMIN' || $role == 'MANAGER');

?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Stok Barang</title>
  <?php include BASE_PATH . '/header.php'; ?>
  <?= renderLoading(); ?>
  <script>showLoading();</script>
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

      <?php
      $htmlTableStock = renderTable([
          'data'           => $stocks ?? [],
          'empty_message'  => 'Tidak ada data stok barang untuk toko ini.',
          'tbody_tr_class' => 'stock-rows',
          'columns'        => [
              ['header' => 'No', 'type' => 'number'],
              ['header' => 'Jenis Barang', 'field' => 'type'],
              ['header' => 'Nama Barang', 'field' => 'name'],
              [
                  'header' => 'Jumlah Stok',
                  'render' => function($row) {
                      $unitType = strtoupper($row['unit_type'] ?? '');
                      return ($unitType === 'M2' || $unitType === 'CM2')
                          ? number_format((float)$row['quantity'], 2)
                          : (int)$row['quantity'];
                  }
              ],
              ['header' => 'Satuan', 'field' => 'unit_type'],
              [
                  'header'  => 'Aksi',
                  'visible' => $isAdmin ?? false,
                  'render'  => function($row) {
                      ob_start();
                      ?>
                      <div class="d-flex gap-1 align-items-center">
                          <form class="d-inline-flex stock-form m-0">
                              <input type="hidden" name="stock" value="add_stock">
                              <input type="hidden" name="product_id" value="<?= sanitize($row['product_id']) ?>">
                              <input type="number" name="quantity" step="0.01" class="form-control form-control-sm me-1" placeholder="+Qty" style="width: 75px;" required>
                              <button type="submit" class="btn btn-success btn-sm" title="Tambah Stok" style="line-height: 0; padding: .4rem .5rem;">
                                  <?= get_icon('create', ['width' => '16', 'height' => '16']) ?>
                              </button>
                          </form>
                          
                          <form class="d-inline-flex stock-form m-0">
                              <input type="hidden" name="stock" value="update_stock">
                              <input type="hidden" name="product_id" value="<?= sanitize($row['product_id']) ?>">
                              <input type="number" name="quantity" step="0.01" value="<?= sanitize($row['quantity']) ?>" class="form-control form-control-sm me-1" style="width: 75px;" required>
                              <button type="submit" class="btn btn-primary btn-sm" title="Update Stok" style="line-height: 0; padding: .4rem .5rem;">
                                  <?= get_icon('update', ['width' => '16', 'height' => '16']) ?>
                              </button>
                          </form>
                      </div>
                      <?php
                      return ob_get_clean();
                  }
              ]
          ]
      ]);
      ?>

      <?= $htmlTableStock; ?>

    </div>
  </div>

  <?php include BASE_PATH . '/footer.php'; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

  async function refreshTable() {
    try {
      const response = await fetch(window.location.href);
      const html = await response.text();
      
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');
      
      const newTableResponsive = doc.querySelector('.table-responsive');
      const oldTableResponsive = document.querySelector('.table-responsive');
      const alertKosong = document.querySelector('.alert-warning');

      if (newTableResponsive) {
        if (oldTableResponsive) {
          oldTableResponsive.innerHTML = newTableResponsive.innerHTML;
        } else if (alertKosong) {
          alertKosong.outerHTML = newTableResponsive.outerHTML;
        }
      } else {
         if (oldTableResponsive) {
             oldTableResponsive.outerHTML = '<div class="alert alert-warning">Tidak ada data stok barang untuk toko ini.</div>';
         }
      }
    } catch (error) {
      console.error(error);
    }
  }

  async function submitDataAPI(formData) {
    try {
      const response = await fetch('stock_action.php', {
        method: 'POST',
        body: formData
      });
      const data = await response.json();
      
      if (data.success) {
        showAlert('success', data.message);
        await refreshTable();
      } else {
        const errorMessage = data.errors ? data.errors.join('<br>') : 'Gagal diproses';
        showAlert('error', errorMessage);
      }
    } catch (error) {
      showAlert('error', 'Terjadi kesalahan sistem.');
    }
  }

  document.addEventListener('submit', function (e) {
    const stockForm = e.target.closest('.stock-form');
    if (stockForm) {
      e.preventDefault();
      submitDataAPI(new FormData(stockForm));
    }
  });

  const searchInput = document.getElementById('searchInput');
  if (searchInput) {
    searchInput.addEventListener('keyup', function () {
      const keyword = this.value.toLowerCase();
      const rows = document.querySelectorAll("table tbody tr");

      rows.forEach(row => {
        const namaBarang = row.children[2]?.textContent.toLowerCase();
        row.style.display = namaBarang && namaBarang.includes(keyword) ? '' : 'none';
      });
    });
  }

});
hideLoading();
</script>
</body>
</html>