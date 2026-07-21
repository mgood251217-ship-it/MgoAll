<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/components/Modal.php';
require_once BASE_PATH . '/components/Alert.php';
require_once BASE_PATH . '/components/Table.php';
require_once BASE_PATH . '/components/Loading.php';
require_once BASE_PATH . '/components/Icon.php';
require_once BASE_PATH . '/models/User.php';
require_once BASE_PATH . '/models/Store.php';
require_once BASE_PATH . '/models/Product.php';
require_once BASE_PATH . '/controllers/ReportController.php';

$productModel = new Product($koneksi);
$userModel = new User($koneksi);
$storeModel = new Store($koneksi);
$reportController = new ReportController($koneksi);

$items = $reportController->failure();

$users = $userModel->getUsersInitial($store_id);
$machines = $storeModel->getMachineByStoreId($store_id);

$categories = $productModel->getCategoryByStoreId($store_id);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Log Kegagalan</title>
  <?php include BASE_PATH . '/header.php'; ?>
      <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
      <link href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.3.1/dist/select2-bootstrap4.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/content.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dark_mode.css">
    <?php if (isset($username) && ($username == 'zannia' || $username == 'vikialvian')) { ?>
      <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/pink_mode.css">
    <?php } ?>
        <script>
            const BASE_URL = '<?= BASE_URL ?>';
            const store_id = <?= (int) $store_id ?>;
        </script>
  <style>
    #detailLogModal .modal-dialog { max-width: 80vw; }
    #detailLogModal .modal-content { height: 80vh; }
    #detailLogBody { overflow-y: auto; max-height: calc(80vh - 100px); }
  </style>
</head>
<body>
  <div id="main-wrapper">
    <?php include '../navbar.php'; ?>
    <div id="main-content" <?= (isset($mode) && $mode === 1) ? 'class="dark-mode"' : '' ?>>
      <?php include '../sidebar.php'; ?>

      <div id="page-content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h1 class="mb-0">Log Kegagalan</h1>
            <?php $showExport = true; include BASE_PATH . '/interval_date.php'; ?>
        </div>

        <?php if (empty($items)): ?>
          <div class="alert alert-warning">Belum ada Log</div>
        <?php else: ?>
        <?php
        $htmlTableGagal = [
            'id'             => 'tableGagal',
            'data'           => $items ?? [],
            'empty_message'  => 'Tidak ada data kegagalan.',
            'table_class'    => 'table table-bordered table-striped',
            'thead_class'    => 'table-primary',
            'row_attributes' => fn($row) => 'class="order-row" data-calc-id="' . $row['failure_id'] . '"',
            'columns'        => [
                ['header' => 'No', 'type' => 'number'],
                ['header' => 'Tanggal', 'field' => 'formatted_date'],
                ['header' => 'Nomorator', 'field' => 'nomorator'],
                ['header' => 'Customer', 'field' => 'customer_name'],
                ['header' => 'Operator', 'field' => 'operator_name'],
                [
                    'header' => 'Mesin',
                    'render' => fn($row) => ($row['nama_mesin'] ?? '-') . '<br><small class="text-muted">' . ($row['machine_type'] ?? '') . '</small>'
                ],
                ['header' => 'Judul Produk', 'field' => 'judul'],
                ['header' => 'Ukuran', 'field' => 'size'],
                ['header' => 'Qty', 'field' => 'quantity'],
                ['header' => 'Finishing', 'field' => 'finishing_names_str'],
                [
                    'header' => 'Detail Gagal',
                    'render' => fn($row) => $row['detail_gagal']
                ],
                [
                    'header' => 'Kerugian',
                    'render' => fn($row) => '<span class="text-danger fw-bold">Rp ' . number_format($row['total_loss'], 0, ',', '.') . '</span>'
                ],
                [
                    'header' => 'Beban',
                    'render' => fn($row) => '<span class="text-danger fw-bold">' . $row['loss_burden'] . '</span>'
                ],
                [
                    'header' => 'Keterangan',
                    'render' => fn($row) => '<span class="text-danger fw-bold">' . $row['info'] . '</span>'
                ],
                [
                    'header'  => 'Aksi',
                    'type'    => 'action_buttons',
                    'buttons' => [
                        [
                            'type'  => 'button',
                            'icon'  => get_icon('update', ['class' => 'me-1']),
                            'text'  => 'Info',
                            'color' => 'info',
                            'class' => 'text-white mb-1 me-1',
                            'onclick' => [
                                'function' => 'bukaModalEditInfo',
                                'param_fields' => ['failure_id', 'info'] 
                            ]
                        ],
                        [
                            'type'        => 'button',
                            'icon'        => get_icon('delete', ['class' => 'me-1']),
                            'text'        => 'Hapus',
                            'color'       => 'danger',
                            'class'       => 'mb-1',
                            'onclick' => [
                                'function' => 'deleteFailure',
                                'param_fields' => ['failure_id'] 
                            ]
                        ]
                    ]
                ]
            ]
        ];

        echo renderTable($htmlTableGagal);
        ?>
        <?php endif; ?>
          <div class="modal fade" id="editInfoModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content shadow-sm" <?= (isset($mode) && $mode === 1) ? 'style="background-color: #333 !important; color: #e0e0e0 !important;"' : '' ?>>
                <div class="modal-header border-bottom pb-3">
                  <h5 class="modal-title mb-0">Edit Keterangan / Info</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" <?= (isset($mode) && $mode === 1) ? 'style="filter: invert(1);"' : '' ?>></button>
                </div>
                
                <form id="formEditInfo">
                  <div class="modal-body p-4">
                    <input type="hidden" id="edit_failure_id" name="failure_id">
                    
                    <div class="mb-3">
                      <label for="edit_info" class="form-label fw-bold">Keterangan Tambahan</label>
                      <textarea class="form-control" id="edit_info" name="info" rows="4" placeholder="Masukkan informasi tambahan mengenai kegagalan ini..."></textarea>
                    </div>
                  </div>
                  
                  <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary fw-bold">Simpan Perubahan</button>
                  </div>
                </form>
              </div>
            </div>
          </div>

            <div class="modal fade" id="detailLogModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl" >
                    <div class="modal-content shadow-sm" <?= (isset($mode) && $mode === 1) ? 'style="background-color: #333 !important; color: #e0e0e0 !important;"' : '' ?>>
                        
                        <div class="modal-header border-bottom pb-3">
                            <h4 class="modal-title mb-0">Tambah Log Kegagalan</h4>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" <?= (isset($mode) && $mode === 1) ? 'style="filter: invert(1);"' : '' ?>></button>
                        </div>

                        <div class="modal-body p-4">
                            <form id="addItemForm">
                                <input type="hidden" name="order_id" value="">

                                <div class="row">
                                    <div class="col-lg-4 border-end pe-lg-4 mb-4 mb-lg-0">
                                        <h5 class="mt-0 mb-3 text-primary border-bottom pb-2">1. Data Pekerjaan</h5>

                                        <div class="row">
                                          <div class="col-6 mb-2">
                                              <label for="nomorator" class="form-label mb-1">Nomorator</label>
                                              <input type="text" id="nomorator" name="nomorator" class="form-control form-control-sm" placeholder="Masukkan Nomorator">
                                          </div>

                                          <div class="col-6 mb-2">
                                              <label for="customer_name" class="form-label mb-1">Customer</label>
                                              <input type="text" id="customer_name" name="customer_name" class="form-control form-control-sm" placeholder="Nama Customer">
                                          </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-6 mb-2">
                                                <label class="form-label mb-1">Operator</label>
                                                <select name="user_id" id="user_id" class="form-select form-select-sm select2" required>
                                                    <option value="">-- Pilih --</option>
                                                    <?php foreach ($users as $id => $initial): ?>
                                                    <option value="<?= $id ?>"><?= htmlspecialchars($initial) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-6 mb-2">
                                                <label class="form-label mb-1">Mesin</label>
                                                <select name="machine_id" id="machine_id" class="form-select form-select-sm select2" required>
                                                    <option value="">-- Pilih --</option>
                                                    <?php foreach ($machines as $mesin): ?>
                                                    <option value="<?= $mesin['machine_id'] ?>"><?= htmlspecialchars($mesin['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row">
                                          <div class="col-6 mb-2">
                                              <label for="loss_burden" class="form-label mb-1">Beban Kerugian</label>
                                              <select id="loss_burden" name="loss_burden" class="form-select form-select-sm select2" required>
                                                  <option value="" selected>-- Pilih --</option>
                                                  <option value="OPERATOR">Operator</option>
                                                  <option value="KANTOR">Kantor</option>
                                              </select>
                                          </div>
                                          <div class="col-6 mb-2">
                                              <label for="date" class="form-label mb-1">Tanggal</label>
                                              <input type="date" id="date" name="date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                                          </div>
                                        </div>

                                        <div class="mb-2">
                                            <label for="info" class="form-label mb-1">Keterangan</label>
                                            <input type="text" id="info" name="info" class="form-control form-control-sm" placeholder="Contoh : Disimpan, Dijual, Dibikin karung" required>
                                        </div>

                                        <div class="row">
                                          <div class="col-6 mb-2">
                                              <label for="kategori" class="form-label mb-1">Kategori Produk</label>
                                              <select id="kategori" name="kategori" class="form-select form-select-sm select2" required>
                                                  <option value="" selected>-- Pilih Kategori --</option>
                                                  <?php foreach ($categories as $c): ?>
                                                  <option data-name="<?= htmlspecialchars($c['name']) ?>" value="<?= htmlspecialchars($c['category_id']) ?>" data-name="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                                                  <?php endforeach; ?>
                                              </select>
                                          </div>

                                          <div class="col-6 mb-2">
                                              <label for="judul" class="form-label mb-1">Judul</label>
                                              <select id="judul" name="judul" class="form-select form-select-sm select2" required>
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
                                        <div class="row">
                                            <div class="col-4 mb-2">
                                                <label for="qty" class="form-label mb-1">Qty Gagal</label>
                                                <input type="number" id="qty" name="qty" class="form-control form-control-sm" min="1" required>
                                            </div>
                                            <div class="col-8 mb-2" id="finishingRow">
                                                <label class="form-label mb-1">Finishing</label>
                                                <div id="finishing" class="d-flex flex-wrap gap-2 mb-1"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 border-end pe-lg-4 ps-lg-4 mb-4 mb-lg-0">
                                        <h5 class="mt-0 mb-3 text-danger border-bottom pb-2">2. Jenis Kegagalan</h5>
                                        
                                        <div class="mb-3">
                                            <div class="p-2 bg-light border rounded" <?= (isset($mode) && $mode === 1) ? 'style="background-color: #444 !important; border-color: #555 !important;"' : '' ?>>
                                                <p class="fw-bold mb-2 text-secondary"><i class="bi bi-palette me-1"></i> Desain / File</p>
                                                <?php 
                                                $desainList = ['Resolusi pecah', 'Salah ukuran', 'Salah penulisan teks', 'Salah data customer', 'Font berubah', 'File corrupt'];
                                                foreach ($desainList as $key => $val): ?>
                                                <div class="form-check mb-1">
                                                    <input class="form-check-input" type="checkbox" name="failure_design[]" value="<?= $val ?>" id="fd_<?= $key ?>">
                                                    <label class="form-check-label" for="fd_<?= $key ?>"><?= $val ?></label>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <div class="p-2 bg-light border rounded" <?= (isset($mode) && $mode === 1) ? 'style="background-color: #444 !important; border-color: #555 !important;"' : '' ?>>
                                                <p class="fw-bold mb-2 text-secondary"><i class="bi bi-printer me-1"></i> Proses Cetak</p>
                                                <?php 
                                                $cetakList = ['Warna tidak sesuai', 'Hasil belang/banding', 'Hasil blur', 'Head strike', 'Tinta bocor', 'Kertas/media macet', 'Hasil miring', 'Hasil terpotong'];
                                                foreach ($cetakList as $key => $val): ?>
                                                <div class="form-check mb-1">
                                                    <input class="form-check-input" type="checkbox" name="failure_print[]" value="<?= $val ?>" id="fc_<?= $key ?>">
                                                    <label class="form-check-label" for="fc_<?= $key ?>"><?= $val ?></label>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <div class="p-2 bg-light border rounded" <?= (isset($mode) && $mode === 1) ? 'style="background-color: #444 !important; border-color: #555 !important;"' : '' ?>>
                                                <p class="fw-bold mb-2 text-secondary"><i class="bi bi-scissors me-1"></i> Finishing</p>
                                                <?php 
                                                $finishFailList = ['Salah potong', 'Salah laminasi', 'Mata ayam tidak rapi', 'Sambungan spanduk kurang rapi', 'Bubble laminasi', 'Lipatan rusak'];
                                                foreach ($finishFailList as $key => $val): ?>
                                                <div class="form-check mb-1">
                                                    <input class="form-check-input" type="checkbox" name="failure_finishing[]" value="<?= $val ?>" id="ff_<?= $key ?>">
                                                    <label class="form-check-label" for="ff_<?= $key ?>"><?= $val ?></label>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 ps-lg-4">
                                        <h5 class="mt-0 mb-3 text-warning border-bottom pb-2">3. Penyebab Kegagalan</h5>
                                        
                                        <div class="p-2 bg-light border rounded mb-4" <?= (isset($mode) && $mode === 1) ? 'style="background-color: #444 !important; border-color: #555 !important;"' : '' ?>>
                                            <?php 
                                            $causeList = ['Human error operator', 'Kesalahan desain', 'Kesalahan customer (revisi mendadak)', 'Kerusakan mesin', 'Media rusak/cacat', 'Setting warna salah', 'Kurang QC', 'Mesin error'];
                                            foreach ($causeList as $key => $val): ?>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" name="failure_cause[]" value="<?= $val ?>" id="cause_<?= $key ?>">
                                                <label class="form-check-label" for="cause_<?= $key ?>"><?= $val ?></label>
                                            </div>
                                            <?php endforeach; ?>
                                            
                                            <hr class="my-2">
                                            
                                            <div class="form-check mb-1">
                                                <input class="form-check-input" type="checkbox" id="cause_lainnya_check">
                                                <label class="form-check-label mb-1" for="cause_lainnya_check">Lainnya:</label>
                                                <input type="text" name="failure_cause_other" id="failure_cause_other" class="form-control form-control-sm" placeholder="Sebutkan penyebab lainnya..." disabled>
                                            </div>
                                        </div>

                                        <div class="alert alert-info py-2 px-3" style="font-size: 13px;">
                                            <i class="bi bi-info-circle me-1"></i> Pastikan semua data kegagalan sudah sesuai sebelum menyimpan log.
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <div class="modal-footer border-top bg-light" <?= (isset($mode) && $mode === 1) ? 'style="background-color: #2a2a2a !important; border-color: #444 !important;"' : '' ?>>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                            <button type="button" class="btn btn-primary px-4 fw-bold" id="btnTambah">Simpan Log Kegagalan</button>
                        </div>

                    </div>
                </div>
            </div>
            <?php include '../footer.php'; ?>
      </div>
        
    </div>

    <div style="position: fixed; bottom: 50px; right: 20px; z-index: 999;">
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#detailLogModal">+ Tambah Log Kegagalan</button>
    </div>
    
  </div>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="<?= BASE_URL ?>/assets/js/jquery-3.7.1.min.js"></script>
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

  function loadFinishingOptions(kategoriId) {
      if (elFinishing) {
          elFinishing.innerHTML = '';
      }

      if (kategoriId) {
          fetch(`../routes/?action=get_finishing&category_id=${encodeURIComponent(kategoriId)}`)
              .then(response => response.json())
              .then(data => {
                  const seenFinishing = new Set();
                  data.data.forEach(finishingItem => {
                      if (!seenFinishing.has(finishingItem.name)) {
                          seenFinishing.add(finishingItem.name);
                          elFinishing.insertAdjacentHTML('beforeend', `
                              <div class="form-check">
                                  <input class="form-check-input finishing-checkbox" type="checkbox" value="${finishingItem.finishing_id}" data-name="${finishingItem.name}">
                                  <label class="form-check-label">${finishingItem.name}</label>
                              </div>
                          `);
                      }
                  });
              });
      }
  }

  function toggleFinishingDisplay(kategoriName) {
      const row = document.getElementById('finishingRow');
      if (row) row.style.display = '';
      
      switch (kategoriName) {
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
  }

  if (elKategori) {
      elKategori.addEventListener('change', function () {
          const kategoriId = this.value;
          const kategoriName = getKategoriName();
          toggleFinishingDisplay(kategoriName);
          loadProdukByKategori(kategoriId, kategoriName);
          updateUkuranView(kategoriName);
          
      });
  }

  if (elJudul) {
      elJudul.addEventListener('change', function () {
          const selectedOption = this.options[this.selectedIndex];
          const name = (selectedOption && selectedOption.dataset && selectedOption.dataset.name) ? selectedOption.dataset.name : '';
          const ukuranList = ukuranMap[name] || [];
          const ukuranDropdown = document.getElementById('ukuranDropdown');
          const ukuranDropdownRow = document.getElementById('ukuranDropdownRow');
          console.log(ukuranList);
          

          if (ukuranList.length > 0) {
              if (ukuranDropdown) {
                  (ukuranDropdown).empty().append('<option value="">-- Pilih Ukuran --</option>');
                  ukuranList.forEach(uk => {
                      (ukuranDropdown).append(`<option value="${uk}">${uk}</option>`);
                  });
                  (ukuranDropdown).trigger('change');
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
          const kategoriName = getKategoriName();
          const selectedOption = elJudul ? elJudul.options[elJudul.selectedIndex] : null;
          const selectedJudul = selectedOption ? selectedOption.dataset.name : '';

          if (typeof submitTambahItem === 'function') {
              submitTambahItem(elJudul ? elJudul.value : '');
          }
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


    function submitTambahItem(productIdToPost = null) {
      const kategoriName = getKategoriName();
      const selectedJudulId = productIdToPost || (elJudul ? elJudul.value : '');
      const selectedOption = elJudul ? elJudul.options[elJudul.selectedIndex] : null;
      let selectedJudulName = selectedOption ? selectedOption.dataset.name : '';
      
      const finishingIds = Array.from(document.querySelectorAll('.finishing-checkbox:checked')).map(cb => cb.value);
      let finishingStr = finishingIds.length > 0 ? finishingIds.join(',') : '-';
      
      const elQtyField = document.getElementById('qty');
      const qty = elQtyField ? parseInt(elQtyField.value) || 1 : 1;
      
      const formElement = document.getElementById('addItemForm');
      const orderItemId = formElement ? formElement.dataset.orderItemId || null : null;

      const nomoratorField = document.getElementById('nomorator');
      const customerNameField = document.getElementById('customer_name');
      const machineIdField = document.getElementById('machine_id');
      const lossBurdenField = document.getElementById('loss_burden');
      const dateField = document.getElementById('date');
      const infoField = document.getElementById('info');
      
      const nomorator = nomoratorField ? nomoratorField.value.trim() : '';
      const customer_name = customerNameField ? customerNameField.value.trim() : '';
      const machine_id = machineIdField ? machineIdField.value : '';
      const loss_burden = lossBurdenField ? lossBurdenField.value.trim() : '';
      const date = dateField ? dateField.value : '';
      const info = infoField ? infoField.value.trim() : '';

      function getCheckedValues(selector) {
          return Array.from(document.querySelectorAll(selector))
              .filter(el => el.checked)
              .map(el => el.value);
      }

      const failure_design = getCheckedValues('input[name="failure_design[]"]');
      const failure_print = getCheckedValues('input[name="failure_print[]"]');
      const failure_finishing = getCheckedValues('input[name="failure_finishing[]"]');
      const failure_cause = getCheckedValues('input[name="failure_cause[]"]');
      
      let failure_cause_other = '';
      const causeLainnyaCheck = document.getElementById('cause_lainnya_check');
      const failureCauseOtherField = document.getElementById('failure_cause_other');
      if (causeLainnyaCheck && causeLainnyaCheck.checked && failureCauseOtherField) {
          failure_cause_other = failureCauseOtherField.value.trim();
      }

      if (!nomorator || !customer_name || !machine_id || !info) {
          Swal.fire({ icon: 'warning', title: 'Data Belum Lengkap', text: 'Nomorator, Customer, dan Mesin wajib diisi!' });
          return;
      }

      let namaProdukLengkap = selectedJudulName.trim();

      if (kategoriName === 'PAKET INDOOR OUTDOOR' || kategoriName === 'KARTU NAMA') {
        let nameToFind = selectedJudulName.trim();

        if (kategoriName === 'KARTU NAMA') {
          const checkedFinishingEl = document.querySelector('.finishing-checkbox:checked');
          const selectedFinishing = checkedFinishingEl ? checkedFinishingEl.dataset.name.trim().toUpperCase() : '';
          if (selectedFinishing && selectedFinishing !== '-') {
            const upperJudul = selectedJudulName.toUpperCase();
            const upperFinishing = selectedFinishing.toUpperCase();
            if (!upperJudul.includes(upperFinishing)) {
              nameToFind += ` ${selectedFinishing}`;
            }
          }
        } 
        
        namaProdukLengkap = nameToFind;
      } else {
        prosesTambahItem(selectedJudulId, namaProdukLengkap);
      }

        function prosesTambahItem(productId, judulFinal) {
          let panjang = parseFloat(elPanjang?.value) || 0;
          let lebar = parseFloat(elLebar?.value) || 0;
          if (selectedJudulName.includes('TRANSFERPAPER') || selectedJudulName.includes('PRINT PRES')) {
              panjang = parseFloat(document.getElementById('panjangSublim')?.value) || 0;
              lebar = parseFloat(document.getElementById('lebarSublim')?.value) || 0;
          }
          const dataPost = {
            user_id: document.getElementById('user_id') ? document.getElementById('user_id').value : '',
            product_id: productId,
            judul: judulFinal,
            quantity: qty,
            finishing: finishingStr,
            panjang: panjang,
            lebar: lebar,
            kiloan: parseFloat(elKiloan?.value) || 0,
            waktu: parseFloat(elWaktu?.value) || 0,
            size : size?.value,
            nomorator: nomorator,
            customer_name: customer_name,
            machine_id: machine_id,
            loss_burden: loss_burden,
            info: info,
            date: date,
            failure_design: failure_design,
            failure_print: failure_print,
            failure_finishing: failure_finishing,
            failure_cause: failure_cause,
            failure_cause_other: failure_cause_other
          };

          if (orderItemId) dataPost.order_item_id = orderItemId;

          fetch('../routes/?action=create_failure', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dataPost)
          })
          .then(response => response.text())
          .then(text => {
            const data = text ? JSON.parse(text) : {};
            if (data.success) {
              window.location.reload();
            } else {
              alert("Gagal tambah/update item: " + data.message);
            }
          })
          .catch(xhr => {
            Swal.fire({
              icon: 'error',
              title: 'Gagal!',
              text: xhr.message || 'Terjadi kesalahan.',
              confirmButtonText: 'Tutup'
           });
          });
        }
    }
      
    });

  </script>

<script>
    document.getElementById('cause_lainnya_check').addEventListener('change', function() {
        const textInput = document.getElementById('failure_cause_other');
        if(this.checked) {
            textInput.removeAttribute('disabled');
            textInput.focus();
        } else {
            textInput.setAttribute('disabled', 'disabled');
            textInput.value = '';
        }
    });

function bukaModalEditInfo(failureId, infoText) {
    document.getElementById('edit_failure_id').value = failureId;
    document.getElementById('edit_info').value = infoText;
    
    const editModal = new bootstrap.Modal(document.getElementById('editInfoModal'));
    editModal.show();
}

function deleteFailure(id){
    fetch('../routes/?action=delete_failure', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `failure_id=${encodeURIComponent(id)}` 
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => { throw new Error(text) });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            Swal.fire('Gagal', data.message, 'error');
        }
    })
    .catch(error => {
        Swal.fire('Error', 'Terjadi kesalahan sistem: ' + error.message, 'error');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const formEditInfo = document.getElementById('formEditInfo');

    if (formEditInfo) {
        formEditInfo.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('../routes/?action=update_failure_info', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => { throw new Error(text) });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Tersimpan!',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Gagal', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Terjadi kesalahan sistem: ' + error.message, 'error');
            });
        });
    }
});
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script>
    document.getElementById('btnExportExcel').addEventListener('click', async () => {
      const workbook = new ExcelJS.Workbook();
      const toko   = "<?= addslashes($storeName ?? 'Nama Toko') ?>";
      const alamat = "<?= addslashes($storeAddress ?? 'Alamat Toko') ?>";
      const startDate = "<?= $start_date ?? '' ?>";
      const endDate   = "<?= $end_date ?? '' ?>";

      const table = document.getElementById("tableGagal");
      if (!table) return;

      const thElements = [...table.querySelectorAll("thead th")];
      const headers = thElements.map(th => th.innerText.trim()).slice(0, -1);

      const allRows = [];
      table.querySelectorAll("tbody tr").forEach(tr => {
        const tdElements = [...tr.querySelectorAll("td")];
        if (tdElements.length > 0) {
          const rowData = tdElements.map(td => td.innerText.trim()).slice(0, -1);
          allRows.push(rowData);
        }
      });

      const IDX_OPERATOR = 4;
      const dataOperator = {};

      allRows.forEach(row => {
        const operatorName = row[IDX_OPERATOR] && row[IDX_OPERATOR] !== '-' ? row[IDX_OPERATOR] : "Tanpa Operator";
        if (!dataOperator[operatorName]) {
          dataOperator[operatorName] = [];
        }
        dataOperator[operatorName].push(row);
      });

      function createSheet(sheetName, dataRows) {
        const sheet = workbook.addWorksheet(sheetName.substring(0, 31));
        let r = 1;

        sheet.mergeCells(`A${r}:N${r}`);
        sheet.getCell(`A${r}`).value = toko;
        sheet.getCell(`A${r}`).font = { bold: true, size: 16 };
        sheet.getCell(`A${r}`).alignment = { horizontal: 'center' };
        r++;

        sheet.mergeCells(`A${r}:N${r}`);
        sheet.getCell(`A${r}`).value = alamat;
        sheet.getCell(`A${r}`).alignment = { horizontal: 'center' };
        r++;

        sheet.mergeCells(`A${r}:N${r}`);
        sheet.getCell(`A${r}`).value = `Periode ${startDate} s.d ${endDate}`;
        sheet.getCell(`A${r}`).alignment = { horizontal: 'center' };
        r += 2;

        const headerRow = sheet.addRow(headers);
        headerRow.font = { bold: true };
        headerRow.eachCell(cell => {
          cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFCCE5FF' } };
          cell.border = { top: { style: 'thin' }, left: { style: 'thin' }, bottom: { style: 'thin' }, right: { style: 'thin' } };
          cell.alignment = { horizontal: 'center', vertical: 'middle' };
        });

        dataRows.forEach((rowData, index) => {
          rowData[0] = index + 1;
          const row = sheet.addRow(rowData);
          row.eachCell(cell => {
        cell.border = { top: { style: 'thin' }, left: { style: 'thin' }, bottom: { style: 'thin' }, right: { style: 'thin' } };
        cell.alignment = { vertical: 'middle', wrapText: true }; 
          });
        });

        sheet.columns = [
          { width: 5 },  // No
          { width: 15 }, // Tanggal
          { width: 15 }, // Nomorator
          { width: 20 }, // Customer
          { width: 20 }, // Operator
          { width: 25 }, // Mesin
          { width: 25 }, // Judul
          { width: 15 }, // Ukuran
          { width: 8 },  // Qty
          { width: 25 }, // Finishing
          { width: 35 }, // Detail Gagal
          { width: 18 }, // Kerugian
          { width: 15 }, // Beban
          { width: 25 }  // Keterangan
        ];
      }

      createSheet("Semua Data", allRows);

      Object.keys(dataOperator).forEach(operator => {
        createSheet(operator, dataOperator[operator]);
      });

      const buffer = await workbook.xlsx.writeBuffer();
      saveAs(
        new Blob([buffer]),
        `Laporan_Gagal_${startDate}_sd_${endDate}.xlsx`
      );
    });
    </script>

</body>
</html>