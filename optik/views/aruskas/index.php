<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js"></script>

<div class="<?= $cash_embedded ? 'cash-flow' : 'page-container' ?>">
    <div class="header-action">
        <div>
            <h2 class="page-title">Arus Kas Harian & Interval</h2>
            <p class="page-subtitle">Laporan Keuangan Real Time</p>
        </div>
        <div class="action-buttons">
            <button class="btn bg-info" onclick="openModal('modalIncome')">+ Saldo Awal / Modal</button>
            <button class="btn bg-danger" onclick="openModal('modalExpenditure')">- Pengeluaran</button>
            <button type="button" class="btn bg-primary" onclick="exportArusKasToExcel()">Export Excel</button>

            <?php if (isset($cash_sync_post_url)): ?>
                <form action="<?= htmlspecialchars($cash_sync_post_url) ?>" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <?php foreach ($cash_form_fields as $field => $value): ?><input type="hidden" name="<?= htmlspecialchars($field) ?>" value="<?= htmlspecialchars((string) $value) ?>"><?php endforeach; ?>
                    <input type="hidden" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                    <input type="hidden" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                    <button type="submit" class="btn bg-warning">↻ Sync Interval</button>
                </form>
            <?php else: ?>
                <a href="<?= htmlspecialchars($sync_url) ?>" class="btn bg-warning">↻ Sync Interval</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="cash-summary-grid">

        <div class="filter-card cash-summary-card border-accent">
            <div class="cash-summary-label">Saldo Awal (Bawaan Kemarin)</div>
            <h3 class="cash-summary-amount text-accent">Rp <?= number_format($summary['awal_total'], 0, ',', '.') ?></h3>
            <div class="money-split">
                <div><span>Cash</span><span class="val-cash"><?= number_format($summary['awal_cash'], 0, ',', '.') ?></span></div>
                <div class="text-right"><span>Transfer</span><span class="val-tf"><?= number_format($summary['awal_tf'], 0, ',', '.') ?></span></div>
            </div>
        </div>

        <div class="filter-card cash-summary-card border-success">
            <div class="cash-summary-label">Masuk (Omzet + Modal)</div>
            <h3 class="cash-summary-amount text-success">+ Rp <?= number_format($summary['masuk_total'], 0, ',', '.') ?></h3>
            <div class="money-split">
                <div><span>Cash</span><span class="val-cash"><?= number_format($summary['masuk_cash'], 0, ',', '.') ?></span></div>
                <div class="text-right"><span>Transfer</span><span class="val-tf"><?= number_format($summary['masuk_tf'], 0, ',', '.') ?></span></div>
            </div>
        </div>

        <div class="filter-card cash-summary-card border-danger">
            <div class="cash-summary-label">Pengeluaran</div>
            <h3 class="cash-summary-amount text-danger">- Rp <?= number_format($summary['keluar_total'], 0, ',', '.') ?></h3>
            <div class="money-split">
                <div><span>Cash</span><span class="val-cash text-danger"><?= number_format($summary['keluar_cash'], 0, ',', '.') ?></span></div>
                <div class="text-right"><span>Transfer</span><span class="val-tf text-danger"><?= number_format($summary['keluar_tf'], 0, ',', '.') ?></span></div>
            </div>
        </div>

        <div class="filter-card cash-summary-card border-warning">
            <div class="cash-summary-label">Saldo Akhir Terkini</div>
            <h3 class="cash-summary-amount text-warning">Rp <?= number_format($summary['akhir_total'], 0, ',', '.') ?></h3>
            <div class="money-split">
                <div><span>Sisa Cash</span><span class="val-cash text-warning"><?= number_format($summary['akhir_cash'], 0, ',', '.') ?></span></div>
                <div class="text-right"><span>Sisa Transfer</span><span class="val-tf text-warning"><?= number_format($summary['akhir_tf'], 0, ',', '.') ?></span></div>
            </div>
        </div>

    </div>

    <form class="filter-card center-store-filter" method="GET" action="<?= htmlspecialchars($cash_page_url) ?>">
        <?php if ($cash_embedded): ?>
            <div class="form-group">
                <label for="cash_store">Toko</label>
                <select id="cash_store" name="store_id">
                    <?php foreach ($store_options as $store): ?><option value="<?= (int) $store['id'] ?>" <?= $store_id === (int) $store['id'] ? 'selected' : '' ?>><?= htmlspecialchars($store['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
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
                    <?php if ($cash_actions): ?><th>Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($transactions)): ?>
                <tr><td class="table-empty" colspan="<?= $cash_actions ? 6 : 5 ?>">Belum ada data pada interval tanggal ini.</td></tr>
                <?php else: ?>
                    <?php foreach($transactions as $row): ?>
                    <tr>
                        <td>
                            <span class="text-medium-dark"><?= htmlspecialchars($row['display_date']) ?></span><br>
                            <small class="text-muted"><?= htmlspecialchars($row['display_time']) ?></small>
                        </td>
                        <td>
                            <span class="text-semibold-primary"><?= htmlspecialchars($row['description']) ?></span><br>
                            <small class="text-muted"><?= htmlspecialchars($row['category']) ?></small>
                        </td>
                        <td class="text-center">
                            <span class="status-badge <?= htmlspecialchars($row['method_class']) ?>">
                                <?= htmlspecialchars($row['payment_method']) ?>
                            </span>
                        </td>
                        <td class="amount-income">
                            <?= htmlspecialchars($row['income_display']) ?>
                        </td>
                        <td class="amount-expense">
                            <?= htmlspecialchars($row['expense_display']) ?>
                        </td>
                        <?php if ($cash_actions): ?>
                            <td>
                                <?php if (!empty($row['action_url'])): ?>
                                    <div class="action-group">
                                        <a href="<?= htmlspecialchars($row['action_url']) ?>" class="btn bg-info">Kelola</a>
                                        <form action="<?= htmlspecialchars($cash_delete_post_url) ?>" method="POST" onsubmit="return confirm('Hapus permanen transaksi ini? Saldo akan dihitung ulang.')">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <?php foreach ($cash_form_fields as $field => $value): ?><input type="hidden" name="<?= htmlspecialchars($field) ?>" value="<?= htmlspecialchars((string) $value) ?>"><?php endforeach; ?>
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="source" value="<?= htmlspecialchars($row['source']) ?>">
                                            <button type="submit" class="btn bg-danger">Hapus</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">Saldo bawaan</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
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
        <form action="<?= htmlspecialchars($cash_post_url) ?>" method="POST">
            <?php if ($cash_embedded): ?><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>"><?php endif; ?>
            <?php foreach ($cash_form_fields as $field => $value): ?><input type="hidden" name="<?= htmlspecialchars($field) ?>" value="<?= htmlspecialchars((string) $value) ?>"><?php endforeach; ?>
            <input type="hidden" name="source" value="income">
            <input type="hidden" name="action" value="add_income">
            <div class="form-group mb-15"><label>Tanggal:</label><input type="date" name="date" value="<?= htmlspecialchars($today) ?>" required></div>
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
        <form action="<?= htmlspecialchars($cash_post_url) ?>" method="POST">
            <?php if ($cash_embedded): ?><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>"><?php endif; ?>
            <?php foreach ($cash_form_fields as $field => $value): ?><input type="hidden" name="<?= htmlspecialchars($field) ?>" value="<?= htmlspecialchars((string) $value) ?>"><?php endforeach; ?>
            <input type="hidden" name="source" value="expenditure">
            <input type="hidden" name="action" value="add_expenditure">
            <div class="form-group mb-15"><label>Tanggal:</label><input type="date" name="date" value="<?= htmlspecialchars($today) ?>" required></div>
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
        const transactions = <?= json_encode($transactions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
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
