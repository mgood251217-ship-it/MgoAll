<?php
session_start();
require_once '../../connect.php';
require_once BASE_PATH . '/global_functions.php';
require_once BASE_PATH . '/administrator/session.php';

// Ambil data ringkasan
$totalCabang = $koneksi->query("SELECT COUNT(*) FROM stores")->fetch_row()[0];
$totalUsers = $koneksi->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$totalOrders = $koneksi->query("SELECT COUNT(*) FROM orders")->fetch_row()[0];

// Total transaksi (jumlah dari kolom nominal di tabel payment)
$totalTransaksiQuery = $koneksi->query("SELECT SUM(nominal) FROM payment");
$totalTransaksi = $totalTransaksiQuery->fetch_row()[0] ?? 0;
$totalTransaksiFormatted = 'Rp ' . number_format($totalTransaksi, 0, ',', '.');

$result = $koneksi->query("SELECT * FROM locations");
$locations = [];

while ($row = $result->fetch_assoc()) {
  $locations[] = [
    'store_id' => $row['store_id'],
    'name' => $row['name'],
    'latitude' => (float)$row['latitude'],
    'longitude' => (float)$row['longitude']
  ];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>
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
        <h1 class="mb-4">Dashboard</h1>

        <div class="row">
          <div class="col-md-3 mb-4">
            <div class="card text-bg-primary position-relative">
              <div class="card-body">
                <h5 class="card-title">Total Cabang</h5>
                <h2><?= $totalCabang ?></h2>
                <i class="bi bi-shop card-icon"></i>
              </div>
            </div>
          </div>
          <div class="col-md-3 mb-4">
            <div class="card text-bg-success position-relative">
              <div class="card-body">
                <h5 class="card-title">Total User</h5>
                <h2><?= $totalUsers ?></h2>
                <i class="bi bi-people-fill card-icon"></i>
              </div>
            </div>
          </div>
          <div class="col-md-3 mb-4">
            <div class="card text-bg-warning position-relative">
              <div class="card-body text-light">
                <h5 class="card-title">Total Order</h5>
                <h2><?= $totalOrders ?></h2>
                <i class="bi bi-receipt-cutoff card-icon"></i>
              </div>
            </div>
          </div>
          <div class="col-md-3 mb-4">
            <div class="card text-bg-danger position-relative">
              <div class="card-body">
                <h5 class="card-title">Total Transaksi</h5>
                <h2><?= $totalTransaksiFormatted ?></h2>
                <i class="bi bi-cash-stack card-icon"></i>
              </div>
            </div>
          </div>
        </div>
        <!-- Map Container -->
        <div id="map" style="height: 400px;"></div>
      </div>
    </main>

    <?php include BASE_PATH . '/administrator/footer.php'; ?>
  </div>
</div>

<?php if (isset($_SESSION['swal_success'])): ?>
  <script>
    Swal.fire({
      icon: 'success',
      title: 'Berhasil',
      text: <?= json_encode($_SESSION['swal_success']) ?>,
      timer: 3500,
      timerProgressBar: true,
      showConfirmButton: false
    });
  </script>
  <?php unset($_SESSION['swal_success']); ?>
<?php elseif (isset($_SESSION['swal_error'])): ?>
  <script>
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: <?= json_encode($_SESSION['swal_error']) ?>,
      timer: 3500,
      timerProgressBar: true,
      showConfirmButton: false
    });
  </script>
  <?php unset($_SESSION['swal_error']); ?>
<?php endif; ?>

<?php
$locationsJS = '[' . implode(',', array_map(function ($loc) {
  return '{store_id: ' . (int)$loc['store_id'] . ', name: "' . addslashes($loc['name']) . '", latitude: ' . $loc['latitude'] . ', longitude: ' . $loc['longitude'] . '}';
}, $locations)) . ']';
?>


<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
let map;
let userMarker;
let tempMarker;

window.addEventListener('DOMContentLoaded', async () => {
  const locations = <?= $locationsJS ?>;

  const firstLoc = locations.length > 0
    ? [locations[0].latitude, locations[0].longitude]
    : [-6.2, 106.8]; // Default Jakarta

  map = L.map('map').setView(firstLoc, 13);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: 'Maxprint'
  }).addTo(map);

  // Tampilkan marker lokasi yang sudah tersimpan
  locations.forEach(loc => {
    L.marker([loc.latitude, loc.longitude])
      .addTo(map)
      .bindPopup(loc.name);
  });
});
</script>
<script>
  console.log(<?= json_encode($_SESSION); ?>);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
