<div class="cash-flow-tables">
    <?php foreach ($cash_tables as $table): ?>
        <section class="table-container border-<?= htmlspecialchars($table['color']) ?>">
            <div class="header-action">
                <h3 class="text-<?= htmlspecialchars($table['color']) ?>"><?= htmlspecialchars($table['title']) ?></h3>
                <span class="text-muted"><?= count($table['rows']) ?> transaksi</span>
            </div>
            <table class="table-modern">
                <thead><tr><th>Waktu</th><th>Keterangan</th><th>Metode</th><th>Nominal</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php foreach ($table['rows'] as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['display_date']) ?><br><small class="text-muted"><?= htmlspecialchars($row['display_time']) ?></small></td>
                            <td><span class="font-semibold"><?= htmlspecialchars($row['description']) ?></span><br><small class="text-muted"><?= htmlspecialchars($row['category']) ?></small></td>
                            <td><span class="status-badge <?= htmlspecialchars($row['method_class']) ?>"><?= htmlspecialchars($row['payment_method']) ?></span></td>
                            <td class="<?= $table['type'] === 'in' ? 'amount-income' : 'amount-expense' ?>">Rp <?= number_format($row['amount'], 0, ',', '.') ?></td>
                            <td><?php require __DIR__ . '/cash_actions.php'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($table['rows'])): ?><tr><td colspan="5" class="table-empty">Belum ada transaksi pada interval tanggal ini.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </section>
    <?php endforeach; ?>
</div>
