<?php

require_once '../connect.php';
require_once '../global_functions.php';
require_once BASE_PATH . '/session.php';
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');


// Ambil data finance untuk tanggal terakhir di rentang
$stmt = $koneksi->prepare("
    SELECT omset_offline, omset_online, saldo, transfer, expenditure, date
    FROM finance
    WHERE store_id = ? AND date BETWEEN ? AND ?
    ORDER BY date DESC
    LIMIT 1
");
$stmt->bind_param("iss", $store_id, $start_date, $end_date);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($omset_offline, $omset_online, $saldo, $transfer, $expenditure, $date);
    $stmt->fetch();
} else {
    $omset_offline = $omset_online = $saldo = $transfer = $expenditure = 0;
    $date = date('Y-m-d');
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_finance'])) {
    // Ambil tanggal dari form hidden
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    // Fungsi bantu untuk generate array tanggal
    function getDatesFromRange($start, $end) {
        $dates = [];
        $current = strtotime($start);
        $end = strtotime($end);
        while ($current <= $end) {
            $dates[] = date('Y-m-d', $current);
            $current = strtotime('+1 day', $current);
        }
        return $dates;
    }

    $dates = getDatesFromRange($start_date, $end_date);
    foreach ($dates as $date) {
        refreshFinance($store_id, $date);
    }

    // Redirect agar tidak submit ulang form saat reload
    header("Location: keuangan?start_date=$start_date&end_date=$end_date");
    exit;
}
$storeNamese = preg_replace('/[^a-zA-Z0-9_-]/', '_', $storeName ?? 'Toko');
$ambilTahune = date('Y', strtotime($start_date));
$ambilBulane = date('m', strtotime($start_date));
$ambilTanggale = date('d', strtotime($start_date));
$folderDatee = $ambilTahune . '/' . $ambilBulane . '/' . $ambilTanggale;
$uploadDire = BASE_PATH . "/assets/img/bukti/$storeNamese/$folderDatee/";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah_pengeluaran'])) {
        $info    = strtoupper(trim($_POST['information']));
        $nominal = trim($_POST['nominal']);
        $tanggal = $start_date;

        $errors = [];
        $pictureName = '';
        $maxFileSize = 80 * 1024; // 50KB

        // ============= Upload & Kompres Gambar (resize, bukan kualitas) =============
        if (!empty($_FILES['picture']['name']) && $_FILES['picture']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($ext, $allowed)) {
                $errors[] = "Format file tidak valid.";
            } else {
                $imageInfo = @getimagesize($_FILES['picture']['tmp_name']);
                if ($imageInfo === false) {
                    $errors[] = "File bukan gambar valid.";
                } else {
                    list($width, $height) = $imageInfo;

                    switch ($ext) {
                        case 'jpg':
                        case 'jpeg':
                            $src = imagecreatefromjpeg($_FILES['picture']['tmp_name']);
                            break;
                        case 'png':
                            $src = imagecreatefrompng($_FILES['picture']['tmp_name']);
                            break;
                        case 'gif':
                            $src = imagecreatefromgif($_FILES['picture']['tmp_name']);
                            break;
                        default:
                            $src = false;
                    }

                    if ($src) {


                        if (!is_dir($uploadDire)) mkdir($uploadDire, 0777, true);

                        $pictureName = uniqid('exp_', true) . '.' . $ext;
                        $destination = $uploadDire . $pictureName;

                        $scale = 1.0;
                        $success = false;

                        // 🔄 Resize bertahap hingga < 50KB
                        do {
                            $newWidth  = (int)($width * $scale);
                            $newHeight = (int)($height * $scale);
                            $dst = imagecreatetruecolor($newWidth, $newHeight);

                            // Transparansi untuk PNG & GIF
                            if ($ext === 'png' || $ext === 'gif') {
                                imagealphablending($dst, false);
                                imagesavealpha($dst, true);
                                $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                                imagefill($dst, 0, 0, $transparent);
                            }

                            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

                            ob_start();
                            if ($ext === 'jpg' || $ext === 'jpeg') {
                                imagejpeg($dst, null, 85);
                            } elseif ($ext === 'png') {
                                imagepng($dst, null, 8);
                            } elseif ($ext === 'gif') {
                                imagegif($dst);
                            }
                            $imgData = ob_get_clean();

                            if (strlen($imgData) <= $maxFileSize) {
                                file_put_contents($destination, $imgData);
                                $success = true;
                                imagedestroy($dst);
                                break;
                            }

                            imagedestroy($dst);
                            $scale -= 0.1;
                        } while ($scale > 0.1);

                        imagedestroy($src);

                        if (!$success) {
                            $errors[] = "Gagal mengompres gambar ke ukuran di bawah 50KB.";
                            $pictureName = '';
                        } else {
                            // Simpan path relatif untuk DB
                            
                        }
                    }
                }
            }
        }

        // ✅ Jika tidak ada error, baru insert
        if (empty($errors)) {
            if ($info && is_numeric($nominal) && $tanggal) {
                $stmt = $koneksi->prepare("INSERT INTO expenditures (store_id, information, nominal, img, date) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("isiss", $store_id, $info, $nominal, $pictureName, $tanggal);
                $stmt->execute();
                $stmt->close();

                refreshFinance($store_id, $tanggal);

                header("Location: keuangan?start_date=$start_date&end_date=$end_date&success=1");
                exit;
            }
        } else {
            // ❌ Ada error → tampilkan pesan di modal
            $_SESSION['upload_error'] = implode('<br>', $errors);
            header("Location: keuangan?start_date=$start_date&end_date=$end_date&error=1");
            exit;
        }

    } elseif (isset($_POST['tambah_pemasukan'])) {
        // bagian pemasukan tetap sama
        $info    = strtoupper(trim($_POST['information_income']));
        $nominal = trim($_POST['nominal_income']);
        $tanggal = $start_date;

        if ($info && is_numeric($nominal) && $tanggal) {
            $stmt = $koneksi->prepare("INSERT INTO income (store_id, information, nominal, date) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isis", $store_id, $info, $nominal, $tanggal);
            $stmt->execute();
            $stmt->close();

            refreshFinance($store_id, $tanggal);
        }

        header("Location: keuangan?start_date=$start_date&end_date=$end_date&success=1");
        exit;
    }
}








// Ambil data pengeluaran di rentang tanggal
$dataPengeluaran = $koneksi->prepare("
    SELECT expenditure_id, information, nominal, img, date
    FROM expenditures
    WHERE store_id = ? AND date BETWEEN ? AND ?
    ORDER BY date ASC
");
$dataPengeluaran->bind_param("iss", $store_id, $start_date, $end_date);
$dataPengeluaran->execute();
$dataPengeluaran = $dataPengeluaran->get_result();

// Ambil data pemasukan di rentang tanggal
$dataPemasukan = $koneksi->prepare("
    SELECT income_id, information, nominal, date
    FROM income
    WHERE store_id = ? AND date BETWEEN ? AND ?
    ORDER BY date ASC
");
$dataPemasukan->bind_param("iss", $store_id, $start_date, $end_date);
$dataPemasukan->execute();
$dataPemasukan = $dataPemasukan->get_result();

?>



<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Keuangan</title>
  <?php include BASE_PATH . '/header.php'; ?>
  <?php include BASE_PATH . '/export_libraries.php'; ?>
  
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/content.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dark_mode.css">
<style>
.img-thumb:hover {
  opacity: 0.5;
  transition: opacity 0.2s ease;
}
</style>

</head>

<body>
<div id="main-wrapper" <?= ($mode === 1) ? 'class="dark-mode"' : '' ?>>
  <?php include BASE_PATH . '/navbar.php'; ?>

  <div id="main-content" <?= (isset($mode) && $mode === 1) ? 'class="dark-mode"' : '' ?>>
    <?php include BASE_PATH . '/sidebar.php'; ?>

    <div id="page-content-wrapper">

      <?php require 'summary_cards.php'; ?>

      <div class="d-flex flex-column flex-md-row justify-content-between align-items-start mb-3">
        <!-- Judul di kiri atas -->
        <h1 class="mb-3 mb-md-0">Keuangan</h1>

        <!-- Form dan tombol export di kanan -->
        <div class="d-flex flex-wrap justify-content-end align-items-end gap-2">
          <form method="get" class="d-flex flex-wrap align-items-end gap-2" id="formTanggal">
            <div>
              <label class="form-label mb-1">Dari Tanggal</label>
              <input 
                type="date" 
                name="start_date" 
                value="<?= $start_date ?>" 
                class="form-control form-control-sm"
                onchange="document.getElementById('formTanggal').submit();"
              >
            </div>
            <div>
              <label class="form-label mb-1">Sampai Tanggal</label>
              <input 
                type="date" 
                name="end_date" 
                value="<?= $end_date ?>" 
                class="form-control form-control-sm"
                onchange="document.getElementById('formTanggal').submit();"
              >

            </div>
          </form>

          <div>
            <label class="form-label mb-1 d-block invisible">Export</label>
            <button id="btnExportExcel" class="btn btn-success btn-sm me-1">Export Excel</button>
            <button id="btnExportWord" class="btn btn-primary btn-sm">Export Word</button>
          </div>
        </div>
      </div>



      <!-- Card Data Keuangan -->
      <div class="card mb-4" <?= ($mode === 1) ? 'style="background-color: #333 !important; color: #e0e0e0 !important;"' : '' ?>>
        <div class="card-header bg-primary text-white">
          Keuangan Terkini (<?= date('d-m-Y', strtotime($start_date)) ?> s.d <?= date('d-m-Y', strtotime($end_date)) ?>)
        </div>

        <div class="card-body">

          <div class="table-responsive">
            <table class="table table-bordered table-striped" id="tableKeuangan">
              <thead class="table-info text-center">
                <tr>
                  <th>No</th>
                  <th>Omset Offline</th>
                  <th>Omset Online</th>
                  <th>Total Omset</th>
                  <th>Transfer Masuk</th>
                  <th>Cash Masuk</th>
                  <th>Pengeluaran</th>
                  <th>Saldo Kas</th>
                  <th>Periode</th>
                </tr>
              </thead>
              <tbody class="text-center">
                <?php
                $no = 1;
                $stmtFinance = $koneksi->prepare("
                  SELECT omset_offline, omset_online, transfer, expenditure, saldo, date
                  FROM finance
                  WHERE store_id = ? AND date BETWEEN ? AND ?
                  ORDER BY date ASC
                ");
                $stmtFinance->bind_param("iss", $store_id, $start_date, $end_date);
                $stmtFinance->execute();
                $resultFinance = $stmtFinance->get_result();

                if ($resultFinance->num_rows > 0) {
                    while ($row = $resultFinance->fetch_assoc()) {
                      ?>
                      <tr>
                        <td><?= $no++ ?></td>
                        <td>Rp <?= ($row['omset_offline'] < 0 ? '-' : '') . number_format(abs($row['omset_offline']), 0, ',', '.') ?></td>
                        <td>Rp <?= ($row['omset_online'] < 0 ? '-' : '') . number_format(abs($row['omset_online']), 0, ',', '.') ?></td>
                        <td>Rp <?= (($row['omset_offline'] + $row['omset_online']) < 0 ? '-' : '') . number_format(abs($row['omset_offline'] + $row['omset_online']), 0, ',', '.') ?></td>
                        <td>Rp <?= ($row['transfer'] < 0 ? '-' : '') . number_format(abs($row['transfer']), 0, ',', '.') ?></td>
                        <td>Rp <?= (($row['omset_offline'] + $row['omset_online'] - $row['transfer']) < 0 ? '-' : '') . number_format(abs($row['omset_offline'] + $row['omset_online'] - $row['transfer']), 0, ',', '.') ?></td>
                        <td>Rp <?= ($row['expenditure'] < 0 ? '-' : '') . number_format(abs($row['expenditure']), 0, ',', '.') ?></td>
                        <td>Rp <?= ($row['saldo'] < 0 ? '-' : '') . number_format(abs($row['saldo']), 0, ',', '.') ?></td>
                        <td><?= $row['date'] ?></td>
                      </tr>
                      <?php
                    }
                } else {
                    echo '<tr><td colspan="7" class="text-center">Tidak ada data keuangan untuk periode ini.</td></tr>';
                }
                ?>
              </tbody>
            </table>
          </div>

          <div class="mt-3 text-end">
            <form method="post" class="d-inline" id="rfrs">
              <input type="hidden" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
              <input type="hidden" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
              <button type="submit" name="refresh_finance" class="btn btn-success px-4">Refresh</button>
            </form>
          </div>

        </div>

        <!-- Modal Error Upload -->
        <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="
              background: rgba(255, 50, 50, 0.15);
              backdrop-filter: blur(10px);
              border: 1px solid rgba(255, 80, 80, 0.4);
              color: #fff;
              text-align: center;
              border-radius: 15px;
              box-shadow: 0 0 25px rgba(255, 0, 0, 0.3);
            ">
              <div class="modal-header border-0 justify-content-center">
                <h5 class="modal-title" id="errorModalLabel" style="font-weight:600; color:#ff4d4d;">
                  ❌ Gagal Upload
                </h5>
              </div>
              <div class="modal-body" style="font-size:15px; color:#fff;">
                <!-- pesan error dari PHP akan diisi otomatis -->
              </div>
              <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">
                  Tutup
                </button>
              </div>
            </div>
          </div>
        </div>
        <?php if (isset($_SESSION['upload_error'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', () => {
          const modalBody = document.querySelector('#errorModal .modal-body');
          modalBody.innerHTML = `<?= $_SESSION['upload_error']; ?>`;
          const modal = new bootstrap.Modal(document.getElementById('errorModal'));
          modal.show();
        });
        </script>
        <?php unset($_SESSION['upload_error']); endif; ?>


        <div class="row card-body">
          <!-- Tabel Pengeluaran -->
          <div class="col-md-6">
            <h5 class="mt-4">Data Pengeluaran Bulan Ini</h5>
            <div class="table-responsive">
              <table class="table table-bordered table-striped" id="tablePengeluaran">
                <thead class="table-danger">
                  <tr>
                    <th>No</th>
                    <th>Keterangan</th>
                    <th>Nominal</th>
                    <th>Foto</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($dataPengeluaran->num_rows > 0): 
                    $no = 1;
                    while ($row = $dataPengeluaran->fetch_assoc()):
                    $storeNames = preg_replace('/[^a-zA-Z0-9_-]/', '_', $storeName ?? 'Toko');
                    $ambilTahun = date('Y', strtotime($row['date']));
                    $ambilBulan = date('m', strtotime($row['date']));
                    $ambilTanggal = date('d', strtotime($row['date']));
                    $folderDate = $ambilTahun . '/' . $ambilBulan . '/' . $ambilTanggal;
                    $uploadDir = BASE_PATH . "/assets/img/bukti/$storeNames/$folderDate/";
                    ?>
                    <tr>
                      <td><?= $no++ ?></td>
                      <td><?= htmlspecialchars($row['information']) ?></td>
                      <td>Rp <?= number_format($row['nominal'], 0, ',', '.') ?></td>
                      <th>

                        <?php 
                        $imgPath = $uploadDir . $row['img']; // path absolut di server
                        $imgUrl = BASE_URL . "/assets/img/bukti/$storeNames/$folderDate/" . htmlspecialchars($row['img']); // path untuk browser

                        if (empty($row['img']) || !file_exists($imgPath)) {
                          // Jika kolom kosong atau file tidak ada di direktori
                          ?>
                          <img 
                            src="<?= BASE_URL . '/assets/img/noproof.png' ?>" 
                            alt="Tanpa Bukti"
                            style="height:30px; object-fit:cover;"
                          >
                        <?php
                        } else {
                          ?>
                          <img 
                            src="<?= $imgUrl ?>" 
                            alt="Bukti"
                            class="img-thumb"
                            onclick="showImageModal('<?= $imgUrl ?>')"
                            style="width:50px; height:50px; object-fit:cover; border-radius:6px; cursor:pointer; position:relative;"
                          >
                        <?php
                        }
                        ?>
                      </th>
                      <td><?= date('d-m-Y', strtotime($row['date'])) ?></td>
                      <td>
                        <button 
                          style="display: none;"
                          class="btn btn-sm btn-warning"
                          data-bs-toggle="modal"
                          data-bs-target="#editModal"
                          data-id="<?= $row['income_id'] ?? $row['expenditure_id'] ?>"
                          data-type="<?= isset($row['income_id']) ? 'income' : 'expenditures' ?>"
                          data-info="<?= htmlspecialchars($row['information']) ?>"
                          data-nominal="<?= $row['nominal'] ?>"
                        >
                          Edit
                        </button>
                        <button 
                          class="btn btn-sm btn-danger"
                          data-bs-toggle="modal"
                          data-bs-target="#deleteModal"
                          data-id="<?= $row['income_id'] ?? $row['expenditure_id'] ?>"
                          data-type="<?= isset($row['income_id']) ? 'income' : 'expenditures' ?>"
                        >
                          Hapus
                        </button>
                      </td>
                    </tr>
                  <?php endwhile; else: ?>
                    <tr><td colspan="6" class="text-center">Tidak ada data Pengeluaran.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Tabel Pemasukan -->
          <div class="col-md-6">
            <h5 class="mt-4">Data Pemasukan Bulan Ini</h5>
            <div class="table-responsive">
              <table class="table table-bordered table-striped" id="tablePemasukan">
                <thead class="table-success">
                  <tr>
                    <th>No</th>
                    <th>Keterangan</th>
                    <th>Nominal</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($dataPemasukan->num_rows > 0): 
                    $no = 1;
                    while ($row = $dataPemasukan->fetch_assoc()): ?>
                    <tr>
                      <td><?= $no++ ?></td>
                      <td><?= htmlspecialchars($row['information']) ?></td>
                      <td>Rp <?= number_format($row['nominal'], 0, ',', '.') ?></td>
                      <td><?= date('d-m-Y', strtotime($row['date'])) ?></td>
                      <td>
                        <button 
                          style="display: none;"
                          class="btn btn-sm btn-warning"
                          data-bs-toggle="modal"
                          data-bs-target="#editModal"
                          data-id="<?= $row['income_id'] ?? $row['expenditure_id'] ?>"
                          data-type="<?= isset($row['income_id']) ? 'income' : 'expenditures' ?>"
                          data-info="<?= htmlspecialchars($row['information']) ?>"
                          data-nominal="<?= $row['nominal'] ?>"
                        >
                          Edit
                        </button>
                        <?php
                          if (str_contains(htmlspecialchars($row['information']), 'INPUT SALDO OTOMATIS')) { 
                              
                          }else{ ?>
                        <button 
                          class="btn btn-sm btn-danger"
                          data-bs-toggle="modal"
                          data-bs-target="#deleteModal"
                          data-id="<?= $row['income_id'] ?? $row['expenditure_id'] ?>"
                          data-type="<?= isset($row['income_id']) ? 'income' : 'expenditures' ?>"
                        >
                          Hapus
                        </button>
                        <?php  } ?>
                      </td>
                    </tr>
                  <?php endwhile; else: ?>
                    <tr><td colspan="5" class="text-center">Tidak ada data Pemasukan.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content" style="background:transparent; border:none;">
            <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-2" data-bs-dismiss="modal"></button>
            <img id="modalImage" src="" alt="Preview Bukti" 
              style=" max-height:80vh; object-fit:contain; display:block; margin:auto; border-radius:10px;">
          </div>
        </div>
      </div>
      <!-- Modal Tambah Pengeluaran -->
      <div class="modal fade" id="addExpenditure" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <form method="post" class="modal-content" enctype="multipart/form-data">
            <div class="modal-header">
              <h5 class="modal-title">Tambah Pengeluaran Baru</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="tambah_pengeluaran" value="1">
              
              <div class="mb-3">
                <label class="form-label">Keterangan</label>
                <input type="text" name="information" class="form-control" required placeholder="Misal: BELI KERTAS" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase();">
              </div>
              
              <div class="mb-3">
                <label class="form-label">Nominal</label>
                <input type="number" name="nominal" class="form-control" required placeholder="Contoh: 100000">
              </div>
              
              <div class="mb-3">
                <label class="form-label">Foto Bukti</label>
                <input type="file" class="form-control" id="picture" name="picture" accept="image/*">
              </div>
              
              <input type="hidden" name="tanggal" value="<?= date('Y-m-d') ?>">
            </div>
            
            <div class="modal-footer">
              <button type="submit" class="btn btn-primary">Tambah</button>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            </div>
          </form>
        </div>
      </div>

        <!-- Modal Tambah Pemasukan -->
        <div class="modal fade" id="addIncome" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog">
            <form method="post" class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Tambah Pemasukan Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="tambah_pemasukan" value="1">
                <div class="mb-3">
                  <label class="form-label">Keterangan</label>
                  <input type="text" name="information_income" class="form-control" required placeholder="Misal: PENJUALAN ONLINE" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase();">
                </div>
                <div class="mb-3">
                  <label class="form-label">Nominal</label>
                  <input type="number" name="nominal_income" class="form-control" required placeholder="Contoh: 200000">
                </div>
                <input type="hidden" name="tanggal_income" value="<?= date('Y-m-d') ?>">
              </div>
              <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Tambah</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              </div>
            </form>
          </div>
        </div>

        <!-- Modal Edit -->
        <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="edit_keuangan.php" class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Edit Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="old_info" id="edit-id">
                <input type="hidden" name="type" id="edit-type">
                <input type="hidden" name="start_date" value="<?= $start_date ?>">
                <input type="hidden" name="end_date" value="<?= $end_date ?>">

                <div class="mb-3">
                  <label class="form-label">Keterangan</label>
                  <input type="text" class="form-control" name="information" id="edit-info" required>
                </div>

                <div class="mb-3">
                  <label class="form-label">Nominal</label>
                  <input type="number" class="form-control" name="nominal" id="edit-nominal" required>
                </div>
              </div>
              <div class="modal-footer">
                <button class="btn btn-primary" type="submit">Simpan Perubahan</button>
              </div>
            </form>
          </div>
        </div>

        <!-- Modal Hapus -->
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="hapus_keuangan.php" class="modal-content">
              
              <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
              <input type="hidden" name="start_date_hapus" value="<?= $start_date ?>">
              <input type="hidden" name="end_date_hapus" value="<?=  $end_date ?>">
              <input type="hidden" name="id" id="delete-id">
              <input type="hidden" name="type" id="delete-type">
                <p>Apakah Anda yakin ingin menghapus data ini?</p>
              </div>
              <div class="modal-footer">
                <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              </div>
            </form>
          </div>
        </div>


        <div class="mt-3 mb-4 text-center">
          <button class="btn btn-danger me-2" id="btnAddExpenditure">+ Pengeluaran</button>
          <button class="btn btn-success" id="btnAddIncome">+ Pemasukan</button>
        </div>

      </div>
      <!-- End Card -->

    </div>
  </div>

  <?php include BASE_PATH . '/footer.php'; ?>
</div>

<!-- <script type="text/javascript">
  const refreshall = document.getElementById('rfrs');
  refreshall.submit();                  
</script> -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  const refreshall = document.getElementsByName('refresh_finance');
  refreshall.submit;
  const addExpenditureModal = new bootstrap.Modal(document.getElementById('addExpenditure'));
  const addIncomeModal = new bootstrap.Modal(document.getElementById('addIncome'));

  document.getElementById('btnAddExpenditure').addEventListener('click', () => {
    addExpenditureModal.show();
  });

  document.getElementById('btnAddIncome').addEventListener('click', () => {
    addIncomeModal.show();
  });

  const modals = ['addExpenditure', 'addIncome'];

  modals.forEach(id => {
    const modalEl = document.getElementById(id);
    modalEl.addEventListener('shown.bs.modal', () => {
      const modalContent = modalEl.querySelector('.modal-content');
      const isDark = document.getElementById('main-content').classList.contains('dark-mode');

      if (isDark) {
        modalContent.classList.add('dark-mode');
      } else {
        modalContent.classList.remove('dark-mode');
      }
    });
  });

});
</script>
<script>
  const editModal = document.getElementById('editModal');

  // Saat modal edit ditampilkan
  editModal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    if (!button) return;

    const info = button.getAttribute('data-info');
    const nominal = button.getAttribute('data-nominal');
    const type = button.getAttribute('data-type');

    editModal.querySelector('#edit-id').value = info;
    editModal.querySelector('#edit-type').value = type;
    editModal.querySelector('#edit-info').value = info;
    editModal.querySelector('#edit-nominal').value = nominal;
  });

  // Saat modal hapus ditampilkan
  document.querySelectorAll('[data-bs-target="#deleteModal"]').forEach(button => {
    button.addEventListener('click', () => {
      const id = button.getAttribute('data-id');
      const type = button.getAttribute('data-type');
      document.getElementById('delete-id').value = id;
      document.getElementById('delete-type').value = type;
    });
  });


</script>
<script>
document.getElementById('btnExportExcel').addEventListener('click', async () => {
  const toko = "<?= addslashes($storeName) ?>";
  const alamat = "<?= addslashes($storeAddress) ?>";
  const startDate = document.querySelector('input[name="start_date"]').value || "";
  const endDate = document.querySelector('input[name="end_date"]').value || "";
  const tanggal = (startDate && endDate) ? `Tanggal ${startDate} s.d. ${endDate}` : 'Tanggal -';

  const workbook = new ExcelJS.Workbook();

  function styleHeaderRow(row) {
    row.font = { bold: true };
    row.eachCell(cell => {
      cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFCCE5FF' } };
      cell.alignment = { horizontal: 'center', vertical: 'middle' };
      cell.border = { top: { style: 'thin' }, bottom: { style: 'thin' }, left: { style: 'thin' }, right: { style: 'thin' } };
    });
  }
  function styleDataRow(row) {
    row.eachCell(cell => {
      cell.alignment = cell.alignment || { vertical: 'middle', horizontal: 'left' };
      cell.border = { top: { style: 'thin' }, bottom: { style: 'thin' }, left: { style: 'thin' }, right: { style: 'thin' } };
    });
  }

  const sheet = workbook.addWorksheet("Laporan Keuangan");

  // Header utama
  sheet.mergeCells("A1:H1");
  sheet.getCell("A1").value = toko;
  sheet.getCell("A1").alignment = { horizontal: 'center', vertical: 'middle' };
  sheet.getCell("A1").font = { bold: true, size: 16 };

  sheet.mergeCells("A2:H2");
  sheet.getCell("A2").value = alamat;
  sheet.getCell("A2").alignment = { horizontal: 'center' };

  sheet.addRow([]);
  sheet.mergeCells("A4:H4");
  sheet.getCell("A4").value = "Laporan Keuangan";
  sheet.getCell("A4").alignment = { horizontal: 'center' };
  sheet.getCell("A4").font = { bold: true, size: 14 };

  sheet.mergeCells("A5:H5");
  sheet.getCell("A5").value = tanggal;
  sheet.getCell("A5").alignment = { horizontal: 'center' };

  sheet.addRow([]);

  // ===== DATA KEUANGAN =====
  const headerKeuangan = ['No', 'Omset Offline', 'Omset Online', 'Total Omset', 'Transfer Masuk','Cash Masuk', 'Pengeluaran', 'Saldo Kas', 'Periode'];
  styleHeaderRow(sheet.addRow(headerKeuangan));

  const rowsKeuangan = document.querySelectorAll("#tableKeuangan tbody tr");
  rowsKeuangan.forEach(tr => {
    const tds = tr.querySelectorAll("td");
    if (tds.length > 7) {
      const parseRupiah = (str) => parseInt(str.replace(/Rp\s?|-|\./g, '').trim()) || 0;

      const no = tds[0].innerText.trim();
      const omsetOffline = parseRupiah(tds[1].innerText);
      const omsetOnline = parseRupiah(tds[2].innerText);
      const totalOmset = parseRupiah(tds[3].innerText);
      const transfer = parseRupiah(tds[4].innerText);
      const cash = parseRupiah(tds[5].innerText);
      const pengeluaran = parseRupiah(tds[6].innerText);
      const saldoKas = parseRupiah(tds[7].innerText);
      const periode = tds[8].innerText.trim();

      const row = sheet.addRow([no, omsetOffline, omsetOnline, totalOmset, transfer, cash, pengeluaran, saldoKas, periode]);
      [2, 3, 4, 5, 6, 7].forEach(i => {
        row.getCell(i).numFmt = '#,##0';
        row.getCell(i).alignment = { horizontal: 'right', vertical: 'middle' };
      });
      styleDataRow(row);
    }
  });

  // Jeda 2 baris
  sheet.addRow([]);
  sheet.addRow([]);

  // ===== DATA PENGELUARAN =====
  const pengeluaranTitleRowNum = sheet.lastRow.number + 1;
  sheet.mergeCells(`A${pengeluaranTitleRowNum}:E${pengeluaranTitleRowNum}`);
  sheet.getCell(`A${pengeluaranTitleRowNum}`).value = "Data Pengeluaran Bulan Ini";
  sheet.getCell(`A${pengeluaranTitleRowNum}`).font = { bold: true, size: 14 };
  sheet.getCell(`A${pengeluaranTitleRowNum}`).alignment = { horizontal: 'center' };

  sheet.addRow([]);
  const headerPengeluaran = ['No', 'Keterangan', 'Nominal', 'Tanggal'];
  styleHeaderRow(sheet.addRow(headerPengeluaran));


  const rowsPengeluaran = document.querySelectorAll("#tablePengeluaran tbody tr");
  rowsPengeluaran.forEach(tr => {
    const tds = tr.querySelectorAll("td");
    if (tds.length >= 4) {  // Bisa >4 karena ada kolom aksi di UI, tapi kita skip
      const no = tds[0].innerText.trim();
      const ket = tds[1].innerText.trim();
      const nominal = parseInt(tds[2].innerText.replace(/Rp\s?|-|\./g, '').trim()) || 0;
      const tanggal = tds[3].innerText.trim();

      const row = sheet.addRow([no, ket, nominal, tanggal]);
      row.getCell(3).numFmt = '#,##0';
      styleDataRow(row);
    }
  });


  // Jeda 2 baris
  sheet.addRow([]);
  sheet.addRow([]);

  // ===== DATA PEMASUKAN =====
  const pemasukanTitleRowNum = sheet.lastRow.number + 1;
  sheet.mergeCells(`A${pemasukanTitleRowNum}:E${pemasukanTitleRowNum}`);
  sheet.getCell(`A${pemasukanTitleRowNum}`).value = "Data Pemasukan Bulan Ini";
  sheet.getCell(`A${pemasukanTitleRowNum}`).font = { bold: true, size: 14 };
  sheet.getCell(`A${pemasukanTitleRowNum}`).alignment = { horizontal: 'center' };

  sheet.addRow([]);
  const headerPemasukan = ['No', 'Keterangan', 'Nominal', 'Tanggal'];
  styleHeaderRow(sheet.addRow(headerPemasukan));

  const rowsPemasukan = document.querySelectorAll("#tablePemasukan tbody tr");
  rowsPemasukan.forEach(tr => {
    const tds = tr.querySelectorAll("td");
    if (tds.length >= 4) {
      const no = tds[0].innerText.trim();
      const ket = tds[1].innerText.trim();
      const nominal = parseInt(tds[2].innerText.replace(/Rp\s?|-|\./g, '').trim()) || 0;
      const tanggal = tds[3].innerText.trim();

      const row = sheet.addRow([no, ket, nominal, tanggal]);
      row.getCell(3).numFmt = '#,##0';
      styleDataRow(row);
    }
  });

  // Set lebar kolom
  sheet.columns = [
    { width: 6 },  // No
    { width: 18 }, // Omset Offline / Keterangan
    { width: 18 }, // Omset Online / Nominal
    { width: 18 }, // Total Omset / Tanggal
    { width: 18 }, // Transfer Masuk / Aksi
    { width: 18 }, // Pengeluaran (kosong untuk pemasukan)
    { width: 18 }, // Saldo Kas (kosong)
    { width: 15 }  // Periode (kosong)
  ];

  // Save file
  const buffer = await workbook.xlsx.writeBuffer();
  saveAs(new Blob([buffer]), `Laporan_Keuangan_${startDate}_sd_${endDate}.xlsx`);
});

document.getElementById('btnExportWord').addEventListener('click', async function () {
  const { Document, Packer, Paragraph, Table, TableCell, TableRow, TextRun, WidthType, AlignmentType, BorderStyle } = window.docx;

  const toko = "<?= addslashes($storeName) ?>";
  const alamat = "<?= addslashes($storeAddress) ?>";

  const startDate = document.querySelector('input[name="start_date"]').value || "";
  const endDate = document.querySelector('input[name="end_date"]').value || "";
  const tanggal = (startDate && endDate) ? `Tanggal ${startDate} s.d. ${endDate}` : 'Tanggal -';

  // Fungsi helper untuk membuat header paragraph center
  function createHeader(text, size=28, bold=true, spacingAfter=200) {
    return new Paragraph({
      children: [new TextRun({ text, bold, size })],
      alignment: AlignmentType.CENTER,
      spacing: { after: spacingAfter }
    });
  }

  // Fungsi helper buat buat table cell teks
  function createCell(text, bold = false, align = AlignmentType.LEFT) {
    return new TableCell({
      children: [new Paragraph({ children: [new TextRun({ text, bold })], alignment: align })],
      margins: { top: 100, bottom: 100, left: 100, right: 100 }
    });
  }

  // Fungsi buat styling border table
  const borders = {
    top: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
    bottom: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
    left: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
    right: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
    insideHorizontal: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
    insideVertical: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
  };

  // ======= Bikin Tabel Keuangan =======
  let keuanganRows = [];

  // Header keuangan
  keuanganRows.push(new TableRow({
    children: [
      createCell("No", true, AlignmentType.CENTER),
      createCell("Omset Offline", true, AlignmentType.CENTER),
      createCell("Omset Online", true, AlignmentType.CENTER),
      createCell("Total Omset", true, AlignmentType.CENTER),
      createCell("Transfer Masuk", true, AlignmentType.CENTER),
      createCell("Pengeluaran", true, AlignmentType.CENTER),
      createCell("Saldo Kas", true, AlignmentType.CENTER),
      createCell("Periode", true, AlignmentType.CENTER),
    ],
    tableHeader: true,
  }));

  // Ambil data dari tabel #tableKeuangan
  document.querySelectorAll("#tableKeuangan tbody tr").forEach(tr => {
    const tds = tr.querySelectorAll("td");
    if (tds.length === 8) {
      const parseRupiah = (str) => parseInt(str.replace(/Rp\s?|-|\./g, '').trim()) || 0;

      keuanganRows.push(new TableRow({
        children: [
          createCell(tds[0].innerText.trim(), false, AlignmentType.CENTER),
          createCell(tds[1].innerText.trim()),
          createCell(tds[2].innerText.trim()),
          createCell(tds[3].innerText.trim()),
          createCell(tds[4].innerText.trim()),
          createCell(tds[5].innerText.trim()),
          createCell(tds[6].innerText.trim()),
          createCell(tds[7].innerText.trim(), false, AlignmentType.CENTER),
        ],
      }));
    }
  });

  const tableKeuangan = new Table({
    rows: keuanganRows,
    width: {
      size: 100,
      type: WidthType.PERCENTAGE,
    },
    borders,
  });

  // ======= Bikin Tabel Pengeluaran =======
  let pengeluaranRows = [];

  // Header pengeluaran tanpa kolom aksi
  pengeluaranRows.push(new TableRow({
    children: [
      createCell("No", true, AlignmentType.CENTER),
      createCell("Keterangan", true, AlignmentType.CENTER),
      createCell("Nominal", true, AlignmentType.CENTER),
      createCell("Tanggal", true, AlignmentType.CENTER),
    ],
    tableHeader: true,
  }));

  document.querySelectorAll("#tablePengeluaran tbody tr").forEach(tr => {
    const tds = tr.querySelectorAll("td");
    if (tds.length >= 4) {
      pengeluaranRows.push(new TableRow({
        children: [
          createCell(tds[0].innerText.trim(), false, AlignmentType.CENTER),
          createCell(tds[1].innerText.trim()),
          createCell(tds[2].innerText.trim()),
          createCell(tds[3].innerText.trim(), false, AlignmentType.CENTER),
        ],
      }));
    }
  });

  const tablePengeluaran = new Table({
    rows: pengeluaranRows,
    width: {
      size: 100,
      type: WidthType.PERCENTAGE,
    },
    borders,
  });

  // ======= Bikin Tabel Pemasukan =======
  let pemasukanRows = [];

  // Header pemasukan tanpa kolom aksi
  pemasukanRows.push(new TableRow({
    children: [
      createCell("No", true, AlignmentType.CENTER),
      createCell("Keterangan", true, AlignmentType.CENTER),
      createCell("Nominal", true, AlignmentType.CENTER),
      createCell("Tanggal", true, AlignmentType.CENTER),
    ],
    tableHeader: true,
  }));

  document.querySelectorAll("#tablePemasukan tbody tr").forEach(tr => {
    const tds = tr.querySelectorAll("td");
    if (tds.length >= 4) {
      pemasukanRows.push(new TableRow({
        children: [
          createCell(tds[0].innerText.trim(), false, AlignmentType.CENTER),
          createCell(tds[1].innerText.trim()),
          createCell(tds[2].innerText.trim()),
          createCell(tds[3].innerText.trim(), false, AlignmentType.CENTER),
        ],
      }));
    }
  });

  const tablePemasukan = new Table({
    rows: pemasukanRows,
    width: {
      size: 100,
      type: WidthType.PERCENTAGE,
    },
    borders,
  });

  // Buat document word
  const doc = new Document({
    sections: [{
      children: [
        createHeader(toko, 32),
        createHeader(alamat, 24),
        createHeader("Laporan Keuangan", 28),
        createHeader(tanggal, 24, false, 400),

        createHeader("Keuangan Terkini", 26, true, 200),
        tableKeuangan,

        new Paragraph({ text: "", spacing: { after: 400 }}), // spasi antar tabel

        createHeader("Data Pengeluaran Bulan Ini", 26, true, 200),
        tablePengeluaran,

        new Paragraph({ text: "", spacing: { after: 400 }}), // spasi antar tabel

        createHeader("Data Pemasukan Bulan Ini", 26, true, 200),
        tablePemasukan,
      ]
    }]
  });

  const blob = await Packer.toBlob(doc);
  saveAs(blob, `Laporan_Keuangan_${startDate}_sd_${endDate}.docx`);
});


</script>
<script>
function showImageModal(src) {
  document.getElementById('modalImage').src = src;
  const modal = new bootstrap.Modal(document.getElementById('imageModal'));
  modal.show();
}
</script>


</body>
</html>
