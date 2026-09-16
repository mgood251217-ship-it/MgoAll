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
require_once __DIR__ . '/../controllers/FinanceController.php';

$access = isset($_SESSION['admin_logged_in']['access']) ? startEnk('dek', $_SESSION['admin_logged_in']['access']) : '';

$controller = new FinanceController($koneksi);
$data = $controller->getIndexData($access);
?>



<div class="page-header">
    <h2>Keuangan (Finance)</h2>
</div>

<div class="filter-card">
    <form method="get" action="/finance" class="filter-form">
        <div class="form-group migrated-style-3">
            <label for="store_id">Pilih Toko</label>
            <select name="store_id" id="store_id" class="form-control">
                <option value="">-- Semua Toko (Global) --</option>
                <?php foreach ($data['stores'] as $store): ?>
                    <option value="<?= $store['store_id'] ?>" <?= $data['current_store_id'] == $store['store_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($store['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group migrated-style-4">
            <label for="start_date">Dari Tanggal</label>
            <input type="date" id="start_date" name="start_date" class="form-control" value="<?= htmlspecialchars($data['startDate']) ?>">
        </div>
        <div class="form-group migrated-style-4">
            <label for="end_date">Sampai Tanggal</label>
            <input type="date" id="end_date" name="end_date" class="form-control" value="<?= htmlspecialchars($data['endDate']) ?>">
        </div>
        <div>
            <button type="submit" class="btn-search">
                <i class="fas fa-search"></i> Terapkan
            </button>
        </div>
    </form>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <h5>Total Omset</h5>
        <h3>Rp <?= number_format($data['totals']['omset'], 0, ',', '.') ?></h3>
    </div>
    <div class="stat-card">
        <h5>Total Cash</h5>
        <h3>Rp <?= number_format($data['totals']['cash'], 0, ',', '.') ?></h3>
    </div>
    <div class="stat-card">
        <h5>Total Omset Offline</h5>
        <h3>Rp <?= number_format($data['totals']['offline'], 0, ',', '.') ?></h3>
    </div>
    <div class="stat-card">
        <h5>Total Omset Online</h5>
        <h3>Rp <?= number_format($data['totals']['online'], 0, ',', '.') ?></h3>
    </div>
    <div class="stat-card">
        <h5>Total Saldo</h5>
        <h3>Rp <?= number_format($data['totals']['saldo'], 0, ',', '.') ?></h3>
    </div>
    <div class="stat-card">
        <h5>Total Transfer</h5>
        <h3>Rp <?= number_format($data['totals']['transfer'], 0, ',', '.') ?></h3>
    </div>
    <div class="stat-card">
        <h5>Total Pengeluaran</h5>
        <h3>Rp <?= number_format($data['totals']['expenditure'], 0, ',', '.') ?></h3>
    </div>
</div>

<div class="table-container">
    <h3 class="table-title">Riwayat Rekap Harian</h3>
    <div class="table-scroll">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Nama Toko</th>
                    <th>Total Omset</th>
                    <th>Cash</th>
                    <th>Omset Offline</th>
                    <th>Omset Online</th>
                    <th>Saldo</th>
                    <th>Transfer</th>
                    <th>Pengeluaran</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['finances'])): ?>
                    <tr>
                        <td class="migrated-style-2" colspan="10">Tidak ada data keuangan ditemukan</td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($data['finances'] as $row): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['date']) ?></td>
                            <td><strong><?= htmlspecialchars($row['store_name']) ?></strong></td>
                            <td class="migrated-style-5">Rp <?= number_format($row['total_omset'], 0, ',', '.') ?></td>
                            <td class="migrated-style-6">Rp <?= number_format($row['cash'], 0, ',', '.') ?></td>
                            <td>Rp <?= number_format($row['omset_offline'], 0, ',', '.') ?></td>
                            <td>Rp <?= number_format($row['omset_online'], 0, ',', '.') ?></td>
                            <td>Rp <?= number_format($row['saldo'], 0, ',', '.') ?></td>
                            <td>Rp <?= number_format($row['transfer'], 0, ',', '.') ?></td>
                            <td class="text-danger">Rp <?= number_format($row['expenditure'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid-2-cols">
    <div class="table-container migrated-style-7">
        <h3 class="table-title migrated-style-8"><i class="fas fa-arrow-down"></i> Pengeluaran (Expenditure)</h3>
        <div class="table-scroll">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>Toko</th>
                        <th>Keterangan</th>
                        <th class="migrated-style-9">Nominal</th>
                        <th class="migrated-style-9">Tanggal</th>
                        <th class="migrated-style-9">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data['expenditures'])): ?>
                        <tr>
                            <td class="migrated-style-10" colspan="5">Tidak ada pengeluaran ditemukan</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($data['expenditures'] as $exp): 
                            $isoDateExp = date('Y-m-d', strtotime($exp['date']));
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($exp['store_name']) ?></strong></td>
                                <td><?= htmlspecialchars($exp['information'] ?? '-') ?></td>
                                <td class="text-danger migrated-style-11">
                                    Rp <?= number_format($exp['nominal'] ?? 0, 0, ',', '.') ?>
                                </td>
                                <td class="migrated-style-12">
                                    <?= date('d/m/Y', strtotime($exp['date'])) ?>
                                </td>
                                <td class="migrated-style-13">
                                    <button type="button" class="btn-action btn-warning me-1" onclick="openEditModal('expenditure', <?= $exp['expenditure_id'] ?>, '<?= htmlspecialchars(addslashes($exp['information'] ?? '')) ?>', <?= $exp['nominal'] ?>, '<?= $isoDateExp ?>')">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button type="button" class="btn-action btn-danger" onclick="deleteRecord('expenditure', <?= $exp['expenditure_id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="table-container migrated-style-7">
        <h3 class="table-title migrated-style-14"><i class="fas fa-arrow-up"></i> Pemasukan (Income)</h3>
        <div class="table-scroll">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>Toko</th>
                        <th>Keterangan</th>
                        <th class="migrated-style-9">Nominal</th>
                        <th class="migrated-style-9">Tanggal</th>
                        <th class="migrated-style-9">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data['incomes'])): ?>
                        <tr>
                            <td class="migrated-style-10" colspan="5">Tidak ada pemasukan ditemukan</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($data['incomes'] as $inc): 
                            $isoDateInc = date('Y-m-d', strtotime($inc['date']));
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($inc['store_name']) ?></strong></td>
                                <td><?= htmlspecialchars($inc['information'] ?? '-') ?></td>
                                <td class="text-success migrated-style-11">
                                    Rp <?= number_format($inc['nominal'] ?? 0, 0, ',', '.') ?>
                                </td>
                                <td class="migrated-style-12">
                                    <?= date('d/m/Y', strtotime($inc['date'])) ?>
                                </td>
                                <td class="migrated-style-13">
                                    <button type="button" class="btn-action btn-warning me-1" onclick="openEditModal('income', <?= $inc['income_id'] ?>, '<?= htmlspecialchars(addslashes($inc['information'] ?? '')) ?>', <?= $inc['nominal'] ?>, '<?= $isoDateInc ?>')">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button type="button" class="btn-action btn-danger" onclick="deleteRecord('income', <?= $inc['income_id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditFinance" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content migrated-style-15" id="formEditFinance">
            <input type="hidden" name="type" id="edit_type">
            <input type="hidden" name="expenditure_id" id="edit_expenditure_id">
            <input type="hidden" name="income_id" id="edit_income_id">
            
            <div class="modal-header migrated-style-16">
                <h5 class="modal-title migrated-style-17" id="modalTitle">Edit Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body migrated-style-18">
                <div class="mb-3">
                    <label class="form-label migrated-style-19">Info / Keterangan Transaksi</label>
                    <input type="text" class="form-control migrated-style-20" name="information" id="edit_information" required>
                </div>
                <div class="mb-3">
                    <label class="form-label migrated-style-19">Nominal (Rp)</label>
                    <input type="number" class="form-control migrated-style-20" name="nominal" id="edit_nominal" required>
                </div>
                <div class="mb-3">
                    <label class="form-label migrated-style-19">Tanggal & Waktu</label>
                    <input type="date" class="form-control migrated-style-20" name="date" id="edit_date" required>
                </div>
                <div class="mb-3">
                    <label class="form-label migrated-style-19">Alasan / Catatan Perubahan</label>
                    <textarea class="form-control migrated-style-20" name="keterangan" rows="2" placeholder="Tuliskan catatan kenapa data ini diubah..."></textarea>
                </div>
            </div>
            <div class="modal-footer migrated-style-21">
                <button type="button" class="btn btn-secondary migrated-style-20" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary migrated-style-22">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(type, id, information, nominal, isoDate) {
    document.getElementById('edit_type').value = type;
    document.getElementById('edit_information').value = information;
    document.getElementById('edit_nominal').value = nominal;
    document.getElementById('edit_date').value = isoDate;
    
    if (type === 'expenditure') {
        document.getElementById('modalTitle').textContent = 'Edit Pengeluaran';
        document.getElementById('edit_expenditure_id').value = id;
        document.getElementById('edit_income_id').value = '';
    } else {
        document.getElementById('modalTitle').textContent = 'Edit Pemasukan';
        document.getElementById('edit_income_id').value = id;
        document.getElementById('edit_expenditure_id').value = '';
    }
    
    var modal = new bootstrap.Modal(document.getElementById('modalEditFinance'));
    modal.show();
}

document.getElementById('formEditFinance').addEventListener('submit', function(e) {
    e.preventDefault();
    const type = document.getElementById('edit_type').value;
    const actionUrl = type === 'expenditure' ? '/action?action=edit_expenditure' : '/action?action=edit_income';
    const formData = new URLSearchParams(new FormData(this));

    fetch(actionUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData
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
    .catch(err => {
        Swal.fire('Error!', 'Terjadi kesalahan sistem', 'error');
    });
});

function deleteRecord(type, id) {
    const titleText = type === 'expenditure' ? 'Hapus Pengeluaran?' : 'Hapus Pemasukan?';
    const actionUrl = type === 'expenditure' ? '/action?action=delete_expenditure' : '/action?action=delete_income';

    Swal.fire({
        title: titleText,
        text: 'Masukkan alasan / keterangan hapus data ini:',
        input: 'text',
        inputPlaceholder: 'Keterangan...',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        customClass: { popup: 'rounded-4' },
        preConfirm: (keterangan) => {
            if (!keterangan) Swal.showValidationMessage('Keterangan wajib diisi!');
            return keterangan;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const bodyData = type === 'expenditure' 
                ? { expenditure_id: id, keterangan_hapus: result.value } 
                : { income_id: id, keterangan_hapus: result.value };

            fetch(actionUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(bodyData)
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
            .catch(err => {
                Swal.fire('Error!', 'Terjadi kesalahan sistem', 'error');
            });
        }
    });
}
</script>