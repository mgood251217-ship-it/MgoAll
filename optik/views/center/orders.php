<div class="header-action">
    <div>
        <h1 class="page-title">Order Seluruh Toko</h1>
        <p class="page-subtitle"><?= number_format($total_orders, 0, ',', '.') ?> order ditemukan.</p>
    </div>
</div>
<form action="<?= htmlspecialchars($center_base) ?>/orders" method="GET" class="filter-card center-store-filter">
    <div class="form-group">
        <label for="order_store">Toko</label>
        <select id="order_store" name="store_id">
            <option value="0">Semua toko</option>
            <?php foreach ($store_options as $store): ?>
                <option value="<?= (int) $store['id'] ?>" <?= $store_id === (int) $store['id'] ? 'selected' : '' ?>><?= htmlspecialchars($store['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="order_start">Dari tanggal</label>
        <input id="order_start" type="date" name="start" value="<?= htmlspecialchars($start_date) ?>" required>
    </div>
    <div class="form-group">
        <label for="order_end">Sampai tanggal</label>
        <input id="order_end" type="date" name="end" value="<?= htmlspecialchars($end_date) ?>" required>
    </div>
    <div class="form-group flex-1">
        <label for="order_search">Pencarian</label>
        <input id="order_search" type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Invoice, pelanggan, atau telepon">
    </div>
    <button type="submit" class="btn bg-primary">Cari</button>
</form>
<div class="table-container">
    <table class="table-modern center-store-table">
        <thead><tr><th>Invoice / Toko</th><th>Pelanggan</th><th>Total</th><th>Dibayar</th><th>Status</th><th>Tanggal</th><th>Aksi</th></tr></thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><span class="font-semibold"><?= htmlspecialchars($order['inv_no']) ?></span><br><small class="text-muted"><?= htmlspecialchars($order['store_name'] ?? '-') ?></small></td>
                    <td><?= htmlspecialchars($order['customer_name']) ?><br><small class="text-muted"><?= htmlspecialchars($order['nomor'] ?? '') ?></small></td>
                    <td>Rp <?= number_format($order['total'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format($order['total_dibayar'], 0, ',', '.') ?></td>
                    <td><span class="status-badge <?= htmlspecialchars($order['badge_class']) ?>"><?= htmlspecialchars($order['status_text']) ?></span></td>
                    <td><?= htmlspecialchars($order['display_date']) ?></td>
                    <td><a href="<?= htmlspecialchars($center_base) ?>/order?id=<?= (int) $order['id'] ?>" class="btn bg-primary">Kelola</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($orders)): ?><tr><td colspan="7" class="table-empty">Tidak ada order yang sesuai.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php if ($total_pages > 1): ?>
        <nav class="center-pagination" aria-label="Halaman order">
            <span class="text-muted">Halaman <?= $current_page ?> dari <?= $total_pages ?></span>
            <?php foreach ($page_links as $link): ?>
                <a href="<?= htmlspecialchars($link['url']) ?>" class="page-link <?= $link['active'] ? 'active' : '' ?>" <?= $link['active'] ? 'aria-current="page"' : '' ?>><?= $link['number'] ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
</div>
