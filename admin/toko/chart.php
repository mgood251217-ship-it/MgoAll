<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

$query_order = "
    SELECT 
        DATE(o.date) AS tanggal,
        COUNT(o.order_id) AS jumlah_order
    FROM orders o
    WHERE o.store_id = $store_id
      AND o.date >= CURDATE() - INTERVAL 30 DAY
    GROUP BY tanggal
    ORDER BY tanggal ASC
";

$result_order = mysqli_query($koneksi, $query_order);

$orders_by_date = [];
while ($row = mysqli_fetch_assoc($result_order)) {
    $orders_by_date[$row['tanggal']] = (int)$row['jumlah_order'];
}

$query_payment = "
    SELECT 
        DATE(p.date) AS tanggal,
        SUM(p.nominal) AS total_order
    FROM payment p
    JOIN orders o ON o.order_id = p.order_id
    WHERE o.store_id = $store_id
      AND p.date >= CURDATE() - INTERVAL 30 DAY
    GROUP BY tanggal
    ORDER BY tanggal ASC
";

$result_payment = mysqli_query($koneksi, $query_payment);

// Simpan hasil total pembayaran
$payments_by_date = [];
while ($row = mysqli_fetch_assoc($result_payment)) {
    $payments_by_date[$row['tanggal']] = (int)$row['total_order'];
}

// --- Gabungkan semua tanggal ---
$all_dates = array_unique(array_merge(array_keys($orders_by_date), array_keys($payments_by_date)));
sort($all_dates);

// --- Siapkan array final untuk grafik ---
$data_tanggal = [];
$data_jumlah = [];
$data_total = [];

foreach ($all_dates as $tanggal) {
    $data_tanggal[] = $tanggal;
    $data_jumlah[] = $orders_by_date[$tanggal] ?? 0;
    $data_total[]  = $payments_by_date[$tanggal] ?? 0;
}


// --- QUERY 1 TAHUN TERAKHIR (bulanan) ---
$query_365 = "
    SELECT 
        DATE_FORMAT(p.date, '%Y-%m') AS bulan, 
        COUNT(DISTINCT o.order_id) AS jumlah_order,
        SUM(p.nominal) AS total_order
    FROM orders o
    JOIN payment p ON o.order_id = p.order_id
    WHERE o.store_id = $store_id 
      AND p.date >= CURDATE() - INTERVAL 1 YEAR
    GROUP BY bulan
    ORDER BY bulan ASC
";

$result_365 = mysqli_query($koneksi, $query_365);

$data_bulan_365 = [];
$data_jumlah_365 = [];
$data_total_365 = [];

while ($row = mysqli_fetch_assoc($result_365)) {
    $data_bulan_365[] = $row['bulan'];
    $data_jumlah_365[] = (int)$row['jumlah_order'];
    $data_total_365[] = (int)$row['total_order'];
}

// Total 30 hari
$q_total30 = "
    SELECT SUM(p.nominal) AS total30 
    FROM orders o
    JOIN payment p ON o.order_id = p.order_id
    WHERE o.store_id = $store_id 
      AND p.date >= CURDATE() - INTERVAL 30 DAY
";

$total30 = mysqli_fetch_assoc(mysqli_query($koneksi, $q_total30))['total30'] ?? 0;

// Total hari ini
$q_today = "
    SELECT SUM(p.nominal) AS total_today 
    FROM orders o
    JOIN payment p ON o.order_id = p.order_id
    WHERE o.store_id = $store_id 
      AND DATE(p.date) = CURDATE()
";


$total_today = mysqli_fetch_assoc(mysqli_query($koneksi, $q_today))['total_today'] ?? 0;

// Customer dengan total tertinggi
$q_top_customer = "
    SELECT o.customer_name, SUM(p.nominal) AS total 
    FROM orders o
    JOIN payment p ON o.order_id = p.order_id
    WHERE o.store_id = $store_id 
      AND p.date >= CURDATE() - INTERVAL 30 DAY
    GROUP BY o.customer_name
    ORDER BY total DESC
    LIMIT 1
";

$row_top = mysqli_fetch_assoc(mysqli_query($koneksi, $q_top_customer));
$top_customer = $row_top['customer_name'] ?? '-';
$top_total = $row_top['total'] ?? 0;

$darkModeClass = ($mode === 1) ? 'dark-mode' : '';

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Dashboard Statistik Order</title>

<!-- Bootstrap CSS (Optional, sesuaikan) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
  /* Dark mode styles */
  .dark-mode {
    background-color: #121212;
    color: #e0e0e0;
  }
  .dark-mode .card {
    background-color: #2c2c2c;
    color: #e0e0e0;
    border-color: #444;
  }
  .dark-mode .card .card-title {
    color: #ddd;
  }
  .dark-mode .list-group-item {
    background-color: #3a3a3a;
    color: #ccc;
    border-color: #444;
  }
  .dark-mode .list-group-item strong {
    color: #fff;
  }
  .dark-mode .text-primary {
    color: #80bdff !important;
  }
  .dark-mode .text-success {
    color: #85e085 !important;
  }
  .dark-mode .fw-bold,
  .dark-mode .fw-semibold {
    color: #eee !important;
  }
  .dark-mode canvas {
    background-color: #1e1e1e;
    border-radius: 8px;
  }
</style>
</head>
<body class="<?= $darkModeClass ?>">



  <!-- GRAFIK 30 HARI -->
  <div class="row mt-4">
    <div class="col-md-8">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Statistik Order 30 Hari Terakhir</h5>
          <canvas id="orderChart" height="100"></canvas>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title">📌 Keterangan</h5>
          <ul class="list-group list-group-flush">
            <li class="list-group-item">
              <strong class="text-dark">Customer:</strong><br>
              <span class="text-primary fw-bold"><?= htmlspecialchars($top_customer); ?></span><br>
              <span class="text-success fw-semibold"><?= rupiah($top_total); ?></span>
            </li>
            <li class="list-group-item">
              <strong class="text-dark">Total 30 Hari:</strong><br>
              <span class="text-success fw-semibold"><?= rupiah($total30); ?></span>
            </li>
            <li class="list-group-item">
              <strong class="text-dark">Total Hari Ini:</strong><br>
              <span class="text-primary fw-semibold"><?= rupiah($total_today); ?></span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- GRAFIK 1 TAHUN -->
  <div class="row mt-4">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Statistik Order 1 Tahun Terakhir</h5>
          <canvas id="orderChart365" height="100"></canvas>
        </div>
      </div>
    </div>
  </div>


<!-- CHART.JS CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Mode gelap dari PHP (boolean)
const isDarkMode = <?= ($mode === 1) ? 'true' : 'false' ?>;

// Opsi chart yang dipakai di dua chart
const commonOptions = {
    responsive: true,
    scales: {
        y: {
            beginAtZero: true,
            position: 'left',
            title: {
                display: true,
                text: 'Jumlah Order',
                color: isDarkMode ? '#eee' : '#000',
            },
            ticks: {
                color: isDarkMode ? '#ccc' : '#000',
            },
            grid: {
                color: isDarkMode ? '#444' : '#ddd',
            }
        },
        y1: {
            beginAtZero: true,
            position: 'right',
            grid: {
                drawOnChartArea: false,
                color: isDarkMode ? '#444' : '#ddd',
            },
            title: {
                display: true,
                text: 'Total Order (Rp)',
                color: isDarkMode ? '#eee' : '#000',
            },
            ticks: {
                color: isDarkMode ? '#ccc' : '#000',
            }
        },
        x: {
            title: {
                display: true,
                color: isDarkMode ? '#eee' : '#000',
            },
            ticks: {
                color: isDarkMode ? '#ccc' : '#000',
            },
            grid: {
                color: isDarkMode ? '#444' : '#ddd',
            }
        }
    },
    plugins: {
        legend: {
            labels: {
                color: isDarkMode ? '#eee' : '#000',
            }
        },
        tooltip: {
            mode: 'index',
            intersect: false,
            backgroundColor: isDarkMode ? '#333' : undefined,
            titleColor: isDarkMode ? '#eee' : undefined,
            bodyColor: isDarkMode ? '#eee' : undefined,
        }
    }
};

// Chart 30 hari
const ctx = document.getElementById('orderChart').getContext('2d');
const orderChart = new Chart(ctx, {
    data: {
        labels: <?= json_encode($data_tanggal); ?>,
        datasets: [
            {
                type: 'bar',
                label: 'Jumlah Order',
                data: <?= json_encode($data_jumlah); ?>,
                backgroundColor: isDarkMode ? 'rgba(33, 150, 243, 0.2)' : 'rgba(33, 150, 243, 0.2)',
                borderColor: '#fff',
                borderWidth: 2,
                yAxisID: 'y'
            },
            {
                type: 'line',
                label: 'Total Omset (Rp)',
                data: <?= json_encode($data_total); ?>,
                backgroundColor: isDarkMode ? 'rgba(255, 235, 59, 0.3)' : 'rgba(255, 235, 59, 0.3)',
                borderColor: '#ff9800',
                borderWidth: 2,
                tension: 0, // <-- garis tajam
                fill: true,
                yAxisID: 'y1',
                pointRadius: 4,
                pointBackgroundColor: '#ff9800'
            }

        ]
    },
    options: commonOptions
});



// Chart 1 tahun
const ctx365 = document.getElementById('orderChart365').getContext('2d');
const orderChart365 = new Chart(ctx365, {
    type: 'line',
    data: {
        labels: <?= json_encode($data_bulan_365); ?>,
        datasets: [
            {
                label: 'Jumlah Order per Bulan',
                data: <?= json_encode($data_jumlah_365); ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true,
                yAxisID: 'y',
                pointRadius: 4,
                pointBackgroundColor: 'rgba(75, 192, 192, 1)'
            },
            {
                label: 'Total Order per Bulan (Rp)',
                data: <?= json_encode($data_total_365); ?>,
                backgroundColor: 'rgba(255, 206, 86, 0.2)',
                borderColor: 'rgba(255, 206, 86, 1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true,
                yAxisID: 'y1',
                pointRadius: 4,
                pointBackgroundColor: 'rgba(255, 206, 86, 1)'
            }
        ]
    },
    options: {
      ...commonOptions,
      scales: {
        ...commonOptions.scales,
        x: {
          ...commonOptions.scales.x,
          title: {
            display: true,
            text: 'Bulan',
            color: isDarkMode ? '#eee' : '#000',
          }
        }
      }
    }
});
</script>

</body>
</html>
