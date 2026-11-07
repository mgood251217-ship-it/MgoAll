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
$options = $editing['options'] ?? ($data['variants'] ? array_fill_keys(array_keys($data['variants'][0]['options']), '') : ['KUALITAS' => '', 'PILIHAN' => '']);
$upper = function ($v) { return function_exists('mb_strtoupper') ? mb_strtoupper($v, 'UTF-8') : strtoupper($v); };
$choiceControl = function ($field, $value, $choices, $locked = false) use ($escape, $upper) {
    $value = $upper($value); $choices = array_values(array_unique(array_merge($choices, $value !== '' ? [$value] : [])));
    $html = '<div data-choice-field><input type="hidden" name="' . $field . '[]" value="' . $escape($value) . '"><select class="form-control" data-choice' . ($locked ? ' disabled' : ' required') . '><option value="">Pilih ' . ($field === 'option_name' ? 'nama pilihan' : 'nilai varian') . '</option>';
    foreach ($choices as $v) $html .= '<option value="' . $escape($v) . '"' . ($v === $value ? ' selected' : '') . '>' . $escape($v) . '</option>';
    if (!$locked) $html .= '<option value="__new__">+ Tambah ' . ($field === 'option_name' ? 'nama pilihan' : 'nilai') . ' lain</option>';
    return $html . '</select><input class="form-control mt-2" data-custom-choice data-uppercase maxlength="' . ($field === 'option_name' ? 40 : 80) . '" placeholder="KETIK ' . ($field === 'option_name' ? 'NAMA PILIHAN' : 'NILAI VARIAN') . '" hidden disabled></div>';
};
$componentControl = function ($component = null) use ($escape, $upper) {
    return '<input type="hidden" name="component_row_id[]" value="' . (int)($component['recipe_id'] ?? 0) . '"><input class="form-control" name="component_name[]" data-meter-name data-uppercase data-auto-name="' . ($component ? '0' : '1') . '" value="' . $escape($upper($component['name'] ?? '')) . '" maxlength="150" placeholder="NAMA DI METERAN" required>';
};
$groupControl = function ($original = '') use ($escape, $choiceControl, $data) {
    $value = $original !== '' ? '<input type="hidden" name="option_value[]" value=""><small>Nilai tiap varian tetap</small>' : $choiceControl('option_value', '', []);
    return '<tr><td><input type="hidden" name="group_original[]" value="' . $escape($original) . '">' . $choiceControl('option_name', $original, array_keys($data['optionChoices'])) . '</td><td>' . $value . '</td><td><div style="display:flex;gap:6px"><button type="button" class="btn-secondary-custom" data-group-up aria-label="Naikkan urutan">↑</button><button type="button" class="btn-secondary-custom" data-group-down aria-label="Turunkan urutan">↓</button><button type="button" class="btn btn-danger" data-remove-group>Hapus</button></div></td></tr>';
};
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
    <?php if ($data['variants']): ?>
    <details class="filter-card" id="variantGroupSettings">
        <summary style="cursor:pointer"><strong>Atur Jenis Varian</strong> — tambah, ubah nama, hapus, atau atur urutan</summary>
        <p class="mt-3">Berlaku untuk semua varian produk ini. Jenis baru membutuhkan nilai awal, misalnya MODEL = REGULER. Nilainya dapat diubah per varian setelah disimpan.</p>
        <form method="post" action="/action?action=save_product_variant" id="variantGroupsForm">
            <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['variant_csrf']) ?>"><input type="hidden" name="store_id" value="<?= $data['store'] ?>"><input type="hidden" name="product_id" value="<?= $data['selected'] ?>"><input type="hidden" name="category_id" value="<?= $data['category'] ?>"><input type="hidden" name="command" value="configure_options"><input type="hidden" name="options_version" value="<?= $escape($data['optionsVersion']) ?>">
            <div class="table-scroll"><table class="table-modern"><thead><tr><th>Jenis varian</th><th>Nilai awal untuk jenis baru</th><th>Urutan / hapus</th></tr></thead><tbody id="variantGroups">
                <?php foreach (array_keys($data['variants'][0]['options']) as $name) echo $groupControl($name); ?>
            </tbody></table></div>
            <p class="mt-3">Harga dan komponen produksi tetap. Jenis yang membedakan kombinasi tidak bisa dihapus jika hasilnya menjadi duplikat. Data pada nota lama tetap tersimpan.</p>
            <div style="display:flex;gap:12px;flex-wrap:wrap"><button type="button" class="btn-secondary-custom" id="addVariantGroup">Tambah Jenis Varian</button><button type="submit" class="btn-primary-custom">Simpan Jenis Varian</button></div>
        </form>
    </details>
    <?php endif; ?>
    <div class="filter-card">
        <h3><?= $editing ? 'Ubah' : 'Tambah' ?> varian <?= $escape($data['product']['name']) ?></h3>
        <p>Pilih nama dan nilai varian dari dropdown. Gunakan “Tambah lainnya” jika belum tersedia.</p>
        <form method="post" action="/action?action=save_product_variant" id="variantForm">
            <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['variant_csrf']) ?>"><input type="hidden" name="store_id" value="<?= $data['store'] ?>"><input type="hidden" name="product_id" value="<?= $data['selected'] ?>"><input type="hidden" name="category_id" value="<?= $data['category'] ?>"><input type="hidden" name="variant_id" value="<?= (int)($editing['id'] ?? 0) ?>"><input type="hidden" name="command" value="save">
            <div class="table-scroll"><table class="table-modern"><thead><tr><th>Nama pilihan</th><th>Nilai varian ini</th><th></th></tr></thead><tbody id="variantOptions">
                <?php foreach ($options as $name => $v): ?><tr><td><?= $choiceControl('option_name', $name, array_keys($data['optionChoices']), (bool)$data['variants']) ?></td><td><?= $choiceControl('option_value', $v, $data['optionChoices'][$upper($name)] ?? []) ?></td><td><?php if (!$data['variants']): ?><button type="button" class="btn btn-danger" data-remove-option>Hapus</button><?php endif; ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php if (!$data['variants']): ?><button type="button" class="btn-secondary-custom" id="addVariantOption" style="margin-top:12px">Tambah Pilihan</button><?php endif; ?>
            <div class="form-group" style="margin-top:16px"><label for="variant_price">Harga dasar varian (Rp / <?= $escape($data['product']['unit_type']) ?>)</label><input id="variant_price" class="form-control" type="number" name="price" min="0.01" max="9999999999999.99" step="0.01" value="<?= $escape($editing['price'] ?? '') ?>" required></div>
            <p>Harga ini menggantikan harga dasar produk. Tambahan ukuran dan finishing tetap mengikuti perhitungan order. <a href="/price-tiers?store_id=<?= $data['store'] ?>&amp;category_id=<?= $data['category'] ?>&amp;product_id=<?= $data['selected'] ?><?= $editing ? '&amp;product_variant_id=' . (int)$editing['id'] : '' ?>">Harga bertingkat dapat diatur per varian</a>; promo beli gratis tetap dapat digunakan. Produk dengan harga paket atau konversi print laser dikelola melalui pengaturannya sendiri.</p>
            <h4>Komponen produksi (opsional)</h4>
            <p>Nama meteran terisi otomatis dan bisa diedit. Untuk setelan, isi dua baris: <strong>MILANO PREMIUM ATASAN = 1</strong> dan <strong>MILANO PREMIUM CELANA = 1</strong>.</p>
            <div class="table-scroll"><table class="table-modern"><thead><tr><th>Nama di meteran</th><th>Jumlah per 1 satuan jual</th><th></th></tr></thead><tbody id="variantComponents">
                <?php foreach ($editing ? $editing['components'] : [null] as $c): ?><tr><td><?= $componentControl($c) ?></td><td><input class="form-control" type="number" name="component_quantity[]" min="0.0001" max="1000000" step="0.0001" value="<?= (float)($c['quantity'] ?? 1) ?>" required></td><td><button type="button" class="btn btn-danger" data-remove-component>Hapus</button></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <p>Komponen dihitung dari seluruh quantity, termasuk pcs bonus. Quantity nota dan stok produk tetap mengikuti satuan jual. Komponen jersey masuk meteran Jersey; semua komponen juga dapat dilihat di meteran Komponen Produksi.</p>
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:16px"><button type="button" class="btn-secondary-custom" id="addVariantComponent">Tambah Komponen</button><button class="btn-primary-custom" type="submit">Simpan Varian</button><?php if ($editing): ?><a class="btn-secondary-custom" href="/product-variants?<?= $escape($query) ?>">Batal / Tambah Baru</a><?php endif; ?></div>
            <small class="text-muted">Perubahan nama meteran berlaku juga pada nota yang sudah terhubung. Harga dan jumlah pada nota tetap tersimpan.</small>
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
        const groupRows = document.getElementById('variantGroups');
        const choices = <?= json_encode($data['optionChoices'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const productName = <?= json_encode($data['product']['name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const upper = value => value.toUpperCase();
        const meterName = () => [productName, ...Array.from(optionRows.querySelectorAll('[name="option_value[]"]'), input => input.value)].filter(Boolean).join(' ').replace(/\s+/g, ' ').trim().toUpperCase();
        const updateMeterNames = () => components.querySelectorAll('[data-meter-name][data-auto-name="1"]').forEach(input => input.value = meterName());
        const setChoices = (field, values) => {
            const select = field.querySelector('[data-choice]');
            select.replaceChildren(new Option('Pilih nilai varian', ''), ...values.map(v => new Option(v, v)), new Option('+ Tambah nilai lain', '__new__'));
            select.value = ''; field.querySelector('input[type="hidden"]').value = '';
            const custom = field.querySelector('[data-custom-choice]'); custom.hidden = true; custom.disabled = true; custom.required = false; custom.value = '';
        };
        [optionRows, groupRows].filter(Boolean).forEach(rows => {
            rows.addEventListener('change', e => {
                if (!e.target.matches('[data-choice]')) return;
                const field = e.target.closest('[data-choice-field]'), custom = field.querySelector('[data-custom-choice]'), hidden = field.querySelector('input[type="hidden"]');
                const isNew = e.target.value === '__new__';
                custom.hidden = !isNew; custom.disabled = !isNew; custom.required = isNew;
                hidden.value = isNew ? upper(custom.value.trim()) : e.target.value;
                if (isNew) custom.focus();
                const valueField = field.closest('tr').children[1].querySelector('[data-choice-field]');
                if (hidden.name === 'option_name[]' && valueField) setChoices(valueField, choices[hidden.value] || []);
                updateMeterNames();
            });
            rows.addEventListener('input', e => {
                if (!e.target.matches('[data-custom-choice]')) return;
                const field = e.target.closest('[data-choice-field]'), hidden = field.querySelector('input[type="hidden"]');
                hidden.value = upper(e.target.value.trim());
                const valueField = field.closest('tr').children[1].querySelector('[data-choice-field]');
                if (hidden.name === 'option_name[]' && valueField) setChoices(valueField, choices[hidden.value] || []);
                updateMeterNames();
            });
        });
        document.getElementById('addVariantGroup')?.addEventListener('click', () => {
            if (groupRows.children.length >= 8) return;
            groupRows.insertAdjacentHTML('beforeend', <?= json_encode($groupControl(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
            groupRows.lastElementChild.querySelector('select').focus();
        });
        groupRows?.addEventListener('click', e => {
            const button = e.target.closest('button'); if (!button) return;
            const row = button.closest('tr');
            if (button.hasAttribute('data-remove-group') && groupRows.children.length > 1) row.remove();
            if (button.hasAttribute('data-group-up') && row.previousElementSibling) groupRows.insertBefore(row, row.previousElementSibling);
            if (button.hasAttribute('data-group-down') && row.nextElementSibling) groupRows.insertBefore(row.nextElementSibling, row);
        });
        document.getElementById('variantGroupsForm')?.addEventListener('submit', e => e.target.querySelector('button[type="submit"]').disabled = true);
        document.getElementById('addVariantOption')?.addEventListener('click', () => {
            if (optionRows.children.length >= 8) return;
            const row = document.createElement('tr');
            row.innerHTML = <?= json_encode('<td>' . $choiceControl('option_name', '', array_keys($data['optionChoices'])) . '</td><td>' . $choiceControl('option_value', '', []) . '</td><td><button type="button" class="btn btn-danger" data-remove-option>Hapus</button></td>', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            optionRows.appendChild(row); row.querySelector('select').focus();
        });
        optionRows.addEventListener('click', e => { if (e.target.closest('[data-remove-option]') && optionRows.children.length > 1) { e.target.closest('tr').remove(); updateMeterNames(); } });
        document.getElementById('addVariantComponent').addEventListener('click', () => {
            const control = <?= json_encode($componentControl(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            components.insertAdjacentHTML('beforeend', '<tr><td>' + control + '</td><td><input class="form-control" type="number" name="component_quantity[]" min="0.0001" max="1000000" step="0.0001" value="1" required></td><td><button type="button" class="btn btn-danger" data-remove-component>Hapus</button></td></tr>');
            updateMeterNames();
        });
        components.addEventListener('input', e => { if (e.target.matches('[data-meter-name]')) e.target.dataset.autoName = '0'; });
        components.addEventListener('click', e => { if (e.target.closest('[data-remove-component]')) e.target.closest('tr').remove(); });
        updateMeterNames();
        document.getElementById('variantForm').addEventListener('submit', e => e.target.querySelector('button[type="submit"]').disabled = true);
    })();
    </script>
<?php endif; ?>

<script>
document.addEventListener('input', e => {
    if (!e.target.matches('[data-uppercase]')) return;
    const start = e.target.selectionStart, end = e.target.selectionEnd;
    e.target.value = e.target.value.toUpperCase();
    if (start !== null) e.target.setSelectionRange(start, end);
});
</script>
