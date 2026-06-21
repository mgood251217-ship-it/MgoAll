<?php
require_once '../connect.php';
require_once BASE_PATH . '/functions/user_validation.php';

$jenis = ['outdoor', 'indoor', 'laser', 'jersey', 'sublim', 'merchandise'];
$satuan = ['meter', 'centimeter', 'pcs', 'lembar'];
$variant = ['ukuran', 'bahan', 'finishing'];

$user_id = $_SESSION['shopee_users']['user_id'] ?? 0;

date_default_timezone_set('Asia/Jakarta');

$date = $_GET['month'] ?? date('Y-m');

$start_month = $date . '-01';
$end_month = date('Y-m-t', strtotime($start_month));

$stmt = $koneksi->prepare('SELECT finance_id, omset, month FROM finance WHERE user_id = ? AND month BETWEEN ? AND ? LIMIT 1');
$stmt->bind_param('iss', $user_id, $start_month, $end_month);
$stmt->execute();
$result_finance = $stmt->get_result()->fetch_assoc();
$stmt->close();
$finance_id = $result_finance['finance_id'] ?? 0;
$omset = $result_finance['omset'] ?? 0;
$dateFinance = $result_finance['month'] ?? date('Y-m-d');
$monthFinance = date('Y-m', strtotime($dateFinance)) ?? $date;

$stmt2 = $koneksi->prepare("SELECT expenditure_id, title, nominal, date FROM expenditure WHERE user_id = ? AND date BETWEEN ? AND ? ORDER BY date DESC");
$stmt2->bind_param("iss", $user_id, $start_month, $end_month);
$stmt2->execute();
$result_expenditure = $stmt2->get_result();
$stmt2->close();

$stmt3 = $koneksi->prepare("SELECT SUM(nominal) AS total_nominal FROM expenditure WHERE user_id = ? AND date BETWEEN ? AND ? ORDER BY date DESC");
$stmt3->bind_param("iss", $user_id, $start_month, $end_month);
$stmt3->execute();
$totalPengeluaran = $stmt3->get_result()->fetch_assoc()['total_nominal'] ?? 0;
$stmt3->close();

$stmt4 = $koneksi->prepare("SELECT foto, date FROM proof WHERE user_id = ? AND finance_id = ? LIMIT 1");
$stmt4->bind_param("ii", $user_id, $finance_id);
$stmt4->execute();
$resultBukti = $stmt4->get_result()->fetch_assoc();
$fotoBukti = $resultBukti['foto'] ?? '';
$tanggalBukti = isset($resultBukti['date']) ? date('d M Y H:i', strtotime($resultBukti['date'])) : '';

$stmt4->close();

$fotoTampil = $fotoBukti ? BASE_URL . '/assets/img/bukti_transaksi/' . $fotoBukti : '';

?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard</title>
  <?php include BASE_PATH . '/elements/header.php'; ?>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/content.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dark_mode.css">
</head>

<style>
  .exin{
    display: flex;
    justify-content: space-between;
    gap:20px;
    margin-top: 50px;
  }
.form-group {
  display: flex;
  flex-direction: column;
  margin-bottom: 15px;
}

.form-group label {
  font-size: 13px;
  margin-bottom: 5px;
  color: #555;
}

.form-group input,
.form-group select {
  padding: 10px;
  border-radius: 6px;
  border: 1px solid #ddd;
  font-size: 14px;
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: #ee4d2d;
}
.keuangan {
  display: flex;
  gap: 30px;
}
.drop-file {
  border: 2px dashed #ccc;
  border-radius: 6px;
  padding: 20px;
  text-align: center;
  width: 200px;
  height: 100px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  cursor: pointer;
}
.drop-file label {
  font-size: 14px;
  color: #555;
}
.proof-image img {
  max-width: 100%;
  max-height: 100px;
}
.drop-file:hover {
  border-color: #ee4d2d;
  background-color: #f9f9f9;
}
.zoomGambar {
  display: none;
  position: fixed;
  z-index: 9999999;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  overflow: auto;
  background-color: rgba(0, 0, 0, 0.8);
}
.zoomGambar .closeZoom {
  position: absolute;
  top: 20px;
  right: 35px;
  color: #fff;
  font-size: 40px;
  font-weight: bold;
  cursor: pointer;
}
.zoomGambar .zoomedImage {
  margin: auto;
  display: block;
  max-width: 80%;
  max-height: 80%;
  margin-top: 60px;
}

</style>

<body>
<div id="main-wrapper" <?= ($mode ?? 0) === 1 ? 'class="dark-mode"' : '' ?>>
  <?php include BASE_PATH . '/elements/navbar.php'; ?>

  <div id="page-content-wrapper" <?= ($mode ?? 0) === 1 ? 'class="dark-mode"' : '' ?>>

    <div class="judul-page">
      <h2>Keuangan</h2>
      <form method="get" class="shopee-form date-form">
        <label for="month">Pilih Bulan: </label>
        <input type="month" id="month" name="month" value="<?= date('Y-m', strtotime($start_month)) ?>" onchange="this.form.submit()">
      </form>
    </div>

    <div class="keuangan">
      <div class="keterangan">
        <h4>Omset</h4>
        <h4>Pengeluaran</h4>
        <h4>Bulan</h4>
      </div>
      <div class="isi">
        <h4>: <?= $omset ?></h4>
        <h4>: <?= $totalPengeluaran ?></h4>
        <h4>: <?= $monthFinance ?></h4>
      </div>
      <div class="proof">
          <div class="drop-file">
            <label>Bukti Transaksi</label>
          </div>
         <form id="buktiTransaksi">
            <input type="hidden" name="finance_id" value="<?= $finance_id ?>">
            <input type="file" name="bukti_transaksi" accept="image/*,application/pdf" style="display: none;">
         </form>
      </div>
      <div class="proof-image">
        <?php if ($fotoTampil): ?>
          <img src="<?= $fotoTampil ?>" alt="Bukti Transaksi" onclick="zoomGambar()">
          <span ><?= $tanggalBukti ?></span>
        <?php else: ?>
          <p>Tidak ada bukti transaksi</p>
        <?php endif; ?>
      </div>
    </div>
    <div class="zoomGambar">
      <span class="closeZoom" onclick="tutupZoom()">&times;</span>
      <img class="zoomedImage" id="zoomedImage" src="" alt="Zoomed Image">
    </div>
    <div class="table-up">
        <label for="modal-update" class="btn-shopee">Update Omset</label>
     </div>
    <input type="checkbox" id="modal-update" hidden>
    <div class="css-modal-update">
      <div class="css-modal-dialog">
          <div class="css-modal-header">
              <h5>Update Omset</h5>
              <label for="modal-update" class="close-btn">&times;</label>
          </div>

          <div class="css-modal-body">
            <form id="omsetForm">

              <div class="form-group">
                <label>Omset</label>
                <input type="number" name="omset" placeholder="Contoh: Omset Bulan yang ditentukan" required>
              </div>

              <input type="hidden" name="month" value="<?= $start_month ?>">

            </form>

          </div>

          <div class="css-modal-footer">
            <button type="button" id="submitOmset" class="btn-shopee">
              Simpan
            </button>
          </div>

      </div>
    </div>

    <h2>Data Pengeluaran</h2>
    <table class="shopee-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Judul</th>
                <th>Nominal</th>
                <th>Tanggal</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <?php $no = 1; ?>
        <tbody>
          <?php while ($rs = $result_expenditure->fetch_assoc()) { ?>  
            <tr>
              <td><?= $no ?></td>
              <td><?= $rs['title'] ?></td>
              <td><?= "Rp " . number_format($rs['nominal'], 0, '.', '.'); ?></td>
              <td><?= $rs['date'] ?></td>
              <td>
                <button id="deletePengeluaran" class="btn-shopee" data-id="<?= $rs['expenditure_id']; ?>">Delete</button>
              </td>
            </tr>
          <?php $no++; ?>
          <?php } ?>  
        </tbody>
    </table>

    <div class="table-up">
        <label for="modal-finance" class="btn-shopee">Tambah Pengeluaran</label>
     </div>

    <input type="checkbox" id="modal-finance" hidden>
    <div class="css-modal">
      <div class="css-modal-dialog">
          <div class="css-modal-header">
              <h5>Modal Pengeluaran</h5>
              <label for="modal-finance" class="close-btn">&times;</label>
          </div>

          <div class="css-modal-body">
            <form id="financeForm">

              <!-- Judul -->
              <div class="form-group">
                <label>Judul</label>
                <input type="text" name="title" placeholder="Contoh: Iklan" required>
              </div>

              <!-- Nominal -->
              <div class="form-group">
                <label>Nominal</label>
                <input type="number" name="nominal" placeholder="Masukkan nominal" required>
              </div>

              <!-- Tanggal -->
              <div class="form-group">
                <label>Tanggal</label>
                <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
              </div>

            </form>

          </div>

          <div class="css-modal-footer">
            <button type="button" id="submitFinance" class="btn-shopee">
              Simpan
            </button>
          </div>

      </div>
    </div>

  <?php include BASE_PATH . '/elements/footer.php'; ?>
</div>
<script>
document.getElementById('submitFinance').addEventListener('click', function () {
  const form = document.getElementById('financeForm');
  const formData = new FormData(form);
  console.log('cek');
  
  // validasi sederhana
  if (!formData.get('title') || !formData.get('nominal')) {
    alert('Lengkapi data terlebih dahulu');
    return;
  }

  fetch('<?= BASE_URL ?>/functions/add_expenditure.php', {
    method: 'POST',
    body: formData,
    body: JSON.stringify({ title: formData.get('title'), nominal: parseFloat(formData.get('nominal')), date: formData.get('date') }),
    headers: {
      'Content-Type': 'application/json'
    }
  })
  .then(res => res.json())
  .then(response => {
    if (response.status === 'success') {
      alert(response.message);
      location.reload();
    } else {
      alert(response.message);
    }
    
    
  })
  .catch(err => {
    console.error(err);
    alert('Gagal menyimpan data');
  });
});

document.getElementById('submitOmset').addEventListener('click', function () {
  const form = document.getElementById('omsetForm');
  const formData = new FormData(form);
  
  // validasi sederhana
  if (!formData.get('omset')) {
    alert('Lengkapi data terlebih dahulu');
    return;
  }

  fetch('<?= BASE_URL ?>/functions/update_omset.php', {
    method: 'POST',
    body: JSON.stringify({ omset: parseFloat(formData.get('omset')), month: formData.get('month') }),
    headers: {
      'Content-Type': 'application/json'
    }
  })
  .then(res => res.json())
  .then(response => {
    if (response.status === 'success') {
      alert(response.message);
      location.reload();
    } else {
      alert(response.message);
    }
    
    
  })
  .catch(err => {
    console.error(err);
    alert('Gagal menyimpan data');
  });
});

document.querySelectorAll('#deletePengeluaran').forEach(button => {
  button.addEventListener('click', function () {
    const expenditureId = this.getAttribute('data-id');

    if (!confirm('Apakah Anda yakin ingin menghapus pengeluaran ini?')) {
      return;
    }

    fetch('<?= BASE_URL ?>/functions/delete_expenditure.php', {
      method: 'POST',
      body: JSON.stringify({ expenditure_id: expenditureId }),
      headers: {
        'Content-Type': 'application/json'
      }
    })
    .then(res => res.json())
    .then(response => {
      if (response.status === 'success') {
        alert(response.message);
        location.reload();
      } else {
        alert(response.message);
      }
    })
    .catch(err => {
      console.error(err);
      alert('Gagal menghapus data');
    });
  });
});

document.querySelector('.drop-file').addEventListener('click', function() {
    document.querySelector('#buktiTransaksi input[name="bukti_transaksi"]').click();
});
document.querySelector('#buktiTransaksi input[name="bukti_transaksi"]').addEventListener('change', function() {
    const fileInput = this;
    const formData = new FormData();
    formData.append('bukti_transaksi', fileInput.files[0]);
    formData.append('month', '<?= $start_month ?>');
    formData.append('finance_id', '<?= $finance_id ?>');

    console.log(fileInput.files[0]);
    

    fetch('<?= BASE_URL ?>/functions/upload_bukti_transaksi.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(response => {
        if (response.status === 'success') {
            alert(response.message);
            location.reload();
        } else {
            alert(response.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Gagal mengunggah');
    });
});

function zoomGambar() {
    const zoomGambarDiv = document.querySelector('.zoomGambar');
    const zoomedImage = document.getElementById('zoomedImage');
    const proofImage = document.querySelector('.proof-image img');

    if (proofImage) {
        zoomedImage.src = proofImage.src;
        zoomGambarDiv.style.display = 'block';
    }
}
function tutupZoom() {
    const zoomGambarDiv = document.querySelector('.zoomGambar');
    zoomGambarDiv.style.display = 'none';
}

</script>
</body>
</html>
