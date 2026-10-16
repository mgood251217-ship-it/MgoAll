<div class="header-action">
    <div>
        <h1 class="page-title">Daftar Toko</h1>
        <p class="page-subtitle"><?= number_format($total_stores, 0, ',', '.') ?> toko ditemukan.</p>
    </div>
</div>
<form action="<?= htmlspecialchars($center_base) ?>/stores" method="GET" class="filter-card center-store-filter">
    <div class="form-group flex-1">
        <label for="center_store_search">Cari toko</label>
        <input id="center_store_search" name="search" type="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nama atau alamat toko">
    </div>
    <button type="submit" class="btn bg-primary">Cari Toko</button>
    <?php if ($search !== ''): ?>
        <a href="<?= htmlspecialchars($center_base) ?>/stores" class="btn bg-secondary text-muted">Reset</a>
    <?php endif; ?>
</form>
<div class="table-container">
    <?php require __DIR__ . '/store_table.php'; ?>
    <?php if ($total_pages > 1): ?>
        <nav class="center-pagination" aria-label="Halaman daftar toko">
            <span class="text-muted">Halaman <?= $current_page ?> dari <?= $total_pages ?></span>
            <?php foreach ($page_links as $link): ?>
                <a href="<?= htmlspecialchars($link['url']) ?>" class="page-link <?= $link['active'] ? 'active' : '' ?>" <?= $link['active'] ? 'aria-current="page"' : '' ?>><?= $link['number'] ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
</div>
