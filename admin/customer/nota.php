<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

  $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

  if ($role === 'PRODUKSI') {
    header("Location: " . BASE_URL . "/customer.php");
  }
  // Ambil detail order + operator (initial dari users)
  $stmt = $koneksi->prepare(" 
      SELECT o.*, u.initial AS operator_initial 
      FROM orders o
      JOIN users u ON o.user_id = u.user_id
      WHERE o.order_id = ? AND o.store_id = ?
  ");
  $stmt->bind_param("ii", $order_id, $store_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $order = $result->fetch_assoc();
  $stmt->close();

  $jenisList = ['OUTDOOR','INDOOR', 'PAKET INDOOR OUTDOOR','LASER A3','SUBLIM','DTF','STAMP', 'MERCENDISE', 'MERCENDISE AKRILIK', 'JERSEY', 'AKRILIK', 'KARTU NAMA', 'CETAKAN', 'JASA'];

  if (isset($_SESSION['stores'])) {
    $resultStores = $_SESSION['stores'];
  }else {
    $resultStores = [];
    $stores = $koneksi->prepare("SELECT store_id, name FROM stores WHERE NOT store_id = ? ORDER BY name");
    $stores->bind_param('i', $store_id);
    $stores->execute();
    $rsStore = $stores->get_result();
    while ($s = $rsStore->fetch_assoc()){
      $resStore = [
          'store_id' => $s['store_id'],
          'name' => $s['name']
      ];
      $resultStores[] = $resStore;
    }
    $_SESSION['stores'] = $resultStores;
  }
                   
  ?>


  <!DOCTYPE html>
  <html lang="id">
  <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1" />
      <title>Nota Order #<?= htmlspecialchars($order_id) ?></title>
      <?php include BASE_PATH . '/header.php'; ?>
      <script src="<?= BASE_URL ?>/assets/js/jquery-3.7.1.min.js"></script>
       <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/select2.min.css">
      <script src="<?= BASE_URL ?>/assets/js/select2.min.js"></script>
      
      <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/select2-bootstrap4.min.css">
      <script>
          const store_id = <?= (int) $store_id ?>;
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
  <body class="<?= ($mode === 1) ? 'dark-mode' : '' ?>">
  <div id="main-wrapper">
    <?php include BASE_PATH . '/navbar.php'; ?>

    <div id="main-content" <?= (isset($mode) && $mode === 1) ? 'class="dark-mode"' : '' ?>>
      <?php include BASE_PATH . '/sidebar.php'; ?>

      <div id="page-content-wrapper">

        <!-- Konten nota.php -->
        <div class="container-fluid py-4 px-2">
          <div class="row h-100 align-items-stretch">
            <!-- Form di kiri -->
            <div class="col-md-5 h-100" >
            <form action="<?= BASE_URL ?>/customer?start_date=<?= date('Y-m-d', strtotime($order['date'])) ?>&end_date=<?= date('Y-m-d', strtotime($order['date'])) ?>" method="post">
              <input type="hidden" name="system" value="<?= htmlspecialchars($order['system']) ?>">
              <button type="submit" class="btn btn-success mb-3">
                <i class="bi bi-arrow-left"></i> Kembali ke Customer
              </button>
            </form>
              <div class="card shadow-sm p-4 mb-4 h-100"
              <?php if ($username == 'zannia' || $username == 'vikialvian') { ?>
                style="background-color: #f5b6cf !important;"
              <?php }elseif($mode === 1){ ?>
                style="background-color: #333 !important; color: #e0e0e0 !important;"
              <?php } ?>
              >
                <h4 class="mb-3">Tambah Item</h4>
                <form id="addItemForm" class="d-flex flex-column h-100">
                  <input type="hidden" name="order_id" value="<?= htmlspecialchars($order_id) ?>">

                  <div class="row mb-3">
                      <label for="jenis" class="col-sm-2 col-form-label">Jenis</label>
                      <div class="col-sm-10">
                      <select id="jenis" name="jenis" class="form-select select2" required>
                        <option value="" selected>-- Pilih Jenis --</option>
                        <?php foreach ($jenisList as $jenis): ?>
                          <option value="<?= htmlspecialchars($jenis) ?>">
                            <?= htmlspecialchars($jenis) ?>
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
                  <!-- Untuk JERSEY -->
                  <div class="row mb-3" id="ukuranJerseyRow" style="display:none;">
                    <label for="ukuranJersey" class="col-sm-2 col-form-label">Ukuran</label>
                    <div class="col-sm-10">
                      <select id="ukuranJersey" name="ukuran_jersey" class="form-select select2">
                        <option value="">-- Pilih Ukuran --</option>
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

                  <!-- Untuk variasi ukuran lain (PAKET, BANNER, DLL) -->
                  <div class="row mb-3" id="ukuranDropdownRow" style="display:none;">
                    <label for="ukuranDropdown" class="col-sm-2 col-form-label">Ukuran</label>
                    <div class="col-sm-10">
                      <select id="ukuranDropdown" name="ukuran_variasi" class="form-select select2">
                        <option value="">-- Pilih Ukuran --</option>
                      </select>
                    </div>
                  </div>

                  <!-- Untuk PRINT PRESS dan TRANSFERPAPER -->
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

                          <!-- Select finishing default -->
                          <select id="finishing" name="finishing" class="form-select mb-2" style="max-width:200px;">
                              <option value="">-- Pilih Finishing --</option>
                          </select>

                          <!-- Finishing multiple khusus jersey -->
                          <div id="finishingJersey" class="d-flex flex-wrap gap-3" style="display:none;">

                          </div>

                          <!-- Cut / Die -->
                          <div id="cutDieCheckboxes" class="d-flex align-items-center gap-2" style="display:none;">
                              <div class="form-check me-2">
                                  <input class="form-check-input" type="checkbox" id="finishingCut" name="finishing_cut" value="1">
                                  <label class="form-check-label">KISS CUT</label>
                              </div>
                              <div class="form-check">
                                  <input class="form-check-input" type="checkbox" id="finishingDie" name="finishing_die" value="1">
                                  <label class="form-check-label">DIE CUT</label>
                              </div>
                          </div>

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
                <!-- <div class="alert alert-warning" role="alert">
                  Maintenance Jasa ✌️
                </div> -->

              </div>
            </div>
            <!-- Tabel di kanan -->
            <div class="col-md-7">
              <div class="row mb-4">
                <div class="col-md-4">
                  <strong>Nomorator:</strong> <?= htmlspecialchars($order['nomorator']) ?>
                </div>
                <div class="col-md-4">
                  <strong>Nama:</strong> <?= htmlspecialchars($order['customer_name']) ?>
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
                    <!-- Data ditambahkan dinamis -->
                  </tbody>
                </table>
              </div>
              <div class="row mt-4 mb-3">
                <div class="col-md-6">
                  <strong>Deadline:</strong> <?= date('d M Y, H:i', strtotime($order['deadline'])) ?>
                </div>
                <div class="col-md-6">
                  <strong>Operator:</strong> <?= htmlspecialchars($order['operator_initial']) ?>
                </div>
              </div>
              <div id="noteSection">
                <!-- Ini diisi oleh JS pakai AJAX -->
                <div id="noteDisplay" class="mb-3"></div>

                <!-- Form tetap selalu ditampilkan -->
                <form action="add_note.php" method="post" id="addNote">
                  <div class="mb-3">
                    <label for="exampleFormControlTextarea1" class="form-label">Catatan untuk konsumen 📝</label>
                    <textarea class="form-control mb-3" id="exampleFormControlTextarea1" rows="3" name="note"
                    placeholder="!Hanya untuk konsumen, Untuk Operator ada di transaksi detil"
                    ></textarea>
                    <input type="hidden" name="order_id" value="<?= $order_id ?>">
                    <button type="submit" class="btn btn-primary p-2">Update Catatan </button>
                  </div>
                </form>
              </div>


            </div>
          </div>
        </div>
        <!-- Modal -->
        <div class="modal fade" id="modalTambahLainnya" tabindex="-1">
          <div class="modal-dialog">
            <form id="formTambahManual" method="post" action="add_manual_item.php" class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Tambah Item Manual</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="order_id" value="<?= htmlspecialchars($order_id) ?>">
                <div class="mb-3">
                  <label for="judulManual" class="form-label">Judul</label>
                  <input list="daftarJudul" class="form-control" name="judul" id="judulManual" required oninput="this.value = this.value.toUpperCase();">
                  <datalist id="daftarJudul">
                    <option value="SETTING">
                    <option value="MATIK LEBIH">
                    <option value="SPIRAL">
                    <option value="POTTONG">
                  </datalist>
                </div>
                <div class="mb-3">
                  <label for="ukuranManual" class="form-label">Ukuran</label>
                  <input type="text" class="form-control" name="ukuran" id="ukuranManual" oninput="this.value = this.value.toUpperCase();">
                </div>
                <div class="mb-3">
                  <label for="qtyManual" class="form-label">Quantity</label>
                  <input type="number" class="form-control" name="qty" id="qtyManual" min="1">
                </div>
                <div class="mb-3">
                  <label for="unitManual" class="form-label">Harga</label>
                  <input type="text" class="form-control" id="unitManualFormatted" placeholder="Rp 0">
                  <input type="hidden" name="unit" id="unitManual" required>
                </div>
              </div>
              <div class="modal-footer">
                <button type="submit" class="btn btn-success">Simpan Item</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <?php include BASE_PATH . '/footer.php'; ?>
  </div>

  <!-- Bootstrap JS Bundle -->

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  
  $(document).ready(function () {
    const isDark = $('body').hasClass('dark-mode') || $('#main-content').hasClass('dark-mode');

    // Inisialisasi Select2 tanpa search box
    $('.select2').select2({
      theme: 'bootstrap4',
      width: '100%',
      dropdownAutoWidth: true,
      minimumResultsForSearch: Infinity // disable pencarian
    });

    // Tambahkan dark mode setelah render
    if (isDark) {
      setTimeout(() => {
        $('.select2-container').addClass('dark-mode-select')
          .find('*').addClass('dark-mode-select');
      }, 100);
    }

    // Saat dropdown Select2 dibuka
    $('.select2').on('select2:open', function () {
      if (isDark) {
        $('.select2-container--open, .select2-dropdown')
          .addClass('dark-mode-select')
          .find('*').addClass('dark-mode-select');
      }


    });

    // Navigasi keyboard panah dan Enter
    $(document).on('keydown', function (e) {
      const isOpen = $('.select2-container--open').length > 0;
      if (!isOpen) return;

      const allOptions = $('.select2-results__option[role=option]');
      const highlighted = $('.select2-results__option--highlighted');
      let index = allOptions.index(highlighted);

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (index < allOptions.length - 1) {
          highlighted.removeClass('select2-results__option--highlighted');
          const next = allOptions.eq(index + 1).addClass('select2-results__option--highlighted');

          // Auto-scroll ke item yang disorot
          const highlightedEl = next[0];
          if (highlightedEl) {
            highlightedEl.scrollIntoView({
              block: 'nearest',
              behavior: 'smooth'
            });
          }
        }
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (index > 0) {
          highlighted.removeClass('select2-results__option--highlighted');
          const next = allOptions.eq(index - 1).addClass('select2-results__option--highlighted');
          // Auto-scroll ke item yang disorot
          const highlightedEl = next[0];
          if (highlightedEl) {
            highlightedEl.scrollIntoView({
              block: 'nearest',
              behavior: 'smooth'
            });
          }
        }
      } else if (e.key === 'Enter') {
        e.preventDefault();
        const value = highlighted.attr('id')?.replace('select2-', '').replace(/-result-.+?-/, '');
        const selectedId = highlighted.data('select2-id') || highlighted.attr('id');
        const text = highlighted.text();
        const optionValue = highlighted.attr('id')?.split('-').pop();

        if (optionValue) {
          const $select = $('.select2-container--open').prev('select');
          $select.val(optionValue).trigger('change');
          $select.select2('close');
        }
      }
      if (isDark) {
        $('.select2-results__option[aria-selected="true"]').addClass('dark-mode-select');
      }else{
        $('.select2-results__option[aria-selected="true"]').addClass('default-mode-select');
      }
      updatePricePreview()
    });

    // Saat halaman load → buka dropdown #jenis
    setTimeout(() => {
      const jenis = $('#jenis');
      if (jenis.data('select2')) {
        jenis.select2('open');
      }
    }, 300);

    // Setelah jenis ditutup → buka judul
    $('#jenis').on('select2:close', () => {
      setTimeout(() => {
        if ($('#judul').data('select2')) {
          // Fokus ke #judul dulu 
          $('#judul').select2('focus');
          $('#judul').select2('open');
        }
      }, 100);
    });


    // Setelah judul ditutup → fokus ke panjang / qty
    $('#judul').on('select2:close', () => {
      setTimeout(() => {
        
        const panjang = document.getElementById('panjang');
        const qty = document.getElementById('qty');
        if (panjang && panjang.offsetParent !== null) {
          panjang.focus();
        } else if (qty) {
          qty.focus();
        }
      }, 100);
    });


    
    // Enter dari panjang → lebar
    $('#panjang').on('keydown', (e) => {
      if (e.key === 'Enter' || e.key === '*' || e.key === 'PageDown') {
        e.preventDefault();
        $('#lebar').focus();
      } else if (e.key === 'PageUp') {
        e.preventDefault();
        $('#judul').select2('focus');
        $('#judul').select2('open');
      }
    });

    // Enter dari lebar → qty
    $('#lebar').on('keydown', (e) => {
      if (e.key === 'Enter'  || e.key === 'PageDown') {
        e.preventDefault();
        $('#qty').focus();
      } else if (e.key === 'PageUp') {
        e.preventDefault();
        $('#panjang').focus();
      }
    });

    // Enter dari qty → klik tombol tambah lalu fokus ke jenis
    $('#qty').on('keydown', function (e) {
      if (e.key === 'Enter' || e.key === '=') {
        e.preventDefault();
        $('#btnTambah').trigger('click');
        setTimeout(() => {
          $('#jenis').focus();
          $('#jenis').select2('open');
        }, 100); // kasih delay agar submit/tambah selesai dulu
      } else if (e.key === 'PageDown') {
        e.preventDefault();
        $('#finishing').focus();
      } else if (e.key === 'PageUp') {
        e.preventDefault();
        $('#lebar').focus();
      }
    });
    // Enter dari lebar → qty
    $('#finishing').on('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        $('#btnTambah').trigger('click');
        setTimeout(() => {
          $('#jenis').focus();
          $('#jenis').select2('open');
        }, 100); // kasih delay agar submit/tambah selesai dulu
      } else if (e.key === 'PageUp') {
        e.preventDefault();
        $('#qty').focus();
      }
    });

  });
</script>

<script>
  let ukuranMap = {};

  $(document).ready(function () {
    $('#enableDiskon').on('change', function () {
      $('#diskonInput').toggle(this.checked).val(this.checked ? $('#diskonInput').val() : '');
    });

    $('#toggleSidebar').on('click', function () {
      $('#sidebar-wrapper').toggleClass('collapsed');
    });
    function maklunCabang(order_item_id, store_id){
      
      
    }
    function loadOrderItems() {
      $.getJSON(`get_order_items.php?order_id=<?= (int)$order_id ?>`, function (data) {
        const items = data.items;
        const total = data.total;
        const diskonPerProduk = data.diskon_per_produk || {};
        
        const tbody = $('#orderItemsTable tbody'); 
        tbody.empty();

        // Kelompokkan item berdasarkan judul produk
        const itemsByJudul = {};
        items.forEach(item => {
          if (!itemsByJudul[item.judul]) itemsByJudul[item.judul] = [];
          itemsByJudul[item.judul].push(item);
        });

        // Render semua item per produk
        for (const judul in itemsByJudul) {
          const grup = itemsByJudul[judul];
          grup.forEach(item => {
            tbody.append(`
              <tr class="order-item-row" 
                  data-order-item-id="${item.order_item_id}"
                  data-jenis="${item.type || ''}"
                  data-judul="${item.product_name || item.judul}"
                  data-qty="${item.quantity}"
                  data-unit="${item.unit}"
                  data-size="${item.size || ''}"
                  data-finishing="${item.finishing || ''}"
                  data-finishing-utama="${item.finishing_utama || ''}"
                  data-finishing-kissdie="${item.finishingkissdie || ''}">
                <td>${item.judul}</td>
                <td>${item.finishing || '-'}</td>
                <td>${item.size || '-'}</td>
                <td>${item.quantity}</td>
                <td>${item.unit}</td>
                <td>${item.amount}</td>
                <td class="d-flex gap-2">
                  <button class="btn btn-sm btn-danger btn-delete">Hapus</button>
                  <select
                    class="form-select maklunCabang"
                    style="width: 120px;"
                    data-order-item-id="${item.order_item_id}"
                    data-maklun="${item.maklun ?? ''}">
                    <option value="0">Maklun</option>
                    <?php foreach ($resultStores as $rs) { ?>
                      <option value="<?= $rs['store_id'] ?>"><?= $rs['name'] ?></option>
                    <?php } ?>
                  </select>
                </td>
              </tr> 
            `);

          });

          document.querySelectorAll('.maklunCabang').forEach(select => {
            const maklunValue = select.dataset.maklun;
            if (maklunValue) {
              select.value = maklunValue;
            }
          });
          let maklunCabang = document.querySelectorAll(".maklunCabang") || '';
          if (maklunCabang != '') {
            maklunCabang.forEach(maklun => {
              let orderItemId = maklun.dataset.orderItemId;
              maklun.addEventListener('change', (e) =>{
                let maklunStoreId = e.target.value;

                fetch('maklun.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                  body: `order_item_id=${encodeURIComponent(orderItemId)}&store_id_maklun=${maklunStoreId}`
                }).then(res => res.json()).then(data => {
                  if (data.success) {
                    loadOrderItems();
                  } else {
                    alert('Gagal ngemaklun: ' + data.message);
                  }
                }).catch(err => {
                  alert('Terjadi kesalahan: ' + err);
                });
                
                
              })
            })
          }

          // Handle double click per row
          $('.order-item-row').off('dblclick').on('dblclick', function () {
            const row = $(this);

            const jenis = row.data('jenis');
            const judul = row.data('judul');
            const qty = row.data('qty');
            const unit = row.data('unit');
            const size = row.data('size');
            const finishing = row.data('finishing');
            const finishingUtama = row.data('finishing-utama');
            const finishingKissdie = row.data('finishing-kissdie');

            // Isi form
            $('#jenis').val(jenis).trigger('change');
            setTimeout(() => {
              const option = $(`#judul option`).filter(function () {
                return $(this).text().trim().toLowerCase() === judul.trim().toLowerCase();
              }).first();

              if (option.length) {
                $('#judul').val(option.val());
              }
              if (finishing) {
                const finishingNames = finishing.toString().split(',').map(s => s.trim().toLowerCase());
                const finishingValues = [];

                $('#finishing option').each(function () {
                  const text = $(this).text().trim().toLowerCase();
                  if (finishingNames.includes(text)) {
                    finishingValues.push($(this).val());
                  }
                });

                if (finishingValues.length) {
                  $('#finishing').val(finishingValues).trigger('change');
                } else {
                  $('#finishing').val(null).trigger('change');
                }
              } else {
                $('#finishing').val(null).trigger('change');
              }
              // Cek Finishing Kissdie
              if (finishingKissdie.toUpperCase() === 'KISS CUT') {
                $('#finishingCut').prop('checked', true);
                $('#finishingDie').prop('checked', false);
              } else if (finishingKissdie.toUpperCase() === 'DIE CUT') {
                $('#finishingDie').prop('checked', true);
                $('#finishingCut').prop('checked', false);
              } else {
                $('#finishingCut').prop('checked', false);
                $('#finishingDie').prop('checked', false);
              }

            }, 300);

            $('#qty').val(qty);

            if (size && size.includes('x')) {
              const [panjang, lebar] = size.split('x').map(s => parseFloat(s.trim()));
              $('#panjang').val(panjang);
              $('#lebar').val(lebar);
              $('#ukuranInputs').show();
            }

            $('html, body').animate({
              scrollTop: $("#addItemForm").offset().top - 100
            }, 300);
          });
        }

        // Diskon
        for (const judul in diskonPerProduk) {
          const nilaiDiskon = diskonPerProduk[judul];
          tbody.append(`
            <tr id="orderDiskonRow_${judul}">
              <td colspan="5" class="text-end"><strong>Diskon ${judul}</strong></td>
              <td colspan="2"><strong>Rp ${nilaiDiskon.toLocaleString('id-ID')}/m?pcs</strong></td>
            </tr>
          `);
        }

        // Total
        tbody.append(`
          <tr id="orderTotalRow">
            <td colspan="5" class="text-end"><strong>Total:</strong></td>
            <td colspan="2"><strong>Rp ${total.toLocaleString('id-ID')}</strong></td>
          </tr>
        `);
      });
    }

  
    function loadProdukByJenis(jenis, typeFinishing = '') {

      $.getJSON(`get_products.php?store_id=${store_id}&type=${encodeURIComponent(jenis)}`, function (data) {
        const judul = $('#judul').empty().append('<option value="">-- Pilih Judul --</option>');
        ukuranMap = {};
        const seen = new Set();

        data.forEach(product => {
          let nameOnly = product.name;

          if (jenis === 'PAKET INDOOR OUTDOOR') {
            nameOnly = nameOnly.replace(/\s*\d+(\.\d+)?\s*[x×X]\s*\d+(\.\d+)?/gi, '').trim();
          } else if (jenis === 'KARTU NAMA') {
            nameOnly = nameOnly.replace(/\s+(GLOSSY|DOFF)\s*$/i, '').trim();
          }

          const ukuranMatch = product.name.match(/(\d+(\.\d+)?\s*[x×X]\s*\d+(\.\d+)?)/i);
          const ukuran = ukuranMatch ? ukuranMatch[0].replace(/×/gi, 'x') : null;

          if (!ukuranMap[nameOnly]) ukuranMap[nameOnly] = [];
          if (ukuran && !ukuranMap[nameOnly].includes(ukuran)) {
            ukuranMap[nameOnly].push(ukuran);
          }

          if (!seen.has(nameOnly)) {
            seen.add(nameOnly);
            judul.append(
              `<option value="${product.product_id}"
                      data-name="${nameOnly}"
                      data-unit="${product.unit_type}"
                      data-price="${product.price}">
                ${nameOnly}
              </option>`
            );
          }
        });
      });

      const finishingSelect = $('#finishing');
      const finishingJersey = $('#finishingJersey');

      finishingSelect.empty().append('<option value="">-- Pilih Finishing --</option>').show();
      finishingJersey.empty().hide();
      $('#ukuranJersey').val('').trigger('change');
      $('#kiloan').val('');
       $('#waktu').val('');

      if (jenis === 'KARTU NAMA') {
        finishingSelect.append('<option value="DOFF">DOFF</option>');
        finishingSelect.append('<option value="GLOSSY">GLOSSY</option>');
        return;
      }

      if (typeFinishing) {
        $.getJSON(`get_products.php?store_id=${store_id}&type=${typeFinishing}`, function (data) {

          const seenFinishing = new Set();

          if (jenis === 'JERSEY') {
            finishingSelect.hide();
            finishingJersey.show();

            data.forEach(product => {
              if (!seenFinishing.has(product.name)) {
                seenFinishing.add(product.name);

                finishingJersey.append(`
                  <div class="form-check">
                    <input class="form-check-input finishing-jersey"
                          type="checkbox"
                          name="finishing_jersey[]"
                          value="${product.product_id}"
                          data-price="${product.price}">
                    <label class="form-check-label">
                      ${product.name}
                    </label>
                  </div>
                `);
              }
            });

          } else {
            data.forEach(product => {
              if (!seenFinishing.has(product.name)) {
                seenFinishing.add(product.name);
                finishingSelect.append(
                  `<option value="${product.product_id}"
                          data-name="${product.name}"
                          data-price="${product.price}">
                    ${product.name}
                  </option>`
                );
              }
            });
          }
        });

      } else {
        finishingSelect.append('<option value="-">-</option>');
      }
    }



    function toggleFinishingDisplay(jenis) {
      const cutDie = $('#cutDieCheckboxes');
      const cut = $('#finishingCut');
      const die = $('#finishingDie');
      const row = $('#finishingRow');

      // RESET AWAL
      row.show();
      cutDie.hide();
      cut.parent().hide();
      die.parent().hide();
      cut.prop('checked', false);
      die.prop('checked', false);

      switch (jenis) {

        case 'LASER A3':
          cutDie.show();
          cut.parent().show();
          die.parent().show();
          break;

        case 'INDOOR':
          cutDie.show();
          cut.parent().show();   // hanya KISS CUT
          break;

        case 'OUTDOOR':
        case 'SUBLIM':
        case 'AKRILIK':
          // finishing ada, tapi tanpa cut/die
          break;

        case 'JERSEY':
          // ❌ PASTI TIDAK ADA KISS / DIE
          cutDie.hide();
          cut.prop('checked', false);
          die.prop('checked', false);
          break;

        case 'PAKET INDOOR OUTDOOR':
          row.hide();
          return;

        case 'STAMP':
        case 'MERCENDISE':
        case 'MERCENDISE AKRILIK':
          row.hide();
          $('#finishing').empty();
          return;
      }

      // Placeholder ukuran
      if (jenis === 'AKRILIK') {
        $('#panjang').attr('placeholder', 'Panjang (cm)');
        $('#lebar').attr('placeholder', 'Lebar (cm)');
      } else {
        $('#panjang').attr('placeholder', 'Panjang (m)');
        $('#lebar').attr('placeholder', 'Lebar (m)');
      }

      // Aturan khusus DTF
      if (jenis === 'DTF') {
        $('#lebar').val('0.58').prop('readonly', true);
        $('#panjang').val('').prop('readonly', false);
      } else {
        $('#lebar').val('').prop('readonly', false);
        $('#panjang').val('').prop('readonly', false);
      }
    }



    $('#jenis').on('change', function () {
      const jenis = $(this).val();
      toggleFinishingDisplay(jenis);

      let finishingType = '';
      if (['OUTDOOR', 'INDOOR', 'SUBLIM', 'JERSEY', 'AKRILIK', 'LASER A3'].includes(jenis)) {
        finishingType = `FINISHING ${jenis}`;
      }
      loadProdukByJenis(jenis, finishingType);
    });

      function updateUkuranView(name) {
        const jenis = $('#jenis').val();
        const unit = $('#judul option:selected').data('unit');
        const judul = $('#judul option:selected').data('name');

        $('#ukuranSublimRow, #ukuranInputs, #ukuranJersey, #bahanSublim, #settingDesain').closest('.row').hide();
        if (name.includes('TRANSFERPAPER') || name.includes('PRINT PRES')) {
          $('#ukuranSublimRow').closest('.row').show();
        } else if (jenis === 'JERSEY') {
          $('#ukuranJersey').closest('.row').show(); 
        } else if (unit === 'M2' || unit === 'CM2') {
          $('#ukuranInputs').closest('.row').show();
        } else if (jenis === 'JASA' && (judul == 'SETTING' || judul == 'POTONG AKRILIK')) {
          $('#settingDesain').closest('.row').show(); 
        }

        
        
        if(judul.includes('BAHAN') && unit == 'PCS'){
          $('#bahanSublim').show();
          $('#ukuranInputs, #finishingRow').hide();
        }
        
      }

      $('#judul').on('change', function () {
        const name = $('#judul option:selected').data('name');
        const ukuranList = ukuranMap[name] || [];

        if (ukuranList.length > 0) {
          $('#ukuranDropdown').empty().append('<option value="">-- Pilih Ukuran --</option>');
          ukuranList.forEach(uk => {
            $('#ukuranDropdown').append(`<option value="${uk}">${uk}</option>`);
          });
          $('#ukuranDropdownRow').show();
        } else {
          $('#ukuranDropdownRow').hide();
        }

        updateUkuranView(name);
      });

    $('#btnTambah').on('click', function () {
      const jenis = $('#jenis').val();
      const selectedJudul = $('#judul option:selected').data('name') || '';
      const selectedFinishing = $('#finishing').val().trim().toUpperCase();

      if (jenis === 'KARTU NAMA') {
        const fullName = (selectedJudul + ' ' + selectedFinishing).trim().toUpperCase();
        $.getJSON(`get_products.php?store_id=${store_id}&type=KARTU NAMA`, function (dataProduk) {
          const produkCocok = dataProduk.find(p => p.name.trim().toUpperCase() === fullName);
          if (!produkCocok) {
            Swal.fire({ icon: 'error', title: 'Produk tidak ditemukan', text: `Tidak ada produk dengan nama "${fullName}"`, confirmButtonText: 'Tutup' });
            return;
          }
          submitTambahItem(produkCocok.product_id);
        });
        return;
      }

      const productId = $('#judul').val();
      submitTambahItem(productId);


    });

    function submitTambahItem() {
      const jenis = $('#jenis').val();
      const selectedJudulId = $('#judul').val(); // default product_id
      let selectedJudulName = $('#judul option:selected').data('name') || '';
      let finishing = $('#finishing').val() || '-';
      let finishingJersey = [];
      let panjang = parseFloat($('#panjang').val()) || 0;
      let lebar = parseFloat($('#lebar').val()) || 0;
      let kiloan = parseFloat($('#kiloan').val()) || 0; 
      let waktu = parseFloat($('#waktu').val()) || 0;
      const qty = parseInt($('#qty').val()) || 1;
      const unitType = $('#judul option:selected').data('unit') || '-';
      const finishingCut = $('#finishingCut').is(':checked') ? 1 : 0;
      const finishingDie = $('#finishingDie').is(':checked') ? 1 : 0;
      const orderItemId = $('#addItemForm').attr('data-order-item-id') || null;

      let ukuranStr = '-';
      if (jenis === 'LASER A3') {
        ukuranStr = 'A3+';
      } else if (selectedJudulName === 'TRANSFERPAPER' || selectedJudulName.includes('PRINT PRES')) {
        panjang = parseFloat($('#panjangSublim').val()) || 0;
        lebar = parseFloat($('#lebarSublim').val()) || 0;
        
      } else if (jenis === 'JERSEY') {
        ukuranStr = $('#ukuranJersey').val();
        $('.finishing-jersey:checked').each(function () {
          finishingJersey.push($(this).val());
        });
      } else if ($('#ukuranDropdownRow').is(':visible')) {
        ukuranStr = $('#ukuranDropdown').val();
      }

      const selectedFinishing = finishing !== '-' ? finishing : '';
      let finalJudul = selectedJudulName;
      if (jenis === 'KARTU NAMA' && selectedFinishing) {
        finalJudul = `${selectedJudulName} ${selectedFinishing}`.trim();
      }

      // Nama produk lengkap = judul + ukuran (jika ada)
      let namaProdukLengkap = finalJudul.trim();
      if (ukuranStr && ukuranStr !== '-') {
        namaProdukLengkap += ` ${ukuranStr.trim()}`;
      }
      const unni = $('#judul option:selected').data('name');
      if(unni.includes('BAHAN') && unitType == 'PCS'){
        selectedJudulName = selectedJudulName + '/KG';
      }


      if (jenis === 'PAKET INDOOR OUTDOOR' || jenis === 'KARTU NAMA') {
        // Siapkan nama produk lengkap sesuai jenis
        let namaProdukLengkap = selectedJudulName.trim();

        if (jenis === 'KARTU NAMA' && selectedFinishing && selectedFinishing !== '-') {
          const upperJudul = selectedJudulName.toUpperCase();
          const upperFinishing = selectedFinishing.toUpperCase();

          // Tambahkan finishing kalau belum ada
          if (!upperJudul.includes(upperFinishing)) {
            namaProdukLengkap += ` ${selectedFinishing}`;
          }
        } else if (jenis === 'PAKET INDOOR OUTDOOR' && ukuranStr && ukuranStr !== '-') {
          namaProdukLengkap += ` ${ukuranStr.trim()}`;
        }

        const typeEncoded = encodeURIComponent(jenis);
        $.getJSON(`get_products.php?store_id=${store_id}&type=${typeEncoded}`, function (data) {
          const produk = data.find(p => p.name.trim().toUpperCase() === namaProdukLengkap.toUpperCase());

          if (!produk) {
            Swal.fire({
              icon: 'error',
              title: 'Produk tidak ditemukan',
              text: `Tidak ada produk dengan nama "${namaProdukLengkap}"`,
            });
            return;
          }

          prosesTambahItem(produk.product_id, namaProdukLengkap, ukuranStr);
        });
      } else {
        prosesTambahItem(selectedJudulId, selectedJudulName, ukuranStr);
      }


        function prosesTambahItem(productId, judulFinal, ukuranFinal) {
          const dataPost = {
            order_id: <?= (int)$order_id ?>,
            product_id: productId,
            judul: judulFinal,
            size: ukuranFinal,
            quantity: qty,
            finishing: finishing,
            finishing_jersey: finishingJersey,
            panjang: panjang,
            lebar: lebar,
            kiloan: kiloan,
            waktu: waktu,
            unit_type: unitType,
            finishing_cut: finishingCut,
            finishing_die: finishingDie
          };

          if ($('#enableDiskon').is(':checked')) {
            dataPost.diskon = parseFloat($('#diskonInput').val()) || 0;
          }
          if (orderItemId) dataPost.order_item_id = orderItemId;
          let loading = ``;
          loading += `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...`;

          function btnTambahLoaded() {
            document.getElementById('btnTambah').innerHTML = loading;
            document.getElementById('btnTambah').disabled = true;
          }
          function btnTambahBeres() {
            document.getElementById('btnTambah').innerHTML = `Tambah  Item`;
            document.getElementById('btnTambah').disabled = false;
          }
          btnTambahLoaded();
          $.post("add_order_item.php", dataPost, function (response) {
            const data = typeof response === "string" ? JSON.parse(response) : response;
            if (data.success) {
              loadOrderItems();
              const jenisValue = $('#jenis').val();
              const judulValue = $('#judul').val();
              $('#addItemForm')[0].reset();
              $('#addItemForm').removeAttr('data-order-item-id');
              $('#ukuranDropdownRow').hide();
              $('#enableDiskon').prop('checked', false);
              $('#diskonInput').val('').hide();
              $('#diskonWrapper').hide();
              $('#jenis').val(jenisValue).trigger('change');
              setTimeout(() => {
                $('#judul').val(judulValue).trigger('change');
              }, 300);
            } else {
              alert("Gagal tambah/update item: " + data.message);
            }
            btnTambahBeres();
          }).fail(function (xhr) {
            Swal.fire({
              icon: 'error',
              title: 'Gagal!',
              text: xhr.responseText || 'Terjadi kesalahan.',
              confirmButtonText: 'Tutup'
            });
            btnTambahBeres();
          });
        }
    }



    $('#orderItemsTable').on('click', '.btn-delete', function () {
      const orderItemId = $(this).closest('tr').data('order-item-id');
      if (!orderItemId) return;

      $.post('delete_order_item.php', { order_item_id: orderItemId }, function (response) {
        if (response.success) {
          loadOrderItems();
        } else {
          alert('Gagal menghapus item: ' + response.message);
        }
      }, 'json').fail(xhr => alert("Gagal koneksi ke server: " + xhr.responseText));
    });

    $('#orderItemsTable').on('click', '.btn-edit', function () {
      const row = $(this).closest('tr');
      const orderItemId = row.data('order-item-id');
      const [judul, finishing, ukuran, qty] = [
        row.find('td:eq(0)').text(),
        row.find('td:eq(1)').text(),
        row.find('td:eq(2)').text(),
        row.find('td:eq(3)').text()
      ];

      $('#judul option').filter(function () { return $(this).text() === judul; }).prop('selected', true).change();
      $('#finishing option').filter(function () { return $(this).text() === finishing; }).prop('selected', true);

      if (ukuran.includes('x')) {
        const [pjg, lbr] = ukuran.replace('cm', '').split('x').map(v => parseFloat(v.trim()));
        $('#panjang').val(pjg);
        $('#lebar').val(lbr);
      } else {
        $('#panjang, #lebar').val('');
      }

      $('#qty').val(qty);
      $('#addItemForm').attr('data-order-item-id', orderItemId);
    });

    $('#finishingCut').on('change', function () {
      if (this.checked) $('#finishingDie').prop('checked', false);
    });

    $('#finishingDie').on('change', function () {
      if (this.checked) $('#finishingCut').prop('checked', false);
    });

    $('#formTambahManual').on('submit', function (e) {
      e.preventDefault();
      $.post('add_manual_item.php', $(this).serialize(), function (response) {
        if (response.success) {
          $('#modalTambahLainnya').modal('hide');
          loadOrderItems();
          $('#formTambahManual')[0].reset();
        } else {
          alert('Gagal tambah item: ' + response.message);
        }
      }, 'json').fail(xhr => alert('Error koneksi: ' + xhr.statusText));
    });

    loadOrderItems();
  });
  function loadNota() {
    const orderId = <?= (int)$order_id ?>;
    $.get(`get_note.php?order_id=${orderId}`, function(response) {
      $('#noteDisplay').html(response);
    });
  }

  $(document).on('submit', '#addNote', function(e) {
    e.preventDefault();
    const formData = $(this).serialize();

    $.post('add_note.php', formData, function(response) {
      $('#noteDisplay').html('<div class="alert alert-danger" role="alert">' + response + '</div>');
      $('#exampleFormControlTextarea1').val(''); // kosongkan form jika mau
    });
  });

  $(document).ready(function() {
    loadNota();
  });

function updatePricePreview() {
  
  function getFinishingList() {
    let list = [];

    const manual = $('#finishing').val();
    if (manual && manual !== '-') {
      list = manual.split(',').map(f => f.trim()).filter(f => f !== '');
    }

    if ($('#finishingCut').is(':checked')) {
      list.push('kiss_cut');
    } else if ($('#finishingDie').is(':checked')) {
      list.push('die_cut');
    }

    return list;
  }

  // Tangani eksklusivitas checkbox
  $('#finishingCut').on('change', function () {
    if (this.checked) $('#finishingDie').prop('checked', false);
    sendRequest(); // perbarui harga
  });

  $('#finishingDie').on('change', function () {
    if (this.checked) $('#finishingCut').prop('checked', false);
    sendRequest(); // perbarui harga
  });

  // Fungsi untuk kirim dan update harga
  function sendRequest() {
    const finishingFinal = getFinishingList().join(',') || '-';

    let jenisAsli = $('#jenis').val();
    let judulAsli = $('#judul option:selected').data('name') || '';
    let unitJudul = $('#judul option:selected').data('unit') || '';
    let panjangAsli = parseFloat($('#panjang').val()) || 0;
    let lebarAsli = parseFloat($('#lebar').val()) || 0;
    let kiloan = parseFloat($('#kiloan').val()) || 0; 
    let waktu = parseFloat($('#waktu').val()) || 0; 
    let finishingJersey = [];
    let ukuranJersey = $('#ukuranJersey').val() || '';

    if ((judulAsli.includes('TRANSFERPAPER') || judulAsli.includes('PRINT PRES') && jenisAsli == 'SUBLIM')) {
      panjangAsli = parseFloat($('#lebarSublim').val()) ;
      lebarAsli = parseFloat($('#panjangSublim').val()) ;
    }

    if (jenisAsli == 'JERSEY') {
      $('.finishing-jersey:checked').each(function () {
        finishingJersey.push($(this).val());
      });
      
    } 
   
    

    const dataPost = {
      order_id: <?= (int)$order_id ?>,
      product_id: $('#judul').val(),
      judul: judulAsli,
      size: $('#ukuranDropdown').is(':visible') ? $('#ukuranDropdown').val() : '-',
      quantity: parseInt($('#qty').val()) || 1,
      finishing: finishingFinal,
      finishingJersey: finishingJersey,
      panjang: panjangAsli,
      lebar: lebarAsli,
      kiloan: kiloan,
      waktu: waktu,
      ukuranJersey: ukuranJersey,
      unit_type: $('#judul option:selected').data('unit') || '-',
      diskon: $('#enableDiskon').is(':checked') ? (parseFloat($('#diskonInput').val()) || 0) : 0
    };

    $.post('get_price.php', dataPost, function(response) {
      if (response.success) {
        $('#priceDisplay').text('Total Harga: Rp ' + response.total_price.toLocaleString('id-ID'));
        if (response.product_price !== undefined) {
          $('#productPriceDisplay').text('Harga Produk: Rp ' + response.product_price.toLocaleString('id-ID'));
        }
        if (response.finishing_price !== undefined) {
          $('#finishingPriceDisplay').text('Harga Finishing: Rp ' + response.finishing_price.toLocaleString('id-ID'));
        }
        
      } else {
        $('#priceDisplay').text('');
      }
    }, 'json').fail((e) => {
      console.log(e.responseJSON);
      
      $('#priceDisplay').text('Gagal koneksi server');
    });
  }

  sendRequest();
}
  

$('#jenis, #judul, #ukuranDropdown, #finishing, #panjang, #lebar, #kiloan, #waktu, #ukuranJersey, #qty, #enableDiskon, #lebarSublim, #panjangSublim, #diskonInput, #finishingCut, #finishingDie').on('change keyup', updatePricePreview);
$(document).on('change', '.finishing-jersey', function () {
  updatePricePreview();
});


$(document).ready(() => {
  updatePricePreview();
});



</script>


<script>
  const formattedInput = document.getElementById('unitManualFormatted');
  const hiddenInput = document.getElementById('unitManual');

  formattedInput.addEventListener('input', function () {
    let raw = this.value.replace(/[^0-9]/g, '');

    let formatted = raw ? 'Rp ' + new Intl.NumberFormat('id-ID').format(raw) : '';

    this.value = formatted;
    hiddenInput.value = raw;
  });

  formattedInput.addEventListener('focus', function () {
    let raw = this.value.replace(/[^0-9]/g, '');
    this.value = raw;
  });

  formattedInput.addEventListener('blur', function () {
    let raw = this.value.replace(/[^0-9]/g, '');
    this.value = raw ? 'Rp ' + new Intl.NumberFormat('id-ID').format(raw) : '';
  });

</script>


  </body>
  </html>
