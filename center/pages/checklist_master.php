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
require_once __DIR__ . '/../controllers/ChecklistMasterController.php';

$controller = new ChecklistMasterController($koneksi);
$data = $controller->getIndexData();
?>

<div class="page-header">
    <h2>Kelola Struktur Checklist</h2>
    <div class="page-actions">
        <button type="button" class="btn-primary-custom" onclick="addTable()">
            <i class="fas fa-plus"></i> Tambah Tabel
        </button>
    </div>
</div>

<?php if (empty($data['tables'])): ?>
    <div class="table-container">
        <div class="cell-empty">Belum ada tabel checklist</div>
    </div>
<?php endif; ?>

<?php foreach ($data['tables'] as $table): ?>
    <div class="table-container">
        <h4 class="table-title" style="display:flex;align-items:center;justify-content:space-between">
            <span><?= htmlspecialchars($table['name']) ?></span>
            <span>
                <button type="button" class="btn-action btn-open" title="Edit Tabel" onclick="editTable(<?= $table['id'] ?>, '<?= htmlspecialchars($table['name'], ENT_QUOTES) ?>')">
                    <i class="fas fa-pen"></i>
                </button>
                <button type="button" class="btn-action btn-danger" title="Hapus Tabel" onclick="deleteTable(<?= $table['id'] ?>)">
                    <i class="fas fa-trash"></i>
                </button>
                <button type="button" class="btn-action btn-open" title="Tambah Kategori" onclick="addCategory(<?= $table['id'] ?>)">
                    <i class="fas fa-plus"></i>
                </button>
            </span>
        </h4>

        <div style="padding:var(--space-5)">
            <?php if (empty($table['categories'])): ?>
                <div class="cell-empty">Belum ada kategori pada tabel ini</div>
            <?php endif; ?>

            <?php foreach ($table['categories'] as $category): ?>
                <div class="checklist-master-category">
                    <div class="checklist-master-row">
                        <strong><?= htmlspecialchars($category['name']) ?></strong>
                        <span>
                            <button type="button" class="btn-action btn-open" title="Edit Kategori" onclick="editCategory(<?= $category['id'] ?>, '<?= htmlspecialchars($category['name'], ENT_QUOTES) ?>')">
                                <i class="fas fa-pen"></i>
                            </button>
                            <button type="button" class="btn-action btn-danger" title="Hapus Kategori" onclick="deleteCategory(<?= $category['id'] ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                            <button type="button" class="btn-action btn-open" title="Tambah Grup" onclick="addGroup(<?= $category['id'] ?>)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </span>
                    </div>

                    <?php foreach ($category['groups'] as $group): ?>
                        <div class="checklist-master-group">
                            <div class="checklist-master-row">
                                <span><?= htmlspecialchars($group['name']) ?></span>
                                <span>
                                    <button type="button" class="btn-action btn-open" title="Edit Grup" onclick="editGroup(<?= $group['id'] ?>, '<?= htmlspecialchars($group['name'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button type="button" class="btn-action btn-danger" title="Hapus Grup" onclick="deleteGroup(<?= $group['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button type="button" class="btn-action btn-open" title="Tambah Item" onclick="addItem(<?= $group['id'] ?>)">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </span>
                            </div>

                            <?php foreach ($group['items'] as $item): ?>
                                <div class="checklist-master-item">
                                    <span><?= htmlspecialchars($item['name']) ?></span>
                                    <span>
                                        <button type="button" class="btn-action btn-open" title="Edit Item" onclick="editItem(<?= $item['id'] ?>, '<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>')">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <button type="button" class="btn-action btn-danger" title="Hapus Item" onclick="deleteItem(<?= $item['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<script>
function promptAndPost(title, action, extraBody, currentValue) {
    Swal.fire({
        title: title,
        input: 'text',
        inputValue: currentValue || '',
        inputPlaceholder: 'Nama...',
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Simpan',
        cancelButtonText: 'Batal',
        customClass: { popup: 'rounded-4' },
        preConfirm: (name) => {
            if (!name) Swal.showValidationMessage('Nama wajib diisi!');
            return name;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            var body = Object.assign({}, extraBody, { name: result.value });
            postAction(action, body);
        }
    });
}

function confirmAndDelete(title, action, id) {
    Swal.fire({
        title: title,
        text: 'Data di dalamnya juga akan ikut tersembunyi.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Hapus',
        cancelButtonText: 'Batal',
        customClass: { popup: 'rounded-4' }
    }).then((result) => {
        if (result.isConfirmed) {
            postAction(action, { id: id });
        }
    });
}

function postAction(action, body) {
    fetch('/action?action=' + action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(body)
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                Swal.fire('Gagal!', data.message, 'error');
            }
        })
        .catch(() => Swal.fire('Error!', 'Terjadi kesalahan sistem', 'error'));
}

function addTable() {
    promptAndPost('Tambah Tabel', 'add_checklist_table', {});
}
function editTable(id, name) {
    promptAndPost('Edit Tabel', 'edit_checklist_table', { id: id }, name);
}
function deleteTable(id) {
    confirmAndDelete('Hapus Tabel?', 'delete_checklist_table', id);
}

function addCategory(tableId) {
    promptAndPost('Tambah Kategori', 'add_checklist_category', { table_id: tableId });
}
function editCategory(id, name) {
    promptAndPost('Edit Kategori', 'edit_checklist_category', { id: id }, name);
}
function deleteCategory(id) {
    confirmAndDelete('Hapus Kategori?', 'delete_checklist_category', id);
}

function addGroup(categoryId) {
    promptAndPost('Tambah Grup', 'add_checklist_group', { category_id: categoryId });
}
function editGroup(id, name) {
    promptAndPost('Edit Grup', 'edit_checklist_group', { id: id }, name);
}
function deleteGroup(id) {
    confirmAndDelete('Hapus Grup?', 'delete_checklist_group', id);
}

function addItem(groupId) {
    promptAndPost('Tambah Item', 'add_checklist_item', { group_id: groupId });
}
function editItem(id, name) {
    promptAndPost('Edit Item', 'edit_checklist_item', { id: id }, name);
}
function deleteItem(id) {
    confirmAndDelete('Hapus Item?', 'delete_checklist_item', id);
}
</script>
