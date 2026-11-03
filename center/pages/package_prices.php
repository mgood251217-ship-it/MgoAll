<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: /login');
    exit;
}
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../controllers/PackagePriceController.php';
$controller = new PackagePriceController($koneksi);
$data = $controller->getData();
$_SESSION['package_price_csrf'] = $_SESSION['package_price_csrf'] ?? bin2hex(random_bytes(32));
$escape = function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
$rupiah = function ($value) { return 'Rp' . number_format((float)$value, 0, ',', '.'); };
$finishingsByCategory = [];
foreach ($data['finishings'] as $finishing) {
    $finishingsByCategory[(int)$finishing['category_id']][] = $finishing;
}
?>

<div class="page-header">
    <h2>Harga Paket</h2>
    <a class="btn-primary-custom" href="/products?store_id=<?= (int)$data['store_id'] ?>">Kembali ke Produk</a>
</div>
<?php foreach (['swal_success' => 'success', 'swal_error' => 'danger'] as $key => $type): ?>
    <?php if (isset($_SESSION[$key])): ?>
        <div class="alert alert-<?= $type ?>"><?= $escape($_SESSION[$key]) ?></div>
        <?php unset($_SESSION[$key]); ?>
    <?php endif; ?>
<?php endforeach; ?>
<div class="filter-card">
    <form method="get" action="/package-prices" class="filter-form">
        <div class="form-group">
            <label for="package_store">Toko</label>
            <select id="package_store" name="store_id" class="form-control" onchange="this.form.submit()">
                <?php foreach ($data['stores'] as $store): ?>
                    <option value="<?= (int)$store['store_id'] ?>" <?= (int)$store['store_id'] === $data['store_id'] ? 'selected' : '' ?>><?= $escape($store['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if (!$data['ready']): ?>
    <div class="alert alert-warning">Jalankan migrasi tabel ke <code>product_package</code> terlebih dahulu.</div>
<?php elseif ($data['store_id']): ?>
    <div class="filter-card">
        <h3>Tambah Paket</h3>
        <p>Pilih bahan, isi nama finishing dan harga paket. Ukuran wajib untuk INDOOR/OUTDOOR dan opsional untuk LASER A3. Meteran dan stok paket LASER A3 tetap dihitung per pcs.</p>
        <form method="post" action="/action?action=save_package_price" class="filter-form">
            <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['package_price_csrf']) ?>">
            <input type="hidden" name="store_id" value="<?= $data['store_id'] ?>">
            <input type="hidden" name="command" value="add">
            <div class="form-group" style="flex:1; min-width:240px">
                <label for="target_product">Bahan yang digunakan</label>
                <select id="target_product" name="product_id" class="form-control select2" required>
                    <option value="">Pilih bahan INDOOR / OUTDOOR / LASER</option>
                    <?php foreach ($data['products'] as $product): ?>
                        <option value="<?= (int)$product['product_id'] ?>" data-supports-size="<?= ProductFinishingPrice::supportsDimensions($product) ? '1' : '0' ?>" data-requires-size="<?= ProductFinishingPrice::requiresDimensions($product) ? '1' : '0' ?>"><?= $escape($product['category']) ?> — #<?= (int)$product['product_id'] ?> <?= $escape($product['name']) ?> — <?= $rupiah($product['price']) ?>/<?= ProductFinishingPrice::requiresDimensions($product) ? 'm²' : ($product['unit_type'] === 'PCS' ? 'pcs' : 'item') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="package_finishing">Finishing paket</label>
                <input id="package_finishing" type="text" class="form-control" name="finishing_name" list="packageFinishingNames" maxlength="100" placeholder="Ketik nama finishing" required>
                <datalist id="packageFinishingNames">
                    <?php
                    $finishingNames = [];
                    foreach ($data['finishings'] as $finishing) {
                        $name = trim(preg_replace('/[\s\p{Z}]+/u', ' ', (string)$finishing['name']) ?? '');
                        $key = function_exists('mb_strtoupper') ? mb_strtoupper($name, 'UTF-8') : strtoupper($name);
                        if ($name === '' || isset($finishingNames[$key])) continue;
                        $finishingNames[$key] = true;
                        echo '<option value="' . $escape($name) . '"></option>';
                    }
                    ?>
                </datalist>
            </div>
            <div class="form-group" data-package-dimension>
                <label for="package_panjang">Panjang (m)</label>
                <input id="package_panjang" class="form-control" name="panjang" type="number" min="0.0001" max="999999.9999" step="0.0001" required>
            </div>
            <div class="form-group" data-package-dimension>
                <label for="package_lebar">Lebar (m)</label>
                <input id="package_lebar" class="form-control" name="lebar" type="number" min="0.0001" max="999999.9999" step="0.0001" required>
            </div>
            <small id="packageDimensionHint" hidden>Paket LASER ini tidak memakai panjang × lebar.</small>
            <div class="form-group">
                <label for="package_price">Harga final / set (Rp)</label>
                <input id="package_price" class="form-control" name="price" type="number" min="0.01" max="9999999999999.99" step="0.01" required>
            </div>
            <button type="submit" class="btn-primary-custom">Simpan Paket</button>
        </form>
    </div>

    <div class="table-container"><div class="table-scroll">
        <table class="table-modern">
            <thead><tr><th>Kategori / Bahan</th><th>Finishing</th><th>Ukuran (m)</th><th>Harga Final / Set (Rp)</th><th title="Jumlah baris order_items, bukan total quantity">Jumlah Item Order</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php if (!$data['rules']): ?>
                <tr><td colspan="6">Belum ada paket untuk toko ini.</td></tr>
            <?php endif; ?>
            <?php foreach ($data['rules'] as $rule): ?>
                <tr>
                    <td><?= $escape($rule['category']) ?> / #<?= (int)$rule['product_id'] ?> <?= $escape($rule['product_name']) ?>
                        <?php if (!$rule['valid']): ?><br><small class="text-danger">Bahan / finishing perlu diperiksa.</small><?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $options = $finishingsByCategory[(int)$rule['category_id']] ?? [];
                        $hasFinishing = in_array((int)$rule['finishing_id'], array_column($options, 'finishing_id'));
                        ?>
                        <select class="form-control" form="package_price_<?= (int)$rule['id'] ?>" name="finishing_id" aria-label="Finishing paket <?= $escape($rule['product_name']) ?>" required style="min-width:180px">
                            <option value="" <?= !$hasFinishing ? 'selected' : '' ?>><?= $hasFinishing ? 'Pilih finishing' : 'Finishing tidak tersedia (#' . (int)$rule['finishing_id'] . ')' ?></option>
                            <?php foreach ($options as $finishing): ?>
                                <option value="<?= (int)$finishing['finishing_id'] ?>" <?= (int)$finishing['finishing_id'] === (int)$rule['finishing_id'] ? 'selected' : '' ?>><?= $escape($finishing['name']) ?> (#<?= (int)$finishing['finishing_id'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><?= $rule['requires_size'] ? (float)$rule['panjang'] . ' × ' . (float)$rule['lebar'] : 'Tanpa ukuran' ?></td>
                    <td><input class="form-control" form="package_price_<?= (int)$rule['id'] ?>" name="price" type="number" min="0.01" max="9999999999999.99" step="0.01" value="<?= $escape($rule['price']) ?>" aria-label="Harga paket <?= $escape($rule['product_name']) ?>" required style="min-width:140px"></td>
                    <td><?= (int)$rule['order_item_count'] ?></td>
                    <td>
                        <form id="package_price_<?= (int)$rule['id'] ?>" method="post" action="/action?action=save_package_price" style="display:flex; gap:8px; flex-wrap:wrap">
                            <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['package_price_csrf']) ?>">
                            <input type="hidden" name="store_id" value="<?= $data['store_id'] ?>">
                            <input type="hidden" name="id" value="<?= (int)$rule['id'] ?>">
                            <button type="submit" name="command" value="update" class="btn-primary-custom">Simpan</button>
                            <button type="submit" name="command" value="delete" formnovalidate class="btn btn-danger" onclick="return confirm('Hapus paket dari daftar harga? Item dan harga pada nota lama tetap tersimpan.')">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
    <script>
    const updatePackageDimensions = () => {
        const selected = document.getElementById('target_product').selectedOptions[0];
        const requiresSize = selected?.dataset.requiresSize === '1';
        const supportsSize = selected?.dataset.supportsSize === '1';
        document.querySelectorAll('[data-package-dimension]').forEach(field => { field.hidden = !supportsSize; });
        ['package_panjang', 'package_lebar'].forEach(id => {
            const field = document.getElementById(id);
            field.disabled = !supportsSize;
            field.required = requiresSize;
            if (!supportsSize) field.value = '';
        });
    };
    document.getElementById('target_product').addEventListener('change', updatePackageDimensions);
    updatePackageDimensions();
    </script>
<?php endif; ?>
