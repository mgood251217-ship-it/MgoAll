<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: /login'); exit; }
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../controllers/PriceTierController.php';
$data = (new PriceTierController($koneksi))->getData();
$_SESSION['price_tier_csrf'] = $_SESSION['price_tier_csrf'] ?? bin2hex(random_bytes(32));
$escape = function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
$number = function ($value) { return rtrim(rtrim(number_format((float)$value, 4, '.', ''), '0'), '.'); };
$unit = $data['product'] ? ProductPriceTier::measure($data['product']) : 'pcs';
$unitLabel = ProductPriceTier::unitLabel($unit);
?>
<div class="page-header">
    <h2>Harga Bertingkat</h2>
    <a class="btn-secondary-custom" href="/products?store_id=<?= (int)$data['store'] ?>&amp;category_id=<?= (int)$data['category'] ?>">Kembali ke Produk</a>
</div>
<?php foreach (['swal_success' => 'success', 'swal_error' => 'danger'] as $key => $type): ?>
    <?php if (isset($_SESSION[$key])): ?><div class="alert alert-<?= $type ?>"><?= $escape($_SESSION[$key]) ?></div><?php unset($_SESSION[$key]); endif; ?>
<?php endforeach; ?>
<div class="filter-card">
    <form method="get" action="/price-tiers" class="filter-form">
        <div class="form-group"><label for="tier_store">Toko</label>
            <select name="store_id" id="tier_store" class="form-control" onchange="this.form.category_id.value=''; this.form.product_id.value=''; if(this.form.product_variant_id) this.form.product_variant_id.value=''; this.form.submit()">
                <?php foreach ($data['stores'] as $store): ?><option value="<?= (int)$store['store_id'] ?>" <?= (int)$store['store_id'] === $data['store'] ? 'selected' : '' ?>><?= $escape($store['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label for="tier_category">Kategori</label>
            <select name="category_id" id="tier_category" class="form-control" onchange="this.form.product_id.value=''; if(this.form.product_variant_id) this.form.product_variant_id.value=''; this.form.submit()">
                <option value="">Pilih kategori</option>
                <?php foreach ($data['categories'] as $c): ?><option value="<?= (int)$c['category_id'] ?>" <?= (int)$c['category_id'] === $data['category'] ? 'selected' : '' ?>><?= $escape($c['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex:2"><label for="tier_product">Produk</label>
            <select name="product_id" id="tier_product" class="form-control" onchange="if(this.form.product_variant_id) this.form.product_variant_id.value=''; this.form.submit()" <?= !$data['products'] ? 'disabled' : '' ?>>
                <option value=""><?= !$data['category'] ? 'Pilih kategori dahulu' : (!$data['products'] ? 'Belum ada produk di kategori ini' : 'Pilih produk') ?></option>
                <?php foreach ($data['products'] as $p): ?><option value="<?= (int)$p['product_id'] ?>" <?= (int)$p['product_id'] === $data['selected'] ? 'selected' : '' ?>><?= $escape($p['name']) ?> (#<?= (int)$p['product_id'] ?>)</option><?php endforeach; ?>
            </select>
        </div>
        <?php if ($data['variants']): ?>
        <div class="form-group" style="flex:2"><label for="tier_variant">Varian</label>
            <select name="product_variant_id" id="tier_variant" class="form-control" onchange="this.form.submit()">
                <option value="0" <?= !$data['variantId'] ? 'selected' : '' ?>>Tanpa varian (harga produk)</option>
                <?php foreach ($data['variants'] as $v): ?><option value="<?= (int)$v['id'] ?>" <?= (int)$v['id'] === $data['variantId'] ? 'selected' : '' ?>><?= $escape(strtoupper($v['label'])) ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </form>
</div>
<?php if (!$data['ready']): ?>
    <div class="alert alert-warning">Jalankan SQL <code>center/utils/product_price_tiers.sql</code> terlebih dahulu.</div>
<?php elseif ($data['product'] && $data['variantMissing']): ?>
    <div class="alert alert-warning">Varian tidak tersedia pada produk ini. Pilih varian lain untuk mengatur tingkatan harganya.</div>
<?php elseif ($data['variant'] && !$data['variantReady']): ?>
    <div class="alert alert-warning">Untuk mengatur harga bertingkat per varian, jalankan <code>center/utils/product_variant_price_tiers.sql</code> terlebih dahulu.</div>
<?php elseif ($data['product'] && !$unit): ?>
    <div class="alert alert-info">
        <?php if (in_array(strtoupper(trim($data['product']['category'])), ['DTF', 'DTF UV'], true)): ?>
            DTF/DTF UV memakai perhitungan khusus. Harga bertingkat untuk perhitungan ini belum tersedia.
        <?php elseif (ProductPriceTier::usesTime($data['product'])): ?>
            <?= $escape($data['product']['name']) ?> dihitung berdasarkan waktu di OrderController, meskipun satuan produknya tertulis PCS. Harga bertingkat berdasarkan waktu belum tersedia.
        <?php else: ?>
            Perhitungan produk ini (<?= $escape($data['product']['unit_type']) ?>) belum didukung harga bertingkat. Saat ini tersedia untuk PCS, ~ (seperti pcs tanpa batas stok), M2, dan CM2.
        <?php endif; ?>
    </div>
<?php elseif ($data['product']): ?>
    <div class="filter-card">
        <h3><?= $escape($data['product']['name']) ?></h3>
        <?php if ($data['variant']): ?>
            <p>Varian: <strong><?= $escape(strtoupper($data['variant']['label'])) ?></strong>. Tingkatan ini hanya berlaku untuk varian tersebut.</p>
        <?php elseif ($data['variants']): ?>
            <div class="alert alert-info">Pengaturan ini untuk item tanpa varian. Pilih varian di atas untuk mengatur harga bertingkatnya.</div>
        <?php endif; ?>
        <?php if ((new ProductPromotion($koneksi))->rule($data['store'], $data['selected']) && ProductPromotion::eligible($data['product'])): ?>
            <div class="alert alert-info">Produk ini memiliki <a href="/promotions?store_id=<?= $data['store'] ?>&amp;product_id=<?= $data['selected'] ?>">promo beli gratis</a>. Nota baru memakai promo tersebut; harga bertingkat disimpan untuk digunakan setelah promo dihapus.</div>
        <?php endif; ?>
        <p>Harga dasar normal: <strong>Rp<?= number_format((float)($data['variant']['price'] ?? $data['product']['price']), 0, ',', '.') ?>/<?= $unitLabel ?></strong>.</p>
        <p>Jumlah dikumpulkan per <?= $data['variant'] ? 'varian' : 'produk tanpa varian' ?> dalam satu nota. Jumlah antar varian tidak digabung. <?= $unit === 'pcs' ? 'Batas 10 berlaku setelah 10, yaitu mulai 11 pcs.' : 'Luas dihitung dari panjang × lebar × quantity dalam ' . $unitLabel . '. Batas 500 berlaku saat total luas lebih dari 500 ' . $unitLabel . '.' ?> Tingkat dengan batas tertinggi yang terpenuhi berlaku untuk seluruh jumlah.</p>
        <?php if (trim($data['product']['unit_type']) === '~'): ?><p>Satuan ~ dihitung seperti pcs. Stok bahan tidak dibatasi dan tidak dikurangi.</p><?php endif; ?>
        <?php if (ProductPriceTier::minimumAmount($data['product'])): ?><p>Minimum tagihan item PRINT UV tetap Rp7.500.</p><?php endif; ?>
        <p>Harga di bawah menggantikan harga dasar <?= $data['variant'] ? 'varian' : 'bahan' ?>; biaya finishing tetap ditambahkan. Harga paket dan konversi print laser mengikuti pengaturannya masing-masing.</p>
        <form method="post" action="/action?action=save_price_tiers" id="tierForm">
            <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['price_tier_csrf']) ?>">
            <input type="hidden" name="store_id" value="<?= (int)$data['store'] ?>">
            <input type="hidden" name="category_id" value="<?= (int)$data['category'] ?>">
            <input type="hidden" name="product_id" value="<?= (int)$data['selected'] ?>">
            <input type="hidden" name="product_variant_id" value="<?= (int)$data['variantId'] ?>">
            <input type="hidden" name="command" value="save">
            <div class="table-scroll"><table class="table-modern">
                <thead><tr><th>Jumlah lebih dari (<?= $unitLabel ?>)</th><th>Harga dasar / <?= $unitLabel ?> (Rp)</th><th>Aksi</th></tr></thead>
                <tbody id="tierRows">
                <?php foreach ($data['rules'] ?: [['quantity_above' => '', 'price' => '']] as $r): ?>
                    <tr><td><input class="form-control" type="number" name="quantity_above[]" min="0" max="999999999999.9999" step="<?= $unit === 'pcs' ? '1' : '0.0001' ?>" value="<?= $r['quantity_above'] === '' ? '' : $number($r['quantity_above']) ?>" aria-label="Jumlah lebih dari" required></td>
                        <td><input class="form-control" type="number" name="price[]" min="0.01" max="9999999999999.99" step="0.01" value="<?= $escape($r['price']) ?>" aria-label="Harga tingkat" required></td>
                        <td><button type="button" class="btn btn-danger" data-remove-tier>Hapus baris</button></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <div style="display:flex; gap:12px; margin-top:16px; flex-wrap:wrap">
                <button type="button" class="btn-secondary-custom" id="addTier">Tambah Tingkat</button>
                <button type="submit" class="btn-primary-custom">Simpan Harga Bertingkat</button>
            </div>
            <small class="text-muted">Tambahkan sebanyak yang diperlukan. Perubahan berlaku untuk nota baru; nota yang sudah memakai produk atau varian ini tetap mengikuti aturan tersimpan.</small>
        </form>
    </div>
    <script>
    const tierRows = document.getElementById('tierRows');
    const template = tierRows.firstElementChild.cloneNode(true);
    document.getElementById('addTier').addEventListener('click', () => {
        const row = template.cloneNode(true);
        row.querySelectorAll('input').forEach(input => { input.value = ''; });
        tierRows.appendChild(row); row.querySelector('input').focus();
    });
    tierRows.addEventListener('click', event => { if (event.target.closest('[data-remove-tier]')) event.target.closest('tr').remove(); });
    document.getElementById('tierForm').addEventListener('submit', event => {
        event.target.querySelector('button[type="submit"]').disabled = true;
    });
    </script>
<?php endif; ?>
<?php if ($data['ready']): ?>
<div class="table-container"><div class="table-scroll"><table class="table-modern">
    <thead><tr><th>Kategori / Produk</th><th>Tingkatan harga dasar</th><th>Aksi</th></tr></thead><tbody>
    <?php if (!$data['groups']): ?><tr><td colspan="3">Belum ada harga bertingkat di toko ini.</td></tr><?php endif; ?>
    <?php foreach ($data['groups'] as $rows): $p = $rows[0]; $id = (int)$p['product_id']; $variantId = (int)$p['product_variant_id']; ?>
        <tr><td><?= $escape(($p['category'] ?? '') . ' / ' . ($p['product_name'] ?? 'Produk tidak tersedia')) ?> (#<?= $id ?>)<br><small><?= $variantId ? $escape(strtoupper($p['variant_label'])) : 'Tanpa varian' ?></small></td>
            <td><?php foreach ($rows as $r): ?><div>Lebih dari <?= $number($r['quantity_above']) ?> <?= ProductPriceTier::unitLabel(strtolower(trim($p['unit_type'] ?? ''))) ?>: <strong>Rp<?= number_format((float)$r['price'], 0, ',', '.') ?></strong></div><?php endforeach; ?></td>
            <td><div style="display:flex; gap:8px; flex-wrap:wrap"><a class="btn-secondary-custom" href="/price-tiers?store_id=<?= (int)$data['store'] ?>&amp;category_id=<?= (int)($p['category_id'] ?? 0) ?>&amp;product_id=<?= $id ?>&amp;product_variant_id=<?= $variantId ?>">Ubah</a>
                <form method="post" action="/action?action=save_price_tiers">
                    <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['price_tier_csrf']) ?>"><input type="hidden" name="store_id" value="<?= (int)$data['store'] ?>"><input type="hidden" name="category_id" value="<?= (int)$data['category'] ?>"><input type="hidden" name="product_id" value="<?= $id ?>"><input type="hidden" name="product_variant_id" value="<?= $variantId ?>"><input type="hidden" name="command" value="delete"><button type="submit" class="btn btn-danger">Hapus Tingkatan</button>
                </form></div></td></tr>
    <?php endforeach; ?>
    </tbody></table></div></div>
<?php endif; ?>
