<div class="header-action">
    <div>
        <h1 class="page-title">Dashboard Pusat</h1>
        <p class="page-subtitle">Ringkasan seluruh toko dalam satu tempat.</p>
    </div>
    <a href="<?= htmlspecialchars($center_base) ?>/stores" class="btn bg-primary">Lihat Daftar Toko</a>
</div>
<div class="stats-grid">
    <div class="stat-card border-accent">
        <div class="stat-info">
            <h4>Total Toko</h4>
            <h2><?= number_format($summary['total_stores'], 0, ',', '.') ?></h2>
            <p class="text-muted">Toko terdaftar</p>
        </div>
    </div>
    <div class="stat-card border-success">
        <div class="stat-info">
            <h4>Akun Pengguna</h4>
            <h2><?= number_format($summary['total_users'], 0, ',', '.') ?></h2>
            <p class="text-muted">Pengguna aplikasi toko</p>
        </div>
    </div>
    <div class="stat-card border-warning">
        <div class="stat-info">
            <h4>Total Produk</h4>
            <h2><?= number_format($summary['total_products'], 0, ',', '.') ?></h2>
            <p class="text-muted">Produk seluruh toko</p>
        </div>
    </div>
    <div class="stat-card border-danger">
        <div class="stat-info">
            <h4>Total Stok</h4>
            <h2><?= number_format($summary['total_stock'], 0, ',', '.') ?></h2>
            <p class="text-muted">Pcs seluruh toko</p>
        </div>
    </div>
</div>
<section class="table-container">
    <div class="header-action">
        <div>
            <h2 class="page-title">Toko Terbaru</h2>
            <p class="page-subtitle">Lima toko terakhir yang terdaftar.</p>
        </div>
        <a href="<?= htmlspecialchars($center_base) ?>/stores" class="text-info">Lihat semua toko</a>
    </div>
    <?php require __DIR__ . '/store_table.php'; ?>
</section>
