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

<style>
    .aruskas-container { padding: 30px; width: 100%; overflow-y: auto; }
    .header-action { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;}
    
    .btn-primary { background: #10b981; color: white; padding: 10px 20px; border-radius: 12px; font-weight: 600; font-size: 14px; border: none; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px;}
    .btn-primary:hover { background: #059669; }
    .btn-danger { background: #ef4444; color: white; padding: 10px 20px; border-radius: 12px; font-weight: 600; font-size: 14px; border: none; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px;}
    .btn-danger:hover { background: #dc2626; }
    
    .filter-card { background: white; padding: 20px; border-radius: 16px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); display: flex; gap: 15px; align-items: flex-end; }
    .input-group { display: flex; flex-direction: column; gap: 5px; }
    .input-group label { font-size: 13px; font-weight: 600; color: #475569; }
    .input-group input, .input-select { padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none; }
    
    .btn-filter { background: #f1f5f9; color: #475569; padding: 10px 20px; border-radius: 8px; font-weight: 600; border: 1px solid #e2e8f0; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; height: 42px; box-sizing: border-box; }
    .btn-filter:hover { background: #e2e8f0; }

    .table-container { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
    .table-modern { width: 100%; border-collapse: collapse; }
    .table-modern th { background: #f8fafc; color: #475569; padding: 15px; text-align: left; font-size: 13px; text-transform: uppercase; font-weight: 600; border-bottom: 2px solid #e2e8f0; }
    .table-modern td { padding: 15px; border-bottom: 1px solid #f1f5f9; color: #333; font-size: 14px; vertical-align: middle; }
    
    .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: none; justify-content: center; align-items: center; }
    .modal-box { background: #ffffff; width: 450px; padding: 30px; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }

    .money-split { display: flex; justify-content: space-between; border-top: 1px dashed #e2e8f0; padding-top: 8px; margin-top: 8px; width: 100%; font-size: 12px; color: #64748b; }
    .money-split div { display: flex; flex-direction: column; }
    .val-cash { color: #10b981; font-weight: 700; font-size: 14px; }
    .val-tf { color: #0ea5e9; font-weight: 700; font-size: 14px; }

    @media (max-width: 768px) {
        .aruskas-container { padding: 15px; }
        .header-action { flex-direction: column; align-items: flex-start; gap: 15px; }
        .filter-card { flex-direction: column; align-items: stretch; }
        .table-container { overflow-x: auto; padding: 10px; }
        .table-modern th, .table-modern td { white-space: nowrap; }
    }
</style>

<div class="aruskas-container">
    <div class="header-action">
        <div>
            <h2 style="color: #1e293b; margin-bottom: 5px;">Arus Kas Harian & Interval</h2>
            <p style="color: #64748b; font-size: 14px;">Laporan Keuangan Real Time</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button class="btn-primary" style="background:#0ea5e9;" onclick="openModal('modalIncome')">+ Saldo Awal / Modal</button>
            <button class="btn-danger" onclick="openModal('modalExpenditure')">- Pengeluaran</button>
            <button type="button" class="btn-primary" onclick="exportArusKasToExcel()">Export Excel</button>
            
            <a href="/aruskas?action=sync&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn-filter" style="color:#b45309; border-color:#fde68a; background:#fef3c7;">↻ Sync Interval</a>
        </div>
    </div>

    <div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
        
        <div class="filter-card" style="flex: 1; min-width: 220px; border-top: 4px solid #8b5cf6; flex-direction: column; align-items: flex-start; margin-bottom: 0;">
            <div style="color: #64748b; font-size: 11px; font-weight: bold; text-transform: uppercase;">Saldo Awal (Bawaan Kemarin)</div>
            <h3 style="margin: 5px 0 0 0; color: #8b5cf6; font-size: 18px;">Rp <?= number_format($summary['awal_cash'] + $summary['awal_tf'], 0, ',', '.') ?></h3>
            <div class="money-split">
                <div><span>Cash</span><span class="val-cash"><?= number_format($summary['awal_cash'], 0, ',', '.') ?></span></div>
                <div style="text-align: right;"><span>Transfer</span><span class="val-tf"><?= number_format($summary['awal_tf'], 0, ',', '.') ?></span></div>
            </div>
        </div>

        <div class="filter-card" style="flex: 1; min-width: 220px; border-top: 4px solid #10b981; flex-direction: column; align-items: flex-start; margin-bottom: 0;">
            <div style="color: #64748b; font-size: 11px; font-weight: bold; text-transform: uppercase;">Masuk (Omzet + Modal)</div>
            <h3 style="margin: 5px 0 0 0; color: #10b981; font-size: 18px;">+ Rp <?= number_format($summary['omzet_cash'] + $summary['masuk_lain_cash'] + $summary['omzet_tf'] + $summary['masuk_lain_tf'], 0, ',', '.') ?></h3>
            <div class="money-split">
                <div><span>Cash</span><span class="val-cash"><?= number_format($summary['omzet_cash'] + $summary['masuk_lain_cash'], 0, ',', '.') ?></span></div>
                <div style="text-align: right;"><span>Transfer</span><span class="val-tf"><?= number_format($summary['omzet_tf'] + $summary['masuk_lain_tf'], 0, ',', '.') ?></span></div>
            </div>
        </div>
        
        <div class="filter-card" style="flex: 1; min-width: 220px; border-top: 4px solid #ef4444; flex-direction: column; align-items: flex-start; margin-bottom: 0;">
            <div style="color: #64748b; font-size: 11px; font-weight: bold; text-transform: uppercase;">Pengeluaran</div>
            <h3 style="margin: 5px 0 0 0; color: #ef4444; font-size: 18px;">- Rp <?= number_format($summary['keluar_cash'] + $summary['keluar_tf'], 0, ',', '.') ?></h3>
            <div class="money-split">
                <div><span>Cash</span><span class="val-cash" style="color:#ef4444;"><?= number_format($summary['keluar_cash'], 0, ',', '.') ?></span></div>
                <div style="text-align: right;"><span>Transfer</span><span class="val-tf" style="color:#ef4444;"><?= number_format($summary['keluar_tf'], 0, ',', '.') ?></span></div>
            </div>
        </div>

        <div class="filter-card" style="flex: 1; min-width: 220px; border-top: 4px solid #f59e0b; flex-direction: column; align-items: flex-start; margin-bottom: 0;">
            <div style="color: #64748b; font-size: 11px; font-weight: bold; text-transform: uppercase;">Saldo Akhir Terkini</div>
            <h3 style="margin: 5px 0 0 0; color: #f59e0b; font-size: 18px;">Rp <?= number_format($summary['akhir_cash'] + $summary['akhir_tf'], 0, ',', '.') ?></h3>
            <div class="money-split">
                <div><span>Sisa Cash</span><span class="val-cash" style="color:#f59e0b;"><?= number_format($summary['akhir_cash'], 0, ',', '.') ?></span></div>
                <div style="text-align: right;"><span>Sisa Transfer</span><span class="val-tf" style="color:#f59e0b;"><?= number_format($summary['akhir_tf'], 0, ',', '.') ?></span></div>
            </div>
        </div>
        
    </div>

    <form class="filter-card" method="GET" action="/aruskas">
        <div class="input-group">
            <label>Dari Tanggal</label><input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
        </div>
        <div class="input-group">
            <label>Sampai Tanggal</label><input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
        </div>
        <div class="input-group" style="flex: 1;">
            <label>Jenis Transaksi Manual</label>
            <select name="type" class="input-select">
                <option value="">Semua Transaksi (Termasuk Penjualan)</option>
                <option value="in" <?= ($type == 'in') ? 'selected' : '' ?>>Uang Masuk Saja</option>
                <option value="out" <?= ($type == 'out') ? 'selected' : '' ?>>Uang Keluar Saja</option>
            </select>
        </div>
        <button type="submit" class="btn-filter" style="background:#2b6cb0; color:white; border:none;">Tampilkan</button>
    </form>

    <div class="table-container">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>Waktu Transaksi</th>
                    <th>Keterangan</th>
                    <th style="text-align: center;">Metode</th>
                    <th style="text-align: right;">Masuk (+)</th>
                    <th style="text-align: right;">Keluar (-)</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($transactions)): ?>
                <tr><td colspan="5" style="text-align: center; padding: 30px; color:#64748b;">Belum ada data pada interval tanggal ini.</td></tr>
                <?php else: ?>
                    <?php foreach($transactions as $row): ?>
                    <tr>
                        <td>
                            <span style="font-weight: 500; color: #1e293b;"><?= date('d M Y', strtotime($row['date'])) ?></span><br>
                            <small style="color: #64748b;"><?= date('H:i', strtotime($row['created_at'] ?? '00:00:00')) ?></small>
                        </td>
                        <td>
                            <span style="font-weight: 600; color: #2b6cb0;"><?= htmlspecialchars($row['description']) ?></span><br>
                            <small style="color: #64748b;"><?= htmlspecialchars($row['category']) ?></small>
                        </td>
                        <td style="text-align: center;">
                            <?php $bg = strtoupper($row['payment_method']) == 'CASH' ? 'background:#dcfce7; color:#166534;' : 'background:#e0f2fe; color:#0369a1;'; ?>
                            <span style="<?= $bg ?> padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;">
                                <?= htmlspecialchars($row['payment_method']) ?>
                            </span>
                        </td>
                        <td style="text-align: right; color: #16a34a; font-weight: 600;">
                            <?= $row['type'] == 'in' ? 'Rp ' . number_format($row['amount'], 0, ',', '.') : '-' ?>
                        </td>
                        <td style="text-align: right; color: #ef4444; font-weight: 600;">
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
        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
            <h3 style="margin:0; color:#0ea5e9;">Input Saldo / Tambahan</h3>
            <button type="button" onclick="closeModal('modalIncome')" style="border:none; background:none; font-size:18px; cursor:pointer;">✕</button>
        </div>
        <form action="/aruskas" method="POST">
            <input type="hidden" name="action" value="add_income">
            <div class="input-group" style="margin-bottom: 15px;"><label>Tanggal:</label><input type="date" name="date" value="<?= date('Y-m-d') ?>" required></div>
            <div class="input-group" style="margin-bottom: 15px;"><label>Keterangan:</label><input type="text" name="description" placeholder="Contoh: Tambahan Uang " required></div>
            <div class="input-group" style="margin-bottom: 15px;"><label>Jumlah Saldo (Rp):</label><input type="number" name="amount" required style="font-weight:bold;"></div>
            <div class="input-group" style="margin-bottom: 25px;">
                <label>Penyimpanan:</label>
                <select name="payment_method" class="input-select" required>
                    <option value="Cash">Cash (Uang Laci)</option>
                    <option value="Transfer">Transfer / Bank</option>
                </select>
            </div>
            <button type="submit" class="btn-primary" style="width:100%; background:#0ea5e9; justify-content:center;">Simpan Saldo</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalExpenditure">
    <div class="modal-box">
        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
            <h3 style="margin:0; color:#ef4444;">Catat Pengeluaran</h3>
            <button type="button" onclick="closeModal('modalExpenditure')" style="border:none; background:none; font-size:18px; cursor:pointer;">✕</button>
        </div>
        <form action="/aruskas" method="POST">
            <input type="hidden" name="action" value="add_expenditure">
            <div class="input-group" style="margin-bottom: 15px;"><label>Tanggal:</label><input type="date" name="date" value="<?= date('Y-m-d') ?>" required></div>
            <div class="input-group" style="margin-bottom: 15px;"><label>Keterangan:</label><input type="text" name="description" placeholder="Contoh: Bayar Listrik, Galon, dll" required></div>
            <div class="input-group" style="margin-bottom: 15px;"><label>Jumlah (Rp):</label><input type="number" name="amount" required style="font-weight:bold;"></div>
            <div class="input-group" style="margin-bottom: 25px;">
                <label>Ambil Uang Dari:</label>
                <select name="payment_method" class="input-select" required>
                    <option value="Cash">Cash (Potong Uang Laci)</option>
                    <option value="Transfer">Transfer (Potong Saldo Bank)</option>
                </select>
            </div>
            <button type="submit" class="btn-danger" style="width:100%; justify-content:center;">Simpan Pengeluaran</button>
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