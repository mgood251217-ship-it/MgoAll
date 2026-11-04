<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: /login'); exit; }
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../controllers/VariantController.php';
$data = (new VariantController($koneksi))->getData();
$_SESSION['variant_csrf'] = $_SESSION['variant_csrf'] ?? bin2hex(random_bytes(32));
$escape = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$query = http_build_query(['store_id' => $data['store'], 'category_id' => $data['category'], 'product_id' => $data['selected']]);
$editing = $data['editing'];
$options = $editing['options'] ?? ($data['variants'] ? array_fill_keys(array_keys($data['variants'][0]['options']), '') : ['Kualitas' => '', 'Pilihan' => '']);
?>
<div class="page-header"><h2>Varian Produk</h2><a class="btn-secondary-custom" href="/products?store_id=<?= $data['store'] ?>&amp;category_id=<?= $data['category'] ?>">Kembali ke Produk</a></div>
<?php foreach (['swal_success' => 'success', 'swal_error' => 'danger'] as $key => $type): ?>
    <?php if (isset($_SESSION[$key])): ?><div class="alert alert-<?= $type ?>"><?= $escape($_SESSION[$key]) ?></div><?php unset($_SESSION[$key]); endif; ?>
<?php endforeach; ?>
<div class="filter-card"><form method="get" action="/product-variants" class="filter-form">
    <div class="form-group"><label for="variant_store">Toko</label><select class="form-control" id="variant_store" name="store_id" onchange="this.form.category_id.value='';this.form.product_id.value='';this.form.submit()">
        <?php foreach ($data['stores'] as $s): ?><option value="<?= (int)$s['store_id'] ?>" <?= (int)$s['store_id'] === $data['store'] ? 'selected' : '' ?>><?= $escape($s['name']) ?></option><?php endforeach; ?>
    </select></div>
    <div class="form-group"><label for="variant_category">Kategori</label><select class="form-control" id="variant_category" name="category_id" onchange="this.form.product_id.value='';this.form.submit()">
        <option value="">Pilih kategori</option><?php foreach ($data['categories'] as $c): ?><option value="<?= (int)$c['category_id'] ?>" <?= (int)$c['category_id'] === $data['category'] ? 'selected' : '' ?>><?= $escape($c['name']) ?></option><?php endforeach; ?>
    </select></div>
    <div class="form-group" style="flex:2"><label for="variant_product">Produk</label><select class="form-control" id="variant_product" name="product_id" onchange="this.form.submit()" <?= !$data['products'] ? 'disabled' : '' ?>>
        <option value=""><?= $data['category'] ? 'Pilih produk' : 'Pilih kategori dahulu' ?></option><?php foreach ($data['products'] as $p): ?><option value="<?= (int)$p['product_id'] ?>" <?= (int)$p['product_id'] === $data['selected'] ? 'selected' : '' ?>><?= $escape($p['name']) ?> (#<?= (int)$p['product_id'] ?>)</option><?php endforeach; ?>
    </select></div>
</form></div>
<?php if (!$data['ready']): ?>
    <div class="alert alert-warning">Jalankan <code>center/utils/product_variants.sql</code> dan pastikan fitur harga bertingkat sudah terpasang.</div>
<?php elseif ($data['product']): ?>
    <div class="filter-card">
        <h3><?= $editing ? 'Ubah' : 'Tambah' ?> varian <?= $escape($data['product']['name']) ?></h3>
        <p>Contoh pilihan: Kualitas = Premium dan Pilihan = Setelan. Nama pilihan dapat disesuaikan untuk setiap produk.</p>
        <form method="post" action="/action?action=save_product_variant" id="variantForm">
            <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['variant_csrf']) ?>"><input type="hidden" name="store_id" value="<?= $data['store'] ?>"><input type="hidden" name="product_id" value="<?= $data['selected'] ?>"><input type="hidden" name="category_id" value="<?= $data['category'] ?>"><input type="hidden" name="variant_id" value="<?= (int)($editing['id'] ?? 0) ?>"><input type="hidden" name="command" value="save">
            <div class="table-scroll"><table class="table-modern"><thead><tr><th>Nama pilihan</th><th>Nilai varian ini</th><th></th></tr></thead><tbody id="variantOptions">
                <?php foreach ($options as $name => $v): ?><tr><td><input class="form-control" name="option_name[]" value="<?= $escape($name) ?>" maxlength="40" <?= $data['variants'] ? 'readonly' : '' ?> required></td><td><input class="form-control" name="option_value[]" value="<?= $escape($v) ?>" maxlength="80" placeholder="Misalnya Premium atau Setelan" required></td><td><?php if (!$data['variants']): ?><button type="button" class="btn btn-danger" data-remove-option>Hapus</button><?php endif; ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php if (!$data['variants']): ?><button type="button" class="btn-secondary-custom" id="addVariantOption" style="margin-top:12px">Tambah Pilihan</button><?php endif; ?>
            <div class="form-group" style="margin-top:16px"><label for="variant_price">Harga dasar varian (Rp / <?= $escape($data['product']['unit_type']) ?>)</label><input id="variant_price" class="form-control" type="number" name="price" min="0.01" max="9999999999999.99" step="0.01" value="<?= $escape($editing['price'] ?? '') ?>" required></div>
            <p>Harga ini menggantikan harga dasar produk. Tambahan ukuran dan finishing tetap mengikuti perhitungan order. Harga bertingkat produk tidak diterapkan pada varian; promo beli gratis tetap dapat digunakan. Produk dengan harga paket atau konversi print laser dikelola melalui pengaturannya sendiri.</p>
            <h4>Komponen produksi (opsional)</h4>
            <p>Contoh setelan: <strong>MILANO PREMIUM ATASAN = 1</strong> dan <strong>MILANO PREMIUM CELANA = 1</strong>. Atasan saja cukup mengisi komponen atasan. Gunakan nama komponen yang sama jika ingin jumlahnya digabung di meteran.</p>
            <div class="table-scroll"><table class="table-modern"><thead><tr><th>Nama di meteran</th><th>Jumlah per 1 satuan jual</th><th></th></tr></thead><tbody id="variantComponents">
                <?php foreach ($editing['components'] ?? [] as $c): ?><tr><td><input class="form-control" name="component_name[]" value="<?= $escape($c['name']) ?>" maxlength="150" required></td><td><input class="form-control" type="number" name="component_quantity[]" min="0.0001" max="1000000" step="0.0001" value="<?= (float)$c['quantity'] ?>" required></td><td><button type="button" class="btn btn-danger" data-remove-component>Hapus</button></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <p>Komponen dihitung dari seluruh quantity, termasuk pcs bonus. Quantity nota dan stok produk tetap mengikuti satuan jual. Komponen jersey masuk meteran Jersey; semua komponen juga dapat dilihat di meteran Komponen Produksi.</p>
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:16px"><button type="button" class="btn-secondary-custom" id="addVariantComponent">Tambah Komponen</button><button class="btn-primary-custom" type="submit">Simpan Varian</button><?php if ($editing): ?><a class="btn-secondary-custom" href="/product-variants?<?= $escape($query) ?>">Batal / Tambah Baru</a><?php endif; ?></div>
            <small class="text-muted">Harga, pilihan, dan komponen disimpan pada item nota. Perubahan atau penghapusan varian tidak mengganti data nota lama.</small>
        </form>
    </div>
    <div class="table-container"><div class="table-scroll"><table class="table-modern"><thead><tr><th>Varian</th><th>Harga dasar</th><th>Komponen produksi</th><th>Aksi</th></tr></thead><tbody>
        <?php if (!$data['variants']): ?><tr><td colspan="4">Belum ada varian. Produk ini masih mengikuti harga biasa.</td></tr><?php endif; ?>
        <?php foreach ($data['variants'] as $v): ?><tr><td><?php foreach ($v['options'] as $name => $value): ?><div><?= $escape($name) ?>: <strong><?= $escape($value) ?></strong></div><?php endforeach; ?></td><td>Rp<?= number_format((float)$v['price'], 0, ',', '.') ?></td><td><?php foreach ($v['components'] as $c): ?><div><?= $escape($c['name']) ?>: <?= (float)$c['quantity'] ?></div><?php endforeach; ?><?= !$v['components'] ? '-' : '' ?></td><td><div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn-secondary-custom" href="/product-variants?<?= $escape($query) ?>&amp;variant_id=<?= (int)$v['id'] ?>">Ubah</a>
            <form method="post" action="/action?action=save_product_variant"><input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['variant_csrf']) ?>"><input type="hidden" name="store_id" value="<?= $data['store'] ?>"><input type="hidden" name="category_id" value="<?= $data['category'] ?>"><input type="hidden" name="product_id" value="<?= $data['selected'] ?>"><input type="hidden" name="variant_id" value="<?= (int)$v['id'] ?>"><input type="hidden" name="command" value="delete"><button class="btn btn-danger" type="submit">Hapus</button></form>
        </div></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
    <script>
    (() => {
        const optionRows = document.getElementById('variantOptions'), components = document.getElementById('variantComponents');
        document.getElementById('addVariantOption')?.addEventListener('click', () => {
            if (optionRows.children.length >= 8) return;
            const row = optionRows.firstElementChild?.cloneNode(true);
            if (!row) return;
            row.querySelectorAll('input').forEach(input => input.value = ''); optionRows.appendChild(row); row.querySelector('input').focus();
        });
        optionRows.addEventListener('click', e => { if (e.target.closest('[data-remove-option]') && optionRows.children.length > 1) e.target.closest('tr').remove(); });
        document.getElementById('addVariantComponent').addEventListener('click', () => {
            components.insertAdjacentHTML('beforeend', '<tr><td><input class="form-control" name="component_name[]" maxlength="150" placeholder="MILANO PREMIUM ATASAN" required></td><td><input class="form-control" type="number" name="component_quantity[]" min="0.0001" max="1000000" step="0.0001" value="1" required></td><td><button type="button" class="btn btn-danger" data-remove-component>Hapus</button></td></tr>');
        });
        components.addEventListener('click', e => { if (e.target.closest('[data-remove-component]')) e.target.closest('tr').remove(); });
        document.getElementById('variantForm').addEventListener('submit', e => e.target.querySelector('button[type="submit"]').disabled = true);
    })();
    </script>
<?php endif; ?>
