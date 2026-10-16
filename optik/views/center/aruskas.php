<div class="header-action">
    <div>
        <h1 class="page-title">Arus Kas Pusat</h1>
        <p class="page-subtitle">Pemasukan, pembayaran order, dan pengeluaran seluruh toko.</p>
    </div>
    <div class="action-buttons">
        <a href="<?= htmlspecialchars($center_base) ?>/cash-entry?source=income&amp;store_id=<?= $store_id ?>" class="btn bg-success">+ Pemasukan</a>
        <a href="<?= htmlspecialchars($center_base) ?>/cash-entry?source=expenditure&amp;store_id=<?= $store_id ?>" class="btn bg-danger">+ Pengeluaran</a>
    </div>
</div>
<form action="<?= htmlspecialchars($center_base) ?>/aruskas" method="GET" class="filter-card center-store-filter">
    <div class="form-group flex-1">
        <label for="cash_store">Toko</label>
        <select name="store_id" id="cash_store">
            <option value="0">Semua toko</option>
            <?php foreach ($store_options as $store): ?>
                <option value="<?= (int) $store['id'] ?>" <?= $store_id === (int) $store['id'] ? 'selected' : '' ?>><?= htmlspecialchars($store['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group"><label for="cash_start">Dari tanggal</label><input id="cash_start" type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" required></div>
    <div class="form-group"><label for="cash_end">Sampai tanggal</label><input id="cash_end" type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" required></div>
    <div class="form-group">
        <label for="cash_type">Jenis transaksi</label>
        <select id="cash_type" name="type"><option value="">Semua transaksi</option><option value="in" <?= $type === 'in' ? 'selected' : '' ?>>Uang masuk</option><option value="out" <?= $type === 'out' ? 'selected' : '' ?>>Uang keluar</option></select>
    </div>
    <button type="submit" class="btn bg-primary">Tampilkan</button>
</form>
<div class="cash-summary-grid">
    <?php foreach ($cards as $card): ?>
        <div class="filter-card cash-summary-card border-<?= htmlspecialchars($card['color']) ?>">
            <div class="cash-summary-label"><?= htmlspecialchars($card['label']) ?></div>
            <h2 class="cash-summary-amount text-<?= htmlspecialchars($card['color']) ?>">Rp <?= number_format($card['total'], 0, ',', '.') ?></h2>
            <div class="money-split">
                <div><span>Cash</span><span class="val-cash"><?= number_format($card['cash'], 0, ',', '.') ?></span></div>
                <div class="text-right"><span>Transfer</span><span class="val-tf"><?= number_format($card['transfer'], 0, ',', '.') ?></span></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<section class="table-container">
    <div class="header-action">
        <div><h2 class="page-title">Riwayat Arus Kas</h2><p class="page-subtitle"><?= number_format($total_transactions, 0, ',', '.') ?> transaksi ditemukan. Ringkasan saldo mencakup semua jenis transaksi pada periode ini.</p></div>
        <?php if ($store_id > 0): ?>
            <form action="<?= htmlspecialchars($center_base) ?>/sync-aruskas" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="store_id" value="<?= $store_id ?>">
                <input type="hidden" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                <input type="hidden" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                <button type="submit" class="btn bg-warning">Sinkronkan Ringkasan Toko</button>
            </form>
        <?php endif; ?>
    </div>
    <table class="table-modern center-store-table">
        <thead><tr><th>Tanggal / Toko</th><th>Keterangan</th><th>Metode</th><th>Masuk</th><th>Keluar</th><th>Aksi</th></tr></thead>
        <tbody>
            <?php foreach ($transactions as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['display_date']) ?><br><small class="text-muted"><?= htmlspecialchars($row['created_at']) ?> · <?= htmlspecialchars($row['store_name'] ?? '-') ?></small></td>
                    <td><span class="font-semibold"><?= htmlspecialchars($row['description']) ?></span><br><small class="text-muted"><?= htmlspecialchars($row['category']) ?></small></td>
                    <td><span class="status-badge <?= htmlspecialchars($row['method_class']) ?>"><?= htmlspecialchars($row['payment_method']) ?></span></td>
                    <td class="amount-income"><?= $row['type'] === 'in' ? 'Rp ' . number_format($row['amount'], 0, ',', '.') : '-' ?></td>
                    <td class="amount-expense"><?= $row['type'] === 'out' ? 'Rp ' . number_format($row['amount'], 0, ',', '.') : '-' ?></td>
                    <td><a href="<?= htmlspecialchars($row['action_url']) ?>" class="btn bg-info"><?= $row['source'] === 'payment' ? 'Kelola Order' : 'Edit / Hapus' ?></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($transactions)): ?><tr><td colspan="6" class="table-empty">Belum ada transaksi pada periode ini.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php if ($total_pages > 1): ?>
        <nav class="center-pagination" aria-label="Halaman arus kas">
            <span class="text-muted">Halaman <?= $current_page ?> dari <?= $total_pages ?></span>
            <?php foreach ($page_links as $link): ?>
                <a href="<?= htmlspecialchars($link['url']) ?>" class="page-link <?= $link['active'] ? 'active' : '' ?>" <?= $link['active'] ? 'aria-current="page"' : '' ?>><?= $link['number'] ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
</section>
