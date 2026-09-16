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

date_default_timezone_set('Asia/Jakarta');

$access = isset($_SESSION['admin_logged_in']['access']) ? startEnk('dek', $_SESSION['admin_logged_in']['access']) : '';

$startDateGet = $_GET['start_date'] ?? null;
$endDateGet = $_GET['end_date'] ?? null;

$controller = new AnalysisController($koneksi);
$data = $controller->getIndexData($access, $startDateGet, $endDateGet);
?>



<div class="page-header">
    <h2>Manajemen Pesanan</h2>
</div>

<div class="filter-card">
    <form method="get" action="/analysis" class="filter-form">
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
    <div class="table-scroll">
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
                        <td class="migrated-style-2" colspan="9">Tidak ada data ditemukan</td>
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