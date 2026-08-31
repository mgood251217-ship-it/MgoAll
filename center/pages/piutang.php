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
require_once __DIR__ . '/../controllers/AnalysisController.php';
require_once __DIR__ . '/../controllers/StoreController.php';

$analysisController = new AnalysisController($koneksi);
$storeController = new StoreController($koneksi);

$access = isset($_SESSION['admin_logged_in']['access']) ? startEnk('dek', $_SESSION['admin_logged_in']['access']) : '';
$stores = $storeController->getStores($access);

if (!empty($_GET['store_id'])) {
    $selectedStorePlain = startEnk('dek', $_GET['store_id']);
} elseif (!empty($stores)) {
    $selectedStorePlain = $stores[0]['store_id'];
} else {
    $selectedStorePlain = '';
}

$selectedStore = !empty($selectedStorePlain) ? startEnk('enk', $selectedStorePlain) : '';

if (empty($_GET['store_id']) && !empty($selectedStore)) {
    $_GET['store_id'] = $selectedStore;
}

$piutangData = $analysisController->piutang($access);
$piutangList = $piutangData['data'] ?? [];
$totalPiutang = $piutangData['total'] ?? 0;
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
    .top-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .info-box {
        background-color: #ffffff;
        border-radius: 12px;
        padding: 16px 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        border: 1px solid #f1f5f9;
        border-left: 4px solid #3b82f6;
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-bottom: 0;
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
        width: 100%;
        max-width: 300px;
        box-sizing: border-box;
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
        margin-bottom: 32px;
    }
    .table-header-title {
        padding: 20px;
        background-color: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        margin: 0;
        font-size: 1.15rem;
        font-weight: 600;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 10px;
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
        background-color: #ffffff;
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
        background-color: #f8fafc;
    }
    .table-modern td {
        color: #0f172a;
        font-size: 0.95rem;
    }
    .badge-info {
        background-color: #e0f2fe;
        color: #0369a1;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-block;
    }
    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 8px;
        border: none;
        transition: all 0.2s ease;
        color: #ffffff;
        cursor: pointer;
        text-decoration: none;
    }
    .btn-action:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
</style>

<div class="page-header">
    <h2>Daftar Piutang Customer</h2>
</div>

<div class="filter-card">
    <form method="get" action="/piutang">
        <div class="form-group">
            <label for="store_id">Pilih Toko</label>
            <select name="store_id" id="store_id" class="form-control" onchange="this.form.submit()">
                <?php foreach ($stores as $store): ?>
                    <?php $encrypted_store_id = startEnk('enk', $store['store_id']); ?>
                    <option value="<?= $encrypted_store_id ?>" <?= ((string)$store['store_id'] === (string)$selectedStorePlain) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($store['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<div class="top-info-grid">
    <div class="info-box" style="border-left-color: #ef4444;">
        <span style="font-size: 0.8rem; color: #64748b; display: block; margin-bottom: 4px;">Total Piutang Berjalan</span>
        <strong style="font-size: 1.5rem; color: #ef4444;">Rp <?= number_format($totalPiutang, 0, ',', '.') ?></strong>
    </div>
</div>

<div class="table-container">
    <h3 class="table-header-title">
        <i class="fas fa-file-invoice-dollar" style="color: #ef4444;"></i> Rincian Hutang Customer
    </h3>
    <div style="overflow-x: auto;">
        <table class="table-modern">
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Tanggal</th>
                    <th>Nomorator</th>
                    <th>Nama Customer</th>
                    <th>Total Order</th>
                    <th>Kekurangan (Hutang)</th>
                    <th>Kasir</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($piutangList)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: #64748b; padding: 40px 0;">
                            Tidak ada data piutang / hutang customer saat ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = 1;
                    foreach ($piutangList as $row): 
                        $encOrderId = startEnk('enk', $row['order_id']);
                        $encStoreId = startEnk('enk', $row['store_id']);
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= date('d M Y, H:i', strtotime($row['date'])) ?></td>
                            <td><strong><?= htmlspecialchars($row['nomorator']) ?></strong></td>
                            <td><?= htmlspecialchars(title_case($row['nama'])) ?></td>
                            <td>Rp <?= number_format($row['total'], 0, ',', '.') ?></td>
                            <td style="color: #ef4444; font-weight: 600;">Rp <?= number_format($row['hutang'], 0, ',', '.') ?></td>
                            <td>
                                <div class="badge-info"><?= htmlspecialchars($row['op_initial'] ?: '-') ?></div>
                            </td>
                            <td style="text-align: right;">
                                <a href="/order?store_id=<?= urlencode($selectedStore) ?>&id=<?= urlencode($encOrderId) ?>" class="btn-action" style="background-color: #3b82f6;" title="Buka Order">
                                    <i class="fas fa-folder-open"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>