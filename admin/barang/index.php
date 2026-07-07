<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/controllers/ProductController.php';
require_once BASE_PATH . '/models/Product.php';
require_once BASE_PATH . '/components/Modal.php';
require_once BASE_PATH . '/components/Alert.php';
require_once BASE_PATH . '/components/Table.php';
require_once BASE_PATH . '/components/Loading.php';
require_once BASE_PATH . '/components/Icon.php';

$productController = new ProductController($koneksi);
$productModel = new Product($koneksi);

$products_data = $productController->getProductByPagination();
$total_pages = $products_data['total_pages'];
$total = $products_data['total'];
$products = $products_data['data'];
$categories = $productModel->getCategoryByStoreId($store_id);
$finishing = $productModel->getFinishingByStoreId($store_id);

$unitList = ['M2', 'CM2', 'PCS', 'RIM', '~'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Data Barang</title>
  <?php include BASE_PATH . '/header.php'; ?>
  <?= renderLoading(); ?>
  <script>showLoading();</script>
</head>
<body>
<div id="main-wrapper">
  <?php include '../navbar.php'; ?>
  <div id="main-content" <?= (isset($mode) && $mode === 1) ? 'class="dark-mode"' : '' ?>>
    <?php include '../sidebar.php'; ?>

    <div id="page-content-wrapper">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Data Barang</h1>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-primary" id="btn-export" style="display: none;">📤 Export Barang</button>
          <?php if ($administrator) { ?>
          <form id="importForm" action="import_products.php" method="POST" enctype="multipart/form-data" style="display: none;">
            <input type="file" id="importFile" name="file" accept=".csv,.xls,.xlsx" onchange="document.getElementById('importForm').submit();">
          </form>
          <button class="btn btn-outline-secondary" onclick="document.getElementById('importFile').click();">📥 Import Barang</button>
          
          <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addProductModal">+ Tambah Barang</button>
          <?php } ?>
        </div>
      </div>

      <div id="data-container">

        <div class="mb-3">
          <form method="GET" action="" class="d-flex gap-2 align-items-center">
            <input type="text" name="search" class="form-control" style="max-width: 250px;" placeholder="Cari Nama Barang..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            <select name="limit" class="form-select w-auto" onchange="this.form.submit()">
                <option value="10" <?= (isset($_GET['limit']) && $_GET['limit'] == 10) ? 'selected' : '' ?>>10 baris</option>
                <option value="50" <?= (isset($_GET['limit']) && $_GET['limit'] == 50) ? 'selected' : '' ?>>50 baris</option>
                <option value="100" <?= (isset($_GET['limit']) && $_GET['limit'] == 100) ? 'selected' : '' ?>>100 baris</option>
            </select>
            <button type="submit" class="btn btn-primary">Cari</button>
            <?php if (isset($_GET['search']) && $_GET['search'] !== '') { ?>
                <a href="?" class="btn btn-outline-secondary">Reset</a>
            <?php } ?>
          </form>
        </div>

        <?php
        $isAdmin = ($role == 'ADMIN' || $role == 'MANAGER');

        $iconEdit   = '<svg style="width: 16px; height: 16px; stroke-width: 2; stroke: white; fill: none;" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>';
        $iconDelete = '<svg style="width: 16px; height: 16px; stroke-width: 2; stroke: white; fill: none;" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>';

        $htmlTableProduct = renderTable([
            'data'           => $products ?? [],
            'empty_message'  => 'Tidak ada data barang untuk toko ini.',
            'tbody_tr_class' => 'barang-rows',
            'columns'        => [
                ['header' => 'No', 'type' => 'number'],
                ['header' => 'Jenis', 'field' => 'category'],
                ['header' => 'Nama', 'field' => 'name'],
                ['header' => 'Harga', 'field' => 'price', 'type' => 'currency'],
                ['header' => 'Satuan', 'field' => 'unit_type'],
                ['header' => 'Maklun <br> Cabang', 'field' => 'reasonable_price'],
                ['header' => 'Harga <br> kegagalan', 'field' => 'failed_price'],
                [
                    'header'  => 'Stock',
                    'visible' => $isAdmin ?? false,
                    'render'  => function($row) {
                        ob_start();
                        ?>
                        <div class="d-flex gap-1 align-items-center">                          
                            <form class="d-inline-flex stock-form m-0">
                                <input type="hidden" name="product_id" value="<?= sanitize($row['product_id']) ?>">
                                <input type="number" name="quantity" value="<?= sanitize($row['stock']) ?>" class="form-control form-control-sm me-1" style="width: 75px;" required>
                                <button type="submit" class="d-none" title="Update Stok" style="line-height: 0; padding: .4rem .5rem;">
                                    <?= get_icon('update', ['width' => '16', 'height' => '16']) ?>
                                </button>
                            </form>
                        </div>
                        <?php
                        return ob_get_clean();
                    }
                ],
                [
                    'header'  => 'Aksi', 
                    'type'    => 'action_buttons',
                    'visible' => $administrator ?? false,
                    'buttons' => [
                        [
                            'color'           => 'warning',
                            'modal'           => 'editProductModal',
                            'icon'            => get_icon('update'),
                            'data_attributes' => [
                                'id'          => 'product_id',
                                'category_id' => 'category_id',
                                'name'        => 'name',
                                'price'       => 'price',
                                'unit'        => 'unit_type',
                                'reasonable'  => 'reasonable_price',
                                'failed'      => 'failed_price'
                            ]
                        ],
                        [
                            'class'           => 'btn-delete-product',
                            'color'           => 'danger',
                            'icon'            => get_icon('delete'),
                            'data_attributes' => [
                                'id' => 'product_id'
                            ]
                        ]
                    ]
                ],
                [
                    'header'         => '<input class="form-check-input" id="selectAllCheckbox" type="checkbox" title="Pilih Semua">', 
                    'type'           => 'checkbox',
                    'id_field'       => 'product_id',
                    'is_header_cell' => true,
                    'visible'        => $administrator ?? false
                ]
            ]
        ]);
        
        echo $htmlTableProduct;
        ?>

        <?php if (isset($total_pages) && $total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mt-3">
            <ul class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= (isset($_GET['page']) && $_GET['page'] == $i) ? 'active' : ((!isset($_GET['page']) && $i == 1) ? 'active' : '') ?>">
                        <a class="page-link" href="?page=<?= $i ?><?= isset($_GET['limit']) ? '&limit='.$_GET['limit'] : '' ?><?= isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : '' ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>

        <hr class="my-5">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Data Finishing</h3>
            <?php if ($administrator) { ?>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addFinishingModal">+ Tambah Finishing</button>
            <?php } ?>
        </div>
        <?php
        $htmlTableFinishing = renderTable([
            'data'           => $finishing ?? [],
            'empty_message'  => 'Tidak ada data finishing.',
            'tbody_tr_class' => 'finishing-rows',
            'columns'        => [
                ['header' => 'No', 'type' => 'number'],
                ['header' => 'Jenis', 'field' => 'category'],
                ['header' => 'Nama', 'field' => 'name'],
                ['header' => 'Harga', 'field' => 'price', 'type' => 'currency'],
                ['header' => 'Satuan', 'field' => 'unit_type'],
                ['header' => 'Maklun <br> Cabang', 'field' => 'reasonable_price'],
                ['header' => 'Harga <br> kegagalan', 'field' => 'failed_price'],
                [
                    'header'  => 'Stock',
                    'visible' => $isAdmin ?? false,
                    'render'  => function($row) {
                        ob_start();
                        ?>
                        <div class="d-flex gap-1 align-items-center">                          
                            <form class="d-inline-flex stock-finishing-form m-0">
                                <input type="hidden" name="finishing_id" value="<?= sanitize($row['finishing_id']) ?>">
                                <input type="number" name="quantity" value="<?= sanitize($row['stock']) ?>" class="form-control form-control-sm me-1" style="width: 75px;" required>
                                <button type="submit" class="d-none" title="Update Stok" style="line-height: 0; padding: .4rem .5rem;">
                                    <?= get_icon('update', ['width' => '16', 'height' => '16']) ?>
                                </button>
                            </form>
                        </div>
                        <?php
                        return ob_get_clean();
                    }
                ],
                [
                    'header'  => 'Aksi', 
                    'type'    => 'action_buttons',
                    'visible' => $administrator ?? false,
                    'buttons' => [
                        [
                            'color'           => 'warning',
                            'modal'           => 'editFinishingModal',
                            'icon'            => get_icon('update'),
                            'data_attributes' => [
                                'id'          => 'finishing_id',
                                'category_id' => 'category_id',
                                'name'        => 'name',
                                'price'       => 'price',
                                'unit'        => 'unit_type',
                                'reasonable'  => 'reasonable_price',
                                'failed'      => 'failed_price'
                            ]
                        ],
                        [
                            'class'           => 'btn-delete-finishing',
                            'color'           => 'danger',
                            'icon'            => get_icon('delete'),
                            'data_attributes' => [
                                'id' => 'finishing_id'
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        echo $htmlTableFinishing;
        ?>

        <hr class="my-5">

        <h3 class="mb-3">Data Kategori</h3>
        <?php
        echo renderTable([
            'data'          => $categories ?? [],
            'empty_message' => 'Tidak ada data kategori.',
            'columns'       => [
                ['header' => 'No', 'type' => 'number'],
                ['header' => 'Nama Kategori', 'field' => 'name']
            ]
        ]);
        ?>

      </div> <?php
      $optionsJenis = [];
      foreach ($categories as $c) { $optionsJenis[$c['category_id']] = $c['name']; }

      $optionsUnit = [];
      foreach ($unitList as $u) { $optionsUnit[$u] = $u; }

      $htmlModalEditProduct = renderModal([
          'id'            => 'editProductModal',
          'form_id'       => 'editProductForm',
          'size'          => 'modal-md',
          'title'         => 'Edit Barang',
          'header_class'  => 'bg-warning',
          'body_class'    => '',
          'layout'        => 'horizontal',
          'label_width'   => 'col-sm-4',
          'input_width'   => 'col-sm-8',
          'btn_color'     => 'warning',
          'btn_text'      => 'Update',
          'custom_bottom' => '<div class="alert alert-warning mb-0" role="alert" style="margin-left: 1rem; margin-right: 1rem;">!Mohon konfirmasi sebelum edit barang</div><br>',
          'inputs'        => [
              ['type' => 'hidden', 'name' => 'product', 'value' => 'update_product'],
              ['type' => 'hidden', 'name' => 'product_id', 'id' => 'edit_product_id'],
              ['type' => 'select', 'name' => 'category_id', 'id' => 'edit_category_id', 'label' => 'Jenis', 'required' => true, 'options' => $optionsJenis, 'no_default_option' => true],
              ['type' => 'text', 'name' => 'name', 'id' => 'edit_name', 'label' => 'Nama Barang', 'required' => true],
              ['type' => 'number', 'name' => 'price', 'id' => 'edit_price', 'label' => 'Harga', 'required' => true],
              ['type' => 'number', 'name' => 'reasonable_price', 'id' => 'edit_reasonable_price', 'label' => 'Harga Maklun'],
              ['type' => 'number', 'name' => 'failed_price', 'id' => 'edit_failed_price', 'label' => 'Harga Kegagalan'],
              ['type' => 'select', 'name' => 'unit_type', 'id' => 'edit_unit_type', 'label' => 'Satuan', 'required' => true, 'options' => $optionsUnit, 'no_default_option' => true]
          ]
      ]);

      $htmlModalAddProduct = renderModal([
          'id'            => 'addProductModal',
          'form_id'       => 'addProductForm',
          'size'          => 'modal-md',
          'title'         => 'Tambah Barang Baru',
          'header_class'  => 'bg-success text-white',
          'body_class'    => '',
          'layout'        => 'horizontal',
          'label_width'   => 'col-sm-4',
          'input_width'   => 'col-sm-8',
          'btn_color'     => 'success',
          'btn_text'      => 'Simpan',
          'custom_bottom' => '<div class="alert alert-warning mb-0" role="alert" style="margin-left: 1rem; margin-right: 1rem;">!Mohon konfirmasi sebelum tambah barang baru</div><br>',
          'inputs'        => [
              ['type' => 'hidden', 'name' => 'product', 'value' => 'create_product'],
              ['type' => 'select', 'name' => 'category_id', 'id' => 'add_category_id', 'label' => 'Jenis', 'required' => true, 'options' => $optionsJenis],
              ['type' => 'text', 'name' => 'name', 'id' => 'add_name', 'label' => 'Nama Barang', 'required' => true],
              ['type' => 'number', 'name' => 'price', 'id' => 'add_price', 'label' => 'Harga', 'required' => true],
              ['type' => 'number', 'name' => 'reasonable_price', 'id' => 'add_reasonable_price', 'label' => 'Harga Maklun', 'required' => true],
              ['type' => 'number', 'name' => 'failed_price', 'id' => 'add_failed_price', 'label' => 'Harga Gagal', 'required' => true],
              ['type' => 'select', 'name' => 'unit_type', 'id' => 'add_unit_type', 'label' => 'Satuan', 'required' => true, 'options' => $optionsUnit]
          ]
      ]);

      $htmlModalAddFinishing = renderModal([
          'id'            => 'addFinishingModal',
          'form_id'       => 'addFinishingForm',
          'size'          => 'modal-md',
          'title'         => 'Tambah Finishing Baru',
          'header_class'  => 'bg-success text-white',
          'body_class'    => '',
          'layout'        => 'horizontal',
          'label_width'   => 'col-sm-4',
          'input_width'   => 'col-sm-8',
          'btn_color'     => 'success',
          'btn_text'      => 'Simpan',
          'custom_bottom' => '<div class="alert alert-warning mb-0" role="alert" style="margin-left: 1rem; margin-right: 1rem;">!Mohon konfirmasi sebelum tambah finishing baru</div><br>',
          'inputs'        => [
              ['type' => 'hidden', 'name' => 'finishing', 'value' => 'create_finishing'],
              ['type' => 'select', 'name' => 'category_id', 'id' => 'add_finishing_category_id', 'label' => 'Jenis', 'required' => true, 'options' => $optionsJenis],
              ['type' => 'text', 'name' => 'name', 'id' => 'add_finishing_name', 'label' => 'Nama Finishing', 'required' => true],
              ['type' => 'number', 'name' => 'price', 'id' => 'add_finishing_price', 'label' => 'Harga', 'required' => true],
              ['type' => 'number', 'name' => 'reasonable_price', 'id' => 'add_finishing_reasonable_price', 'label' => 'Harga Maklun', 'required' => true],
              ['type' => 'number', 'name' => 'failed_price', 'id' => 'add_finishing_failed_price', 'label' => 'Harga Gagal', 'required' => true],
              ['type' => 'select', 'name' => 'unit_type', 'id' => 'add_finishing_unit_type', 'label' => 'Satuan', 'required' => true, 'options' => $optionsUnit]
          ]
      ]);

      $htmlModalEditFinishing = renderModal([
          'id'            => 'editFinishingModal',
          'form_id'       => 'editFinishingForm',
          'size'          => 'modal-md',
          'title'         => 'Edit Finishing',
          'header_class'  => 'bg-warning',
          'body_class'    => '',
          'layout'        => 'horizontal',
          'label_width'   => 'col-sm-4',
          'input_width'   => 'col-sm-8',
          'btn_color'     => 'warning',
          'btn_text'      => 'Update',
          'custom_bottom' => '<div class="alert alert-warning mb-0" role="alert" style="margin-left: 1rem; margin-right: 1rem;">!Mohon konfirmasi sebelum edit finishing</div><br>',
          'inputs'        => [
              ['type' => 'hidden', 'name' => 'finishing', 'value' => 'update_finishing'],
              ['type' => 'hidden', 'name' => 'finishing_id', 'id' => 'edit_finishing_id'],
              ['type' => 'select', 'name' => 'category_id', 'id' => 'edit_finishing_category_id', 'label' => 'Jenis', 'required' => true, 'options' => $optionsJenis, 'no_default_option' => true],
              ['type' => 'text', 'name' => 'name', 'id' => 'edit_finishing_name', 'label' => 'Nama Finishing', 'required' => true],
              ['type' => 'number', 'name' => 'price', 'id' => 'edit_finishing_price', 'label' => 'Harga', 'required' => true],
              ['type' => 'number', 'name' => 'reasonable_price', 'id' => 'edit_finishing_reasonable_price', 'label' => 'Harga Maklun'],
              ['type' => 'number', 'name' => 'failed_price', 'id' => 'edit_finishing_failed_price', 'label' => 'Harga Kegagalan'],
              ['type' => 'select', 'name' => 'unit_type', 'id' => 'edit_finishing_unit_type', 'label' => 'Satuan', 'required' => true, 'options' => $optionsUnit, 'no_default_option' => true]
          ]
      ]);

      echo $htmlModalEditProduct;
      echo $htmlModalAddProduct;
      echo $htmlModalAddFinishing;
      echo $htmlModalEditFinishing;
      ?>

    </div>
  </div>

  <?php include '../footer.php'; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

  async function refreshTable() {
    try {
      const response = await fetch(window.location.href);
      const html = await response.text();
      
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');
      
      const newContainer = doc.getElementById('data-container');
      const oldContainer = document.getElementById('data-container');

      if (newContainer && oldContainer) {
          oldContainer.innerHTML = newContainer.innerHTML;
      }

      displayExport();
    } catch (error) {
      console.error(error);
    }
  }

  const editModal = document.getElementById('editProductModal');
  if (editModal) {
    editModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      document.getElementById('edit_product_id').value = button.getAttribute('data-id') || '';
      document.getElementById('edit_category_id').value = button.getAttribute('data-category_id') || '';
      document.getElementById('edit_name').value = button.getAttribute('data-name') || '';
      document.getElementById('edit_price').value = button.getAttribute('data-price') || '';
      document.getElementById('edit_unit_type').value = button.getAttribute('data-unit') || '';
      document.getElementById('edit_reasonable_price').value = button.getAttribute('data-reasonable') || '';
      document.getElementById('edit_failed_price').value = button.getAttribute('data-failed') || '';
    });
  }

  const editFinishingModal = document.getElementById('editFinishingModal');
  if (editFinishingModal) {
    editFinishingModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      document.getElementById('edit_finishing_id').value = button.getAttribute('data-id') || '';
      document.getElementById('edit_finishing_category_id').value = button.getAttribute('data-category_id') || '';
      document.getElementById('edit_finishing_name').value = button.getAttribute('data-name') || '';
      document.getElementById('edit_finishing_price').value = button.getAttribute('data-price') || '';
      document.getElementById('edit_finishing_unit_type').value = button.getAttribute('data-unit') || '';
      document.getElementById('edit_finishing_reasonable_price').value = button.getAttribute('data-reasonable') || '';
      document.getElementById('edit_finishing_failed_price').value = button.getAttribute('data-failed') || '';
    });
  }

  const btnExport = document.querySelector('#btn-export');
  
  function displayExport() {
    const checked = document.querySelectorAll('.check-barang:checked');
    if (btnExport) {
        btnExport.style.display = (checked.length === 0) ? 'none' : 'block';
    }
  }

  document.addEventListener('change', function (e) {
    if (e.target.id === 'selectAllCheckbox') {
      const checkboxes = document.querySelectorAll('.check-barang');
      checkboxes.forEach(cb => cb.checked = e.target.checked);
      displayExport();
    } 
    else if (e.target.classList.contains('check-barang')) {
      const selectAllCheckbox = document.getElementById('selectAllCheckbox');
      const checkboxes = document.querySelectorAll('.check-barang');
      
      if (selectAllCheckbox) {
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        const someChecked = Array.from(checkboxes).some(cb => cb.checked);
        selectAllCheckbox.checked = allChecked;
        selectAllCheckbox.indeterminate = someChecked && !allChecked;
      }
      displayExport();
    }
  });

  if (btnExport) {
    btnExport.addEventListener('click', () => {
      const checked = document.querySelectorAll('.check-barang:checked');
      const barangIds = Array.from(checked).map(cb => cb.dataset.id);

      const formData = new URLSearchParams();
      barangIds.forEach(id => formData.append('barangIds[]', id));

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
        showAlert('success', 'File berhasil diexport!');
      })
      .catch(err => {
        console.error(err);
        showAlert('error', 'Gagal export data');
      });
    });
  }

document.addEventListener('submit', function (e) {
  const stockForm = e.target.closest('.stock-form');
  if (stockForm) {
    e.preventDefault();
    const formData = new FormData(stockForm);
    fetch('../routes/?action=update_stock', {
      method: 'POST',
      body: formData
    }).then(response => response.json())
    .then(data => {
      if (data.success) {
        showAlert('success', data.message);
        refreshTable();
      } else {
        const errorMessage = data.errors ? data.errors.join('<br>') : 'Gagal diproses';
        showAlert('error', errorMessage);
      }
    })
  }
});

document.addEventListener('submit', function (e) {
  const stockFinishingForm = e.target.closest('.stock-finishing-form');
  if (stockFinishingForm) {
    e.preventDefault();
    const formData = new FormData(stockFinishingForm);
    fetch('../routes/?action=update_stock_finishing', {
      method: 'POST',
      body: formData
    }).then(response => response.json())
    .then(data => {
      if (data.success) {
        showAlert('success', data.message);
        refreshTable();
      } else {
        const errorMessage = data.errors ? data.errors.join('<br>') : 'Gagal diproses';
        showAlert('error', errorMessage);
      }
    })
  }
});

document.getElementById('addProductForm')?.addEventListener('submit', function (e) {
  e.preventDefault();
  const formData = new FormData(this);
  fetch('../routes/?action=create_product', {
    method: 'POST',
    body: formData
  }).then(response => response.json())
  .then(data => {
    if (data.success) {
      showAlert('success', data.message);
      refreshTable();
      const addModal = bootstrap.Modal.getInstance(document.getElementById('addProductModal'));
      addModal.hide();
      this.reset();
    } else {
      const errorMessage = data.errors ? data.errors.join('<br>') : 'Gagal diproses';
      showAlert('error', errorMessage);
    }
  })
});

document.getElementById('editProductForm')?.addEventListener('submit', function (e) {
  e.preventDefault();
  const formData = new FormData(this);
  fetch('../routes/?action=update_product', {
    method: 'POST',
    body: formData
  }).then(response => response.json())
  .then(data => {
    if (data.success) {
      showAlert('success', data.message);
      refreshTable();
      const editModal = bootstrap.Modal.getInstance(document.getElementById('editProductModal'));
      editModal.hide();
    } else {
      const errorMessage = data.errors ? data.errors.join('<br>') : 'Gagal diproses';
      showAlert('error', errorMessage);
    }
  })
});

document.getElementById('addFinishingForm')?.addEventListener('submit', function (e) {
  e.preventDefault();
  const formData = new FormData(this);
  fetch('../routes/?action=create_finishing', {
    method: 'POST',
    body: formData
  }).then(response => response.json())
  .then(data => {
    console.log(formData);
    
    if (data.success) {
      showAlert('success', data.message);
      refreshTable();
      const addFinishingModal = bootstrap.Modal.getInstance(document.getElementById('addFinishingModal'));
      addFinishingModal.hide();
      this.reset();
    } else {
      const errorMessage = data.errors ? data.errors.join('<br>') : 'Gagal diproses';
      showAlert('error', errorMessage);
    }
  })
});

document.getElementById('editFinishingForm')?.addEventListener('submit', function (e) {
  e.preventDefault();
  const formData = new FormData(this);
  fetch('../routes/?action=update_finishing', {
    method: 'POST',
    body: formData
  }).then(response => response.json())
  .then(data => {
    if (data.success) {
      showAlert('success', data.message);
      refreshTable();
      const editFinishingModal = bootstrap.Modal.getInstance(document.getElementById('editFinishingModal'));
      editFinishingModal.hide();
    } else {
      const errorMessage = data.errors ? data.errors.join('<br>') : 'Gagal diproses';
      showAlert('error', errorMessage);
    }
  })
});

  document.addEventListener('click', function (e) {
    const deleteProductBtn = e.target.closest('.btn-delete-product');
    if (deleteProductBtn) {
      const productId = deleteProductBtn.getAttribute('data-id');
      if (confirm('Apakah Anda yakin ingin menghapus barang ini?')) {
        const formData = new FormData();
        formData.append('product_id', productId);
        fetch('../routes/?action=delete_product', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showAlert('success', data.message);
            refreshTable();
          } else {
            const errorMessage = data.errors ? data.errors.join('<br>') : 'Gagal menghapus barang';
            showAlert('error', errorMessage);
          }
        });
      }
    }

    const deleteFinishingBtn = e.target.closest('.btn-delete-finishing');
    if (deleteFinishingBtn) {
      const finishingId = deleteFinishingBtn.getAttribute('data-id');
      if (confirm('Apakah Anda yakin ingin menghapus finishing ini?')) {
        const formData = new FormData();
        formData.append('finishing_id', finishingId);
        fetch('../routes/?action=delete_finishing', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showAlert('success', data.message);
            refreshTable();
          } else {
            const errorMessage = data.errors ? data.errors.join('<br>') : 'Gagal menghapus finishing';
            showAlert('error', errorMessage);
          }
        });
      }
    }
  });

});

hideLoading();
</script>
</body>
</html>