<?php if (!empty($row['action_url'])): ?>
    <div class="action-group">
        <a href="<?= htmlspecialchars($row['action_url']) ?>" class="btn bg-info">Kelola</a>
        <form action="<?= htmlspecialchars($cash_delete_post_url) ?>" method="POST" onsubmit="return confirm('Hapus permanen transaksi ini? Saldo akan dihitung ulang.')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <?php foreach ($cash_form_fields as $field => $value): ?><input type="hidden" name="<?= htmlspecialchars($field) ?>" value="<?= htmlspecialchars((string) $value) ?>"><?php endforeach; ?>
            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            <input type="hidden" name="source" value="<?= htmlspecialchars($row['source']) ?>">
            <button type="submit" class="btn bg-danger">Hapus</button>
        </form>
    </div>
<?php else: ?>
    <span class="text-muted">Saldo bawaan</span>
<?php endif; ?>
