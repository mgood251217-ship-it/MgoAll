<?php
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
global $pdo;

if (!isset($pdo) || $pdo === null) {
    if(file_exists('config/database.php')) require_once 'config/database.php';
    else die("<b>FATAL ERROR:</b> Koneksi database (\$pdo) tidak terdeteksi.");
}

require_once 'models/Finance.php';
$financeModel = new Finance($pdo);
$store_id = $_SESSION['store_id'] ?? 0;

$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date   = $_GET['end_date'] ?? date('Y-m-d');
$type       = $_GET['type'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $date = $_POST['date'] ?? date('Y-m-d'); $desc = $_POST['description'] ?? '';
    $amount = $_POST['amount'] ?? 0; $method = $_POST['payment_method'] ?? 'Cash';

    if ($_POST['action'] === 'add_income') $financeModel->addIncome($store_id, $date, $desc, $amount, $method);
    elseif ($_POST['action'] === 'add_expenditure') $financeModel->addExpenditure($store_id, $date, $desc, $amount, $method);

    echo "<script>window.location.href='/aruskas?start_date=$start_date&end_date=$end_date';</script>"; exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'sync') {
    $financeModel->syncInterval($store_id, $start_date, $end_date);
    echo "<script>window.location.href='/aruskas?start_date=$start_date&end_date=$end_date';</script>"; exit;
}

$summary = $financeModel->getSummary($store_id, $start_date, $end_date);
$transactions = $financeModel->getTransactions($store_id, $start_date, $end_date, $type);
if (!is_array($transactions)) $transactions = [];
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js"></script>

<div class="page-container">
    <div class="header-action">
        <div>
            <h2 class="page-title">Arus Kas Harian & Interval</h2>
            <p class="page-subtitle">Laporan Keuangan Real Time</p>
        </div>
        <div class="action-buttons">
            <button class="btn bg-info" onclick="openModal('modalIncome')">+ Saldo Awal / Modal</button>
            <button class="btn bg-danger" onclick="openModal('modalExpenditure')">- Pengeluaran</button>
            <button type="button" class="btn bg-primary" onclick="exportArusKasToExcel()">Export Excel</button>

            <a href="/aruskas?action=sync&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn bg-warning">↻ Sync Interval</a>
        </div>
    </div>

    <div class="cash-summary-grid">

        <div class="filter-card cash-summary-card border-accent">
            <div class="cash-summary-label">Saldo Awal (Bawaan Kemarin)</div>
            <h3 class="cash-summary-amount text-accent">Rp <?= number_format($summary['awal_cash'] + $summary['awal_tf'], 0, ',', '.') ?></h3>
            <div class="money-split">
                <div><span>Cash</span><span class="val-cash"><?= number_format($summary['awal_cash'], 0, ',', '.') ?></span></div>
                <div class="text-right"><span>Transfer</span><span class="val-tf"><?= number_format($summary['awal_tf'], 0, ',', '.') ?></span></div>
            </div>
        </div>

        <div class="filter-card cash-summary-card border-success">
            <div class="cash-summary-label">Masuk (Omzet + Modal)</div>
            <h3 class="cash-summary-amount text-success">+ Rp <?= number_format($summary['omzet_cash'] + $summary['masuk_lain_cash'] + $summary['omzet_tf'] + $summary['masuk_lain_tf'], 0, ',', '.') ?></h3>
            <div class="money-split">
                <div><span>Cash</span><span class="val-cash"><?= number_format($summary['omzet_cash'] + $summary['masuk_lain_cash'], 0, ',', '.') ?></span></div>
                <div class="text-right"><span>Transfer</span><span class="val-tf"><?= number_format($summary['omzet_tf'] + $summary['masuk_lain_tf'], 0, ',', '.') ?></span></div>
            </div>
        </div>

        <div class="filter-card cash-summary-card border-danger">
            <div class="cash-summary-label">Pengeluaran</div>
            <h3 class="cash-summary-amount text-danger">- Rp <?= number_format($summary['keluar_cash'] + $summary['keluar_tf'], 0, ',', '.') ?></h3>
            <div class="money-split">
                <div><span>Cash</span><span class="val-cash text-danger"><?= number_format($summary['keluar_cash'], 0, ',', '.') ?></span></div>
                <div class="text-right"><span>Transfer</span><span class="val-tf text-danger"><?= number_format($summary['keluar_tf'], 0, ',', '.') ?></span></div>
            </div>
        </div>

        <div class="filter-card cash-summary-card border-warning">
            <div class="cash-summary-label">Saldo Akhir Terkini</div>
            <h3 class="cash-summary-amount text-warning">Rp <?= number_format($summary['akhir_cash'] + $summary['akhir_tf'], 0, ',', '.') ?></h3>
            <div class="money-split">
                <div><span>Sisa Cash</span><span class="val-cash text-warning"><?= number_format($summary['akhir_cash'], 0, ',', '.') ?></span></div>
                <div class="text-right"><span>Sisa Transfer</span><span class="val-tf text-warning"><?= number_format($summary['akhir_tf'], 0, ',', '.') ?></span></div>
            </div>
        </div>

    </div>

    <form class="filter-card" method="GET" action="/aruskas">
        <div class="form-group">
            <label>Dari Tanggal</label><input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
        </div>
        <div class="form-group">
            <label>Sampai Tanggal</label><input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
        </div>
        <div class="form-group flex-1">
            <label>Jenis Transaksi Manual</label>
            <select name="type" class="input-select">
                <option value="">Semua Transaksi (Termasuk Penjualan)</option>
                <option value="in" <?= ($type == 'in') ? 'selected' : '' ?>>Uang Masuk Saja</option>
                <option value="out" <?= ($type == 'out') ? 'selected' : '' ?>>Uang Keluar Saja</option>
            </select>
        </div>
        <button type="submit" class="btn bg-primary">Tampilkan</button>
    </form>

    <div class="table-container">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>Waktu Transaksi</th>
                    <th>Keterangan</th>
                    <th class="text-center">Metode</th>
                    <th class="text-right">Masuk (+)</th>
                    <th class="text-right">Keluar (-)</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($transactions)): ?>
                <tr><td class="table-empty" colspan="5">Belum ada data pada interval tanggal ini.</td></tr>
                <?php else: ?>
                    <?php foreach($transactions as $row): ?>
                    <tr>
                        <td>
                            <span class="text-medium-dark"><?= date('d M Y', strtotime($row['date'])) ?></span><br>
                            <small class="text-muted"><?= date('H:i', strtotime($row['created_at'] ?? '00:00:00')) ?></small>
                        </td>
                        <td>
                            <span class="text-semibold-primary"><?= htmlspecialchars($row['description']) ?></span><br>
                            <small class="text-muted"><?= htmlspecialchars($row['category']) ?></small>
                        </td>
                        <td class="text-center">
                            <?php $method_class = strtoupper($row['payment_method']) == 'CASH' ? 'bg-success' : 'bg-info'; ?>
                            <span class="status-badge <?= $method_class ?>">
                                <?= htmlspecialchars($row['payment_method']) ?>
                            </span>
                        </td>
                        <td class="amount-income">
                            <?= $row['type'] == 'in' ? 'Rp ' . number_format($row['amount'], 0, ',', '.') : '-' ?>
                        </td>
                        <td class="amount-expense">
                            <?= $row['type'] == 'out' ? 'Rp ' . number_format($row['amount'], 0, ',', '.') : '-' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="modalIncome">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="dialog-title text-info">Input Saldo / Tambahan</h3>
            <button class="close-btn" type="button" onclick="closeModal('modalIncome')">✕</button>
        </div>
        <form action="/aruskas" method="POST">
            <input type="hidden" name="action" value="add_income">
            <div class="form-group mb-15"><label>Tanggal:</label><input type="date" name="date" value="<?= date('Y-m-d') ?>" required></div>
            <div class="form-group mb-15"><label>Keterangan:</label><input type="text" name="description" placeholder="Contoh: Tambahan Uang " required></div>
            <div class="form-group mb-15"><label>Jumlah Saldo (Rp):</label><input class="font-bold" type="number" name="amount" required></div>
            <div class="form-group mb-25">
                <label>Penyimpanan:</label>
                <select name="payment_method" class="input-select" required>
                    <option value="Cash">Cash (Uang Laci)</option>
                    <option value="Transfer">Transfer / Bank</option>
                </select>
            </div>
            <button type="submit" class="btn button-full bg-info">Simpan Saldo</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalExpenditure">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="dialog-title text-danger">Catat Pengeluaran</h3>
            <button class="close-btn" type="button" onclick="closeModal('modalExpenditure')">✕</button>
        </div>
        <form action="/aruskas" method="POST">
            <input type="hidden" name="action" value="add_expenditure">
            <div class="form-group mb-15"><label>Tanggal:</label><input type="date" name="date" value="<?= date('Y-m-d') ?>" required></div>
            <div class="form-group mb-15"><label>Keterangan:</label><input type="text" name="description" placeholder="Contoh: Bayar Listrik, Galon, dll" required></div>
            <div class="form-group mb-15"><label>Jumlah (Rp):</label><input class="font-bold" type="number" name="amount" required></div>
            <div class="form-group mb-25">
                <label>Ambil Uang Dari:</label>
                <select name="payment_method" class="input-select" required>
                    <option value="Cash">Cash (Potong Uang Laci)</option>
                    <option value="Transfer">Transfer (Potong Saldo Bank)</option>
                </select>
            </div>
            <button type="submit" class="btn bg-danger button-full">Simpan Pengeluaran</button>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    async function exportArusKasToExcel() {
        const transactions = <?= empty($transactions) ? '[]' : json_encode($transactions) ?>;
        if(transactions.length === 0) { alert("Tidak ada data tabel!"); return; }

        const workbook = new ExcelJS.Workbook();
        const sheet = workbook.addWorksheet('Detail Arus Kas', {views: [{showGridLines: false}]});

        sheet.mergeCells('A1:E1'); sheet.getCell('A1').value = 'LAPORAN ARUS KAS';
        sheet.getCell('A1').font = { size: 14, bold: true, color: { argb: 'FFFFFFFF' } };
        sheet.getCell('A1').fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF0F172A' } };
        sheet.getCell('A1').alignment = { horizontal: 'center' };
        sheet.addRow([]);

        const headerRow = sheet.addRow(['TANGGAL & JAM', 'KETERANGAN', 'METODE', 'MASUK (+)', 'KELUAR (-)']);
        headerRow.eachCell(cell => { cell.font={bold:true}; cell.alignment={horizontal:'center'}; cell.border={bottom:{style:'medium'}}; });

        let inTotal = 0; let outTotal = 0;
        transactions.forEach(row => {
            let m = row.type === 'in' ? parseFloat(row.amount) : 0;
            let k = row.type === 'out' ? parseFloat(row.amount) : 0;
            inTotal += m; outTotal += k;

            let dt = `${row.date} ${row.created_at ? row.created_at.substring(0,5) : ''}`;
            const dr = sheet.addRow([dt, row.description, row.payment_method, m||'-', k||'-']);
            if(m>0) dr.getCell(4).numFmt = 'Rp #,##0';
            if(k>0) dr.getCell(5).numFmt = 'Rp #,##0';
        });

        const totalRow = sheet.addRow(['', 'TOTAL KESELURUHAN', '', inTotal, outTotal]);
        totalRow.font = {bold:true}; totalRow.getCell(4).numFmt='Rp #,##0'; totalRow.getCell(5).numFmt='Rp #,##0';
        sheet.getColumn(2).width = 30; sheet.getColumn(4).width = 20; sheet.getColumn(5).width = 20;

        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url;
        a.download = `ArusKas.xlsx`; a.click();
    }
</script>