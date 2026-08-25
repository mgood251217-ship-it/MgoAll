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
        flex-wrap: wrap;
        gap: 16px;
        align-items: flex-end;
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
        flex: 1;
        min-width: 200px;
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
        box-sizing: border-box;
    }
    .form-control:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        background-color: #ffffff;
    }
    .btn-search {
        background-color: #3b82f6;
        color: #ffffff;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 500;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.2s;
        height: 42px;
    }
    .btn-search:hover {
        background-color: #2563eb;
    }
    .table-container {
        background-color: #ffffff;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        overflow: hidden;
        margin-bottom: 24px;
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
    .badge-pill {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
        background-color: #f1f5f9;
        color: #475569;
    }
</style>

<div class="page-header">
    <h2>Laporan Produksi / Omset Item</h2>
</div>

<div class="filter-card">
    <form method="get" action="/productions" class="filter-form">
        <div class="form-group" style="flex: 2;">
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
        <div class="form-group" style="flex: 1;">
            <label for="start_date">Dari Tanggal</label>
            <input type="date" id="start_date" name="start_date" class="form-control" value="<?= htmlspecialchars($data['start_date']) ?>">
        </div>
        <div class="form-group" style="flex: 1;">
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
    <div style="overflow-x: auto;">
        <table class="table-modern">
            <thead>
                <tr>
                    <th style="width: 60px;">No</th>
                    <th>Nama Barang / Produk</th>
                    <th>Satuan</th>
                    <th style="text-align: right;">Total Terjual</th>
                    <th style="text-align: right;">Total Omset</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['productions'])): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #64748b; padding: 32px 0;">Tidak ada data produksi ditemukan</td>
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
                            <td style="text-align: right; font-weight: 500;">
                                <?php
                                    $terjual = (float)$row['total_terjual'];
                                    echo fmod($terjual, 1) !== 0.00 ? number_format($terjual, 2, ',', '.') : number_format($terjual, 0, ',', '.');
                                ?>
                            </td>
                            <td style="text-align: right; font-weight: 600; color: #10b981;">
                                Rp <?= number_format($row['total_omset'], 0, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="background-color: #f8fafc; font-weight: 700;">
                        <td colspan="4" style="text-align: right;">GRAND TOTAL OMSET</td>
                        <td style="text-align: right; color: #3b82f6; font-size: 1.1rem;">
                            Rp <?= number_format($grandTotalOmset, 0, ',', '.') ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>