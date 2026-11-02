<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: /login');
    exit;
}
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../controllers/LaserConversionController.php';
$controller = new LaserConversionController($koneksi);
$data = $controller->getData();
$_SESSION['laser_conversion_csrf'] = $_SESSION['laser_conversion_csrf'] ?? bin2hex(random_bytes(32));
$escape = function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
$laserOptions = function ($selected = 0) use ($data, $escape) {
    echo '<option value="">Pilih bahan print</option>';
    if ($selected && !in_array($selected, array_column($data['laserProducts'], 'product_id'))) {
        echo '<option value="' . (int)$selected . '" selected>Bahan tidak tersedia (#' . (int)$selected . ')</option>';
    }
    foreach ($data['laserProducts'] as $product) {
        echo '<option value="' . (int)$product['product_id'] . '"' . ((int)$selected === (int)$product['product_id'] ? ' selected' : '') . '>'
            . $escape($product['name']) . ' (#' . (int)$product['product_id'] . ')</option>';
    }
};
$finishingCheckboxes = function ($selected = '', $formId = '') use ($data, $escape) {
    $ids = ProductLaserConversion::finishingIds($selected);
    $options = $data['finishings'];
    foreach ($ids as $id) {
        if (!in_array($id, array_column($options, 'finishing_id'))) $options[] = ['finishing_id' => $id, 'name' => 'Finishing tidak tersedia (#' . $id . ')'];
    }
    echo '<div style="display:flex; gap:8px; flex-wrap:wrap; min-width:180px">';
    foreach ($options as $finishing) {
        $id = (int)$finishing['finishing_id'];
        echo '<label style="display:flex; align-items:center; gap:4px"><input type="checkbox" name="finishings[]" value="' . $id . '"'
            . ($formId ? ' form="' . $escape($formId) . '"' : '') . (in_array($id, $ids) ? ' checked' : '') . '> ' . $escape($finishing['name']) . '</label>';
    }
    if (!$options) echo '<small>Belum ada finishing ANEKA CETAK.</small>';
    echo '</div>';
};
?>

<div class="page-header">
    <h2>Konversi Print Laser</h2>
    <a class="btn-primary-custom" href="/products?store_id=<?= (int)$data['store_id'] ?>">Kembali ke Produk</a>
</div>
<?php foreach (['swal_success' => 'success', 'swal_error' => 'danger'] as $key => $type): ?>
    <?php if (isset($_SESSION[$key])): ?>
        <div class="alert alert-<?= $type ?>"><?= $escape($_SESSION[$key]) ?></div>
        <?php unset($_SESSION[$key]); ?>
    <?php endif; ?>
<?php endforeach; ?>
<div class="filter-card">
    <form method="get" action="/laser-conversions" class="filter-form">
        <div class="form-group">
            <label for="laser_store">Toko</label>
            <select id="laser_store" name="store_id" class="form-control" onchange="this.form.submit()">
                <?php foreach ($data['stores'] as $store): ?>
                    <option value="<?= (int)$store['store_id'] ?>" <?= (int)$store['store_id'] === $data['store_id'] ? 'selected' : '' ?>><?= $escape($store['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if (!$data['ready']): ?>
    <div class="alert alert-warning">Jalankan SQL <code>product_laser_variants.sql</code> terlebih dahulu.</div>
<?php elseif ($data['store_id']): ?>
    <div class="filter-card">
        <h3>Tambah Varian Konversi</h3>
        <p>Produk diambil dari kategori ANEKA CETAK, sedangkan bahan print dari LASER A3. Contoh: 40 GANCI = 1 pcs print, atau 1 ID CARD = 1 pcs print.</p>
        <p>Hasil dibulatkan ke atas per item order: 41 ganci = 2 pcs print. Pengaturan berlaku juga untuk laporan nota lama; jumlah, harga, dan stok pada nota tetap sama.</p>
        <p>Print dihitung per kelompok produk. Jika 25 produk = 2 print, maka 12 produk = 2 print dan 26 produk = 4 print.</p>
        <p>Ukuran boleh kosong atau berupa label, misalnya 44 mm atau A4. Harga final per pcs sudah mencakup semua finishing yang dicentang.</p>
        <?php if (!$data['laserProducts']): ?><div class="alert alert-warning">Belum ada bahan print satuan di kategori LASER A3 pada toko ini.</div><?php endif; ?>
        <?php if (!$data['products']): ?><div class="alert alert-warning">Belum ada produk satuan di ANEKA CETAK. Tambahkan kategori ANEKA CETAK di halaman Produk, lalu masukkan atau pindahkan produk yang akan dikonversi ke kategori tersebut.</div><?php endif; ?>
        <form method="post" action="/action?action=save_laser_conversion" class="filter-form">
            <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['laser_conversion_csrf']) ?>">
            <input type="hidden" name="store_id" value="<?= (int)$data['store_id'] ?>">
            <input type="hidden" name="command" value="save">
            <div class="form-group" style="flex:1; min-width:240px">
                <label for="conversion_product">Produk ANEKA CETAK</label>
                <select id="conversion_product" name="product_id" class="form-control select2" required>
                    <option value="">Pilih produk ANEKA CETAK</option>
                    <?php foreach ($data['products'] as $product): ?>
                        <option value="<?= (int)$product['product_id'] ?>" data-price="<?= $escape($product['price']) ?>"><?= $escape($product['category']) ?> / <?= $escape($product['name']) ?> (#<?= (int)$product['product_id'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="conversion_size">Ukuran (opsional)</label>
                <input id="conversion_size" type="text" name="size" class="form-control" maxlength="64" placeholder="Contoh: 44 mm / A4">
            </div>
            <div class="form-group"><label>Finishing (boleh lebih dari satu)</label><?php $finishingCheckboxes(); ?></div>
            <div class="form-group">
                <label for="conversion_price">Harga final / pcs (Rp)</label>
                <input id="conversion_price" type="number" name="price" class="form-control" min="0.01" max="9999999999999.99" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="conversion_quantity">Jumlah produk</label>
                <input id="conversion_quantity" type="number" name="product_quantity" class="form-control" min="1" max="1000000" step="1" value="40" required>
            </div>
            <div class="form-group">
                <label for="conversion_laser">Bahan print laser</label>
                <select id="conversion_laser" name="laser_product_id" class="form-control select2" required><?php $laserOptions(); ?></select>
            </div>
            <div class="form-group">
                <label for="conversion_print_quantity">Jumlah print (pcs)</label>
                <input id="conversion_print_quantity" type="number" name="print_quantity" class="form-control" min="1" max="1000000" step="1" value="1" required>
            </div>
            <button type="submit" class="btn-primary-custom">Simpan Konversi</button>
        </form>
        <small>Satu produk boleh memiliki beberapa varian ukuran dan finishing. Ubah varian yang sudah ada melalui tabel di bawah.</small>
    </div>
    <div class="table-container"><div class="table-scroll">
        <table class="table-modern">
            <thead><tr><th>Kategori / Produk</th><th>Ukuran (opsional)</th><th>Finishing</th><th>Harga Final / pcs (Rp)</th><th>Jumlah Produk</th><th>Bahan Print Laser</th><th>Jumlah Print (pcs)</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php if (!$data['rules']): ?><tr><td colspan="8">Belum ada konversi untuk toko ini.</td></tr><?php endif; ?>
            <?php foreach ($data['rules'] as $rule): $formId = 'laser_rule_' . (int)$rule['id']; ?>
                <tr>
                    <td>
                        <select form="<?= $formId ?>" name="product_id" class="form-control" aria-label="Produk ANEKA CETAK" required style="min-width:220px">
                            <option value="">Pilih produk ANEKA CETAK</option>
                            <?php if (!in_array((int)$rule['product_id'], array_column($data['products'], 'product_id'))): ?>
                                <option value="<?= (int)$rule['product_id'] ?>" selected><?= $escape($rule['product_name'] ?? 'Produk tidak tersedia') ?> (#<?= (int)$rule['product_id'] ?>) — perlu diganti</option>
                            <?php endif; ?>
                            <?php foreach ($data['products'] as $product): ?>
                                <option value="<?= (int)$product['product_id'] ?>" <?= (int)$rule['product_id'] === (int)$product['product_id'] ? 'selected' : '' ?>><?= $escape($product['name']) ?> (#<?= (int)$product['product_id'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$rule['valid']): ?><br><small class="text-danger">Konversi belum berlaku. Pastikan produk satuan berada di ANEKA CETAK dan bahan print di LASER A3.</small><?php endif; ?>
                    </td>
                    <td><input form="<?= $formId ?>" name="size" type="text" maxlength="64" class="form-control" value="<?= $escape($rule['size']) ?>" placeholder="Tanpa ukuran" aria-label="Ukuran varian"></td>
                    <td><?php $finishingCheckboxes($rule['finishing_ids'], $formId); ?></td>
                    <td><input form="<?= $formId ?>" name="price" type="number" min="0.01" max="9999999999999.99" step="0.01" class="form-control" value="<?= $escape($rule['price'] ?? $rule['product_price']) ?>" aria-label="Harga final varian" required></td>
                    <td><input form="<?= $formId ?>" name="product_quantity" type="number" min="1" max="1000000" step="1" class="form-control" value="<?= (int)$rule['product_quantity'] ?>" aria-label="Jumlah produk" required></td>
                    <td><select form="<?= $formId ?>" name="laser_product_id" class="form-control" aria-label="Bahan print laser" required><?php $laserOptions($rule['laser_product_id']); ?></select></td>
                    <td><input form="<?= $formId ?>" name="print_quantity" type="number" min="1" max="1000000" step="1" class="form-control" value="<?= (int)$rule['print_quantity'] ?>" aria-label="Jumlah print" required></td>
                    <td>
                        <form id="<?= $formId ?>" method="post" action="/action?action=save_laser_conversion" style="display:flex; gap:8px; flex-wrap:wrap">
                            <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['laser_conversion_csrf']) ?>">
                            <input type="hidden" name="store_id" value="<?= (int)$data['store_id'] ?>">
                            <input type="hidden" name="id" value="<?= (int)$rule['id'] ?>">
                            <button type="submit" name="command" value="save" class="btn-primary-custom">Simpan</button>
                            <button type="submit" name="command" value="delete" formnovalidate class="btn btn-danger" onclick="return confirm('Hapus konversi produk ini?')">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
    <script>
    document.getElementById('conversion_product').addEventListener('change', function () {
        document.getElementById('conversion_price').value = this.selectedOptions[0]?.dataset.price || '';
    });
    </script>
<?php endif; ?>
