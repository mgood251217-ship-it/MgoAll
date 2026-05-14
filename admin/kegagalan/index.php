<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

// Ambil input rentang bulan & tahun dari GET
$month = $_GET['month'] ?? date('m');
$year  = $_GET['year'] ?? date('Y');

// Bangun tanggal awal dan akhir dalam format YYYY-MM-DD
$date = "$year-$month";

$startDatef = $date . "-01 00:00:00";
$endDatef = $date . "-31 23:59:59";


// Ambil operator
$stmtUser = $koneksi->prepare("SELECT user_id, name FROM users WHERE store_id = ?");
$stmtUser->bind_param("i", $store_id);
$stmtUser->execute();
$userResult = $stmtUser->get_result();
$users = [];
while ($u = $userResult->fetch_assoc()) {
  $users[$u['user_id']] = $u['name'];
}
$stmtUser->close();

// Ambil item kalkulator
$stmt2 = $koneksi->prepare("SELECT failure_id, user_id, store_id, product_id, judul, finishing, size, quantity, date
                            FROM failure WHERE store_id = ? AND date BETWEEN ? AND ? ");
$stmt2->bind_param("iss", $store_id, $startDatef, $endDatef);
$stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

function getFinishingFailedPrice(string $finishing_ids, $size, mysqli $koneksi): int {
    if ($finishing_ids === '-' || empty($finishing_ids)) {
        return 0;
    }

    $total = 0;
    $ids = explode(',', $finishing_ids);

    foreach ($ids as $fid) {
        $fid = trim($fid);
        if (ctype_digit($fid)) {
          $ketProduct = $koneksi->prepare('SELECT failed_price, unit_type FROM products WHERE product_id = ?');
          $ketProduct->bind_param('i', $fid);
          $ketProduct->execute();
          $keterangan = $ketProduct->get_result()->fetch_assoc();
          $harga = (int)$keterangan['failed_price'];
          $unit_type = $keterangan["unit_type"];

            $total += $harga;
          
        }
    }

    return $total;
}

function getSatuanHarga($product_id, $size, $finishing, $koneksi){

  $ketProduct = $koneksi->prepare('SELECT type, name, unit_type, failed_price FROM products WHERE product_id = ?');
  $ketProduct->bind_param('i', $product_id);
  $ketProduct->execute();
  $keterangan = $ketProduct->get_result()->fetch_assoc();

  $unit_type = $keterangan['unit_type'];
  $type = $keterangan['type'];
  $reasonable_price = $keterangan['failed_price'];
  $product_name = $keterangan['name'];
  $finishing_price = getFinishingFailedPrice($finishing, $size, $koneksi);
  $hargaSatuan = 0;
  if ($product_id != 0) {
    if ($unit_type == 'M2') {
      if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $size, $match)) {
        $p = floatval($match[1]);
        $l = floatval($match[2]);
        if ($keterangan['type'] == 'DTF') {
          $hargaSatuan = $p * ((float)$reasonable_price += $finishing_price);
        }else {
          $hargaSatuan = $p * $l * ((float)$reasonable_price += $finishing_price);
        }
      }
    }elseif ($unit_type == 'PCS') {
      $hargaSatuan = (float)$reasonable_price += (float)$finishing_price;
      if($type == 'JERSEY'){
        $harga_jersey = 0;
        if ($size === '5XL') {
            $harga_jersey += 40000;
        } elseif ($size === '4XL') {
            $harga_jersey += 30000;
        } elseif ($size === '3XL') {
            $harga_jersey += 20000;
        } elseif ($size === '2XL') {
            $harga_jersey += 10000;
        }
        $hargaSatuan += $harga_jersey;
      }elseif ($type == 'SUBLIM' && str_contains($product_name, 'BAHAN')) {
        $kata = explode(" ", $size);
        $hargaSatuan *= (float)$kata[0];
      }
    }
  }
  return $hargaSatuan;
}

$jenisList = ['OUTDOOR','INDOOR', 'PAKET INDOOR OUTDOOR','LASER A3','SUBLIM','DTF','STAMP', 'MERCENDISE', 'MERCENDISE AKRILIK', 'JERSEY', 'AKRILIK', 'KARTU NAMA', 'CETAKAN'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Log Kegagalan</title>
  <?php include BASE_PATH . '/header.php'; ?>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
      <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
      <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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
    #detailLogModal .modal-content { height: 60vh; }
    #detailLogBody { overflow-y: auto; max-height: calc(80vh - 100px); }
  </style>
</head>
<body class="<?= ($mode === 1) ? 'dark-mode' : '' ?>">
  <div id="main-wrapper">
    <?php include '../navbar.php'; ?>
    <div id="main-content" <?= (isset($mode) && $mode === 1) ? 'class="dark-mode"' : '' ?>>
      <?php include '../sidebar.php'; ?>

      <div id="page-content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h1 class="mb-0">Log Kegagalan</h1>
            <?php
            $currentYear = date('Y');
            $currentMonth = date('m');

            // Default nilai jika GET tidak tersedia
            $month = $_GET['month'] ?? $currentMonth;
            $year  = $_GET['year'] ?? $currentYear;
            ?>
            <form method="get" class="row g-2 align-items-end justify-content-end" id="filterForm" style="margin-bottom:0;">
                <div class="col-auto">
                    <label for="month" class="form-label mb-0">Dari Bulan</label>
                    <select name="month" id="month" class="form-select">
                        <?php 
                        for ($i = 1; $i <= 12; $i++):
                            $val = str_pad($i, 2, '0', STR_PAD_LEFT);
                            $selected = ($month == $val) ? 'selected' : '';
                        ?>
                            <option value="<?= $val ?>" <?= $selected ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label for="year" class="form-label mb-0">Tahun</label>
                    <select name="year" id="year" class="form-select">
                        <?php 
                        for ($i = $currentYear; $i >= 2023; $i--):
                            $selected = ($year == $i) ? 'selected' : '';
                        ?>
                            <option value="<?= $i ?>" <?= $selected ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php if (empty($items)): ?>
          <div class="alert alert-warning">Belum ada Log</div>
        <?php else: ?>
          <div class="table-responsive mb-5">
            <table class="table table-bordered table-striped">
              <thead class="table-primary">
                <tr>
                  <th>No</th>
                  <th>Nama</th>
                  <th>Judul</th>
                  <th>Finishing</th>
                  <th>Ukuran</th>
                  <th>Qty</th>
                  <th>Tanggal</th>
                  <th>Jumlah</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $no => $p): ?>
                  <tr class="order-row" data-calc-id="<?= $p['failure_id'] ?>">
                    <td><?= $no + 1 ?></td>
                    <td><?= htmlspecialchars($users[$p['user_id']] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['judul']) ?></td>
                    <?php
                        $finishing_names = '-';
                        $finishing_utama_names = '-';
                        $finishing_kissdie_names = '-';
                        $finishing_ids = [];

                        if (!empty($p['finishing']) && $p['finishing'] !== '-') {
                            $finishing_ids = array_filter(array_map('intval', explode(',', $p['finishing'])));
                            if (!empty($finishing_ids)) {
                                $placeholders = implode(',', array_fill(0, count($finishing_ids), '?'));
                                $types = str_repeat('i', count($finishing_ids));
                                $sqlF = "SELECT product_id, name FROM products WHERE product_id IN ($placeholders)";
                                $stmtF = $koneksi->prepare($sqlF);

                                if ($stmtF) {
                                    $stmtF->bind_param($types, ...$finishing_ids);
                                    $stmtF->execute();
                                    $resF = $stmtF->get_result();
                                    $all_names = [];
                                    $utama_names = [];
                                    $kissdie_names = [];

                                    while ($rF = $resF->fetch_assoc()) {
                                        $name = $rF['name'];
                                        $all_names[] = $name;

                                        if (stripos($name, 'KISS CUT') !== false || stripos($name, 'DIE CUT') !== false) {
                                            $kissdie_names[] = $name;
                                        } else {
                                            $utama_names[] = $name;
                                        }
                                    }
                                    $stmtF->close();

                                    $finishing_names = implode(', ', $all_names);
                                    $finishing_utama_names = implode(', ', $utama_names) ?: '-';
                                    $finishing_kissdie_names = implode(', ', $kissdie_names) ?: '-';
                                }
                            }
                        }
                    ?>
                    <td><?= $finishing_names ?></td>
                    <td><?= htmlspecialchars($p['size']) ?></td>
                    <td><?= htmlspecialchars($p['quantity']) ?></td>
                    <td><?= htmlspecialchars($p['date']) ?></td>
                    <td>
                      <?php
                        $size = $p['size'];

                        $total_satuan = getSatuanHarga($p['product_id'], $size, $p['finishing'], $koneksi);
                        echo $total_satuan;
                      ?>
                    </td>
                    <td>
                      <a href="delete_failure.php?id=<?= $p['failure_id'] ?>" class="btn btn-danger btn-sm"
                        onclick="return confirm('Yakin ingin menghapus log ini?')">Hapus</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
            <!-- Modal Detail Log -->
            <div class="modal fade" id="detailLogModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" >
                    <div class="modal-content shadow-sm p-4 mb-4" <?= ($mode === 1) ? 'style="background-color: #333 !important; color: #e0e0e0 !important;"' : '' ?>>
                        <h4 class="mb-3">Tambah Item</h4>
                        <form id="addItemForm" class="d-flex flex-column">
                        <input type="hidden" name="order_id" value="">

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
                                <option value="XXL">XXL</option>
                            </select>
                            </div>
                        </div> 
                        <div class="row mb-3" id="bahanSublim" style="display:none;">
                            <label class="col-sm-2 col-form-label">Kiloan</label>
                            <div class="col-sm-10">
                                <input type="number" step="0.01" min="0" id="kiloan" class="form-control" placeholder="Berat (kg)">
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
                        <div class="row mb-3">
                          <label class="col-sm-2 col-form-label">Operator</label>
                            <div class="col-sm-10">
                              <select name="user_id" id="user_id" class="form-select" required>
                                <option value="">-- Pilih Operator --</option>
                                <?php foreach ($users as $id => $initial): ?>
                                  <option value="<?= $id ?>"><?= htmlspecialchars($initial) ?></option>
                                <?php endforeach; ?>
                              </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                            <button type="button" class="btn btn-secondary w-100" data-bs-toggle="modal" data-bs-target="#modalTambahLainnya">
                                Tambah Lainnya
                            </button>
                            </div>
                            <div class="col-6">
                            <button type="button" class="btn btn-primary w-100" id="btnTambah">
                                Tambah Item
                            </button>
                            </div>
                        </div>
                        </form>
                    </div>
                </div>
            </div>

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
                    <label for="unitManualFormatted" class="form-label">Harga</label>
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
        <?php include '../footer.php'; ?>
    </div>

    <div style="position: fixed; bottom: 50px; right: 20px; z-index: 999;">
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#detailLogModal">+ Tambah Log Kegagalan</button>
    </div>
    
  </div>
    <!-- Bootstrap JS Bundle -->

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
      // Submit form otomatis saat select bulan atau tahun berubah
      document.querySelectorAll('#month, #year').forEach(function(el) {
          el.addEventListener('change', function() {
              document.getElementById('filterForm').submit();
          });
      });
  </script>
  <script>


    $(document).ready(function () {

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

      // 🔥 gunakan delegated listener agar berfungsi walau elemen muncul di modal
      $(document).on('change', '#jenis', function() {
      const jenis = $(this).val();
      toggleFinishingDisplay(jenis);

      let finishingType = '';
      if (['OUTDOOR', 'INDOOR', 'KAIN', 'JERSEY', 'AKRILIK', 'LASER A3'].includes(jenis)) {
        finishingType = `FINISHING ${jenis}`;
      }
      loadProdukByJenis(jenis, finishingType);
      });

      function updateUkuranView(name) {
        const jenis = $('#jenis').val();
        const unit = $('#judul option:selected').data('unit');
        const judul = $('#judul option:selected').data('name');

        $('#ukuranSublimRow, #ukuranInputs, #ukuranJersey, #bahanSublim').closest('.row').hide();
        if (name.includes('TRANSFERPAPER') || name.includes('PRINT PRES')) {
          $('#ukuranSublimRow').closest('.row').show();
        } else if (jenis === 'JERSEY') {
          $('#ukuranJersey').closest('.row').show(); 
        } else if (unit === 'M2' || unit === 'CM2') {
          $('#ukuranInputs').closest('.row').show();
        }

        if(judul.includes('BAHAN') && unit == 'PCS'){
          $('#bahanSublim').show();
          $('#ukuranInputs, #finishingRow').hide();
        }
      }

      $(document).on('change', '#judul', function() {
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

      $(document).on('click', '#btnTambah', function() {
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
            user_id: $('#user_id').val(),
            product_id: productId,
            judul: judulFinal,
            size: ukuranFinal,
            quantity: qty,
            finishing: finishing,
            finishing_jersey: finishingJersey,
            panjang: panjang,
            lebar: lebar,
            kiloan: kiloan,
            unit_type: unitType,
            finishing_cut: finishingCut,
            finishing_die: finishingDie
          };

          if ($('#enableDiskon').is(':checked')) {
            dataPost.diskon = parseFloat($('#diskonInput').val()) || 0;
          }
          if (orderItemId) dataPost.order_item_id = orderItemId;

          $.post("add_failure_items.php", dataPost, function (response) {
            const data = typeof response === "string" ? JSON.parse(response) : response;
            if (data.success) {
              location.reload();
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
          }).fail(function (xhr) {
            Swal.fire({
              icon: 'error',
              title: 'Gagal!',
              text: xhr.responseText || 'Terjadi kesalahan.',
              confirmButtonText: 'Tutup'
            });
          });
        }
    }
      
    });

  </script>
</body>
</html>