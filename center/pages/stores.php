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
require_once __DIR__ . '/../controllers/StoreController.php';

$access = isset($_SESSION['admin_logged_in']['access']) ? startEnk('dek', $_SESSION['admin_logged_in']['access']) : '';

$controller = new StoreController($koneksi);
$data = $controller->getIndexData($access);
$stores = $data['stores'];
$all_users = $data['all_users'];
$_SESSION['store_copy_csrf'] = $_SESSION['store_copy_csrf'] ?? bin2hex(random_bytes(32));
?>



<div class="page-header">
    <h2>Cabang & Toko</h2>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalTambahToko">
        <i class="fas fa-plus"></i> Tambah Toko
    </button>
</div>

<div class="table-container">
    <div class="table-scroll">
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
                    <th class="migrated-style-95">Aksi</th>
                    <th class="migrated-style-95">Kelola</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($stores)): ?>
                    <tr>
                        <td class="migrated-style-80" colspan="10">Tidak ada data toko ditemukan</td>
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
                            <td class="migrated-style-95">
                                <button type="button" class="btn-action btn-warning"
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
                                <button type="button" class="btn-action btn-kelola btn-open" data-bs-toggle="modal"
                                    data-bs-target="#copyCatalogModal" data-store-id="<?= (int)$row['store_id'] ?>"
                                    data-store-name="<?= htmlspecialchars($row['name'] . ' - ' . $row['branch'], ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="fas fa-file-import me-1"></i> Impor Produk
                                </button>
                            </td>
                            <td class="migrated-style-95">
                                <form class="kelolaForm migrated-style-96">
                                    <input type="hidden" name="user_id" value="<?= $row['owner_id'] ?>">
                                    <button type="submit" class="btn btn-danger">Kelola</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="copyCatalogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content modal-content-custom" method="post" action="/action?action=copy_store_catalog" id="copyCatalogForm">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['store_copy_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="target_store_id" id="copy_target_store_id">
            <input type="hidden" name="confirmed" id="copy_confirmed" value="0">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title-custom">Impor Produk dari Toko Lain</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-body-custom">
                <p>Toko tujuan: <strong id="copy_target_store_name"></strong></p>
                <div class="mb-3">
                    <label for="copy_source_store_id" class="form-label-custom">Toko sumber</label>
                    <select id="copy_source_store_id" name="source_store_id" class="form-control-custom form-select" required>
                        <option value="">-- Pilih Toko Sumber --</option>
                        <?php foreach ($stores as $store): ?>
                            <option value="<?= (int)$store['store_id'] ?>"><?= htmlspecialchars($store['name'] . ' - ' . $store['branch'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="copy_password" class="form-label-custom">Sandi akun Anda</label>
                    <input type="password" id="copy_password" name="password" class="form-control-custom" autocomplete="current-password" required>
                    <small>Masukkan sandi akun administrator yang sedang login.</small>
                </div>
                <p class="mb-0">Tambahkan kategori, produk, dan finishing dari toko sumber ke toko ini, termasuk harga dan stok. Data toko sumber tetap utuh. Toko tujuan harus belum memiliki kategori, produk, atau finishing.</p>
            </div>
            <div class="modal-footer modal-footer-custom">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-primary-custom">Impor Produk</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form action="/action?action=edit_store" method="POST">
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
                    <button type="button" class="btn btn-secondary migrated-style-20" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success migrated-style-20">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambahToko" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content modal-content-custom" method="post" action="/action?action=add_store" enctype="multipart/form-data">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title-custom">Tambah Toko Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-body-custom">
                <div class="mb-3">
                    <label class="form-label-custom">Nama Toko</label>
                    <input type="text" class="form-control-custom migrated-style-97" name="name" required oninput="this.value = this.value.toUpperCase();">
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Cabang</label>
                    <input type="text" class="form-control-custom migrated-style-97" name="branch" required oninput="this.value = this.value.toUpperCase();">
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
                <button type="button" class="btn btn-secondary migrated-style-20" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-primary-custom migrated-style-94">Simpan Toko</button>
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
    const copyModal = document.getElementById('copyCatalogModal');
    const copyForm = document.getElementById('copyCatalogForm');
    copyModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        if (!button) return;
        copyForm.reset();
        delete copyForm.dataset.submitting;
        const targetId = button.dataset.storeId;
        document.getElementById('copy_target_store_id').value = targetId;
        document.getElementById('copy_target_store_name').textContent = button.dataset.storeName;
        const sourceSelect = document.getElementById('copy_source_store_id');
        sourceSelect.value = '';
        Array.from(sourceSelect.options).forEach(option => {
            option.disabled = option.value === targetId;
        });
        const submit = copyForm.querySelector('button[type="submit"]');
        submit.disabled = false;
        submit.textContent = 'Impor Produk';
    });
    copyModal.addEventListener('hidden.bs.modal', function() {
        document.getElementById('copy_password').value = '';
        document.getElementById('copy_confirmed').value = '0';
    });
    copyForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        if (copyForm.dataset.submitting || !copyForm.reportValidity()) return;
        copyForm.dataset.submitting = '1';
        const submit = copyForm.querySelector('button[type="submit"]');
        submit.disabled = true;
        try {
            const source = document.getElementById('copy_source_store_id').selectedOptions[0].textContent.trim();
            const target = document.getElementById('copy_target_store_name').textContent;
            const result = await Swal.fire({
                icon: 'question',
                title: 'Konfirmasi Impor Produk',
                text: `Tambahkan kategori, produk, dan finishing dari ${source} ke ${target}, termasuk harga dan stok?`,
                showCancelButton: true,
                confirmButtonText: 'Ya, Impor Sekarang',
                cancelButtonText: 'Batal',
                focusCancel: true
            });
            if (result.isConfirmed) {
                document.getElementById('copy_confirmed').value = '1';
                submit.textContent = 'Mengimpor...';
                copyForm.submit();
                return;
            }
        } finally {
            if (document.getElementById('copy_confirmed').value !== '1') {
                delete copyForm.dataset.submitting;
                submit.disabled = false;
            }
        }
    });
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
        const adminWindow = window.open('about:blank', '_blank');
        if (!adminWindow) {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: 'Browser memblokir tab admin. Izinkan pop-up lalu coba lagi.',
                customClass: { popup: 'rounded-4' }
            });
            return;
        }

        const formData = new URLSearchParams(new FormData(form)).toString();

        fetch('/action?action=set_session', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: formData
        })
        .then(async response => {
            const result = (await response.text()).trim();
            if (!response.ok || result !== 'success') {
                throw new Error('Gagal menyiapkan sesi admin.');
            }
            adminWindow.location.href = 'https://mgood.my.id/admin/customer/';
        })
        .catch(error => {
            adminWindow.close();
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
