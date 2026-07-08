<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/models/Product.php';
require_once BASE_PATH . '/models/Order.php';
require_once BASE_PATH . '/models/Store.php'; 
require_once BASE_PATH . '/models/Payment.php';
require_once BASE_PATH . '/functions/helpers.php';
require_once BASE_PATH . '/components/Alert.php';

$order_id = (int)startEnk('dek', $_GET['id']);
$orderModel = new Order($koneksi);
$storeModel = new Store($koneksi);
$paymentModel = new Payment($koneksi);
$productModel = new Product($koneksi);

if($paymentModel->getPaidByOrderId($order_id) && !$administrator){
  header("Location: " . BASE_URL . "/customer/");
  exit;
}

if ($role === 'PRODUKSI') {
  header("Location: " . BASE_URL . "/customer/");
}

$order = $orderModel->getOrderById($order_id);

if ($order['store_id'] != $store_id){
  header('Location : index');
  exit;
}
$categories = $productModel->getCategoryByStoreId($store_id);
$resultStores = $storeModel->getStoreForMaklun($store_id);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Nota Order</title>
    <?php include BASE_PATH . '/header.php'; ?>
    <script src="<?= BASE_URL ?>/assets/js/jquery-3.7.1.min.js"></script>
      <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/select2.min.css">
    <script src="<?= BASE_URL ?>/assets/js/select2.min.js"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/select2-bootstrap4.min.css">
    <script>
        const store_id = <?= (int) $store_id ?>;
        const categoriesList = <?= json_encode($categories) ?>;
    </script>
    <style>
    .dark-mode-select{
        background-color: #1e1e1e !important;
        color: #e0e0e0 !important;
        border: none #333 !important;
        border-radius: 7px;
    }
    .dark-mode-select .select2-results__option--highlighted {
        background-color:rgb(63, 42, 42) !important;
        border: none #333 !important;
    }
    <?php if (isset($username) && ($username == 'zannia' || $username == 'vikialvian')) { ?>
    .dark-mode-select{
        background-color: white !important;
        color: rgb(115, 0, 90) !important;
        border: none #333 !important;
    }
    .dark-mode-select .select2-results__option--highlighted {
        background-color: rgb(255, 151, 232) !important;
        border: none #333 !important;
    }
    <?php } ?>
    .dark-mode-select[aria-selected="true"] {
        border: none !important;
        outline: none !important;
    }
    .default-mode-select{
        background-color: white !important;
        color: black !important;
    }
    </style>
</head>
<body>
  <div id="main-wrapper">
    <?php include BASE_PATH . '/navbar.php'; ?>
    <div id="main-content" <?= (isset($mode) && $mode === 1) ? 'class="dark-mode"' : '' ?>>
      <?php include BASE_PATH . '/sidebar.php'; ?>
      <div id="page-content-wrapper">
        <div class="container-fluid py-4 px-2">
          <div class="row align-items-start">
            <div class="col-md-5">
            <form action="<?= BASE_URL ?>/customer?start_date=<?= date('Y-m-d', strtotime($order['date'])) ?>&end_date=<?= date('Y-m-d', strtotime($order['date'])) ?>" method="post">
              <input type="hidden" name="system" value="<?= sanitize($order['system']) ?>">
              <button type="submit" class="btn btn-success mb-3">
                <i class="bi bi-arrow-left"></i> Kembali ke Customer
              </button>
            </form>
              <div class="card shadow-sm p-4 mb-4"
              <?php if ($username == 'zannia' || $username == 'vikialvian') { ?>
                style="background-color: #f5b6cf !important;"
              <?php }elseif($mode === 1){ ?>
                style="background-color: #333 !important; color: #e0e0e0 !important;"
              <?php } ?>
              >
                <h4 class="mb-3">Tambah Item</h4>
                <form id="addItemForm" class="d-flex flex-column">
                  <input type="hidden" name="order_id" value="<?= sanitize($order_id) ?>">
                  <div class="row mb-3">
                      <label for="kategori" class="col-sm-2 col-form-label">Kategori</label>
                      <div class="col-sm-10">
                      <select id="kategori" name="kategori" class="form-select select2" required>
                        <option value="" selected>-- Pilih Kategori --</option>
                        <?php foreach ($categories as $category): ?>
                          <option value="<?= sanitize($category['category_id']) ?>" data-name="<?= sanitize($category['name']) ?>">
                            <?= sanitize($category['name']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      </div>
                  </div>
                  <div class="row mb-3">
                      <label for="judul" class="col-sm-2 col-form-label">Judul</label>
                      <div class="col-sm-10">
                          <select id="judul" name="judul" class="form-select select2" required>
                              <option value="" selected>-- Pilih Judul --</option>
                          </select>
                      </div>
                  </div>
                  <div class="row mb-3" id="ukuranInputs" style="display:none;">
                      <label class="col-sm-2 col-form-label">Ukuran</label>
                      <div class="col-sm-5">
                          <input type="number" step="0.01" min="0" id="panjang" class="form-control" placeholder="Panjang (m)">
                      </div>
                      <div class="col-sm-5">
                          <input type="number" step="0.01" min="0" id="lebar" class="form-control" placeholder="Lebar (m)">
                      </div>
                  </div>
                  <div class="row mb-3" id="ukuranJerseyRow" style="display:none;">
                    <label for="ukuranJersey" class="col-sm-2 col-form-label">Ukuran</label>
                    <div class="col-sm-10">
                      <select id="ukuranJersey" name="ukuran_jersey" class="form-select select2">
                        <option value="">-- Pilih Ukuran --</option>
                        <option value="XS">XS</option>
                        <option value="S">S</option>
                        <option value="M">M</option>
                        <option value="L">L</option>
                        <option value="XL">XL</option>
                        <option value="2XL">2XL</option>
                        <option value="3XL">3XL</option>
                        <option value="4XL">4XL</option>
                        <option value="5XL">5XL</option>
                      </select>
                    </div>
                  </div>
                  <div class="row mb-3" id="bahanSublim" style="display:none;">
                      <label class="col-sm-2 col-form-label">Kiloan</label>
                      <div class="col-sm-10">
                          <input type="number" step="0.01" min="0" id="kiloan" class="form-control" placeholder="Berat (kg)">
                      </div>
                  </div>
                  <div class="row mb-3" id="settingDesain" style="display:none;">
                      <label class="col-sm-2 col-form-label">Waktu</label>
                      <div class="col-sm-10">
                          <input type="number" min="00:00" max="23:59" id="waktu" class="form-control" placeholder="Price / Menit">
                      </div>
                  </div>
                  <div class="row mb-3" id="ukuranDropdownRow" style="display:none;">
                    <label for="ukuranDropdown" class="col-sm-2 col-form-label">Ukuran</label>
                    <div class="col-sm-10">
                      <select id="ukuranDropdown" name="ukuran_variasi" class="form-select select2">
                        <option value="">-- Pilih Ukuran --</option>
                      </select>
                    </div>
                  </div>
                  <div class="row mb-3" id="ukuranSublimRow" style="display:none;">
                    <label for="ukuranSublim" class="col-sm-2 col-form-label">Ukuran</label>
                    <div class="col-sm-5">
                        <input type="number" step="0.01" min="0" id="panjangSublim" name="panjang_sublim" class="form-control" placeholder="Panjang (m)">
                    </div>
                    <div class="col-sm-5">
                      <select id="lebarSublim" name="lebar_sublim" class="form-select select2">
                        <option value="">-- Lebar Bahan --</option>
                        <option value="1.1">1.1</option>
                        <option value="1.2">1.2</option>
                        <option value="1.5">1.5</option>
                        <option value="1.6">1.6</option>
                        <option value="1.8">1.8</option>
                      </select>
                    </div>
                  </div> 
                  <div class="row mb-3">
                      <label for="qty" class="col-sm-2 col-form-label">Qty</label>
                      <div class="col-sm-10">
                          <input type="number" id="qty" name="qty" class="form-control" min="1" required>
                      </div>
                  </div>
                  <div class="row mb-4" id="finishingRow">
                      <label class="col-sm-2 col-form-label">Finishing</label>
                      <div class="col-sm-10">
                          <div id="finishing" class="d-flex flex-wrap gap-3 mb-2"></div>
                      </div>
                  </div>
                  <div class="row mb-5 align-items-center">
                    <div class="col-auto">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="enableDiskon">
                        <label class="form-check-label" for="enableDiskon">Diskon</label>
                      </div>
                    </div>
                    <div class="col-auto">
                      <input type="number" class="form-control" id="diskonInput" style="display: none;" min="0" max="100">
                    </div>
                  </div>
                  <div class="row mb-4">
                    <div class="col-6">
                      <button type="button" class="btn btn-secondary w-100" data-bs-toggle="modal" data-bs-target="#modalTambahLainnya" style="display: none;">
                        Tambah Lainnya
                      </button>
                    </div>
                    <div class="col-12">
                      <button type="button" class="btn btn-primary w-100" id="btnTambah">
                        Tambah Item
                      </button>
                    </div>
                  </div>
                </form>
                <div id="priceDisplay" style="margin-top:10px; font-weight:bold;">Total Harga: Rp 0</div>
              </div>
            </div>
            <div class="col-md-7">
              <div class="row mb-4">
                <div class="col-md-4">
                  <strong>Nomorator:</strong> <?= sanitize($order['nomorator']) ?>
                </div>
                <div class="col-md-4">
                  <strong>Nama:</strong> <?= sanitize(title_case($order['customer_name'])) ?>
                </div>
                <div class="col-md-4">
                  <strong>Tanggal:</strong> <?= date('d M Y, H:i', strtotime($order['date'])) ?>
                </div>
              </div>
              <div class="table-responsive">
                <table class="table table-bordered table-striped" id="orderItemsTable">
                  <thead class="table-primary">
                    <tr>
                      <th>Judul</th>
                      <th>Finishing</th>
                      <th>Ukuran</th>
                      <th>Qty</th>
                      <th>Satuan</th>
                      <th>Jumlah</th>
                      <th>Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                  </tbody>
                </table>
              </div>
              <div class="row mt-4 mb-3">
                <div class="col-md-6">
                  <strong>Deadline:</strong> <?= date('d M Y, H:i', strtotime($order['deadline'])) ?>
                </div>
                <div class="col-md-6">
                  <strong>Operator:</strong> <?= sanitize($order['operator_initial']) ?>
                </div>
              </div>
              <div id="noteSection">
                <div id="noteDisplay" class="mb-3"></div>
                <form id="addNote">
                  <div class="mb-3">
                    <label for="exampleFormControlTextarea1" class="form-label">Catatan untuk konsumen 📝</label>
                    <textarea class="form-control mb-3" id="exampleFormControlTextarea1" rows="3" name="note" placeholder="!Hanya untuk konsumen, Untuk Operator ada di transaksi detil"></textarea>
                    <input type="hidden" name="order_id" value="<?= $order_id ?>">
                    <button type="submit" class="btn btn-primary p-2">Update Catatan </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php include BASE_PATH . '/footer.php'; ?>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const elMainContent = document.getElementById('main-content');
  const elKategori = document.getElementById('kategori');
  const elJudul = document.getElementById('judul');
  const elPanjang = document.getElementById('panjang');
  const elLebar = document.getElementById('lebar');
  const elQty = document.getElementById('qty');
  const elFinishing = document.getElementById('finishing');
  const elBtnTambah = document.getElementById('btnTambah');
  const elEnableDiskon = document.getElementById('enableDiskon');
  const elDiskonInput = document.getElementById('diskonInput');
  const bahanSublim = document.getElementById('bahanSublim');
  const ukuranInputs = document.getElementById('ukuranInputs');
  const finishingRow = document.getElementById('finishingRow');
  const elUkuranJersey = document.getElementById('ukuranJersey');
  const elKiloan = document.getElementById('kiloan');
  const elWaktu = document.getElementById('waktu');
  const size = document.getElementById('ukuranDropdown');
  let ukuranMap = {};

  function getKategoriName() {
      if (!elKategori) return '';
      const opt = elKategori.options[elKategori.selectedIndex];
      return opt ? (opt.dataset.name || '') : '';
  }

  function loadOrderItems() {
      fetch(`../routes/?action=get_order_items&order_id=<?= (int)$order_id ?>`)
          .then(res => res.json())
          .then(res => {
              const items = res.data.items;
              const total = res.data.total;
              const diskonPerProduk = res.data.diskon_per_produk || {};
              const tbody = document.querySelector('#orderItemsTable tbody');
              tbody.innerHTML = '';
              const itemsByJudul = {};
              
              items.forEach(item => {
                  if (!itemsByJudul[item.judul]) itemsByJudul[item.judul] = [];
                  itemsByJudul[item.judul].push(item);
              });

              for (const judul in itemsByJudul) {
                  const grup = itemsByJudul[judul];
                  grup.forEach(item => {
                      tbody.insertAdjacentHTML('beforeend', `
                          <tr class="order-item-row" 
                              data-order-item-id="${item.order_item_id}"
                              data-kategori="${item.type || ''}"
                              data-judul="${item.product_name || item.judul}"
                              data-qty="${item.quantity}"
                              data-unit="${item.unit}"
                              data-size="${item.size || ''}"
                              data-finishing="${item.finishing || ''}"
                              data-finishing-utama="${item.finishing_utama || ''}">
                              <td>${item.judul}</td>
                              <td>${item.finishing_names || '-'}</td>
                              <td>${item.size || '-'}</td>
                              <td>${item.quantity}</td>
                              <td>${item.unit}</td>
                              <td>${item.amount}</td>
                              <td class="d-flex gap-2">
                                  <button class="btn btn-sm btn-danger btn-delete">Hapus</button>
                                  <select class="form-select maklunCabang" style="width: 120px;" data-order-item-id="${item.order_item_id}" data-maklun="${item.maklun ?? ''}">
                                      <option value="0">Maklun</option>
                                      <?php foreach ($resultStores as $rs) { ?>
                                          <option value="<?= $rs['store_id'] ?>"><?= $rs['name'] ?></option>
                                      <?php } ?>
                                  </select>
                              </td>
                          </tr> 
                      `);
                  });
              }

              const maklunCabangElements = document.querySelectorAll(".maklunCabang");
              maklunCabangElements.forEach(select => {
                  const maklunValue = select.dataset.maklun;
                  if (maklunValue) {
                      select.value = maklunValue;
                  }
                  
                  select.addEventListener('change', (e) => {
                      const maklunStoreId = e.target.value;
                      const orderItemId = select.dataset.orderItemId;
                      fetch('../routes/?action=maklun', {
                          method: 'POST',
                          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                          body: `order_item_id=${encodeURIComponent(orderItemId)}&store_id_maklun=${maklunStoreId}`
                      })
                      .then(res => res.json())
                      .then(data => {
                          if (data.success) {
                              loadOrderItems();
                          } else {
                              alert('Gagal ngemaklun: ' + data.message);
                          }
                      })
                      .catch(err => {
                          alert('Terjadi kesalahan: ' + err);
                      });
                  });
              });

              document.querySelectorAll('.order-item-row').forEach(row => {
                  row.addEventListener('dblclick', function () {
                      const kategoriValue = this.dataset.kategori;
                      const judul = this.dataset.judul;
                      const qty = this.dataset.qty;
                      const size = this.dataset.size;
                      const finishing = this.dataset.finishing;
                      const orderItemId = this.dataset.orderItemId;
                      
                      document.getElementById('addItemForm').setAttribute('data-order-item-id', orderItemId);

                      const elKategoriLocal = document.getElementById('kategori');
                      if (elKategoriLocal && kategoriValue) {
                          let matchedOpt = null;
                          Array.from(elKategoriLocal.options).forEach(opt => {
                              if (opt.value === kategoriValue || opt.dataset.name === kategoriValue) {
                                  matchedOpt = opt.value;
                              }
                          });
                          if (matchedOpt) {
                              $(elKategoriLocal).val(matchedOpt).trigger('change');
                          }
                      }

                      setTimeout(() => {
                          if (elJudul) {
                              const options = Array.from(elJudul.options);
                              const optionMatch = options.find(opt => opt.text.trim().toLowerCase() === judul.trim().toLowerCase());
                              if (optionMatch) {
                                  $(elJudul).val(optionMatch.value).trigger('change');
                              }
                          }

                          if (finishing && elFinishing) {
                              const finishingNames = finishing.toString().split(',').map(s => s.trim().toLowerCase());
                              document.querySelectorAll('.finishing-checkbox').forEach(cb => {
                                  const text = (cb.dataset.name || '').toLowerCase();
                                  const val = (cb.value || '').toLowerCase();
                                  cb.checked = finishingNames.includes(text) || finishingNames.includes(val);
                              });
                              updatePricePreview();
                          } else {
                              document.querySelectorAll('.finishing-checkbox').forEach(cb => cb.checked = false);
                              updatePricePreview();
                          }
                      }, 300);

                      if (elQty) elQty.value = qty;

                      if (size && size.includes('x')) {
                          const sizeParts = size.split('x').map(s => parseFloat(s.trim()));
                          if (elPanjang) elPanjang.value = sizeParts[0];
                          if (elLebar) elLebar.value = sizeParts[1];
                          const elUkuranInputs = document.getElementById('ukuranInputs');
                          if (elUkuranInputs) elUkuranInputs.style.display = 'flex';
                      }

                      const formElement = document.getElementById("addItemForm");
                      if (formElement) {
                          window.scrollTo({
                              top: formElement.offsetTop - 100,
                              behavior: 'smooth'
                          });
                      }
                  });
              });

              for (const judulKey in diskonPerProduk) {
                  const nilaiDiskon = Number(diskonPerProduk[judulKey]);
                  tbody.insertAdjacentHTML('beforeend', `
                      <tr id="orderDiskonRow_${judulKey}">
                          <td colspan="5" class="text-end"><strong>Diskon ${judulKey}</strong></td>
                          <td colspan="2"><strong>Rp ${nilaiDiskon.toLocaleString('id-ID')}/m?pcs</strong></td>
                      </tr>
                  `);
              }

              tbody.insertAdjacentHTML('beforeend', `
                  <tr id="orderTotalRow">
                      <td colspan="5" class="text-end"><strong>Total:</strong></td>
                      <td colspan="2"><strong>Rp ${Number(total).toLocaleString('id-ID')}</strong></td>
                  </tr>
              `);
          });
  }

  function loadProdukByKategori(kategoriId, kategoriName) {
      if (elJudul) {
          $(elJudul).empty().append('<option value="">-- Pilih Judul --</option>').trigger('change');
      }
      ukuranMap = {};
      const seen = new Set();

      fetch(`../routes/?action=get_product&category_id=${encodeURIComponent(kategoriId)}`)
          .then(response => response.json())
          .then(data => {
              data.data.forEach(product => {
                  let nameOnly = product.name;
                  if (kategoriName === 'PAKET INDOOR OUTDOOR') {
                      nameOnly = nameOnly.replace(/\s*\d+(\.\d+)?\s*[x×X]\s*\d+(\.\d+)?/gi, '').trim();
                  }
                  const ukuranMatch = product.name.match(/(\d+(\.\d+)?\s*[x×X]\s*\d+(\.\d+)?)/i);
                  const ukuran = ukuranMatch ? ukuranMatch[0].replace(/×/gi, 'x') : null;

                  if (!ukuranMap[nameOnly]) ukuranMap[nameOnly] = [];
                  if (ukuran && !ukuranMap[nameOnly].includes(ukuran)) {
                      ukuranMap[nameOnly].push(ukuran);
                  }

                  if (!seen.has(nameOnly)) {
                      seen.add(nameOnly);
                      const opt = document.createElement('option');
                      opt.value = product.product_id;
                      opt.dataset.name = nameOnly;
                      opt.dataset.unit = product.unit_type;
                      opt.dataset.price = product.price;
                      opt.textContent = nameOnly;
                      if (elJudul) elJudul.appendChild(opt);
                  }
              });
          });

      if (elUkuranJersey) $(elUkuranJersey).val('').trigger('change');
      if (elKiloan) elKiloan.value = '';
      if (elWaktu) elWaktu.value = '';
      loadFinishingOptions(kategoriId);
  }

  function loadFinishingOptions(kategori) {
      if (elFinishing) {
          elFinishing.innerHTML = '';
          elFinishing.style.display = 'flex'; 
      }

      if (kategori) {
          fetch(`../routes/?action=get_finishing&category_id=${encodeURIComponent(kategori)}`)
              .then(response => response.json())
              .then(data => {
                  const seenFinishing = new Set();
                  data.data.forEach(finishing => {
                      if (!seenFinishing.has(finishing.name)) {
                          seenFinishing.add(finishing.name);
                          elFinishing.insertAdjacentHTML('beforeend', `
                              <div class="form-check">
                                  <input class="form-check-input finishing-checkbox" type="checkbox" value="${finishing.finishing_id}" data-name="${finishing.name}" data-price="${finishing.price}">
                                  <label class="form-check-label">${finishing.name}</label>
                              </div>
                          `);
                      }
                  });
              });
      }
  }

  function toggleFinishingDisplay(kategori) {
      const row = document.getElementById('finishingRow');
      if (row) row.style.display = '';

      switch (kategori) {
          case 'LASER A3':
          case 'INDOOR':
          case 'OUTDOOR':
          case 'SUBLIM':
          case 'AKRILIK':
          case 'JERSEY':
              break;
          case 'PAKET INDOOR OUTDOOR':
              if (row) row.style.display = 'none';
              return;
          case 'STAMP':
          case 'MERCENDISE':
          case 'MERCENDISE AKRILIK':
              if (row) row.style.display = 'none';
              if (elFinishing) elFinishing.innerHTML = '';
              return;
      }

      if (kategori === 'AKRILIK') {
          if (elPanjang) elPanjang.placeholder = 'Panjang (cm)';
          if (elLebar) elLebar.placeholder = 'Lebar (cm)';
      } else {
          if (elPanjang) elPanjang.placeholder = 'Panjang (m)';
          if (elLebar) elLebar.placeholder = 'Lebar (m)';
      }

      if (kategori === 'DTF') {
          if (elLebar) {
              elLebar.value = '0.58';
              elLebar.readOnly = true;
          }
          if (elPanjang) {
              elPanjang.value = '';
              elPanjang.readOnly = false;
          }
      } else {
          if (elLebar) {
              elLebar.value = '';
              elLebar.readOnly = false;
          }
          if (elPanjang) {
              elPanjang.value = '';
              elPanjang.readOnly = false;
          }
      }
  }

  if (elKategori) {
      elKategori.addEventListener('change', () => {
          if (elJudul) elJudul.focus();
      });
  }

  if (elJudul) {
      elJudul.addEventListener('change', () => {
          if (elPanjang && elPanjang.offsetParent !== null) {
              elPanjang.focus();
          } else if (elQty) {
              elQty.focus();
          }
      });
  }

  if (elPanjang) {
      elPanjang.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === '*' || e.key === 'PageDown') {
              e.preventDefault();
              if (elLebar) elLebar.focus();
          } else if (e.key === 'PageUp') {
              e.preventDefault();
              if (elJudul) elJudul.focus();
          }
      });
  }

  if (elLebar) {
      elLebar.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === 'PageDown') {
              e.preventDefault();
              if (elQty) elQty.focus();
          } else if (e.key === 'PageUp') {
              e.preventDefault();
              if (elPanjang) elPanjang.focus();
          }
      });
  }

  if (elQty) {
      elQty.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === '=') {
              e.preventDefault();
              if (elBtnTambah) elBtnTambah.click();
              setTimeout(() => {
                  if (elKategori) elKategori.focus();
              }, 100);
          } else if (e.key === 'PageDown') {
              e.preventDefault();
              if (elFinishing) elFinishing.focus();
          } else if (e.key === 'PageUp') {
              e.preventDefault();
              if (elLebar) elLebar.focus();
          }
      });
  }

  if (elKategori) {
      elKategori.addEventListener('change', function () {
          const kategoriId = this.value;
          const kategoriName = getKategoriName();
          toggleFinishingDisplay(kategoriName);
          loadProdukByKategori(kategoriId, kategoriName);
      });
  }

  function updateUkuranView(name) {
      const nameStr = name ? String(name) : '';
      const kategori = getKategoriName();
      const selectedOption = elJudul ? elJudul.options[elJudul.selectedIndex] : null;
      const unit = (selectedOption && selectedOption.dataset.unit) ? selectedOption.dataset.unit : '';
      const judul = (selectedOption && selectedOption.dataset.name) ? selectedOption.dataset.name : '';
      const idsToHide = ['ukuranSublimRow', 'ukuranInputs', 'ukuranJersey', 'bahanSublim', 'settingDesain'];
      idsToHide.forEach(id => {
          const el = document.getElementById(id);
          if (el) {
              const rowWrapper = el.closest('.row');
              if (rowWrapper) rowWrapper.style.display = 'none';
              else el.style.display = 'none';
          }
      });

      if (nameStr.includes('TRANSFERPAPER') || nameStr.includes('PRINT PRES')) {
          const subRow = document.getElementById('ukuranSublimRow');
          if (subRow && subRow.closest('.row')) subRow.closest('.row').style.display = '';
      } else if (kategori === 'JERSEY') {
          const jerRow = document.getElementById('ukuranJersey');
          if (jerRow && jerRow.closest('.row')) jerRow.closest('.row').style.display = '';
      } else if (unit === 'M2' || unit === 'CM2') {
          const inpRow = document.getElementById('ukuranInputs');
          if (inpRow && inpRow.closest('.row')) inpRow.closest('.row').style.display = '';
      } else if (kategori === 'JASA' && (judul === 'SETTING' || judul === 'POTONG AKRILIK')) {
          const setRow = document.getElementById('settingDesain');
          if (setRow && setRow.closest('.row')) setRow.closest('.row').style.display = '';
      }

      if (judul.includes('BAHAN') && unit === 'PCS') {
          if (bahanSublim) bahanSublim.style.display = '';
          if (ukuranInputs) ukuranInputs.style.display = 'none';
          if (finishingRow) finishingRow.style.display = 'none';
      }
  }

  if (elJudul) {
      $(elJudul).on('change', function () {
          const selectedOption = this.options[this.selectedIndex];
          const name = (selectedOption && selectedOption.dataset && selectedOption.dataset.name) ? selectedOption.dataset.name : '';
          const ukuranList = ukuranMap[name] || [];
          const ukuranDropdown = document.getElementById('ukuranDropdown');
          const ukuranDropdownRow = document.getElementById('ukuranDropdownRow');

          if (ukuranList.length > 0) {
              if (ukuranDropdown) {
                  $(ukuranDropdown).empty().append('<option value="">-- Pilih Ukuran --</option>');
                  ukuranList.forEach(uk => {
                      $(ukuranDropdown).append(`<option value="${uk}">${uk}</option>`);
                  });
                  $(ukuranDropdown).trigger('change');
              }
              if (ukuranDropdownRow) ukuranDropdownRow.style.display = '';
          } else {
              if (ukuranDropdownRow) ukuranDropdownRow.style.display = 'none';
          }
          updateUkuranView(name);
      });
  }

  if (elBtnTambah) {
      elBtnTambah.addEventListener('click', function () {
          if (typeof submitTambahItem === 'function') {
              submitTambahItem(elJudul ? elJudul.value : '');
          }
      });
  }

  if (elEnableDiskon) {
      elEnableDiskon.addEventListener('change', function () {
          elDiskonInput.style.display = this.checked ? 'block' : 'none';
          if (!this.checked) elDiskonInput.value = '';
      });
  }

  function submitTambahItem(productId) {
    const elForm = document.getElementById('addItemForm');
    const kategori = getKategoriName();
    const selectedOpt = elJudul?.options[elJudul.selectedIndex];
    const selectedJudulName = selectedOpt?.dataset?.name || '';
    
    let panjang = parseFloat(elPanjang?.value) || 0;
    let lebar = parseFloat(elLebar?.value) || 0;
    if (selectedJudulName.includes('TRANSFERPAPER') || selectedJudulName.includes('PRINT PRES')) {
        panjang = parseFloat(document.getElementById('panjangSublim')?.value) || 0;
        lebar = parseFloat(document.getElementById('lebarSublim')?.value) || 0;
    }

    const finishingIds = Array.from(document.querySelectorAll('.finishing-checkbox:checked')).map(cb => cb.value);

    const dataPost = {
        order_id: "<?= (int)$order_id ?>",
        product_id: productId,
        judul: selectedJudulName,
        quantity: parseInt(elQty?.value) || 1,
        finishing: finishingIds.join(','),
        panjang: panjang,
        lebar: lebar,
        kiloan: parseFloat(elKiloan?.value) || 0,
        waktu: parseFloat(elWaktu?.value) || 0,
        size : size?.value || elUkuranJersey?.value
    };

    if (elEnableDiskon?.checked) {
        dataPost.diskon = parseFloat(elDiskonInput?.value) || 0;
    }
    const orderItemId = elForm.getAttribute('data-order-item-id');
    if (orderItemId) dataPost.order_item_id = orderItemId;
    
    eksekusiTambahItem(dataPost);
  }

  function eksekusiTambahItem(dataPost) {
    const btn = document.getElementById('btnTambah');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Loading...';
    btn.disabled = true;
    fetch("../routes/?action=create_item", { 
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(dataPost)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadOrderItems();
            document.getElementById('addItemForm').reset();
            document.getElementById('addItemForm').removeAttribute('data-order-item-id');
            document.querySelectorAll('.finishing-checkbox').forEach(cb => cb.checked = false);
            $(document.getElementById('kategori')).trigger('change');
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(err => {
        showAlert('error', 'Terjadi kesalahan sistem: ' + err);
    })
    .finally(() => {
        btn.innerHTML = 'Tambah Item';
        btn.disabled = false;
    });
  }

  document.querySelector('#orderItemsTable').addEventListener('click', function (e) {
      if (e.target && e.target.classList.contains('btn-delete')) {
          const row = e.target.closest('tr');
          const orderItemId = row.dataset.orderItemId;
          if (!orderItemId) return;
          fetch('../routes/?action=delete_item', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: `order_item_id=${encodeURIComponent(orderItemId)}`
          })
          .then(res => res.json())
          .then(response => {
              if (response.success) {
                  loadOrderItems();
              } else {
                  alert('Gagal menghapus item: ' + response.message);
              }
          })
          .catch(err => alert("Gagal koneksi ke server: " + err));
      }
  });

  function loadNote() {
      const orderId = <?= (int)$order_id ?>;
      fetch(`../routes/?action=get_note&order_id=${orderId}`)
          .then(res => res.json())
          .then(response => {
              document.getElementById('noteDisplay').innerHTML = `<div class="alert alert-success" role="alert">${response.data.note}</div>`;
          }).catch(err => {
            document.getElementById('noteDisplay').innerHTML = ``;
          });
  }

  const elAddNote = document.getElementById('addNote');
  if (elAddNote) {
      elAddNote.addEventListener('submit', function (e) {
          e.preventDefault();
          const formData = new URLSearchParams(new FormData(this));
          fetch('../routes/?action=save_note', {
              method: 'POST',
              body: formData
          })
          .then(res => res.json())
          .then(response => {
              showAlert('success', 'Catatan berhasil disimpan.');
              document.getElementById('noteDisplay').innerHTML = `<div class="alert alert-success">${response.data.note}</div>`;
              document.getElementById('exampleFormControlTextarea1').value = '';
          });
      });
  }
  loadNote();

  function updatePricePreview() {
      if (!elJudul) return;
      const judulValue = elJudul.value || '';
      const kategoriValue = elKategori ? elKategori.value : '';
      const qtyValue = elQty ? elQty.value : '';
      if (!judulValue.trim() || !kategoriValue.trim() || !qtyValue.trim()) return;

      const selectedOption = elJudul.options[elJudul.selectedIndex];
      const judulAsli = selectedOption?.dataset?.name || '';
      const kategoriAsli = getKategoriName();
      
      let panjangAsli = elPanjang ? parseFloat(elPanjang.value) || 0 : 0;
      let lebarAsli = elLebar ? parseFloat(elLebar.value) || 0 : 0;
      if ((judulAsli.includes('TRANSFERPAPER') || judulAsli.includes('PRINT PRES')) && kategoriAsli === 'SUBLIM' ) {
          panjangAsli = parseFloat(document.getElementById('lebarSublim')?.value) || 0;
          lebarAsli = parseFloat(document.getElementById('panjangSublim')?.value) || 0;
      }

      const finishingIds = Array.from(document.querySelectorAll('.finishing-checkbox:checked')).map(cb => cb.value);

      const dataPost = {
          order_id: "<?= (int)$order_id ?>",
          product_id: elJudul.value,
          judul: judulAsli,
          quantity: parseInt(elQty?.value) || 1,
          finishing: finishingIds.join(','),
          panjang: panjangAsli,
          lebar: lebarAsli,
          kiloan: parseFloat(elKiloan?.value) || 0,
          waktu: parseFloat(elWaktu?.value) || 0,
          ukuranJersey: elUkuranJersey?.value || '',
          diskon: elEnableDiskon?.checked ? parseFloat(elDiskonInput?.value) || 0 : 0,
          size : size?.value || elUkuranJersey?.value
      };
      
      fetch('../routes/?action=price', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(dataPost)
      })
      .then(response => {
          if (!response.ok) throw new Error('HTTP Error');
          return response.json();
      })
      .then(response => {
          const priceDisplay = document.getElementById('priceDisplay');
          if (priceDisplay) {
              priceDisplay.textContent = response.success ? 'Total Harga: Rp ' + Number(response.data.total || 0).toLocaleString('id-ID') : (response.message || '');
          }
      })
      .catch(() => {
          const priceDisplay = document.getElementById('priceDisplay');
          if (priceDisplay) priceDisplay.textContent = 'Gagal koneksi server';
      });
  }  

  const inputsToWatch = ['kategori', 'judul', 'ukuranDropdown', 'panjang', 'lebar', 'kiloan', 'waktu', 'ukuranJersey', 'qty', 'enableDiskon', 'lebarSublim', 'panjangSublim', 'diskonInput'];
  inputsToWatch.forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('change', updatePricePreview);
      if (el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA')) el.addEventListener('keyup', updatePricePreview);
  });
  
  document.addEventListener('change', (e) => {
      if (e.target.classList.contains('finishing-checkbox')) updatePricePreview();
  });

  document.addEventListener('DOMContentLoaded', () => {
      loadNote();
      updatePricePreview();
  });

  loadOrderItems();
});
</script>
  </body>
  </html>