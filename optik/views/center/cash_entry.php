<div class="header-action">
    <div><h1 class="page-title"><?= $entry['id'] > 0 ? 'Edit' : 'Tambah' ?> <?= $source === 'income' ? 'Pemasukan' : 'Pengeluaran' ?></h1><p class="page-subtitle">Catat transaksi manual dan metode penyimpanannya.</p></div>
    <a href="<?= htmlspecialchars($center_base) ?>/aruskas?store_id=<?= $store_id ?>" class="btn bg-secondary text-muted">Kembali ke Arus Kas</a>
</div>
<form action="<?= htmlspecialchars($center_base) ?>/save-cash-entry" method="POST" class="table-container mb-20">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
    <input type="hidden" name="source" value="<?= htmlspecialchars($source) ?>">
    <div class="product-form-grid">
        <div class="form-group">
            <label for="entry_store">Toko</label>
            <?php if ($entry['id'] > 0): ?><input type="hidden" name="store_id" value="<?= $store_id ?>"><?php endif; ?>
            <select id="entry_store" name="store_id" <?= $entry['id'] > 0 ? 'disabled' : 'required' ?>>
                <option value="">Pilih toko</option>
                <?php foreach ($store_options as $store): ?><option value="<?= (int) $store['id'] ?>" <?= $store_id === (int) $store['id'] ? 'selected' : '' ?>><?= htmlspecialchars($store['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label for="entry_date">Tanggal</label><input id="entry_date" type="date" name="date" value="<?= htmlspecialchars($entry['date']) ?>" required></div>
        <div class="form-group"><label for="entry_amount">Nominal (Rp)</label><input id="entry_amount" type="number" name="amount" value="<?= htmlspecialchars((string) $entry['amount']) ?>" min="0.01" step="0.01" required></div>
        <div class="form-group"><label for="entry_method">Metode</label><select id="entry_method" name="payment_method" required><option value="Cash" <?= $entry['payment_method'] === 'Cash' ? 'selected' : '' ?>>Cash</option><option value="Transfer" <?= $entry['payment_method'] === 'Transfer' ? 'selected' : '' ?>>Transfer</option></select></div>
    </div>
    <div class="form-group"><label for="entry_description">Keterangan</label><input id="entry_description" name="description" value="<?= htmlspecialchars($entry['description']) ?>" placeholder="Contoh: Tambahan modal atau biaya operasional" required></div>
    <button type="submit" class="btn bg-primary">Simpan Transaksi</button>
</form>
<?php if ($entry['id'] > 0): ?>
    <div class="table-container center-delete-order border-danger">
        <div><h2 class="page-title">Hapus Transaksi</h2><p class="page-subtitle">Transaksi ini akan dihapus permanen dan saldo akan dihitung ulang.</p></div>
        <form action="<?= htmlspecialchars($center_base) ?>/delete-cash-entry" method="POST" onsubmit="return confirm('Hapus permanen transaksi arus kas ini?')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
            <input type="hidden" name="source" value="<?= htmlspecialchars($source) ?>">
            <input type="hidden" name="store_id" value="<?= $store_id ?>">
            <button type="submit" class="btn bg-danger">Hapus Transaksi</button>
        </form>
    </div>
<?php endif; ?>
