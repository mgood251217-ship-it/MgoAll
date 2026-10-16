<?php if ($report_available): ?>
    <?php require __DIR__ . '/../aruskas/index.php'; ?>
    <?php if ($type !== 'out'): ?>
        <section class="table-container mt-30">
            <div class="header-action">
                <div><h2 class="page-title">Pembayaran Order</h2><p class="page-subtitle">Kelola pembayaran pada toko dan periode yang dipilih.</p></div>
            </div>
            <table class="table-modern center-store-table">
                <thead><tr><th>Invoice</th><th>Tanggal</th><th>Jenis Pembayaran</th><th>Metode</th><th>Nominal</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td class="font-semibold"><?= htmlspecialchars($payment['inv_no']) ?></td>
                            <td><?= htmlspecialchars($payment['display_date']) ?></td>
                            <td><?= htmlspecialchars($payment['payment_type']) ?></td>
                            <td><?= htmlspecialchars($payment['payment_method']) ?></td>
                            <td>Rp <?= number_format($payment['nominal'], 0, ',', '.') ?></td>
                            <td>
                                <div class="action-group">
                                    <a href="<?= htmlspecialchars($payment['action_url']) ?>" class="btn bg-info">Kelola</a>
                                    <form action="<?= htmlspecialchars($payment_delete_post_url) ?>" method="POST" onsubmit="return confirm('Hapus pembayaran ini? Sisa tagihan dan saldo akan dihitung ulang.')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        <input type="hidden" name="order_id" value="<?= (int) $payment['order_id'] ?>">
                                        <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                                        <button type="submit" class="btn bg-danger">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($payments)): ?><tr><td colspan="6" class="table-empty">Belum ada pembayaran pada periode ini.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
<?php else: ?>
    <div class="table-container">
        <h1 class="page-title mb-20">Arus Kas Harian & Interval</h1>
        <p class="text-muted">Belum ada toko terdaftar untuk menampilkan arus kas.</p>
    </div>
<?php endif; ?>
