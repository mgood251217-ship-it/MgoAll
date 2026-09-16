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
    <div class="info-box migrated-style-30">
        <span class="migrated-style-28">Total Piutang Berjalan</span>
        <strong class="migrated-style-81">Rp <?= number_format($totalPiutang, 0, ',', '.') ?></strong>
    </div>
</div>

<div class="table-container">
    <h3 class="table-header-title">
        <i class="fas fa-file-invoice-dollar migrated-style-8"></i> Rincian Hutang Customer
    </h3>
    <div class="table-scroll">
        <table class="table-modern">
            <thead>
                <tr>
                    <th class="migrated-style-82">No</th>
                    <th>Tanggal</th>
                    <th>Nomorator</th>
                    <th>Nama Customer</th>
                    <th>Total Order</th>
                    <th>Kekurangan (Hutang)</th>
                    <th>Kasir</th>
                    <th class="migrated-style-9">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($piutangList)): ?>
                    <tr>
                        <td class="migrated-style-83" colspan="8">
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
                            <td class="migrated-style-84">Rp <?= number_format($row['hutang'], 0, ',', '.') ?></td>
                            <td>
                                <div class="badge-info"><?= htmlspecialchars($row['op_initial'] ?: '-') ?></div>
                            </td>
                            <td class="migrated-style-9">
                                <a href="/order?store_id=<?= urlencode($selectedStore) ?>&id=<?= urlencode($encOrderId) ?>" class="btn-action btn-open" title="Buka Order">
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