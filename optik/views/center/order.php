<div class="header-action">
    <div>
        <h1 class="page-title"><?= htmlspecialchars($order['inv_no']) ?></h1>
        <p class="page-subtitle"><?= htmlspecialchars($store_name) ?> · <?= htmlspecialchars($order['display_date']) ?></p>
    </div>
    <a href="<?= htmlspecialchars($center_base) ?>/orders" class="btn bg-secondary text-muted">Kembali ke Order</a>
</div>
<div class="stats-grid">
    <div class="stat-card border-accent"><div class="stat-info"><h4>Total Order</h4><h2>Rp <?= number_format($order['total'], 0, ',', '.') ?></h2></div></div>
    <div class="stat-card border-success"><div class="stat-info"><h4>Sudah Dibayar</h4><h2>Rp <?= number_format($order['total_dibayar'], 0, ',', '.') ?></h2></div></div>
    <div class="stat-card border-warning"><div class="stat-info"><h4>Sisa Tagihan</h4><h2>Rp <?= number_format($order['remaining'], 0, ',', '.') ?></h2><p><?= htmlspecialchars($order['status_text']) ?></p></div></div>
</div>
<form action="<?= htmlspecialchars($center_base) ?>/update-order" method="POST" class="table-container mb-20" id="centerOrderForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
    <h2 class="page-title mb-20">Edit Order</h2>
    <div class="product-form-grid">
        <div class="form-group"><label for="order_customer">Nama Pelanggan</label><input id="order_customer" name="customer_name" value="<?= htmlspecialchars($order['customer_name']) ?>" required></div>
        <div class="form-group"><label for="order_phone">Nomor Telepon</label><input id="order_phone" name="nomor" type="tel" value="<?= htmlspecialchars($order['nomor'] ?? '') ?>"></div>
    </div>
    <div class="center-order-items">
        <table class="table-modern center-edit-table">
            <thead><tr><th>Produk</th><th>Jumlah</th><th>Harga sebelum diskon</th><th>Diskon (%)</th><th>Aksi</th></tr></thead>
            <tbody id="centerOrderItems">
                <?php foreach ($items as $index => $item): ?>
                    <tr>
                        <td><span><?= htmlspecialchars($item['name']) ?></span><input type="hidden" name="items[<?= $index ?>][product_id]" value="<?= (int) $item['product_id'] ?>"></td>
                        <td><input aria-label="Jumlah item <?= $index + 1 ?>" type="number" name="items[<?= $index ?>][quantity]" value="<?= (int) $item['quantity'] ?>" min="1" step="1" required></td>
                        <td><input aria-label="Harga item <?= $index + 1 ?>" type="number" name="items[<?= $index ?>][price]" value="<?= htmlspecialchars($item['gross_price']) ?>" min="0" step="any" required></td>
                        <td><input aria-label="Diskon item <?= $index + 1 ?>" type="number" name="items[<?= $index ?>][discount]" value="<?= (float) $item['diskon'] ?>" min="0" max="100" step="any" required></td>
                        <td><button type="button" class="btn bg-danger" data-remove-item>Hapus Item</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="header-action mt-10">
        <button type="button" class="btn bg-secondary text-muted" id="addCenterOrderItem">+ Tambah Item</button>
        <div><span class="text-muted">Total setelah pembulatan Rp500: </span><strong id="centerOrderTotal">Rp <?= number_format($order['total'], 0, ',', '.') ?></strong></div>
    </div>
    <button type="submit" class="btn bg-primary">Simpan Perubahan Order</button>
</form>
<section class="table-container mb-20">
    <h2 class="page-title mb-20">Riwayat Pembayaran</h2>
    <table class="table-modern center-store-table">
        <thead><tr><th>Tanggal</th><th>Nominal</th><th>Jenis Pembayaran</th><th>Metode Pembayaran</th><th>Aksi</th></tr></thead>
        <tbody>
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?= htmlspecialchars($payment['display_date']) ?></td>
                    <td>Rp <?= number_format($payment['nominal'], 0, ',', '.') ?></td>
                    <td><span class="font-semibold"><?= htmlspecialchars($payment['payment_type']) ?></span><?php if ($payment['payment_note'] !== ''): ?><br><small class="text-muted"><?= htmlspecialchars($payment['payment_note']) ?></small><?php endif; ?></td>
                    <td>
                        <form action="<?= htmlspecialchars($center_base) ?>/update-payment" method="POST" class="center-payment-method">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                            <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                            <select name="payment_method" class="input-select" aria-label="Metode pembayaran #<?= (int) $payment['id'] ?>">
                                <option value="Cash" <?= $payment['method_value'] === 'Cash' ? 'selected' : '' ?>>Cash</option>
                                <option value="TF" <?= $payment['method_value'] === 'TF' ? 'selected' : '' ?>>Transfer</option>
                            </select>
                            <button type="submit" class="btn bg-primary">Simpan</button>
                        </form>
                    </td>
                    <td>
                        <form action="<?= htmlspecialchars($center_base) ?>/delete-payment" method="POST" onsubmit="return confirm('Hapus pembayaran ini? Sisa tagihan order akan dihitung ulang.')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                            <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                            <button type="submit" class="btn bg-danger">Hapus Pembayaran</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($payments)): ?><tr><td colspan="5" class="table-empty">Belum ada pembayaran.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
<div class="table-container center-delete-order border-danger">
    <div><h2 class="page-title">Hapus Order</h2><p class="page-subtitle">Order, seluruh item, dan seluruh pembayarannya akan dihapus permanen.</p></div>
    <form action="<?= htmlspecialchars($center_base) ?>/delete-order" method="POST" onsubmit="return confirm('Hapus permanen order beserta semua item dan pembayarannya?')">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
        <button type="submit" class="btn bg-danger">Hapus Order</button>
    </form>
</div>
<template id="centerOrderItemTemplate">
    <tr>
        <td><select class="input-select" data-field="product_id" aria-label="Produk baru" required><option value="">Pilih produk</option><?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>" data-price="<?= (float) $product['price'] ?>"><?= htmlspecialchars($product['name']) ?></option><?php endforeach; ?></select></td>
        <td><input type="number" data-field="quantity" aria-label="Jumlah produk baru" value="1" min="1" step="1" required></td>
        <td><input type="number" data-field="price" aria-label="Harga produk baru" value="0" min="0" step="any" required></td>
        <td><input type="number" data-field="discount" aria-label="Diskon produk baru" value="0" min="0" max="100" step="any" required></td>
        <td><button type="button" class="btn bg-danger" data-remove-item>Hapus Item</button></td>
    </tr>
</template>
<script>
    const centerItems = document.getElementById('centerOrderItems');
    let centerItemIndex = <?= count($items) ?>;
    function updateCenterOrderTotal() {
        let total = 0;
        centerItems.querySelectorAll('tr').forEach(row => {
            const quantity = Number(row.querySelector('[name$="[quantity]"]').value);
            const price = Number(row.querySelector('[name$="[price]"]').value);
            const discount = Number(row.querySelector('[name$="[discount]"]').value);
            total += Math.round((price * (1 - discount / 100) + Number.EPSILON) * 100) / 100 * quantity;
        });
        document.getElementById('centerOrderTotal').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.floor(total / 500) * 500);
    }
    document.getElementById('addCenterOrderItem').addEventListener('click', () => {
        const row = document.getElementById('centerOrderItemTemplate').content.cloneNode(true);
        row.querySelectorAll('[data-field]').forEach(input => input.name = 'items[' + centerItemIndex + '][' + input.dataset.field + ']');
        centerItemIndex++;
        centerItems.appendChild(row);
        updateCenterOrderTotal();
    });
    centerItems.addEventListener('click', event => {
        const button = event.target.closest('[data-remove-item]');
        if (button) {
            button.closest('tr').remove();
            updateCenterOrderTotal();
        }
    });
    centerItems.addEventListener('change', event => {
        if (event.target.matches('select[data-field="product_id"]')) {
            const row = event.target.closest('tr');
            row.querySelector('[data-field="price"]').value = event.target.selectedOptions[0].dataset.price || 0;
            row.querySelector('[data-field="discount"]').value = 0;
        }
        updateCenterOrderTotal();
    });
    centerItems.addEventListener('input', updateCenterOrderTotal);
</script>
