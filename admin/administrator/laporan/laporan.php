<?php
require_once '../../connect.php';
require_once BASE_PATH . '/global_functions.php';

?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cabang & Toko</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/content.css">
</head>
<body>

<?php include BASE_PATH . '/navbar.php'; ?>

<div id="mainWrapper">
  <?php include BASE_PATH . '/sidebar.php'; ?>

  <div id="contentWrapper">
    <main id="mainContent">
      <div class="container-fluid">
        <div class="row g-4">
          <!-- Chart Lebar -->
          <div class="col-12 col-md-8">
            <div class="card shadow-sm h-100">
              <div class="card-header text-center fw-bold">Penjualan Bulanan</div>
              <div class="card-body">
                <canvas id="chartPenjualan"></canvas>
              </div>
            </div>
          </div>

          <!-- Chart Kecil -->
          <div class="col-12 col-md-4">
            <div class="card shadow-sm h-100">
              <div class="card-header text-center fw-bold">Produk Terlaris</div>
              <div class="card-body">
                <canvas id="chartProduk"></canvas>
              </div>
            </div>
          </div>

          <!-- Chart Sedang -->
          <div class="col-12 col-md-6">
            <div class="card shadow-sm h-100">
              <div class="card-header text-center fw-bold">Kategori</div>
              <div class="card-body">
                <canvas id="chartKategori"></canvas>
              </div>
            </div>
          </div>

          <!-- Chart Sedang -->
          <div class="col-12 col-md-6">
            <div class="card shadow-sm h-100">
              <div class="card-header text-center fw-bold">Metode Pembayaran</div>
              <div class="card-body">
                <canvas id="chartPembayaran"></canvas>
              </div>
            </div>
          </div>
        </div>
      </div>

    </main>

    <?php include BASE_PATH . '/footer.php'; ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx1 = document.getElementById('chartPenjualan');
new Chart(ctx1, {
  type: 'line',
  data: {
    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'],
    datasets: [{ label: 'Penjualan', data: [10, 20, 15, 30, 25, 40], borderColor: 'blue', fill: false }]
  }
});

const ctx2 = document.getElementById('chartProduk');
new Chart(ctx2, {
  type: 'bar',
  data: {
    labels: ['Produk A', 'Produk B', 'Produk C'],
    datasets: [{ label: 'Terjual', data: [12, 19, 7], backgroundColor: ['red', 'green', 'blue'] }]
  }
});

const ctx3 = document.getElementById('chartKategori');
new Chart(ctx3, {
  type: 'pie',
  data: {
    labels: ['Makanan', 'Minuman', 'Snack'],
    datasets: [{ data: [30, 50, 20], backgroundColor: ['orange', 'purple', 'cyan'] }]
  }
});

const ctx4 = document.getElementById('chartPembayaran');
new Chart(ctx4, {
  type: 'doughnut',
  data: {
    labels: ['Cash', 'Transfer', 'QRIS'],
    datasets: [{ data: [40, 35, 25], backgroundColor: ['yellow', 'pink', 'lightblue'] }]
  }
});
</script>


</body>
</html>