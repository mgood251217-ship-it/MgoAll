<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: /login");
    exit;
}

require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../functions/helpers.php';
require_once __DIR__ . '/../controllers/OrderController.php';

date_default_timezone_set('Asia/Jakarta');

$access = isset($_SESSION['admin_logged_in']['access']) ? startEnk('dek', $_SESSION['admin_logged_in']['access']) : '';

$startDateGet = $_GET['start_date'] ?? null;
$endDateGet = $_GET['end_date'] ?? null;

$controller = new OrderController($koneksi);
$data = $controller->getIndexData($access, $startDateGet, $endDateGet);
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }
    .page-header h2 {
        margin: 0;
        color: #0f172a;
        font-size: 1.5rem;
        font-weight: 600;
    }
    .filter-card {
        background-color: #ffffff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        margin-bottom: 24px;
    }
    .filter-form {
        display: flex;
        gap: 16px;
        align-items: flex-end;
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .form-group label {
        font-size: 0.875rem;
        font-weight: 500;
        color: #475569;
    }
    .form-control {
        padding: 10px 16px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-family: inherit;
        font-size: 0.95rem;
        color: #0f172a;
        background-color: #f8fafc;
        transition: all 0.2s;
    }
    .form-control:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        background-color: #ffffff;
    }
    .table-container {
        background-color: #ffffff;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .table-modern {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }
    .table-modern th, .table-modern td {
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
    }
    .table-modern th {
        background-color: #f8fafc;
        color: #475569;
        font-weight: 600;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .table-modern tbody tr:last-child td {
        border-bottom: none;
    }
    .table-modern tbody tr:hover {
        background-color: #f1f5f9;
    }
    .table-modern td {
        color: #0f172a;
        font-size: 0.95rem;
    }
    .badge {
        padding: 4px 12px;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .badge-primary { background-color: #dbeafe; color: #1d4ed8; }
    .badge-danger { background-color: #fee2e2; color: #b91c1c; }
    .badge-warning { background-color: #fef3c7; color: #b45309; }
    .badge-success { background-color: #dcfce3; color: #15803d; }
</style>

<div class="page-header">
    <h2>Manajemen Pesanan</h2>
</div>

<div class="filter-card">
    <form method="get" action="/orders" class="filter-form">
        <div class="form-group">
            <label for="start_date">Dari Tanggal</label>
            <input type="date" id="start_date" name="start_date" class="form-control" value="<?= htmlspecialchars($data['startDate']) ?>" onchange="this.form.submit()">
        </div>
        <div class="form-group">
            <label for="end_date">Sampai Tanggal</label>
            <input type="date" id="end_date" name="end_date" class="form-control" value="<?= htmlspecialchars($data['endDate']) ?>" onchange="this.form.submit()">
        </div>
    </form>
</div>

<div class="table-container">
    <div style="overflow-x: auto;">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Toko</th>
                    <th>Cabang</th>
                    <th>Jumlah Pesanan</th>
                    <th>Belum Bayar</th>
                    <th>DP</th>
                    <th>Lunas</th>
                    <th>Total Nominal</th>
                    <th>Pendapatan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['storesData'])): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: #64748b;">Tidak ada data ditemukan</td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($data['storesData'] as $store): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= htmlspecialchars($store['name']) ?></strong></td>
                            <td><?= htmlspecialchars($store['branch']) ?></td>
                            <td><span class="badge badge-primary"><?= $store['jumlahPesanan'] ?></span></td>
                            <td><span class="badge badge-danger"><?= $store['belumBayar'] ?></span></td>
                            <td><span class="badge badge-warning"><?= $store['dp'] ?></span></td>
                            <td><span class="badge badge-success"><?= $store['lunas'] ?></span></td>
                            <td>Rp <?= number_format($store['totalNominal'], 0, ',', '.') ?></td>
                            <td><strong>Rp <?= number_format($store['pendapatan'], 0, ',', '.') ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>