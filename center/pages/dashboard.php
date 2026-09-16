<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: /login");
    exit;
}

require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../controllers/DashboardController.php';

$controller = new DashboardController($koneksi);
$data = $controller->getIndexData();

$locationsJS = json_encode($data['locations']);
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>



<div class="dashboard-header">
    <h2>Dashboard Overview</h2>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-details">
            <h5>Total Cabang</h5>
            <h3><?= htmlspecialchars($data['totalCabang']) ?></h3>
        </div>
        <div class="stat-icon icon-blue">
            <i class="fas fa-store"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-details">
            <h5>Total User</h5>
            <h3><?= htmlspecialchars($data['totalUsers']) ?></h3>
        </div>
        <div class="stat-icon icon-green">
            <i class="fas fa-users"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-details">
            <h5>Total Order</h5>
            <h3><?= htmlspecialchars($data['totalOrders']) ?></h3>
        </div>
        <div class="stat-icon icon-orange">
            <i class="fas fa-shopping-cart"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-details">
            <h5>Total Transaksi</h5>
            <h3><?= htmlspecialchars($data['totalTransaksiFormatted']) ?></h3>
        </div>
        <div class="stat-icon icon-red">
            <i class="fas fa-wallet"></i>
        </div>
    </div>
</div>

<div class="map-wrapper">
    <h4>Sebaran Lokasi</h4>
    <div id="map"></div>
</div>

<?php if (isset($_SESSION['swal_success'])): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: <?= json_encode($_SESSION['swal_success']) ?>,
        timer: 3500,
        timerProgressBar: true,
        showConfirmButton: false,
        customClass: { popup: 'rounded-4' }
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
        showConfirmButton: false,
        customClass: { popup: 'rounded-4' }
    });
</script>
<?php unset($_SESSION['swal_error']); ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const locations = <?= $locationsJS ?>;
    const firstLoc = locations.length > 0 
        ? [locations[0].latitude, locations[0].longitude] 
        : [-6.2088, 106.8456]; 

    const map = L.map('map').setView(firstLoc, 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: 'App Center'
    }).addTo(map);

    locations.forEach(loc => {
        L.marker([loc.latitude, loc.longitude])
            .addTo(map)
            .bindPopup(`<b>${loc.name}</b>`);
    });
});
</script>