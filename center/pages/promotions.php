<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: /login'); exit; }
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../controllers/PromotionController.php';
$data = (new PromotionController($koneksi))->getData();
$_SESSION['promotion_csrf'] = $_SESSION['promotion_csrf'] ?? bin2hex(random_bytes(32));
$escape = function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
?>
<div class="page-header">
    <h2>Promo Beli Gratis</h2>
    <a class="btn-secondary-custom" href="/products?store_id=<?= $data['store'] ?>&amp;category_id=<?= $data['category'] ?>">Kembali ke Produk</a>
</div>
<?php foreach (['swal_success' => 'success', 'swal_error' => 'danger'] as $key => $type): ?>
    <?php if (isset($_SESSION[$key])): ?><div class="alert alert-<?= $type ?>"><?= $escape($_SESSION[$key]) ?></div><?php unset($_SESSION[$key]); endif; ?>
<?php endforeach; ?>
<div class="filter-card">
    <form method="get" action="/promotions" class="filter-form">
        <div class="form-group"><label for="promo_store">Toko</label>
            <select name="store_id" id="promo_store" class="form-control" onchange="this.form.category_id.value=''; this.form.product_id.value=''; this.form.submit()">
                <?php foreach ($data['stores'] as $store): ?><option value="<?= (int)$store['store_id'] ?>" <?= (int)$store['store_id'] === $data['store'] ? 'selected' : '' ?>><?= $escape($store['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label for="promo_category">Kategori</label>
            <select name="category_id" id="promo_category" class="form-control" onchange="this.form.product_id.value=''; this.form.submit()">
                <option value="">Pilih kategori</option>
                <?php foreach ($data['categories'] as $c): ?><option value="<?= (int)$c['category_id'] ?>" <?= (int)$c['category_id'] === $data['category'] ? 'selected' : '' ?>><?= $escape($c['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex:2"><label for="promo_product">Produk</label>
            <select name="product_id" id="promo_product" class="form-control" onchange="this.form.submit()" <?= !$data['products'] ? 'disabled' : '' ?>>
                <option value=""><?= !$data['category'] ? 'Pilih kategori dahulu' : (!$data['products'] ? 'Belum ada produk di kategori ini' : 'Pilih produk') ?></option>
                <?php foreach ($data['products'] as $p): ?><option value="<?= (int)$p['product_id'] ?>" <?= (int)$p['product_id'] === $data['selected'] ? 'selected' : '' ?>><?= $escape($p['name']) ?> (#<?= (int)$p['product_id'] ?>)</option><?php endforeach; ?>
            </select>
        </div>
    </form>
</div>
<?php if (!$data['ready']): ?>
    <div class="alert alert-warning">Jalankan <code>center/utils/product_promotions.sql</code> dan pastikan fitur harga bertingkat sudah terpasang.</div>
<?php elseif ($data['product'] && !ProductPromotion::eligible($data['product'])): ?>
    <div class="alert alert-info">Promo tersedia untuk produk satuan PCS dan ~ yang dihitung per pcs. Produk dengan perhitungan waktu, berat, atau luas mengikuti harga biasanya.</div>
<?php elseif ($data['product']): ?>
    <div class="filter-card">
        <h3><?= $escape($data['product']['name']) ?></h3>
        <p>Isi jumlah seluruh barang yang diterima pelanggan pada order, termasuk bonus. Bonus produk yang sama dihitung otomatis per baris dan berlaku kelipatan.</p>
        <form method="post" action="/action?action=save_promotion" id="promotionForm">
            <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['promotion_csrf']) ?>">
            <input type="hidden" name="store_id" value="<?= $data['store'] ?>"><input type="hidden" name="category_id" value="<?= $data['category'] ?>"><input type="hidden" name="product_id" value="<?= $data['selected'] ?>"><input type="hidden" name="command" value="save">
            <div class="filter-form">
                <div class="form-group"><label for="promo_buy">Beli (pcs)</label><input class="form-control" type="number" name="buy_quantity" id="promo_buy" min="1" max="1000000" step="1" value="<?= (int)($data['rule']['buy_quantity'] ?? 10) ?>" required></div>
                <div class="form-group"><label for="promo_free">Gratis (pcs)</label><input class="form-control" type="number" name="free_quantity" id="promo_free" min="1" max="1000000" step="1" value="<?= (int)($data['rule']['free_quantity'] ?? 1) ?>" required></div>
                <button type="submit" class="btn-primary-custom">Simpan Promo</button>
            </div>
            <p id="promo_preview" aria-live="polite"></p>
        </form>
        <p>Stok dan pemakaian bahan mengikuti seluruh quantity, termasuk bonus. Biaya produk dan finishing pada pcs bonus ikut gratis. Promo memakai harga normal dan tidak ditumpuk dengan harga bertingkat.</p>
        <small class="text-muted">Aturan tersimpan bersama item nota. Perubahan atau penghapusan promo tidak mengganti aturan produk yang sudah dimasukkan ke nota sebelumnya.</small>
    </div>
    <script>
    (() => {
        const buy = document.getElementById('promo_buy'), free = document.getElementById('promo_free');
        const preview = () => {
            const b = Number(buy.value), f = Number(free.value);
            document.getElementById('promo_preview').textContent = Number.isInteger(b) && Number.isInteger(f) && b > 0 && f > 0
                ? `Input ${b + f} pcs: bayar ${b}, gratis ${f}. Input ${(b + f) * 2} pcs: bayar ${b * 2}, gratis ${f * 2}.` : '';
        };
        buy.addEventListener('input', preview); free.addEventListener('input', preview); preview();
        document.getElementById('promotionForm').addEventListener('submit', event => { event.target.querySelector('button[type="submit"]').disabled = true; });
    })();
    </script>
<?php endif; ?>
<?php if ($data['ready']): ?>
<div class="table-container"><div class="table-scroll"><table class="table-modern">
    <thead><tr><th>Kategori / Produk</th><th>Promo</th><th>Aksi</th></tr></thead><tbody>
    <?php if (!$data['rules']): ?><tr><td colspan="3">Belum ada promo di toko ini.</td></tr><?php endif; ?>
    <?php foreach ($data['rules'] as $r): ?>
        <tr><td><?= $escape(($r['category'] ?? '') . ' / ' . ($r['product_name'] ?? 'Produk tidak tersedia')) ?> (#<?= (int)$r['product_id'] ?>)</td>
            <td>Beli <?= (int)$r['buy_quantity'] ?> gratis <?= (int)$r['free_quantity'] ?><?php if (!ProductPromotion::eligible(['category' => $r['category'], 'name' => $r['product_name'], 'unit_type' => $r['unit_type']])): ?><small class="d-block text-muted">Produk sudah tidak sesuai; promo tidak diterapkan pada nota baru.</small><?php endif; ?></td>
            <td><div style="display:flex;gap:8px;flex-wrap:wrap">
                <a class="btn-secondary-custom" href="/promotions?store_id=<?= $data['store'] ?>&amp;category_id=<?= (int)$r['category_id'] ?>&amp;product_id=<?= (int)$r['product_id'] ?>">Ubah</a>
                <form method="post" action="/action?action=save_promotion">
                    <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['promotion_csrf']) ?>"><input type="hidden" name="store_id" value="<?= $data['store'] ?>"><input type="hidden" name="category_id" value="<?= $data['category'] ?>"><input type="hidden" name="product_id" value="<?= (int)$r['product_id'] ?>"><input type="hidden" name="command" value="delete"><button type="submit" class="btn btn-danger">Hapus Promo</button>
                </form>
            </div></td></tr>
    <?php endforeach; ?>
    </tbody></table></div></div>
<?php endif; ?>
