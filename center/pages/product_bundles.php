<?php
require_once __DIR__ . '/../controllers/ProductBundleController.php';

$bundleData = $bundlePageData ?? (new ProductBundleController($koneksi))->getData();
$canBundle = (new AdministratorAccessController($koneksi))->can('save_product_bundle');
$_SESSION['product_bundle_csrf'] = $_SESSION['product_bundle_csrf'] ?? bin2hex(random_bytes(32));
$editing = null;
foreach ($bundleData['bundles'] as $bundle) {
    if ((int) $bundle['product_id'] === (int) ($_GET['product_id'] ?? 0)) {
        $editing = $bundle;
    }
}
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<div class="filter-card">
    <form method="get" action="/package-prices" class="filter-form">
        <input type="hidden" name="tab" value="bundles">
        <div class="form-group">
            <label for="bundleStore">Toko</label>
            <select id="bundleStore" name="store_id" class="form-control" onchange="this.form.submit()">
                <?php foreach ($bundleData['stores'] as $store): ?>
                    <option value="<?= (int) $store['store_id'] ?>" <?= (int) $store['store_id'] === $bundleData['store'] ? 'selected' : '' ?>>
                        <?= $escape($store['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php foreach ($bundleData['warnings'] as $warning): ?>
    <div class="alert alert-warning"><?= $escape($warning) ?></div>
<?php endforeach; ?>

<?php if (!$bundleData['ready']): ?>
    <div class="alert alert-warning">Pengaturan paket gabungan belum tersedia.</div>
<?php elseif ($bundleData['store']): ?>
    <?php if ($canBundle): ?>
        <div class="filter-card">
            <h3><?= $editing ? 'Ubah' : 'Tambah' ?> Paket Gabungan</h3>
            <form method="post" action="/action?action=save_product_bundle" id="bundleForm">
                <input type="hidden" name="csrf" value="<?= $escape($_SESSION['product_bundle_csrf']) ?>">
                <input type="hidden" name="store_id" value="<?= $bundleData['store'] ?>">
                <div class="filter-form">
                    <div class="form-group">
                        <label for="bundleProduct">Produk paket</label>
                        <select id="bundleProduct" name="product_id" class="form-control" required>
                            <option value="">Pilih produk paket</option>
                            <?php foreach ($bundleData['products'] as $product): ?>
                                <?php if ($product['unit_type'] !== '~' || strtoupper(trim($product['category'])) !== 'PELENGKAP') continue; ?>
                                <option value="<?= (int) $product['product_id'] ?>" <?= (int) $product['product_id'] === (int) ($editing['product_id'] ?? 0) ? 'selected' : '' ?>>
                                    #<?= (int) $product['product_id'] ?> <?= $escape($product['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="bundlePrice">Harga paket (Rp)</label>
                        <input id="bundlePrice" name="price" type="number" min="0.01" max="9999999999999.99" step="0.01"
                               class="form-control" value="<?= $escape($editing['price'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="table-scroll">
                    <table class="table-modern">
                        <thead>
                            <tr><th>Produk</th><th>Varian</th><th>Konversi print laser</th><th>Ukuran (opsional)</th><th>Jumlah</th><th></th></tr>
                        </thead>
                        <tbody id="bundleRows"></tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <button type="button" class="btn-secondary-custom" id="addBundleRow">Tambah Isi</button>
                    <button class="btn-primary-custom" type="submit">Simpan Paket</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="table-container">
        <table class="table-modern">
            <thead><tr><th>Paket</th><th>Harga</th><th>Isi</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php if (!$bundleData['bundles']): ?>
                    <tr><td colspan="4">Belum ada paket gabungan.</td></tr>
                <?php endif; ?>
                <?php foreach ($bundleData['bundles'] as $bundle): ?>
                    <tr>
                        <td><?= $escape($bundle['name'] ?? 'Produk tidak tersedia') ?></td>
                        <td><?= $rupiah($bundle['price']) ?></td>
                        <td>
                            <?php foreach ($bundle['display_items'] as $item): ?>
                                <div><?= (int) $item['quantity'] ?> x <?= $escape($item['label']) ?></div>
                            <?php endforeach; ?>
                            <?php if (isset($bundle['error'])): ?>
                                <div class="text-danger"><?= $escape($bundle['error']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($canBundle): ?>
                                <a class="btn-action btn-kelola" href="/package-prices?tab=bundles&store_id=<?= $bundleData['store'] ?>&amp;product_id=<?= (int) $bundle['product_id'] ?>">Ubah</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const products = <?= json_encode($bundleData['products'], $jsonFlags) ?>;
        const variants = <?= json_encode($bundleData['variants'], $jsonFlags) ?>;
        const rules = <?= json_encode($bundleData['rules'], $jsonFlags) ?>;
        const initial = <?= json_encode($editing['items'] ?? [[], []], $jsonFlags) ?>;
        const rows = document.getElementById('bundleRows');
        if (!rows) return;
        let serial = 0;

        const addRow = (value = {}) => {
            if (rows.children.length >= 30) return;
            const index = serial++;
            const row = document.createElement('tr');
            const field = (tag, key) => {
                const cell = document.createElement('td');
                const element = document.createElement(tag);
                element.className = 'form-control';
                element.name = `items[${index}][${key}]`;
                cell.appendChild(element);
                row.appendChild(cell);
                return element;
            };
            const product = field('select', 'product_id');
            const variant = field('select', 'product_variant_id');
            const laser = field('select', 'laser_conversion_id');
            const size = field('input', 'size');
            const quantity = field('input', 'quantity');
            product.required = true;
            size.maxLength = 80;
            quantity.type = 'number';
            quantity.min = '1';
            quantity.max = '10000';
            quantity.required = true;
            quantity.value = value.quantity || 1;
            product.add(new Option('Pilih produk isi', ''));
            products.forEach(item => product.add(new Option(`${item.category} - ${item.name}`, item.product_id)));

            const refresh = () => {
                const productId = Number(product.value);
                const productVariants = variants.filter(item => Number(item.product_id) === productId);
                const productRules = rules.filter(item => Number(item.product_id) === productId);
                variant.replaceChildren(new Option(productVariants.length ? 'Pilih varian' : 'Tanpa varian', ''));
                productVariants.forEach(item => variant.add(new Option(item.label, item.id)));
                variant.required = Boolean(productVariants.length);
                laser.replaceChildren(new Option(productRules.length ? 'Pilih konversi' : 'Tanpa konversi', ''));
                productRules.forEach(item => {
                    const label = [item.laser_name, item.size, item.finishing_names].filter(Boolean).join(' / ');
                    laser.add(new Option(label, item.id));
                });
                laser.required = Boolean(productRules.length);
                if (productVariants.length === 1) variant.value = String(productVariants[0].id);
                if (productRules.length === 1) laser.value = String(productRules[0].id);
                size.disabled = Boolean(productRules.length);
            };

            product.value = String(value.product_id || '');
            refresh();
            if (value.product_variant_id) variant.value = String(value.product_variant_id);
            if (value.laser_conversion_id) laser.value = String(value.laser_conversion_id);
            size.value = value.size || '';
            product.addEventListener('change', refresh);

            const cell = document.createElement('td');
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-danger';
            remove.textContent = 'Hapus';
            remove.addEventListener('click', () => {
                if (rows.children.length > 2) row.remove();
            });
            cell.appendChild(remove);
            row.appendChild(cell);
            rows.appendChild(row);
        };

        initial.forEach(addRow);
        document.getElementById('addBundleRow').addEventListener('click', () => addRow());
        document.getElementById('bundleForm').addEventListener('submit', event => {
            if (event.submitter) {
                event.submitter.disabled = true;
                event.submitter.textContent = 'Menyimpan...';
            }
        });
    });
    </script>
<?php endif; ?>
