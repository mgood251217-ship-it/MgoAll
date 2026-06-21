<?php
// File: cabang.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../../connect.php';
require_once BASE_PATH . '/global_functions.php';
require_once BASE_PATH . '/administrator/session.php';

date_default_timezone_set('Asia/Jakarta');
// Ambil filter tanggal dari GET atau default ke hari ini
$start_date_f = isset($_GET['start_date']) && $_GET['start_date'] ? $_GET['start_date'] : date('Y-m-d');
$end_date_f = isset($_GET['end_date']) && $_GET['end_date'] ? $_GET['end_date'] : date('Y-m-d');

$start_date = $start_date_f . ' 00:00:00';
$end_date = $end_date_f . ' 23:59:59';

if ($access == 'ALL') {
  $result = $koneksi->query("SELECT * FROM stores");
}else {
  $stmtStore = $koneksi->prepare("SELECT * FROM stores WHERE administrator = ?");
  $stmtStore->bind_param("s", $access);
  $stmtStore->execute();
  $result = $stmtStore->get_result();
}

// Ambil user untuk opsi dropdown owner
$userResult = $koneksi->query("SELECT user_id, name FROM users ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manajemen Pesanan</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="<?= BASE_URL ?>/administrator/assets/css/content.css">
</head>
<body>

<?php include BASE_PATH . '/administrator/navbar.php'; ?>

<div id="mainWrapper">
  <?php include BASE_PATH . '/administrator/sidebar.php'; ?>

  <div id="contentWrapper">
    <main id="mainContent">
      <div class="container-fluid">
        <h1 class="mb-4">Manajemen Pesanan</h1>
        <!-- Filter tanggal -->
        <form method="get" class="row g-3 mb-4" action="pesanan.php">
          <div class="col-auto">
            <label for="start_date" class="form-label">Dari Tanggal</label>
            <input type="date" id="start_date" name="start_date" class="form-control"
              value="<?= htmlspecialchars($start_date_f) ?>"
              onchange="this.form.submit()" />
          </div>
          <div class="col-auto">
            <label for="end_date" class="form-label">Sampai Tanggal</label>
            <input type="date" id="end_date" name="end_date" class="form-control"
              value="<?= htmlspecialchars($end_date_f) ?>"
              onchange="this.form.submit()" />
          </div>
        </form>
        <div class="table-responsive">
          <table class="table table-modern">
            <thead>
              <tr>
                <th>No</th>
                <th>Nama Toko</th>
                <th>Cabang</th>
                <th>Jumlah Pesanan</th>
                <th>Belum Bayar</th>
                <th>DP</th>
                <th>Lunas</th>
                <th>Total</th>
                <th>Pendapatan</th>
              </tr>
            </thead>
            <tbody>
              <?php $no = 1; while ($row = $result->fetch_assoc()):
                $storeId = $row['store_id'];

              $stmt = $koneksi->prepare("SELECT order_id, total FROM orders WHERE store_id = ? AND date BETWEEN ? AND ?");
              $stmt->bind_param("iss", $storeId, $start_date, $end_date);
              $stmt->execute();
              $orderResult = $stmt->get_result();

              $orders = [];
              $orderIds = [];
              $totalNominal = 0;
              while ($o = $orderResult->fetch_assoc()) {
                  $orders[$o['order_id']] = $o['total'];
                  $orderIds[] = $o['order_id'];
                  $totalNominal += $o['total'];
              }


              $jumlahPesanan = count($orders);
              $dp = 0;
              $lunas = 0;
              $pendapatan = 0;
              $belumBayar = 0;

              if (!empty($orderIds)) {
                  $inClause = implode(',', $orderIds);

                  // Hitung total pendapatan (sum nominal payment)
                  $qPendapatan = "
                      SELECT IFNULL(SUM(nominal), 0) AS total_pendapatan
                      FROM payment
                      WHERE order_id IN ($inClause)
                  ";
                  $pendapatan = (float)$koneksi->query($qPendapatan)->fetch_assoc()['total_pendapatan'];

                  // Ambil status lunas dan total DP per order
                  $qStatus = "
                      SELECT 
                          order_id,
                          MAX(CASE WHEN status = 'LUNAS' THEN 1 ELSE 0 END) AS is_lunas,
                          SUM(CASE WHEN status = 'DP' THEN nominal ELSE 0 END) AS total_dp,
                          SUM(nominal) AS total_bayar
                      FROM payment
                      WHERE order_id IN ($inClause)
                      GROUP BY order_id
                  ";
                  $resultStatus = $koneksi->query($qStatus);

                  $dp = 0;
                  $lunas = 0;
                  $belumBayar = 0;

                  // Buat array untuk cek sisa hutang tiap order
                  $sisaHutangPerOrder = [];

                  while ($statusRow = $resultStatus->fetch_assoc()) {
                      $order_id = $statusRow['order_id'];
                      $is_lunas = $statusRow['is_lunas'];
                      $total_dp = $statusRow['total_dp'];
                      $total_bayar = $statusRow['total_bayar'];

                      // Hitung sisa hutang
                      $sisa = $orders[$order_id] - $total_bayar;
                      $sisaHutangPerOrder[$order_id] = $sisa;

                      if ($is_lunas == 1) {
                          $lunas++;
                      } elseif ($total_dp > 0) {
                          $dp++;
                      }
                      // Kalau sisa hutang > 0 tapi ada bayar DP atau lainnya, jangan dihitung "belum bayar" di sini,
                      // karena sudah bayar sebagian, kita hitung terpisah nanti.
                  }

                  // Hitung order yang belum bayar sama sekali (tidak ada payment)
                  $ordersWithPayment = array_keys($sisaHutangPerOrder);
                  $belumBayar = 0;
                  foreach ($orders as $oid => $total) {
                      if (!in_array($oid, $ordersWithPayment)) {
                          // Order sama sekali belum ada pembayaran
                          $belumBayar++;
                      } else {
                          // Kalau sudah ada pembayaran, cek sisa hutang > 0 juga berarti belum lunas, tapi sudah bayar sebagian
                          if (isset($sisaHutangPerOrder[$oid]) && $sisaHutangPerOrder[$oid] > 0) {
                              // Kalau kamu mau hitung ini juga sebagai belum lunas, bisa ditambah ke dp atau kategori lain
                              // Tapi biasanya dianggap sudah bayar sebagian, bukan belum bayar sama sekali.
                          }
                      }
                  }
              } else {
                  $pendapatan = 0;
                  $dp = 0;
                  $lunas = 0;
                  $belumBayar = 0;
              }

              ?>
              <tr>
                <td><?= $no++ ?></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['branch']) ?></td>
                <td><?= $jumlahPesanan ?></td>
                <td><?= $belumBayar ?></td>
                <td><?= $dp ?></td>
                <td><?= $lunas ?></td>
                <td><?= number_format($totalNominal, 0, ',', '.') ?></td>
                <td><?= number_format($pendapatan, 0, ',', '.') ?></td>
              </tr>
              <?php endwhile; ?>

            </tbody>
          </table>
        </div>
      </div>
    </main>

    <?php include BASE_PATH . '/administrator/footer.php'; ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
