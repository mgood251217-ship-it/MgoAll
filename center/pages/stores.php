<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: /center/login");
    exit;
}

require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../functions/helpers.php';
require_once __DIR__ . '/../controllers/StoreController.php';

$access = isset($_SESSION['admin_logged_in']['access']) ? startEnk('dek', $_SESSION['admin_logged_in']['access']) : '';

$controller = new StoreController($koneksi);
$data = $controller->getIndexData($access);
$stores = $data['stores'];
$all_users = $data['all_users'];
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }
    .page-header h2 {
        margin: 0;
        color: #0f172a;
        font-size: 1.5rem;
        font-weight: 600;
    }
    .btn-primary-custom {
        background-color: #3b82f6;
        color: #ffffff;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 500;
        font-size: 0.95rem;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    .btn-primary-custom:hover {
        background-color: #2563eb;
        color: #ffffff;
        transform: translateY(-1px);
    }
    .table-container {
        background-color: #ffffff;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .table-modern {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        margin-bottom: 0;
    }
    .table-modern th, .table-modern td {
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: middle;
    }
    .table-modern th {
        background-color: #f8fafc;
        color: #475569;
        font-weight: 600;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .table-modern tbody tr:last-child td {
        border-bottom: none;
    }
    .table-modern tbody tr:hover {
        background-color: #f1f5f9;
    }
    .table-modern td {
        color: #0f172a;
        font-size: 0.95rem;
    }
    .badge-custom {
        padding: 6px 12px;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        background-color: #dbeafe;
        color: #1d4ed8;
        display: inline-block;
    }
    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: none;
        transition: all 0.2s ease;
        color: #ffffff;
        cursor: pointer;
    }
    .btn-edit { background-color: #3b82f6; }
    .btn-edit:hover { background-color: #2563eb; }
    .btn-kelola { 
        background-color: #ef4444; 
        font-size: 0.875rem; 
        width: auto; 
        padding: 0 16px; 
        font-weight: 500;
    }
    .btn-kelola:hover { background-color: #dc2626; }
    
    .modal-content-custom {
        border: none;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
    .modal-header-custom {
        background-color: #ffffff;
        border-bottom: 1px solid #f1f5f9;
        padding: 20px 24px;
    }
    .modal-title-custom {
        font-weight: 600;
        color: #0f172a;
        font-size: 1.25rem;
        margin: 0;
    }
    .modal-body-custom {
        padding: 24px;
        background-color: #f8fafc;
    }
    .modal-footer-custom {
        border-top: 1px solid #f1f5f9;
        padding: 16px 24px;
        background-color: #ffffff;
    }
    .form-label-custom {
        font-weight: 500;
        font-size: 0.875rem;
        color: #334155;
        margin-bottom: 8px;
        display: block;
    }
    .form-control-custom {
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        padding: 10px 16px;
        font-size: 0.95rem;
        background-color: #ffffff;
        transition: all 0.2s ease;
        width: 100%;
        box-sizing: border-box;
    }
    .form-control-custom:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
</style>

<div class="page-header">
    <h2>Cabang & Toko</h2>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalTambahToko">
        <i class="fas fa-plus"></i> Tambah Toko
    </button>
</div>

<div class="table-container">
    <div style="overflow-x: auto;">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Nama</th>
                    <th>Cabang</th>
                    <th>Alamat</th>
                    <th>Nomor</th>
                    <th>Email</th>
                    <th>Manager</th>
                    <th>Karyawan</th>
                    <th style="text-align: center;">Aksi</th>
                    <th style="text-align: center;">Kelola</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($stores)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; color: #64748b; padding: 32px 0;">Tidak ada data toko ditemukan</td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($stores as $row): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                            <td><?= htmlspecialchars($row['branch']) ?></td>
                            <td><?= htmlspecialchars($row['address']) ?></td>
                            <td><?= htmlspecialchars($row['nomor']) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td><?= htmlspecialchars($row['owner_name'] ?? '-') ?></td>
                            <td><span class="badge-custom"><?= (int)$row['total_karyawan'] ?> Orang</span></td>
                            <td style="text-align: center;">
                                <button type="button" class="btn-action btn-edit" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editModal"
                                    data-store-id="<?= $row['store_id'] ?>"
                                    data-name="<?= htmlspecialchars($row['name']) ?>"
                                    data-branch="<?= htmlspecialchars($row['branch']) ?>"
                                    data-address="<?= htmlspecialchars($row['address']) ?>"
                                    data-nomor="<?= htmlspecialchars($row['nomor']) ?>"
                                    data-email="<?= htmlspecialchars($row['email']) ?>"
                                    data-owner-id="<?= $row['owner_id'] ?>">
                                    <i class="fas fa-pen"></i>
                                </button>
                            </td>
                            <td style="text-align: center;">
                                <form class="kelolaForm" style="margin: 0; display: inline-block;">
                                    <input type="hidden" name="user_id" value="<?= $row['owner_id'] ?>">
                                    <button type="submit" class="btn-action btn-kelola">Kelola</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form action="/center/action?action=edit_store" method="POST">
                <input type="hidden" name="store_id" id="edit_store_id">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title-custom">Edit Cabang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Nama Toko</label>
                        <input type="text" name="name" id="edit_name" class="form-control-custom" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Cabang</label>
                        <input type="text" name="branch" id="edit_branch" class="form-control-custom" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Alamat</label>
                        <textarea name="address" id="edit_address" class="form-control-custom" rows="2" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Nomor</label>
                        <input type="text" name="nomor" id="edit_nomor" class="form-control-custom" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control-custom">
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Manager</label>
                        <select name="owner_id" id="edit_owner_id" class="form-control-custom form-select">
                            <option value="">-- Pilih Manager --</option>
                            <?php foreach ($all_users as $mgr): ?>
                                <option value="<?= $mgr['user_id'] ?>">
                                    <?= htmlspecialchars($mgr['name']) ?> (<?= htmlspecialchars($mgr['username']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer modal-footer-custom">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Batal</button>
                    <button type="submit" class="btn btn-success" style="border-radius: 8px;">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambahToko" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content modal-content-custom" method="post" action="/center/action?action=add_store" enctype="multipart/form-data">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title-custom">Tambah Toko Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-body-custom">
                <div class="mb-3">
                    <label class="form-label-custom">Nama Toko</label>
                    <input type="text" class="form-control-custom" name="name" required style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase();">
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Cabang</label>
                    <input type="text" class="form-control-custom" name="branch" required style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase();">
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Alamat</label>
                    <textarea class="form-control-custom" name="address" rows="2"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Nomor</label>
                    <input type="number" class="form-control-custom" name="nomor">
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Email</label>
                    <input type="email" class="form-control-custom" name="email">
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Logo</label>
                    <input type="file" class="form-control-custom" name="logo" accept="image/*">
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Manager</label>
                    <select class="form-control-custom form-select" name="owner_id">
                        <option value="">-- Pilih Manager --</option>
                        <?php foreach ($all_users as $user): ?>
                            <option value="<?= $user['user_id'] ?>"><?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['username']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer modal-footer-custom">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Batal</button>
                <button type="submit" class="btn-primary-custom" style="padding: 8px 20px;">Simpan Toko</button>
            </div>
        </form>
    </div>
</div>

<?php if (isset($_SESSION['swal_success'])): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: <?= json_encode($_SESSION['swal_success']) ?>,
        timer: 3500,
        timerProgressBar: true,
        showConfirmButton: false,
        customClass: { popup: 'rounded-4' }
    });
</script>
<?php unset($_SESSION['swal_success']); ?>
<?php elseif (isset($_SESSION['swal_error'])): ?>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: <?= json_encode($_SESSION['swal_error']) ?>,
        timer: 3500,
        timerProgressBar: true,
        showConfirmButton: false,
        customClass: { popup: 'rounded-4' }
    });
</script>
<?php unset($_SESSION['swal_error']); ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            
            document.getElementById('edit_store_id').value = button.getAttribute('data-store-id');
            document.getElementById('edit_name').value = button.getAttribute('data-name');
            document.getElementById('edit_branch').value = button.getAttribute('data-branch');
            document.getElementById('edit_address').value = button.getAttribute('data-address');
            document.getElementById('edit_nomor').value = button.getAttribute('data-nomor');
            document.getElementById('edit_email').value = button.getAttribute('data-email');
            document.getElementById('edit_owner_id').value = button.getAttribute('data-owner-id');
        });
    }
});

document.addEventListener('submit', function(e) {
    if (e.target && e.target.classList.contains('kelolaForm')) {
        e.preventDefault();
        
        const form = e.target;
        const formData = new URLSearchParams(new FormData(form)).toString();

        fetch('/center/action?action=set_session', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            window.open('https://mgood.my.id/admin/customer/');
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: 'Gagal set session!',
                customClass: { popup: 'rounded-4' }
            });
        });
    }
});
</script>