<table class="table-modern center-store-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Nama Toko</th>
            <th>Alamat</th>
            <th>Pengguna</th>
            <th>Produk</th>
            <th>Stok (Pcs)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($stores as $store): ?>
            <tr>
                <td>#<?= (int) $store['id'] ?></td>
                <td class="font-semibold"><?= htmlspecialchars($store['name'] ?? '') ?></td>
                <td><?= htmlspecialchars($store['address'] ?? '-') ?></td>
                <td><?= number_format($store['users_count'], 0, ',', '.') ?></td>
                <td><?= number_format($store['products_count'], 0, ',', '.') ?></td>
                <td><?= number_format($store['total_stock'], 0, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($stores)): ?>
            <tr><td colspan="6" class="table-empty">Belum ada toko yang sesuai.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
