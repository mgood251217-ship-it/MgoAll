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
require_once __DIR__ . '/../controllers/FinanceController.php';

$access = isset($_SESSION['admin_logged_in']['access']) ? startEnk('dek', $_SESSION['admin_logged_in']['access']) : '';

$startDateGet = $_GET['start_date'] ?? null;
$endDateGet = $_GET['end_date'] ?? null;

$controller = new FinanceController($koneksi);
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
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }
    .stat-card {
        background-color: #ffffff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .stat-card h5 {
        margin: 0 0 8px 0;
        color: #64748b;
        font-size: 0.85rem;
        font-weight: 500;
    }
    .stat-card h3 {
        margin: 0;
        color: #0f172a;
        font-size: 1.25rem;
        font-weight: 700;
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
        vertical-align: middle;
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
</style>

<div class="page-header">
    <h2>Keuangan (Finance)</h2>
</div>

<div class="filter-card">
    <form method="get" action="/finance" class="filter-form">
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

<div class="stats-grid">
    <div class="stat-card">
        <h5>Total Omset Offline</h5>
        <h3>Rp <?= number_format($data['totals']['offline'], 0, ',', '.') ?></h3>
    </div>
    <div class="stat-card">
        <h5>Total Omset Online</h5>
        <h3>Rp <?= number_format($data['totals']['online'], 0, ',', '.') ?></h3>
    </div>
    <div class="stat-card">
        <h5>Total Saldo</h5>
        <h3>Rp <?= number_format($data['totals']['saldo'], 0, ',', '.') ?></h3>
    </div>
    <div class="stat-card">
        <h5>Total Transfer</h5>
        <h3>Rp <?= number_format($data['totals']['transfer'], 0, ',', '.') ?></h3>
    </div>
    <div class="stat-card">
        <h5>Total Expenditure</h5>
        <h3>Rp <?= number_format($data['totals']['expenditure'], 0, ',', '.') ?></h3>
    </div>
</div>

<div class="table-container">
    <div style="overflow-x: auto;">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Nama Toko</th>
                    <th>Cabang</th>
                    <th>Omset Offline</th>
                    <th>Omset Online</th>
                    <th>Saldo</th>
                    <th>Transfer</th>
                    <th>Expenditure</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['finances'])): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: #64748b;">Tidak ada data keuangan ditemukan</td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($data['finances'] as $row): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['date']) ?></td>
                            <td><strong><?= htmlspecialchars($row['store_name']) ?></strong></td>
                            <td><?= htmlspecialchars($row['branch']) ?></td>
                            <td>Rp <?= number_format($row['omset_offline'], 0, ',', '.') ?></td>
                            <td>Rp <?= number_format($row['omset_online'], 0, ',', '.') ?></td>
                            <td>Rp <?= number_format($row['saldo'], 0, ',', '.') ?></td>
                            <td>Rp <?= number_format($row['transfer'], 0, ',', '.') ?></td>
                            <td>Rp <?= number_format($row['expenditure'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>