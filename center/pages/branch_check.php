<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: /login");
    exit;
}

require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../functions/helpers.php';
require_once __DIR__ . '/../controllers/BranchCheckController.php';

$access = isset($_SESSION['admin_logged_in']['access']) ? startEnk('dek', $_SESSION['admin_logged_in']['access']) : '';

$controller = new BranchCheckController($koneksi);
$data = $controller->getIndexData($access);

$statusBadgeClass = [
    'Baik' => 'bg-success-light',
    'Perlu Perhatian' => 'bg-danger-light',
    'Belum Lengkap' => 'bg-warning-light',
];

function renderChecklistRow($no, $name, $kind, $id, $status, $notes, $parentGroupId = null)
{
    $parentAttr = $parentGroupId !== null ? ' data-parent-group="' . $parentGroupId . '"' : '';
    echo '<tr class="checklist-item-row" data-kind="' . $kind . '" data-id="' . $id . '"' . $parentAttr . '>';
    echo '<td data-label="No">' . $no . '</td>';
    echo '<td data-label="List Check">' . htmlspecialchars($name) . '</td>';
    echo '<td class="text-center" data-label="Sesuai"><input type="checkbox" class="checklist-checkbox row-ok" ' . ($status === 'ok' ? 'checked' : '') . '></td>';
    echo '<td class="text-center" data-label="Tidak Sesuai"><input type="checkbox" class="checklist-checkbox row-notok" ' . ($status === 'not_ok' ? 'checked' : '') . '></td>';
    echo '<td data-label="Keterangan"><input type="text" class="form-control-custom row-notes" placeholder="Keterangan..." value="' . htmlspecialchars($notes) . '"></td>';
    echo '</tr>';
}
?>

<div class="page-header">
    <h2>Checklist Pengecekan Cabang</h2>
    <div class="page-actions">
        <a href="/checklist_master" class="btn-secondary-custom">
            <i class="fas fa-cog"></i> Kelola Struktur Checklist
        </a>
    </div>
</div>

<div class="filter-card">
    <form method="get" action="/branch_check" class="filter-form">
        <div class="form-group">
            <label for="store_id">Pilih Cabang</label>
            <select name="store_id" id="store_id" class="form-control" onchange="this.form.submit()">
                <?php if (empty($data['stores'])): ?>
                    <option value="">Tidak ada cabang tersedia</option>
                <?php else: ?>
                    <?php foreach ($data['stores'] as $store): ?>
                        <option value="<?= $store['store_id'] ?>" <?= $data['current_store_id'] == $store['store_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($store['name']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="check_date">Tanggal Pengecekan</label>
            <input type="date" name="check_date" id="check_date" class="form-control" value="<?= htmlspecialchars($data['check_date']) ?>" onchange="this.form.submit()">
        </div>
    </form>
</div>

<?php if ($data['history_count'] > 0): ?>
    <div class="table-container">
        <h4 class="table-title" style="display:flex;align-items:center;justify-content:space-between">
            <span>Riwayat Pengecekan Cabang Ini</span>
            <span class="badge-pill bg-info-light"><?= $data['history_count'] ?> pengecekan tercatat</span>
        </h4>
        <div class="table-scroll">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Diperiksa Oleh</th>
                        <th class="text-center">Sesuai</th>
                        <th class="text-center">Tidak Sesuai</th>
                        <th class="table-actions">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['history'] as $historyRow): ?>
                        <?php
                        $historyUrl = '/branch_check?store_id=' . $data['current_store_id'] . '&check_date=' . urlencode($historyRow['check_date']);
                        $isActive = $historyRow['check_date'] === $data['check_date'];
                        ?>
                        <tr <?= $isActive ? 'style="background:var(--color-surface-hover)"' : '' ?>>
                            <td><?= date('d/m/Y', strtotime($historyRow['check_date'])) ?></td>
                            <td><?= htmlspecialchars($historyRow['checked_by'] ?? '-') ?></td>
                            <td class="text-center"><?= (int)$historyRow['total_ok'] ?></td>
                            <td class="text-center"><?= (int)$historyRow['total_not_ok'] ?></td>
                            <td class="table-actions">
                                <a href="<?= $historyUrl ?>" class="btn-action btn-open" title="Buka">
                                    <i class="fas fa-folder-open"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($data['tables'])): ?>
    <div class="table-container">
        <div class="cell-empty">
            Belum ada tabel checklist. Buat dulu di <a href="/checklist_master">Kelola Struktur Checklist</a>.
        </div>
    </div>
<?php endif; ?>

<?php $no = 1; ?>
<?php foreach ($data['tables'] as $table): ?>
    <div class="table-container">
        <h4 class="table-title"><?= htmlspecialchars($table['name']) ?></h4>
        <div class="table-scroll">
            <table class="table-modern checklist-table">
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th>List Check</th>
                        <th class="text-center col-check">Sesuai / Baik / Berjalan</th>
                        <th class="text-center col-check">Tidak / Rusak / Terhenti</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($table['categories'])): ?>
                        <tr>
                            <td class="cell-empty" colspan="5">Belum ada kategori pada tabel ini</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($table['categories'] as $category): ?>
                        <tr class="checklist-category-row">
                            <td colspan="5"><strong><?= htmlspecialchars($category['name']) ?></strong></td>
                        </tr>

                        <?php if (empty($category['groups'])): ?>
                            <tr>
                                <td class="cell-empty" colspan="5">Belum ada grup pada kategori ini</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($category['groups'] as $group): ?>
                            <?php if (empty($group['items'])): ?>
                                <?php renderChecklistRow($no++, $group['name'], 'group', $group['id'], $group['status'], $group['notes']); ?>
                            <?php else: ?>
                                <tr class="checklist-group-row">
                                    <td colspan="2"><?= htmlspecialchars($group['name']) ?></td>
                                    <td class="text-center" data-label="Centang Semua">
                                        <input type="checkbox" class="checklist-checkbox group-toggle" data-group-id="<?= $group['id'] ?>">
                                    </td>
                                    <td colspan="2"></td>
                                </tr>
                                <?php foreach ($group['items'] as $item): ?>
                                    <?php renderChecklistRow($no++, $item['name'], 'item', $item['id'], $item['status'], $item['notes'], $group['id']); ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<div class="summary-card">
    <h4 class="table-title" style="padding:0 0 var(--space-4) 0;border:0">Rangkuman Hasil Akhir</h4>
    <div class="top-info-grid">
        <div class="info-box">
            <span>Total Item</span>
            <strong id="summaryTotal"><?= $data['summary']['total'] ?></strong>
        </div>
        <div class="info-box">
            <span>Sesuai</span>
            <strong id="summaryOk"><?= $data['summary']['ok'] ?></strong>
        </div>
        <div class="info-box">
            <span>Tidak Sesuai</span>
            <strong id="summaryNotOk"><?= $data['summary']['not_ok'] ?></strong>
        </div>
        <div class="info-box">
            <span>Status</span>
            <strong>
                <span id="summaryStatus" class="badge-pill <?= $statusBadgeClass[$data['summary']['label']] ?? 'bg-warning-light' ?>">
                    <?= htmlspecialchars($data['summary']['label']) ?>
                </span>
            </strong>
        </div>
    </div>

    <div class="form-group" style="margin-bottom:var(--space-5)">
        <label for="summary_note">Catatan Umum</label>
        <textarea id="summary_note" class="form-control-custom" rows="3" placeholder="Catatan tambahan untuk pengecekan ini..."><?= htmlspecialchars($data['branch_check']['summary'] ?? '') ?></textarea>
    </div>

    <button type="button" class="btn-success-custom" onclick="saveBranchCheck()">
        <i class="fas fa-save"></i> Simpan Pengecekan
    </button>
</div>

<div class="summary-card" id="photoSection">
    <h4 class="table-title" style="padding:0 0 var(--space-4) 0;border:0">Foto Pengecekan (Opsional)</h4>

    <?php if (empty($data['branch_check'])): ?>
        <p class="cell-empty" style="padding:0">Simpan pengecekan terlebih dahulu untuk bisa menambahkan foto.</p>
    <?php else: ?>
        <div id="photoGrid" style="display:flex;flex-wrap:wrap;gap:var(--space-3);margin-bottom:var(--space-4)">
            <?php foreach ($data['photos'] as $photo): ?>
                <div class="photo-thumb" data-photo-id="<?= $photo['id'] ?>">
                    <a href="<?= htmlspecialchars($photo['url']) ?>" target="_blank">
                        <img src="<?= htmlspecialchars($photo['url']) ?>" alt="Foto pengecekan">
                    </a>
                    <button type="button" class="photo-thumb-remove" title="Hapus" onclick="deletePhoto(<?= $photo['id'] ?>, this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="form-group" style="max-width:360px">
            <label for="photo_input">Tambah Foto</label>
            <input type="file" id="photo_input" class="form-control" accept="image/jpeg,image/png,image/webp">
        </div>
        <button type="button" class="btn-secondary-custom" style="margin-top:var(--space-3)" onclick="uploadPhoto(<?= $data['branch_check']['id'] ?>)">
            <i class="fas fa-upload"></i> Upload Foto
        </button>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.checklist-item-row').forEach(function (row) {
    var ok = row.querySelector('.row-ok');
    var notOk = row.querySelector('.row-notok');
    ok.addEventListener('change', function () {
        if (this.checked) notOk.checked = false;
    });
    notOk.addEventListener('change', function () {
        if (this.checked) ok.checked = false;
    });
});

document.querySelectorAll('.group-toggle').forEach(function (cb) {
    cb.addEventListener('change', function () {
        var groupId = this.dataset.groupId;
        var checked = this.checked;
        document.querySelectorAll('.checklist-item-row[data-parent-group="' + groupId + '"]').forEach(function (row) {
            row.querySelector('.row-ok').checked = checked;
            if (checked) row.querySelector('.row-notok').checked = false;
        });
    });
});

function saveBranchCheck() {
    var items = [];
    document.querySelectorAll('.checklist-item-row').forEach(function (row) {
        var ok = row.querySelector('.row-ok').checked;
        var notOk = row.querySelector('.row-notok').checked;
        items.push({
            kind: row.dataset.kind,
            id: row.dataset.id,
            status: ok ? 'ok' : (notOk ? 'not_ok' : null),
            notes: row.querySelector('.row-notes').value
        });
    });

    var payload = {
        store_id: document.getElementById('store_id').value,
        check_date: document.getElementById('check_date').value,
        summary_note: document.getElementById('summary_note').value,
        items: items
    };

    fetch('/action?action=save_branch_check', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: data.message,
                    timer: 1500,
                    showConfirmButton: false,
                    customClass: { popup: 'rounded-4' }
                }).then(() => location.reload());
            } else {
                Swal.fire('Gagal!', data.message, 'error');
            }
        })
        .catch(() => Swal.fire('Error!', 'Terjadi kesalahan sistem', 'error'));
}function uploadPhoto(branchCheckId) {
    var input = document.getElementById('photo_input');
    if (!input.files || !input.files[0]) {
        Swal.fire('Pilih foto dulu', '', 'warning');
        return;
    }

    var formData = new FormData();
    formData.append('branch_check_id', branchCheckId);
    formData.append('photo', input.files[0]);

    fetch('/action?action=upload_branch_check_photo', {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                input.value = '';
                var grid = document.getElementById('photoGrid');
                var div = document.createElement('div');
                div.className = 'photo-thumb';
                div.setAttribute('data-photo-id', data.id);
                div.innerHTML = '<a href="' + data.url + '" target="_blank"><img src="' + data.url + '" alt="Foto pengecekan"></a>' +
                    '<button type="button" class="photo-thumb-remove" title="Hapus" onclick="deletePhoto(' + data.id + ', this)"><i class="fas fa-times"></i></button>';
                grid.appendChild(div);
            } else {
                Swal.fire('Gagal!', data.message, 'error');
            }
        })
        .catch(() => Swal.fire('Error!', 'Terjadi kesalahan sistem', 'error'));
}

function deletePhoto(id, btn) {
    Swal.fire({
        title: 'Hapus Foto?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Hapus',
        cancelButtonText: 'Batal',
        customClass: { popup: 'rounded-4' }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('/action?action=delete_branch_check_photo', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ id: id })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        btn.closest('.photo-thumb').remove();
                    } else {
                        Swal.fire('Gagal!', data.message, 'error');
                    }
                })
                .catch(() => Swal.fire('Error!', 'Terjadi kesalahan sistem', 'error'));
        }
    });
}
</script>
