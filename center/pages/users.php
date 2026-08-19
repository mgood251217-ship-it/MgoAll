<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: /center/login");
    exit;
}

require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../controllers/UserController.php';

$controller = new UserController($koneksi);
$data = $controller->getIndexData();
$groupedUsers = $data['groupedUsers'];
$deletedUsers = $data['deletedUsers'];
$stores = $data['stores'];
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
    .store-group-title {
        color: #334155;
        font-size: 1.15rem;
        font-weight: 600;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .store-group-title i {
        color: #3b82f6;
    }
    .store-group-title.deleted i {
        color: #ef4444;
    }
    .table-container {
        background-color: #ffffff;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        overflow: hidden;
        margin-bottom: 32px;
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
    .img-thumbnail-small {
        width: 44px;
        height: 44px;
        object-fit: cover;
        border-radius: 50%;
        border: 2px solid #e2e8f0;
    }
    .img-placeholder {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background-color: #e2e8f0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        font-size: 1.2rem;
    }
    .badge-role {
        padding: 6px 12px;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        background-color: #f1f5f9;
        color: #475569;
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
    .btn-edit { background-color: #f59e0b; }
    .btn-edit:hover { background-color: #d97706; }
    .btn-delete { background-color: #ef4444; }
    .btn-delete:hover { background-color: #dc2626; }
    .btn-restore { background-color: #10b981; }
    .btn-restore:hover { background-color: #059669; }
    
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
    <h2>User & Karyawan</h2>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalTambahUser">
        <i class="fas fa-plus"></i> Tambah User
    </button>
</div>

<?php if (empty($groupedUsers)): ?>
    <div class="table-container">
        <table class="table-modern">
            <tr>
                <td style="text-align: center; color: #64748b; padding: 32px 0;">Tidak ada data user aktif ditemukan</td>
            </tr>
        </table>
    </div>
<?php else: ?>
    <?php foreach ($groupedUsers as $storeName => $users): ?>
        <h4 class="store-group-title"><i class="fas fa-store"></i> <?= htmlspecialchars($storeName) ?></h4>
        <div class="table-container">
            <div style="overflow-x: auto;">
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th style="width: 80px;">Foto</th>
                            <th>Nama</th>
                            <th>Username</th>
                            <th>Peran</th>
                            <th style="text-align: right;">Aksi & Pindah Toko</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($users as $row): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <?php if (!empty($row['picture'])): ?>
                                        <img src="https://mgood.my.id/admin/assets/img/user/<?= htmlspecialchars($row['picture']) ?>" alt="Foto" class="img-thumbnail-small" />
                                    <?php else: ?>
                                        <div class="img-placeholder"><i class="fas fa-user"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                                <td><?= htmlspecialchars($row['username']) ?></td>
                                <td><span class="badge-role"><?= htmlspecialchars($row['role']) ?></span></td>
                                <td style="text-align: right;">
                                    <button type="button" class="btn-action btn-edit me-1" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editModal"
                                        data-user-id="<?= $row['user_id'] ?>"
                                        data-name="<?= htmlspecialchars($row['name']) ?>"
                                        data-username="<?= htmlspecialchars($row['username']) ?>"
                                        data-initial="<?= htmlspecialchars($row['initial']) ?>"
                                        data-role="<?= htmlspecialchars($row['role']) ?>"
                                        data-store-id="<?= $row['store_id'] ?>"
                                        title="Edit User">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    
                                    <button type="button" class="btn-action btn-delete me-2" 
                                        onclick="deleteUser(<?= $row['user_id'] ?>)" 
                                        title="Hapus User">
                                        <i class="fas fa-trash"></i>
                                    </button>

                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" style="border-radius: 6px;">
                                            Pindah
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 8px;">
                                            <?php foreach ($stores as $store): ?>
                                                <li>
                                                    <a class="dropdown-item py-2" href="#" onclick="changeStore(<?= $row['user_id'] ?>, <?= $store['store_id'] ?>, '<?= addslashes(htmlspecialchars($store['name'])) ?>'); return false;">
                                                        <?= htmlspecialchars($store['name']) ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($deletedUsers)): ?>
    <hr style="margin: 40px 0; border-color: #cbd5e1;">
    <h4 class="store-group-title deleted"><i class="fas fa-trash-alt"></i> User Terhapus</h4>
    <div class="table-container">
        <div style="overflow-x: auto;">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Toko Terakhir</th>
                        <th>Peran</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($deletedUsers as $row): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong style="color: #64748b; text-decoration: line-through;"><?= htmlspecialchars($row['name']) ?></strong></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td><?= htmlspecialchars($row['store_name'] ?: 'Tanpa Toko') ?></td>
                            <td><span class="badge-role"><?= htmlspecialchars($row['role']) ?></span></td>
                            <td style="text-align: right;">
                                <button type="button" class="btn-action btn-restore" 
                                    onclick="restoreUser(<?= $row['user_id'] ?>)" 
                                    title="Restore User">
                                    <i class="fas fa-undo"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="modal fade" id="modalTambahUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content modal-content-custom" method="post" action="/center/action?action=add_user" enctype="multipart/form-data">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title-custom">Tambah User Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-body-custom">
                <div class="mb-3">
                    <label class="form-label-custom">Nama Lengkap</label>
                    <input type="text" class="form-control-custom" name="name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Username</label>
                    <input type="text" class="form-control-custom" name="username" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Password</label>
                    <input type="password" class="form-control-custom" name="password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Initial (Singkatan Nama)</label>
                    <input type="text" class="form-control-custom" name="initial" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Peran</label>
                    <select name="role" class="form-control-custom form-select" required>
                        <option value="">-- Pilih Peran --</option>
                        <option value="ADMIN">ADMIN</option>
                        <option value="SETTING">SETTING</option>
                        <option value="ONLINE">ONLINE</option>
                        <option value="PRODUKSI">PRODUKSI</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Toko</label>
                    <select name="store_id" class="form-control-custom form-select" required>
                        <option value="">-- Pilih Toko --</option>
                        <?php foreach ($stores as $s): ?>
                            <option value="<?= $s['store_id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Foto (Otomatis Dikompres)</label>
                    <input type="file" class="form-control-custom" name="picture" accept="image/*">
                </div>
            </div>
            <div class="modal-footer modal-footer-custom">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Batal</button>
                <button type="submit" class="btn-primary-custom" style="padding: 8px 20px;">Simpan User</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content modal-content-custom" method="post" action="/center/action?action=edit_user" enctype="multipart/form-data">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title-custom">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-body-custom">
                <div class="mb-3">
                    <label class="form-label-custom">Nama Lengkap</label>
                    <input type="text" class="form-control-custom" name="name" id="edit_name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Username</label>
                    <input type="text" class="form-control-custom" name="username" id="edit_username" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Password (Kosongkan jika tidak diubah)</label>
                    <input type="password" class="form-control-custom" name="password" placeholder="***">
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Initial (Singkatan Nama)</label>
                    <input type="text" class="form-control-custom" name="initial" id="edit_initial" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Peran</label>
                    <select name="role" id="edit_role" class="form-control-custom form-select" required>
                        <option value="">-- Pilih Peran --</option>
                        <option value="ADMIN">ADMIN</option>
                        <option value="SETTING">SETTING</option>
                        <option value="ONLINE">ONLINE</option>
                        <option value="PRODUKSI">PRODUKSI</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Toko</label>
                    <select name="store_id" id="edit_store_id" class="form-control-custom form-select" required>
                        <option value="">-- Pilih Toko --</option>
                        <?php foreach ($stores as $s): ?>
                            <option value="<?= $s['store_id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Foto (Biarkan kosong jika tidak diubah)</label>
                    <input type="file" class="form-control-custom" name="picture" accept="image/*">
                </div>
            </div>
            <div class="modal-footer modal-footer-custom">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Batal</button>
                <button type="submit" class="btn btn-success" style="padding: 8px 20px; border-radius: 8px;">Simpan Perubahan</button>
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
            document.getElementById('edit_user_id').value = button.getAttribute('data-user-id');
            document.getElementById('edit_name').value = button.getAttribute('data-name');
            document.getElementById('edit_username').value = button.getAttribute('data-username');
            document.getElementById('edit_initial').value = button.getAttribute('data-initial');
            document.getElementById('edit_role').value = button.getAttribute('data-role');
            document.getElementById('edit_store_id').value = button.getAttribute('data-store-id');
        });
    }
});

function deleteUser(userId) {
    Swal.fire({
        title: 'Hapus User?',
        text: 'User ini akan dipindahkan ke daftar terhapus.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        customClass: { popup: 'rounded-4' }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('/center/action?action=delete_user', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ user_id: userId })
            })
            .then(res => res.text())
            .then(data => {
                if (data.trim() === 'OK') {
                    location.reload();
                } else {
                    Swal.fire('Gagal!', 'Gagal menghapus user.', 'error');
                }
            });
        }
    });
}

function restoreUser(userId) {
    Swal.fire({
        title: 'Restore User?',
        text: 'User akan dikembalikan ke daftar aktif.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Restore',
        cancelButtonText: 'Batal',
        customClass: { popup: 'rounded-4' }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('/center/action?action=restore_user', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ user_id: userId })
            })
            .then(res => res.text())
            .then(data => {
                if (data.trim() === 'OK') {
                    location.reload();
                } else {
                    Swal.fire('Gagal!', 'Gagal merestore user.', 'error');
                }
            });
        }
    });
}

function changeStore(userId, storeId, storeName) {
    Swal.fire({
        title: 'Konfirmasi',
        text: `Apakah Anda yakin ingin memindahkan user ke toko ${storeName}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Pindahkan',
        cancelButtonText: 'Batal',
        customClass: { popup: 'rounded-4' }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('/center/action?action=change_user_store', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ user_id: userId, store_id: storeId })
            })
            .then(res => res.text())
            .then(data => {
                if (data.trim() === 'OK') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: 'User berhasil dipindahkan.',
                        timer: 1500,
                        showConfirmButton: false,
                        customClass: { popup: 'rounded-4' }
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Gagal!', data, 'error');
                }
            });
        }
    });
}
</script>