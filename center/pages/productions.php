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
require_once __DIR__ . '/../controllers/ProductionController.php';

$access = isset($_SESSION['admin_logged_in']['access']) ? startEnk('dek', $_SESSION['admin_logged_in']['access']) : '';

$controller = new ProductionController($koneksi);
$data = $controller->getIndexData($access);
?>



<div class="page-header">
    <h2>Laporan Produksi / Omset Item</h2>
</div>

<div class="filter-card">
    <form method="get" action="/productions" class="filter-form">
        <div class="form-group migrated-style-3">
            <label for="store_id">Pilih Toko</label>
            <select name="store_id" id="store_id" class="form-control">
                <option value="">-- Semua Toko (Global) --</option>
                <?php foreach ($data['stores'] as $store): ?>
                    <option value="<?= $store['store_id'] ?>" <?= $data['current_store_id'] == $store['store_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($store['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group migrated-style-4">
            <label for="start_date">Dari Tanggal</label>
            <input type="date" id="start_date" name="start_date" class="form-control" value="<?= htmlspecialchars($data['start_date']) ?>">
        </div>
        <div class="form-group migrated-style-4">
            <label for="end_date">Sampai Tanggal</label>
            <input type="date" id="end_date" name="end_date" class="form-control" value="<?= htmlspecialchars($data['end_date']) ?>">
        </div>
        <div>
            <button type="submit" class="btn-search">
                <i class="fas fa-search"></i> Terapkan
            </button>
        </div>
    </form>
</div>

<div class="table-container">
    <div class="table-scroll">
        <table class="table-modern">
            <thead>
                <tr>
                    <th class="migrated-style-78">No</th>
                    <th>Nama Barang / Produk</th>
                    <th>Satuan</th>
                    <th class="migrated-style-9">Total Terjual</th>
                    <th class="migrated-style-9">Total Omset</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['productions'])): ?>
                    <tr>
                        <td class="migrated-style-80" colspan="5">Tidak ada data produksi ditemukan</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = 1;
                    $grandTotalOmset = 0;
                    foreach ($data['productions'] as $row): 
                        $grandTotalOmset += (float)$row['total_omset'];
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= htmlspecialchars($row['nama_barang']) ?></strong></td>
                            <td><span class="badge-pill"><?= htmlspecialchars($row['satuan']) ?></span></td>
                            <td class="migrated-style-11">
                                <?php
                                    $terjual = (float)$row['total_terjual'];
                                    echo fmod($terjual, 1) !== 0.00 ? number_format($terjual, 2, ',', '.') : number_format($terjual, 0, ',', '.');
                                ?>
                            </td>
                            <td class="migrated-style-85">
                                Rp <?= number_format($row['total_omset'], 0, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="migrated-style-86">
                        <td class="migrated-style-9" colspan="4">GRAND TOTAL OMSET</td>
                        <td class="migrated-style-87">
                            Rp <?= number_format($grandTotalOmset, 0, ',', '.') ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>