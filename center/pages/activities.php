<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: /login'); exit; }
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../controllers/ActivityController.php';
$controller = new ActivityController($koneksi);
if (!$controller->canView()) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Halaman ini hanya untuk akun dengan akses ALL.</div>';
    return;
}
$escape = function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
try { $data = $controller->getData(); }
catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo '<div class="alert alert-danger">' . $escape($e->getMessage()) . '</div><a href="/activities">Kembali ke Aktivitas</a>';
    return;
}
$url = function ($changes = []) use ($data, $escape) {
    return '/activities?' . $escape(http_build_query(array_merge([
        'store_id' => $data['store'], 'start_date' => $data['start'], 'end_date' => $data['end'], 'page' => $data['page'],
    ], $changes)));
};
$activities = array_filter($data['rows'], function ($row) { return $row['type'] === 'activity'; });
$archives = array_filter($data['rows'], function ($row) { return $row['type'] === 'archive'; });
if ($data['selectedArchive'] && !in_array((int)$data['selectedArchive']['order_id'], array_map('intval', array_column($archives, 'order_id')), true)) {
    $selected = $data['selectedArchive'];
    $selected['event_date'] = $selected['deleted_at'];
    array_unshift($archives, $selected);
}
$money = function ($value) { return 'Rp ' . number_format((float)$value, 0, ',', '.'); };
?>
<div class="page-header"><h2>Aktivitas</h2></div>
<div class="filter-card"><form method="get" action="/activities" class="filter-form">
    <div class="form-group"><label for="activityStore">Toko</label><select name="store_id" id="activityStore" class="form-control">
        <?php if (!$data['stores']): ?><option value="">Tidak ada toko</option><?php endif; ?>
        <?php foreach ($data['stores'] as $store): ?><option value="<?= (int)$store['store_id'] ?>" <?= (int)$store['store_id'] === $data['store'] ? 'selected' : '' ?>><?= $escape($store['name'] . ($store['branch'] ? ' - ' . $store['branch'] : '')) ?></option><?php endforeach; ?>
    </select></div>
    <div class="form-group"><label for="activityStart">Dari tanggal</label><input type="date" class="form-control" id="activityStart" name="start_date" value="<?= $escape($data['start']) ?>" required></div>
    <div class="form-group"><label for="activityEnd">Sampai tanggal</label><input type="date" class="form-control" id="activityEnd" name="end_date" value="<?= $escape($data['end']) ?>" required></div>
    <div class="form-group" style="align-self:end"><button class="btn-primary-custom" type="submit">Tampilkan</button></div>
</form></div>
<p><?= number_format($data['total'], 0, ',', '.') ?> aktivitas dan arsip order, diurutkan dari yang terbaru.</p>
<h3>Aktivitas</h3>
<div class="table-container mb-4"><div class="table-scroll"><table class="table-modern">
    <thead><tr><th>Waktu</th><th>Judul</th><th>Pesan</th><th>Keterangan</th><th>Administrator</th><th>Order</th><th>Tanggal Order</th><th>Status</th></tr></thead>
    <tbody>
    <?php if (!$activities): ?><tr><td colspan="8">Tidak ada aktivitas pada halaman ini.</td></tr><?php endif; ?>
    <?php foreach ($activities as $row): ?><tr>
        <td style="white-space:nowrap"><?= $escape($row['event_date']) ?></td><td><?= $escape($row['title']) ?></td>
        <td style="overflow-wrap:anywhere"><?= nl2br($escape($row['message'])) ?></td><td style="overflow-wrap:anywhere"><?= nl2br($escape($row['information'])) ?></td>
        <td><?= $escape($row['administrator_name'] ?? ($row['administrator_id'] ? 'Akun dihapus (#' . (int)$row['administrator_id'] . ')' : '-')) ?></td>
        <td><?php if ($row['live_order_id']): ?><a href="/order?id=<?= rawurlencode(startEnk('enk', $row['live_order_id'])) ?>&amp;store_id=<?= rawurlencode(startEnk('enk', $data['store'])) ?>">#<?= (int)$row['order_id'] ?></a><?php elseif ($row['archived_order_id']): ?><a href="<?= $url(['archive_order_id' => (int)$row['archived_order_id']]) ?>#archive-order-<?= (int)$row['archived_order_id'] ?>">#<?= (int)$row['order_id'] ?> (arsip)</a><?php else: ?><?= $row['order_id'] ? '#' . (int)$row['order_id'] . ' (tidak tersedia)' : '-' ?><?php endif; ?></td>
        <td><?= $escape($row['order_date'] ?? '-') ?></td><td><?= (int)$row['done'] === 1 ? 'Selesai' : 'Belum selesai' ?></td>
    </tr><?php endforeach; ?>
    </tbody>
</table></div></div>
<h3>Arsip Order Dihapus</h3>
<div class="d-flex flex-column gap-3">
<?php if (!$archives): ?><div class="filter-card">Tidak ada arsip order pada halaman ini.</div><?php endif; ?>
<?php foreach ($archives as $row): ?>
<article class="card" data-event-type="archive" id="archive-order-<?= (int)$row['order_id'] ?>" style="scroll-margin-top:24px">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <strong>Order Dihapus</strong>
            <time class="text-body-secondary"><?= $escape($row['event_date']) ?></time>
        </div>
        <div class="d-flex justify-content-between flex-wrap gap-3 mb-3">
            <div><strong>Order #<?= (int)$row['order_id'] ?> &middot; Nota <?= $escape($row['nomorator']) ?></strong><br><?= $escape($row['customer_name']) ?><br><small class="text-body-secondary">Tanggal order: <?= $escape($row['date']) ?></small></div>
            <div><strong><?= $money($row['total']) ?></strong><br><small>Dihapus oleh: <?= $escape($row['deleted_by_name'] ?? ($row['deleted_by'] ? 'Akun dihapus (#' . (int)$row['deleted_by'] . ')' : '-')) ?></small></div>
        </div>
        <?php if (!$row['items']): ?><p class="mb-0">Tidak ada item yang diarsipkan.</p><?php else: ?>
        <div class="table-scroll"><table class="table-modern">
            <thead><tr><th>Produk</th><th>Finishing</th><th>Ukuran</th><th>Qty</th><th>Harga</th><th>Jumlah</th></tr></thead>
            <tbody><?php foreach ($row['items'] as $item): ?><tr>
                <td><?= $escape($item['judul']) ?><br><small>Produk #<?= (int)$item['product_id'] ?></small></td><td><?= $escape($item['finishing_names'] ?? ($item['finishing'] ?: '-')) ?></td><td><?= $escape($item['size']) ?></td><td><?= $escape($item['quantity']) ?></td><td><?= $money($item['unit']) ?></td><td><?= $money($item['amount']) ?></td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
        <?php endif; ?>
    </div>
</article>
<?php endforeach; ?>
</div>
<?php if ($data['totalPages'] > 1): ?>
<nav class="d-flex align-items-center gap-3 mt-3" aria-label="Halaman aktivitas">
    <?php if ($data['page'] > 1): ?><a class="btn-secondary-custom" href="<?= $url(['page' => $data['page'] - 1]) ?>">Sebelumnya</a><?php endif; ?>
    <span>Halaman <?= $data['page'] ?> dari <?= $data['totalPages'] ?></span>
    <?php if ($data['page'] < $data['totalPages']): ?><a class="btn-secondary-custom" href="<?= $url(['page' => $data['page'] + 1]) ?>">Berikutnya</a><?php endif; ?>
</nav>
<?php endif; ?>
