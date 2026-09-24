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

// NOTE: this now expects 4 tiers instead of the old 3 ('Baik' / 'Perlu Perhatian' / 'Belum Lengkap').
// BranchCheckController::getIndexData() must be updated to return one of these 4 labels
// in $data['summary']['label'] for the badge to pick the right color.
$statusBadgeClass = [
    'Kurang' => 'bg-danger-light',
    'Cukup' => 'bg-warning-light',
    'Baik' => 'bg-info-light',
    'Sangat Baik' => 'bg-success-light',
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
        <button type="button" class="btn-secondary-custom" onclick="exportPdf()">
            <i class="fas fa-file-pdf"></i> Export PDF
        </button>
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
                            <td><?= htmlspecialchars($historyRow['checked_by_name'] ?? '-') ?></td>
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
    <div class="table-container checklist-table-container">
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

<div class="summary-card" id="problemItemsCard">
    <h4 class="table-title" style="padding:0 0 var(--space-4) 0;border:0;color:#b91c1c">
        <i class="fas fa-triangle-exclamation"></i> Rincian Item Bermasalah
    </h4>
    <div id="problemItemsList">
        <p class="cell-empty" style="padding:0">Tidak ada item bermasalah.</p>
    </div>
</div>

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

        <div class="form-group" style="margin-top:var(--space-4)">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:normal">
                <input type="checkbox" id="includePhotosInPdf" checked>
                Sertakan foto pengecekan ini saat Export PDF
            </label>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script>
document.querySelectorAll('.checklist-item-row').forEach(function (row) {
    var ok = row.querySelector('.row-ok');
    var notOk = row.querySelector('.row-notok');
    var notes = row.querySelector('.row-notes');
    ok.addEventListener('change', function () {
        if (this.checked) notOk.checked = false;
        renderProblemItems();
    });
    notOk.addEventListener('change', function () {
        if (this.checked) ok.checked = false;
        renderProblemItems();
    });
    notes.addEventListener('input', renderProblemItems);
});

document.querySelectorAll('.group-toggle').forEach(function (cb) {
    cb.addEventListener('change', function () {
        var groupId = this.dataset.groupId;
        var checked = this.checked;
        document.querySelectorAll('.checklist-item-row[data-parent-group="' + groupId + '"]').forEach(function (row) {
            row.querySelector('.row-ok').checked = checked;
            if (checked) row.querySelector('.row-notok').checked = false;
        });
        renderProblemItems();
    });
});

// Render kartu "Rincian Item Bermasalah" di halaman web, bukan cuma di PDF.
// Dipanggil setiap ada perubahan status/keterangan supaya selalu up-to-date.
function escapeHtmlText(str) {
    var div = document.createElement('div');
    div.textContent = str == null ? '' : str;
    return div.innerHTML;
}

function renderProblemItems() {
    var listEl = document.getElementById('problemItemsList');
    if (!listEl) return;

    var rows = [];
    document.querySelectorAll('.checklist-item-row').forEach(function (row) {
        var notOk = row.querySelector('.row-notok');
        if (notOk && notOk.checked) {
            var container = row.closest('.checklist-table-container');
            var tableTitleEl = container ? container.querySelector('.table-title') : null;
            var noCell = row.querySelector('td[data-label="No"]');
            var nameCell = row.querySelector('td[data-label="List Check"]');
            var notesInput = row.querySelector('.row-notes');
            rows.push({
                table: tableTitleEl ? tableTitleEl.textContent.trim() : '',
                no: noCell ? noCell.textContent.trim() : '',
                name: nameCell ? nameCell.textContent.trim() : '',
                notes: (notesInput && notesInput.value) ? notesInput.value : '-'
            });
        }
    });

    if (rows.length === 0) {
        listEl.innerHTML = '<p class="cell-empty" style="padding:0">Tidak ada item bermasalah.</p>';
        return;
    }

    var html = '<div class="table-scroll"><table class="table-modern">' +
        '<thead><tr><th class="col-no">No</th><th>Tabel</th><th>List Check</th><th>Keterangan</th></tr></thead><tbody>';
    rows.forEach(function (r) {
        html += '<tr style="color:#b91c1c">' +
            '<td data-label="No">' + escapeHtmlText(r.no) + '</td>' +
            '<td data-label="Tabel">' + escapeHtmlText(r.table) + '</td>' +
            '<td data-label="List Check">' + escapeHtmlText(r.name) + '</td>' +
            '<td data-label="Keterangan">' + escapeHtmlText(r.notes) + '</td>' +
            '</tr>';
    });
    html += '</tbody></table></div>';
    listEl.innerHTML = html;
}

renderProblemItems();

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

var checkedByName = <?= json_encode($data['branch_check']['checked_by_name'] ?? '') ?>;

function collectChecklistForExport() {
    var tables = [];
    document.querySelectorAll('.checklist-table-container').forEach(function (container) {
        var titleEl = container.querySelector('.table-title');
        var rows = [];
        container.querySelectorAll('tbody tr').forEach(function (tr) {
            if (tr.classList.contains('checklist-category-row')) {
                rows.push({ type: 'category', text: tr.querySelector('td').textContent.trim() });
            } else if (tr.classList.contains('checklist-group-row')) {
                rows.push({ type: 'group', text: tr.querySelector('td').textContent.trim() });
            } else if (tr.classList.contains('checklist-item-row')) {
                var noCell = tr.querySelector('td[data-label="No"]');
                var nameCell = tr.querySelector('td[data-label="List Check"]');
                var ok = tr.querySelector('.row-ok').checked;
                var notOk = tr.querySelector('.row-notok').checked;
                var notes = tr.querySelector('.row-notes').value;
                rows.push({
                    type: 'item',
                    no: noCell ? noCell.textContent.trim() : '',
                    name: nameCell ? nameCell.textContent.trim() : '',
                    status: ok ? 'Sesuai' : (notOk ? 'Tidak Sesuai' : '-'),
                    notes: notes || '-'
                });
            }
        });
        tables.push({ title: titleEl ? titleEl.textContent.trim() : '', rows: rows });
    });
    return tables;
}

// Kumpulkan semua item yang statusnya "Tidak Sesuai" (bermasalah) dari semua tabel,
// beserta keterangannya jika ada, untuk ditampilkan sebagai rincian di PDF.
function collectProblemItems(tables) {
    var problems = [];
    tables.forEach(function (table) {
        table.rows.forEach(function (row) {
            if (row.type === 'item' && row.status === 'Tidak Sesuai') {
                problems.push({
                    table: table.title,
                    no: row.no,
                    name: row.name,
                    notes: (row.notes && row.notes !== '-') ? row.notes : '-'
                });
            }
        });
    });
    return problems;
}

function formatIndonesianDate(dateStr) {
    if (!dateStr) return '-';
    var months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    var parts = dateStr.split('-');
    var y = parseInt(parts[0], 10);
    var m = parseInt(parts[1], 10) - 1;
    var d = parseInt(parts[2], 10);
    if (isNaN(y) || isNaN(m) || isNaN(d) || !months[m]) return dateStr;
    return d + ' ' + months[m] + ' ' + y;
}

function loadImageAsDataUrl(url) {
    return fetch(url)
        .then(function (res) {
            if (!res.ok) throw new Error('fetch failed');
            return res.blob();
        })
        .then(function (blob) {
            return new Promise(function (resolve, reject) {
                var reader = new FileReader();
                reader.onloadend = function () { resolve(reader.result); };
                reader.onerror = reject;
                reader.readAsDataURL(blob);
            });
        });
}

async function exportPdf() {
    var jsPDF = window.jspdf.jsPDF;
    var doc = new jsPDF({ unit: 'mm', format: 'a4' });
    var pageWidth = doc.internal.pageSize.getWidth();
    var pageHeight = doc.internal.pageSize.getHeight();
    var marginLeft = 14;
    var labelX = marginLeft;
    var colonX = marginLeft + 42;
    var valueX = marginLeft + 45;

    var storeSelect = document.getElementById('store_id');
    var storeName = storeSelect.options[storeSelect.selectedIndex] ? storeSelect.options[storeSelect.selectedIndex].text : '-';
    var checkDate = document.getElementById('check_date').value;
    var checkDateDisplay = formatIndonesianDate(checkDate);

    doc.setFontSize(14);
    doc.setFont(undefined, 'bold');
    doc.text('LAPORAN CHECKLIST PENGECEKAN CABANG', pageWidth / 2, 16, { align: 'center' });

    doc.setFontSize(10);
    doc.setFont(undefined, 'normal');
    doc.text('Nama Toko', labelX, 26);
    doc.text(':', colonX, 26);
    doc.text(storeName, valueX, 26);
    doc.text('Tanggal Pengecekan', labelX, 32);
    doc.text(':', colonX, 32);
    doc.text(checkDateDisplay, valueX, 32);
    doc.text('Diperiksa Oleh', labelX, 38);
    doc.text(':', colonX, 38);
    doc.text(checkedByName || '-', valueX, 38);

    var currentY = 46;
    var tables = collectChecklistForExport();

    tables.forEach(function (table) {
        doc.setFontSize(11);
        doc.setFont(undefined, 'bold');
        doc.text(table.title, marginLeft, currentY);
        currentY += 3;

        var body = [];
        table.rows.forEach(function (row) {
            if (row.type === 'category') {
                body.push([{ content: row.text, colSpan: 4, styles: { fontStyle: 'bold', fillColor: [226, 232, 240], textColor: [15, 23, 42] } }]);
            } else if (row.type === 'group') {
                body.push([{ content: row.text, colSpan: 4, styles: { fontStyle: 'bold', fillColor: [241, 245, 249], textColor: [51, 65, 85] } }]);
            } else {
                // Baris item yang "Tidak Sesuai" ditandai merah agar langsung terlihat di tabel utama.
                if (row.status === 'Tidak Sesuai') {
                    body.push([
                        { content: row.no, styles: { textColor: [185, 28, 28] } },
                        { content: row.name, styles: { textColor: [185, 28, 28] } },
                        { content: row.status, styles: { textColor: [185, 28, 28], fontStyle: 'bold' } },
                        { content: row.notes, styles: { textColor: [185, 28, 28] } }
                    ]);
                } else {
                    body.push([row.no, row.name, row.status, row.notes]);
                }
            }
        });

        doc.autoTable({
            startY: currentY,
            margin: { left: marginLeft, right: marginLeft },
            head: [['No', 'List Check', 'Status', 'Keterangan']],
            body: body,
            theme: 'grid',
            styles: { fontSize: 8, cellPadding: 1.5 },
            headStyles: { fillColor: [59, 130, 246], textColor: 255 },
            columnStyles: {
                0: { cellWidth: 10 },
                2: { cellWidth: 25 },
                3: { cellWidth: 45 }
            }
        });

        currentY = doc.lastAutoTable.finalY + 8;
    });

    // Rincian item bermasalah (semua yang "Tidak Sesuai"), lengkap dengan keterangannya jika ada,
    // dikumpulkan lintas tabel dan ditampilkan terpisah dengan warna merah agar mudah dipantau.
    var problemItems = collectProblemItems(tables);
    if (problemItems.length > 0) {
        if (currentY > pageHeight - 60) {
            doc.addPage();
            currentY = 20;
        }

        doc.setFontSize(11);
        doc.setFont(undefined, 'bold');
        doc.setTextColor(220, 38, 38);
        doc.text('Rincian Item Bermasalah', marginLeft, currentY);
        doc.setTextColor(0, 0, 0);
        currentY += 6;

        doc.autoTable({
            startY: currentY,
            margin: { left: marginLeft, right: marginLeft },
            theme: 'grid',
            styles: { fontSize: 8, cellPadding: 2, textColor: [185, 28, 28] },
            head: [['No', 'Tabel', 'List Check', 'Keterangan']],
            headStyles: { fillColor: [220, 38, 38], textColor: 255 },
            columnStyles: {
                0: { cellWidth: 10 },
                1: { cellWidth: 35 },
                3: { cellWidth: 55 }
            },
            body: problemItems.map(function (p) {
                return [p.no, p.table, p.name, p.notes];
            })
        });
        currentY = doc.lastAutoTable.finalY + 8;
    }

    var summaryTotal = document.getElementById('summaryTotal').textContent.trim();
    var summaryOk = document.getElementById('summaryOk').textContent.trim();
    var summaryNotOk = document.getElementById('summaryNotOk').textContent.trim();
    var summaryStatus = document.getElementById('summaryStatus').textContent.trim();
    var summaryNote = document.getElementById('summary_note').value || '-';

    if (currentY > pageHeight - 70) {
        doc.addPage();
        currentY = 20;
    }

    doc.setFontSize(11);
    doc.setFont(undefined, 'bold');
    doc.text('Rangkuman Hasil Akhir', marginLeft, currentY);
    currentY += 6;

    doc.autoTable({
        startY: currentY,
        margin: { left: marginLeft, right: marginLeft },
        theme: 'grid',
        styles: { fontSize: 9, cellPadding: 2.5 },
        head: [['Keterangan', 'Jumlah / Nilai']],
        headStyles: { fillColor: [100, 116, 139], textColor: 255 },
        columnStyles: {
            0: { cellWidth: 60, fontStyle: 'bold' },
            1: { cellWidth: 60 }
        },
        body: [
            ['Total Item Diperiksa', summaryTotal],
            ['Jumlah Sesuai', summaryOk],
            ['Jumlah Tidak Sesuai', summaryNotOk],
            ['Status Akhir', summaryStatus]
        ]
    });
    currentY = doc.lastAutoTable.finalY + 6;

    doc.setFont(undefined, 'bold');
    doc.setFontSize(9);
    doc.text('Catatan Umum:', marginLeft, currentY);
    currentY += 5;
    doc.setFont(undefined, 'normal');
    var noteLines = doc.splitTextToSize(summaryNote, pageWidth - marginLeft * 2);
    doc.text(noteLines, marginLeft, currentY);
    currentY += noteLines.length * 5 + 6;

    // Foto hanya disertakan ke PDF jika checkbox "Sertakan foto di PDF" ada dan dicentang.
    // Jika elemennya tidak ada (belum ada pengecekan tersimpan) dianggap tidak menyertakan foto.
    var includePhotosCheckbox = document.getElementById('includePhotosInPdf');
    var shouldIncludePhotos = includePhotosCheckbox ? includePhotosCheckbox.checked : false;
    var photoImgs = shouldIncludePhotos ? document.querySelectorAll('#photoGrid img') : [];

    if (photoImgs.length > 0) {
        if (currentY > pageHeight - 70) {
            doc.addPage();
            currentY = 20;
        }
        doc.setFontSize(11);
        doc.setFont(undefined, 'bold');
        doc.text('Foto Pengecekan', marginLeft, currentY);
        currentY += 6;

        var imgWidth = 80;
        var imgHeight = 55;
        var gap = 6;
        var col = 0;

        for (var i = 0; i < photoImgs.length; i++) {
            if (currentY + imgHeight > pageHeight - 20) {
                doc.addPage();
                currentY = 20;
                col = 0;
            }

            var x = marginLeft + col * (imgWidth + gap);
            var dataUrl = null;
            try {
                dataUrl = await loadImageAsDataUrl(photoImgs[i].src);
            } catch (e) {
                dataUrl = null;
            }

            if (dataUrl) {
                var format = 'JPEG';
                if (dataUrl.indexOf('image/png') !== -1) format = 'PNG';
                try {
                    doc.addImage(dataUrl, format, x, currentY, imgWidth, imgHeight);
                } catch (e) {
                    doc.setDrawColor(200);
                    doc.rect(x, currentY, imgWidth, imgHeight);
                    doc.setFontSize(8);
                    doc.text('(Foto tidak dapat dimuat)', x + 5, currentY + imgHeight / 2);
                }
            } else {
                doc.setDrawColor(200);
                doc.rect(x, currentY, imgWidth, imgHeight);
                doc.setFontSize(8);
                doc.text('(Foto tidak dapat dimuat)', x + 5, currentY + imgHeight / 2);
            }

            col++;
            if (col >= 2) {
                col = 0;
                currentY += imgHeight + gap;
            }
        }

        if (col !== 0) {
            currentY += imgHeight + gap;
        }
        currentY += 4;
    }

    if (currentY > pageHeight - 45) {
        doc.addPage();
        currentY = 30;
    }

    var halfWidth = (pageWidth - marginLeft * 2) / 2;
    var leftCenterX = marginLeft + halfWidth / 2;
    var rightCenterX = marginLeft + halfWidth + halfWidth / 2;

    doc.setFontSize(10);
    doc.setFont(undefined, 'normal');
    doc.text('Mengetahui,', leftCenterX, currentY, { align: 'center' });
    doc.text('Diperiksa oleh,', rightCenterX, currentY, { align: 'center' });
    currentY += 25;
    doc.text('( ......................... )', leftCenterX, currentY, { align: 'center' });
    doc.text('( ' + (checkedByName || '.........................') + ' )', rightCenterX, currentY, { align: 'center' });

    var fileStore = storeName.replace(/[^A-Za-z0-9]+/g, '_');
    doc.save('checklist_' + fileStore + '_' + checkDate + '.pdf');
}
</script>