<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/components/Modal.php';
require_once BASE_PATH . '/components/Alert.php';
require_once BASE_PATH . '/components/Table.php';
require_once BASE_PATH . '/components/Loading.php';
require_once BASE_PATH . '/components/Icon.php';

$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$stmtUser = $koneksi->prepare("SELECT user_id, name FROM users WHERE store_id = ?");
$stmtUser->bind_param("i", $store_id);
$stmtUser->execute();
$userResult = $stmtUser->get_result();
$users = [];
while ($u = $userResult->fetch_assoc()) {
  $users[$u['user_id']] = $u['name'];
}
$stmtUser->close();

$sql = "SELECT f.*, m.name AS nama_mesin, m.type AS machine_type 
        FROM failure f 
        LEFT JOIN machine m ON f.machine_id = m.machine_id 
        WHERE f.store_id = ? AND f.date BETWEEN ? AND ?
        ORDER BY f.date DESC";

$stmt2 = $koneksi->prepare($sql);
$stmt2->bind_param("iss", $store_id, $start_date, $end_date);
$stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

$stmt2 = $koneksi->prepare("SELECT * FROM machine WHERE store_id = ?");
$stmt2->bind_param("i", $store_id);
$stmt2->execute();
$machines = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
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
            'data'           => $items ?? [],
            'empty_message'  => 'Tidak ada data kegagalan.',
            'table_class'    => 'table table-bordered table-striped',
            'thead_class'    => 'table-primary',
            'row_attributes' => function($row) {
                return 'class="order-row" data-calc-id="' . $row['failure_id'] . '"';
            },
            'columns'        => [
                ['header' => 'No', 'type' => 'number'],
                ['header' => 'Tanggal', 'render' => fn($p) => date('d M Y', strtotime($p['date']))],
                ['header' => 'Nomorator', 'field' => 'nomorator'],
                ['header' => 'Customer', 'field' => 'customer_name'],
                ['header' => 'Operator', 'render' => fn($p) => htmlspecialchars($users[$p['user_id']] ?? '-')],
                ['header' => 'Mesin', 'render' => fn($p) => htmlspecialchars($p['nama_mesin'] ?? '-') . '<br><small class="text-muted">' . htmlspecialchars($p['machine_type'] ?? '') . '</small>'],
                ['header' => 'Judul Produk', 'field' => 'judul'],
                ['header' => 'Ukuran', 'field' => 'size'],
                ['header' => 'Qty', 'field' => 'quantity'],
                [
                    'header' => 'Finishing',
                    'render' => function($p) use ($koneksi) {
                        if (empty($p['finishing']) || $p['finishing'] === '-') return '-';
                        
                        $ids = array_filter(array_map('intval', explode(',', $p['finishing'])));
                        if (empty($ids)) return '-';
                        
                        $placeholders = implode(',', array_fill(0, count($ids), '?'));
                        $stmt = $koneksi->prepare("SELECT name FROM products WHERE product_id IN ($placeholders)");
                        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $names = [];
                        while ($r = $res->fetch_assoc()) $names[] = $r['name'];
                        $stmt->close();
                        return implode(', ', $names);
                    }
                ],
                [
                    'header' => 'Detail Gagal',
                    'render' => function($p) {
                        $details = [];
                        if (!empty($p['failure_design'])) $details[] = "Desain: " . htmlspecialchars($p['failure_design']);
                        if (!empty($p['failure_print'])) $details[] = "Cetak: " . htmlspecialchars($p['failure_print']);
                        if (!empty($p['failure_finishing'])) $details[] = "Finishing: " . htmlspecialchars($p['failure_finishing']);
                        if (!empty($p['failure_cause'])) $details[] = "Penyebab: " . htmlspecialchars($p['failure_cause']);
                        if (!empty($p['failure_cause_other'])) $details[] = "Lainnya: " . htmlspecialchars($p['failure_cause_other']);
                        return empty($details) ? '-' : implode('<br>', $details);
                    }
                ],
                ['header' => 'Kerugian', 'render' => fn($p) => '<span class="text-danger fw-bold">Rp ' . number_format(getSatuanHarga($p['product_id'], $p['size'], $p['finishing'], $koneksi) * (int)$p['quantity'], 0, ',', '.') . '</span>'],
                ['header' => 'Beban', 'render' => fn($p) => '<span class="text-danger fw-bold">' . htmlspecialchars($p['loss_burden']) . '</span>'],
                ['header' => 'Keterangan', 'render' => fn($p) => '<span class="text-danger fw-bold">' . htmlspecialchars($p['info']) . '</span>'],
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
                                // Fitur baru: param_fields berupa array, otomatis menjadi: 
                                // bukaModalEditInfo('1', 'Teks Info');
                                'param_fields' => ['failure_id', 'info'] 
                            ]
                        ],
                        [
                            // Fitur baru: Tipe Link (<a> tag) yang memiliki fungsi confirm()
                            'type'        => 'link',
                            'icon'        => get_icon('delete', ['class' => 'me-1']),
                            'text'        => 'Hapus',
                            'color'       => 'danger',
                            'class'       => 'mb-1',
                            'href'        => 'delete_failure.php?id=',
                            'param_field' => 'failure_id',
                            'confirm'     => 'Yakin ingin menghapus log ini?'
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
                                              <label for="jenis" class="form-label mb-1">Jenis Produk</label>
                                              <select id="jenis" name="jenis" class="form-select form-select-sm select2" required>
                                                  <option value="" selected>-- Pilih Jenis --</option>
                                                  <?php foreach ($jenisList as $jenis): ?>
                                                  <option value="<?= htmlspecialchars($jenis) ?>"><?= htmlspecialchars($jenis) ?></option>
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
                                        <div class="row mb-2" id="ukuranInputs" style="display:none;">
                                            <div class="col-6">
                                                <label class="form-label mb-1">Panjang (m)</label>
                                                <input type="number" step="0.01" min="0" id="panjang" class="form-control form-control-sm" placeholder="P">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label mb-1">Lebar (m)</label>
                                                <input type="number" step="0.01" min="0" id="lebar" class="form-control form-control-sm" placeholder="L">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-2" id="ukuranJerseyRow" style="display:none;">
                                            <label for="ukuranJersey" class="form-label mb-1">Ukuran Jersey</label>
                                            <select id="ukuranJersey" name="ukuran_jersey" class="form-select form-select-sm select2">
                                                <option value="">-- Pilih --</option>
                                                <option value="S">S</option><option value="M">M</option><option value="L">L</option><option value="XL">XL</option><option value="XXL">XXL</option>
                                            </select>
                                        </div> 
                                        
                                        <div class="mb-2" id="bahanSublim" style="display:none;">
                                            <label class="form-label mb-1">Kiloan</label>
                                            <input type="number" step="0.01" min="0" id="kiloan" class="form-control form-control-sm" placeholder="Berat (kg)">
                                        </div>
                                        
                                        <div class="mb-2" id="ukuranDropdownRow" style="display:none;">
                                            <label for="ukuranDropdown" class="form-label mb-1">Ukuran</label>
                                            <select id="ukuranDropdown" name="ukuran_variasi" class="form-select form-select-sm select2">
                                                <option value="">-- Pilih --</option>
                                            </select>
                                        </div>
                                        
                                        <div class="row mb-2" id="ukuranSublimRow" style="display:none;">
                                            <div class="col-6">
                                                <label class="form-label mb-1">Panjang</label>
                                                <input type="number" step="0.01" min="0" id="panjangSublim" name="panjang_sublim" class="form-control form-control-sm" placeholder="(m)">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label mb-1">Lebar</label>
                                                <select id="lebarSublim" name="lebar_sublim" class="form-select form-select-sm select2">
                                                    <option value="">-- Lebar --</option>
                                                    <option value="1.1">1.1</option><option value="1.2">1.2</option><option value="1.5">1.5</option><option value="1.6">1.6</option><option value="1.8">1.8</option>
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
                                                <select id="finishing" name="finishing" class="form-select form-select-sm mb-1">
                                                    <option value="">-- Finishing --</option>
                                                </select>
                                                <div id="finishingJersey" class="d-flex flex-wrap gap-2" style="display:none;"></div>
                                                <div id="cutDieCheckboxes" class="d-flex align-items-center gap-2 mt-1" style="display:none;">
                                                    <div class="form-check form-check-sm me-1">
                                                        <input class="form-check-input" type="checkbox" id="finishingCut" name="finishing_cut" value="1">
                                                        <label class="form-check-label" style="font-size: 12px;">KISS CUT</label>
                                                    </div>
                                                    <div class="form-check form-check-sm">
                                                        <input class="form-check-input" type="checkbox" id="finishingDie" name="finishing_die" value="1">
                                                        <label class="form-check-label" style="font-size: 12px;">DIE CUT</label>
                                                    </div>
                                                </div>
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
      </div>
        <?php include '../footer.php'; ?>
    </div>

    <div style="position: fixed; bottom: 50px; right: 20px; z-index: 999;">
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#detailLogModal">+ Tambah Log Kegagalan</button>
    </div>
    
  </div>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
      document.querySelectorAll('#month, #year').forEach(function(el) {
          el.addEventListener('change', function() {
              document.getElementById('filterForm').submit();
          });
      });

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
          cut.parent().show();
          break;

        case 'OUTDOOR':
        case 'SUBLIM':
        case 'AKRILIK':
          break;

        case 'JERSEY':
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

        // Hapus .closest('.row') dan panggil ID pembungkusnya langsung
        $('#ukuranSublimRow, #ukuranInputs, #ukuranJerseyRow, #bahanSublim').hide();

        if (name.includes('TRANSFERPAPER') || name.includes('PRINT PRES')) {
          $('#ukuranSublimRow').show();
        } else if (jenis === 'JERSEY') {
          $('#ukuranJerseyRow').show(); 
        } else if (unit === 'M2' || unit === 'CM2') {
          $('#ukuranInputs').show();
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
      // 1. TANGKAP VARIABEL LAMA
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

      // 2. TANGKAP VARIABEL BARU (Data Pekerjaan & Kegagalan)date
      const nomorator = $('#nomorator').val().trim();
      const customer_name = $('#customer_name').val().trim();
      const machine_id = $('#machine_id').val();
      const loss_burden = $('#loss_burden').val().trim();
      const date = $('#date').val();
      const info = $('#info').val().trim();

      // Menangkap array dari checkbox yang dicentang
      const failure_design = $('input[name="failure_design[]"]:checked').map(function(){ return $(this).val(); }).get();
      const failure_print = $('input[name="failure_print[]"]:checked').map(function(){ return $(this).val(); }).get();
      const failure_finishing = $('input[name="failure_finishing[]"]:checked').map(function(){ return $(this).val(); }).get();
      const failure_cause = $('input[name="failure_cause[]"]:checked').map(function(){ return $(this).val(); }).get();
      
      // Menangkap nilai input "Lainnya" jika dicentang
      let failure_cause_other = '';
      if ($('#cause_lainnya_check').is(':checked')) {
          failure_cause_other = $('#failure_cause_other').val().trim();
      }

      // Validasi sederhana memastikan form pekerjaan sudah diisi
      if (!nomorator || !customer_name || !machine_id || !info) {
          Swal.fire({ icon: 'warning', title: 'Data Belum Lengkap', text: 'Nomorator, Customer, dan Mesin wajib diisi!' });
          return;
      }

      // 3. LOGIKA PENENTUAN UKURAN (Lama)
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

      // 4. PENCARIAN PRODUK KHUSUS (Lama)
      if (jenis === 'PAKET INDOOR OUTDOOR' || jenis === 'KARTU NAMA') {
        let namaProdukLengkap = selectedJudulName.trim();

        if (jenis === 'KARTU NAMA' && selectedFinishing && selectedFinishing !== '-') {
          const upperJudul = selectedJudulName.toUpperCase();
          const upperFinishing = selectedFinishing.toUpperCase();

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

        // 5. FUNGSI EKSEKUSI AJAX PENGIRIMAN DATA
        function prosesTambahItem(productId, judulFinal, ukuranFinal) {
          const dataPost = {
            // Data Lama
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
            finishing_die: finishingDie,

            // TAMBAHAN: Data Baru Dimasukkan ke payload AJAX
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


document.addEventListener('DOMContentLoaded', function() {
    const formEditInfo = document.getElementById('formEditInfo');

    if (formEditInfo) {
        formEditInfo.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('edit_failure_info.php', {
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