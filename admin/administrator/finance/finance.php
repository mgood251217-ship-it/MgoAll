<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../../connect.php';
require_once BASE_PATH . '/global_functions.php';

date_default_timezone_set('Asia/Jakarta');
// Ambil filter tanggal
$start_date_f = isset($_GET['start_date']) && $_GET['start_date'] ? $_GET['start_date'] : date('Y-m-d');
$end_date_f = isset($_GET['end_date']) && $_GET['end_date'] ? $_GET['end_date'] : date('Y-m-d');

$start_date = $start_date_f . ' 00:00:00';
$end_date = $end_date_f . ' 23:59:59';

// Ambil data finance sesuai rentang tanggal
$dataFinance = [];
if ($access == 'ALL') {
  $stores = $koneksi->query("SELECT store_id, name FROM stores");
  $stmt = $koneksi->prepare("
    SELECT f.*, s.name AS store_name 
    FROM finance f
    JOIN stores s ON s.store_id = f.store_id
    WHERE f.date BETWEEN ? AND ?
      AND (f.omset_offline != 0 OR f.omset_online != 0 OR f.transfer != 0)
    ORDER BY s.name, f.date ASC
  ");

  $stmt->bind_param("ss", $start_date, $end_date);
  $stmt->execute();
  $res = $stmt->get_result();
}else {
  $stores = $koneksi->query("SELECT store_id, name FROM stores");
  $stmt = $koneksi->prepare("
    SELECT f.*, s.name AS store_name 
    FROM finance f
    JOIN stores s ON s.store_id = f.store_id
    WHERE f.date BETWEEN ? AND ?
      AND (f.omset_offline != 0 OR f.omset_online != 0 OR f.transfer != 0)
      AND s.administrator = ?
    ORDER BY s.name, f.date ASC
  ");

  $stmt->bind_param("sss", $start_date, $end_date, $access);
  $stmt->execute();
  $res = $stmt->get_result();
}

while ($row = $res->fetch_assoc()) {
  $dataFinance[$row['store_id']]['store_name'] = $row['store_name'];
  $dataFinance[$row['store_id']]['records'][] = $row;
}

function refreshAllFinance($start_date, $end_date) {
    global $koneksi;

    $storeIds = [];
    $res = $koneksi->query("SELECT store_id FROM stores");
    while ($row = $res->fetch_assoc()) {
        $storeIds[] = $row['store_id'];
    }

    // Loop tanggal dari start ke end
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = new DateInterval('P1D');
    $dateRange = new DatePeriod($start, $interval, $end->modify('+1 day'));

    foreach ($dateRange as $dateObj) {
        $dateStr = $dateObj->format('Y-m-d');
        foreach ($storeIds as $store_id) {
            refreshFinance($store_id, $dateStr);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_all_range'])) {
    $start = $start_date_f;
    $end = $end_date_f;
    if (!empty($start) && !empty($end)) {
        refreshAllFinance($start, $end);

        // Redirect pakai GET supaya reload-nya bersih
        header("Location: " . $_SERVER['PHP_SELF'] . "?start_date=$start&end_date=$end&refreshed=1");
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Laporan Keuangan</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/content.css">
  <style>
    .accordion-button:not(.collapsed) {
      background-color: #f8f9fa;
    }
  </style>
</head>
<body>

<?php include BASE_PATH . '/navbar.php'; ?>
<div id="mainWrapper">
  <?php include BASE_PATH . '/sidebar.php'; ?>

  <div id="contentWrapper">
    <main id="mainContent">
      <div class="container-fluid">
        <h1 class="mb-4">Laporan Keuangan</h1>

        <!-- FORM FILTER (GET) -->
        <form method="get" class="row mb-4">
          <div class="col-md-3">
            <label>Dari Tanggal</label>
            <input type="date" name="start_date" value="<?= $start_date_f ?>" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label>Sampai Tanggal</label>
            <input type="date" name="end_date" value="<?= $end_date_f ?>" class="form-control" required>
          </div>
          <div class="col-md-2 align-self-end">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
          </div>
        </form>

        <!-- FORM REFRESH (POST) -->
        <form method="post" class="row mb-4">
          <div class="col-md-2">
            <label class="invisible">_</label>
            <button type="submit" name="refresh_all_range" value="1" class="btn btn-success w-100">
              <i class="bi bi-arrow-clockwise"></i> Refresh Semua
            </button>
          </div>
        </form>


        <div class="accordion" id="financeAccordion">
          <?php $index = 0; foreach ($dataFinance as $store_id => $data): ?>
            <div class="accordion-item">
              <h2 class="accordion-header" id="heading<?= $index ?>">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $index ?>" aria-expanded="false">
                  <?= htmlspecialchars($data['store_name']) ?>
                </button>
              </h2>
              <div id="collapse<?= $index ?>" class="accordion-collapse collapse" data-bs-parent="#financeAccordion">
                <div class="accordion-body p-0">
                  <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                      <thead>
                        <tr class="table-light">
                          <th>Tanggal</th>
                          <th>Omset Offline</th>
                          <th>Omset Online</th>
                          <th>Transfer</th>
                          <th>Pengeluaran</th>
                          <th>Saldo</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($data['records'] as $record): ?>
                          <tr>
                            <td><?= htmlspecialchars($record['date']) ?></td>
                            <td><?= number_format($record['omset_offline']) ?></td>
                            <td><?= number_format($record['omset_online']) ?></td>
                            <td><?= number_format($record['transfer']) ?></td>
                            <td><?= number_format($record['expenditure']) ?></td>
                            <td><?= number_format($record['saldo']) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          <?php $index++; endforeach; ?>
        </div>

      </div>
    </main>
    <br>
    <br>
    <?php include BASE_PATH . '/footer.php'; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
