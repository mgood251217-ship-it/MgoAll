<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: /center/login");
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

<style>
    .dashboard-header {
        margin-bottom: 24px;
    }
    .dashboard-header h2 {
        margin: 0;
        color: #0f172a;
        font-size: 1.5rem;
        font-weight: 600;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 24px;
        margin-bottom: 24px;
    }
    .stat-card {
        background-color: #ffffff;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }
    .stat-details h5 {
        margin: 0 0 8px 0;
        color: #64748b;
        font-size: 0.875rem;
        font-weight: 500;
    }
    .stat-details h3 {
        margin: 0;
        color: #0f172a;
        font-size: 1.5rem;
        font-weight: 700;
    }
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    .icon-blue { background-color: #eff6ff; color: #3b82f6; }
    .icon-green { background-color: #f0fdf4; color: #22c55e; }
    .icon-orange { background-color: #fff7ed; color: #f97316; }
    .icon-red { background-color: #fef2f2; color: #ef4444; }
    
    .map-wrapper {
        background-color: #ffffff;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .map-wrapper h4 {
        margin: 0 0 16px 0;
        color: #0f172a;
        font-size: 1.1rem;
        font-weight: 600;
    }
    #map {
        height: 400px;
        border-radius: 8px;
        z-index: 1;
    }
</style>

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