<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: /login'); exit; }
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../controllers/ProductHistoryController.php';
try {
    $data = (new ProductHistoryController($koneksi))->getData();
} catch (InvalidArgumentException $e) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Halaman ini hanya untuk akun dengan akses ALL.</div>';
    return;
}
$escape = function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
$pageUrl = function ($page) use ($data, $escape) { return '?' . $escape(http_build_query(['store_id' => $data['store'], 'search' => $data['search'], 'page' => $page])); };
?>
<div class="page-header"><h2>Riwayat Tambah Produk</h2><a class="btn-secondary-custom" href="/products">Kembali ke Produk</a></div>
<p>Penambahan produk melalui tombol Tambah Produk di Center. Nama dan harga mengikuti data saat ini; produk atau akun yang sudah dihapus tetap ditampilkan dengan ID.</p>
<?php if (!$data['ready']): ?>
    <div class="alert alert-warning">Jalankan <code>center/utils/product_creation_logs.sql</code> sekali untuk menyiapkan pencatatan penambahan produk.</div>
<?php else: ?>
    <div class="filter-card"><form method="get" action="/product-history" class="filter-form">
        <div class="form-group"><label for="historyStore">Toko</label><select class="form-control" name="store_id" id="historyStore">
            <option value="0">Semua toko</option>
            <?php foreach ($data['stores'] as $store): ?><option value="<?= (int)$store['store_id'] ?>" <?= (int)$store['store_id'] === $data['store'] ? 'selected' : '' ?>><?= $escape($store['name']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="form-group" style="flex:2"><label for="historySearch">Cari produk atau pembuat</label><input class="form-control" id="historySearch" type="search" name="search" value="<?= $escape($data['search']) ?>" placeholder="Nama / ID produk, nama pembuat, atau username"></div>
        <div class="form-group" style="align-self:end"><button type="submit" class="btn-primary-custom">Tampilkan</button></div>
    </form></div>
    <p><?= number_format($data['total'], 0, ',', '.') ?> penambahan produk</p>
    <div class="table-container"><div class="table-scroll"><table class="table-modern">
        <thead><tr><th>Waktu</th><th>Ditambahkan oleh</th><th>Toko</th><th>Produk</th><th>Kategori</th><th>Harga saat ini</th></tr></thead>
        <tbody>
            <?php if (!$data['rows']): ?><tr><td colspan="6">Belum ada penambahan produk yang sesuai.</td></tr><?php endif; ?>
            <?php foreach ($data['rows'] as $row): ?>
                <tr>
                    <td style="white-space:nowrap"><?= $escape($row['created_at']) ?></td>
                    <td><strong><?= $escape($row['administrator_name'] ?? 'Akun dihapus') ?></strong><br><small><?= $escape($row['administrator_username'] ?? '') ?> (#<?= (int)$row['administrator_id'] ?>)</small></td>
                    <td><?= $row['store_id'] !== null ? $escape($row['store_name']) . ' (#' . (int)$row['store_id'] . ')' : '-' ?></td>
                    <td><strong><?= $escape($row['product_name'] ?? 'Produk dihapus') ?></strong> <small>(#<?= (int)$row['product_id'] ?>)</small></td>
                    <td><?= $row['product_name'] !== null ? $escape($row['category_name'] ?? 'Tanpa Kategori') : '-' ?></td>
                    <td><?= $row['price'] !== null ? 'Rp' . number_format((float)$row['price'], 0, ',', '.') . ' / ' . $escape($row['unit_type']) : '-' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table></div></div>
    <?php if ($data['totalPages'] > 1): ?>
        <nav aria-label="Halaman riwayat produk" style="display:flex;align-items:center;gap:12px;margin-top:16px">
            <?php if ($data['page'] > 1): ?><a class="btn-secondary-custom" href="<?= $pageUrl($data['page'] - 1) ?>">Sebelumnya</a><?php endif; ?>
            <span>Halaman <?= $data['page'] ?> dari <?= $data['totalPages'] ?></span>
            <?php if ($data['page'] < $data['totalPages']): ?><a class="btn-secondary-custom" href="<?= $pageUrl($data['page'] + 1) ?>">Berikutnya</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
