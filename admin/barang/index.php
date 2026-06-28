<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/controllers/ProductController.php';
require_once BASE_PATH . '/components/Modal.php';
require_once BASE_PATH . '/components/Alert.php';
require_once BASE_PATH . '/components/Table.php';
require_once BASE_PATH . '/components/Loading.php';
require_once BASE_PATH . '/components/Icon.php';
if (isset($_SESSION['admin_logged_in']['administrator_id'])) {
  $administrator = true;
}

$productController = new ProductController($koneksi);
$products = $productController->index();

$jenisList = ['OUTDOOR', 'FINISHING OUTDOOR','INDOOR','FINISHING INDOOR', 'PAKET INDOOR OUTDOOR','LASER A3','FINISHING LASER A3','SUBLIM','FINISHING SUBLIM','DTF','STAMP', 'MERCENDISE', 'MERCENDISE AKRILIK', 'JERSEY', 'FINISHING JERSEY', 'AKRILIK', 'FINISHING AKRILIK', 'KARTU NAMA', 'CETAKAN', 'FINISHING CETAKAN', 'JASA'];
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
              ['header' => 'Jenis', 'field' => 'type'],
              ['header' => 'Nama', 'field' => 'name'],
              ['header' => 'Harga', 'field' => 'price', 'type' => 'currency'],
              ['header' => 'Satuan', 'field' => 'unit_type'],
              ['header' => 'Maklun <br> Cabang', 'field' => 'reasonable_price'],
              ['header' => 'Harga <br> kegagalan', 'field' => 'failed_price'],
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
                              'id'         => 'product_id',
                              'type'       => 'type',
                              'name'       => 'name',
                              'price'      => 'price',
                              'unit'       => 'unit_type',
                              'reasonable' => 'reasonable_price',
                              'failed'     => 'failed_price'
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

      <?php
      $optionsJenis = [];
      foreach ($jenisList as $j) { $optionsJenis[$j] = $j; }

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
              [
                  'type'  => 'hidden',
                  'name'  => 'product',
                  'value' => 'update_product'
              ],
              [
                  'type'  => 'hidden',
                  'name'  => 'product_id',
                  'id'    => 'edit_product_id'
              ],
              [
                  'type'              => 'select',
                  'name'              => 'type',
                  'id'                => 'edit_type',
                  'label'             => 'Jenis',
                  'required'          => true,
                  'options'           => $optionsJenis,
                  'no_default_option' => true
              ],
              [
                  'type'          => 'text',
                  'name'          => 'name',
                  'id'            => 'edit_name',
                  'label'         => 'Nama Barang',
                  'required'      => true
              ],
              [
                  'type'          => 'number',
                  'name'          => 'price',
                  'id'            => 'edit_price',
                  'label'         => 'Harga',
                  'required'      => true
              ],
              [
                  'type'          => 'number',
                  'name'          => 'reasonable_price',
                  'id'            => 'edit_reasonable_price',
                  'label'         => 'Harga Maklun'
              ],
              [
                  'type'          => 'number',
                  'name'          => 'failed_price',
                  'id'            => 'edit_failed_price',
                  'label'         => 'Harga Kegagalan'
              ],
              [
                  'type'              => 'select',
                  'name'              => 'unit_type',
                  'id'                => 'edit_unit_type',
                  'label'             => 'Satuan',
                  'required'          => true,
                  'options'           => $optionsUnit,
                  'no_default_option' => true
              ]
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
              [
                  'type'  => 'hidden',
                  'name'  => 'product',
                  'value' => 'create_product'
              ],
              [
                  'type'          => 'select',
                  'name'          => 'type',
                  'id'            => 'add_type',
                  'label'         => 'Jenis',
                  'required'      => true,
                  'options'       => $optionsJenis
              ],
              [
                  'type'          => 'text',
                  'name'          => 'name',
                  'id'            => 'add_name',
                  'label'         => 'Nama Barang',
                  'required'      => true
              ],
              [
                  'type'          => 'number',
                  'name'          => 'price',
                  'id'            => 'add_price',
                  'label'         => 'Harga',
                  'required'      => true
              ],
              [
                  'type'          => 'number',
                  'name'          => 'reasonable_price',
                  'id'            => 'add_reasonable_price',
                  'label'         => 'Harga Maklun',
                  'required'      => true
              ],
              [
                  'type'          => 'number',
                  'name'          => 'failed_price',
                  'id'            => 'add_failed_price',
                  'label'         => 'Harga Gagal',
                  'required'      => true
              ],
              [
                  'type'          => 'select',
                  'name'          => 'unit_type',
                  'id'            => 'add_unit_type',
                  'label'         => 'Satuan',
                  'required'      => true,
                  'options'       => $optionsUnit
              ]
          ]
      ]);

      echo $htmlModalEditProduct;
      echo $htmlModalAddProduct;
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
             oldTableResponsive.outerHTML = '<div class="alert alert-warning">Tidak ada data barang untuk toko ini.</div>';
         }
      }

      displayExport();
    } catch (error) {
      console.error(error);
    }
  }

  async function submitDataAPI(formData, modalId = null) {
    try {
      const response = await fetch('product_action.php', {
        method: 'POST',
        body: formData
      });
      const data = await response.json();
      
      if (data.success) {
        if (modalId) {
          bootstrap.Modal.getInstance(document.getElementById(modalId))?.hide();
        }
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

  document.getElementById('addProductForm')?.addEventListener('submit', function (e) {
    e.preventDefault();
    submitDataAPI(new FormData(this), 'addProductModal');
  });

  document.getElementById('editProductForm')?.addEventListener('submit', function (e) {
    e.preventDefault();
    submitDataAPI(new FormData(this), 'editProductModal');
  });

  document.addEventListener('click', function (e) {
    const btnDelete = e.target.closest('.btn-delete-product');
    
    if (btnDelete) {
      e.preventDefault();
      
      const productId = btnDelete.getAttribute('data-id');
      
      Swal.fire({
        title: 'Yakin ingin menghapus?',
        text: "Data produk akan dihapus permanen.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal'
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('product', 'delete_product');
          formData.append('product_id', productId);
          
          submitDataAPI(formData);
        }
      });
    }
  });

  const editModal = document.getElementById('editProductModal');
  if (editModal) {
    editModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      document.getElementById('edit_product_id').value = button.getAttribute('data-id') || '';
      document.getElementById('edit_type').value = button.getAttribute('data-type') || '';
      document.getElementById('edit_name').value = button.getAttribute('data-name') || '';
      document.getElementById('edit_price').value = button.getAttribute('data-price') || '';
      document.getElementById('edit_unit_type').value = button.getAttribute('data-unit') || '';
      document.getElementById('edit_reasonable_price').value = button.getAttribute('data-reasonable') || '';
      document.getElementById('edit_failed_price').value = button.getAttribute('data-failed') || '';
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

});

hideLoading();
</script>
</body>
</html>