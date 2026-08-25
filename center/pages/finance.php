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
    .filter-card {
        background-color: #ffffff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        margin-bottom: 24px;
    }
    .filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        align-items: flex-end;
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
        flex: 1;
        min-width: 200px;
    }
    .form-group label {
        font-size: 0.875rem;
        font-weight: 500;
        color: #475569;
    }
    .form-control {
        padding: 10px 16px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-family: inherit;
        font-size: 0.95rem;
        color: #0f172a;
        background-color: #f8fafc;
        transition: all 0.2s;
        width: 100%;
        box-sizing: border-box;
    }
    .form-control:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        background-color: #ffffff;
    }
    .btn-search {
        background-color: #3b82f6;
        color: #ffffff;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 500;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.2s;
        height: 42px;
    }
    .btn-search:hover {
        background-color: #2563eb;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }
    .stat-card {
        background-color: #ffffff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .stat-card h5 {
        margin: 0 0 8px 0;
        color: #64748b;
        font-size: 0.85rem;
        font-weight: 500;
    }
    .stat-card h3 {
        margin: 0;
        color: #0f172a;
        font-size: 1.25rem;
        font-weight: 700;
    }
    .table-container {
        background-color: #ffffff;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        overflow: hidden;
        margin-bottom: 24px;
    }
    .table-modern {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
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
    .table-title {
        padding: 16px 20px;
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        background-color: #ffffff;
        border-bottom: 1px solid #e2e8f0;
    }
    .text-danger { color: #ef4444 !important; }
    .text-success { color: #10b981 !important; }
    
    .grid-2-cols {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        margin-bottom: 24px;
    }
    @media (max-width: 1024px) {
        .grid-2-cols {
            grid-template-columns: 1fr;
        }
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
</style>

<div class="page-header">
    <h2>Keuangan (Finance)</h2>
</div>

<div class="filter-card">
    <form method="get" action="/finance" class="filter-form">
        <div class="form-group" style="flex: 2;">
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
        <div class="form-group" style="flex: 1;">
            <label for="start_date">Dari Tanggal</label>
            <input type="date" id="start_date" name="start_date" class="form-control" value="<?= htmlspecialchars($data['startDate']) ?>">
        </div>
        <div class="form-group" style="flex: 1;">
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
    <div style="overflow-x: auto;">
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
                        <td colspan="10" style="text-align: center; color: #64748b;">Tidak ada data keuangan ditemukan</td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($data['finances'] as $row): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['date']) ?></td>
                            <td><strong><?= htmlspecialchars($row['store_name']) ?></strong></td>
                            <td style="font-weight: 600;">Rp <?= number_format($row['total_omset'], 0, ',', '.') ?></td>
                            <td style="font-weight: 600; color: #10b981;">Rp <?= number_format($row['cash'], 0, ',', '.') ?></td>
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
    <div class="table-container" style="margin-bottom: 0;">
        <h3 class="table-title" style="color: #ef4444;"><i class="fas fa-arrow-down"></i> Pengeluaran (Expenditure)</h3>
        <div style="overflow-x: auto;">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>Toko</th>
                        <th>Keterangan</th>
                        <th style="text-align: right;">Nominal</th>
                        <th style="text-align: right;">Tanggal</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data['expenditures'])): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #64748b; padding: 24px 0;">Tidak ada pengeluaran ditemukan</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($data['expenditures'] as $exp): 
                            $isoDateExp = date('Y-m-d', strtotime($exp['date']));
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($exp['store_name']) ?></strong></td>
                                <td><?= htmlspecialchars($exp['information'] ?? '-') ?></td>
                                <td style="text-align: right; font-weight: 500;" class="text-danger">
                                    Rp <?= number_format($exp['nominal'] ?? 0, 0, ',', '.') ?>
                                </td>
                                <td style="text-align: right; color: #64748b; font-size: 0.85rem;">
                                    <?= date('d/m/Y', strtotime($exp['date'])) ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <button type="button" class="btn-action btn-edit me-1" onclick="openEditModal('expenditure', <?= $exp['expenditure_id'] ?>, '<?= htmlspecialchars(addslashes($exp['information'] ?? '')) ?>', <?= $exp['nominal'] ?>, '<?= $isoDateExp ?>')">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button type="button" class="btn-action btn-delete" onclick="deleteRecord('expenditure', <?= $exp['expenditure_id'] ?>)">
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

    <div class="table-container" style="margin-bottom: 0;">
        <h3 class="table-title" style="color: #10b981;"><i class="fas fa-arrow-up"></i> Pemasukan (Income)</h3>
        <div style="overflow-x: auto;">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>Toko</th>
                        <th>Keterangan</th>
                        <th style="text-align: right;">Nominal</th>
                        <th style="text-align: right;">Tanggal</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data['incomes'])): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #64748b; padding: 24px 0;">Tidak ada pemasukan ditemukan</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($data['incomes'] as $inc): 
                            $isoDateInc = date('Y-m-d', strtotime($inc['date']));
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($inc['store_name']) ?></strong></td>
                                <td><?= htmlspecialchars($inc['information'] ?? '-') ?></td>
                                <td style="text-align: right; font-weight: 500;" class="text-success">
                                    Rp <?= number_format($inc['nominal'] ?? 0, 0, ',', '.') ?>
                                </td>
                                <td style="text-align: right; color: #64748b; font-size: 0.85rem;">
                                    <?= date('d/m/Y', strtotime($inc['date'])) ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <button type="button" class="btn-action btn-edit me-1" onclick="openEditModal('income', <?= $inc['income_id'] ?>, '<?= htmlspecialchars(addslashes($inc['information'] ?? '')) ?>', <?= $inc['nominal'] ?>, '<?= $isoDateInc ?>')">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button type="button" class="btn-action btn-delete" onclick="deleteRecord('income', <?= $inc['income_id'] ?>)">
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
        <form class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);" id="formEditFinance">
            <input type="hidden" name="type" id="edit_type">
            <input type="hidden" name="expenditure_id" id="edit_expenditure_id">
            <input type="hidden" name="income_id" id="edit_income_id">
            
            <div class="modal-header" style="background-color: #ffffff; border-bottom: 1px solid #f1f5f9; padding: 20px 24px;">
                <h5 class="modal-title" style="font-weight: 600; color: #0f172a;" id="modalTitle">Edit Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 24px; background-color: #f8fafc;">
                <div class="mb-3">
                    <label class="form-label" style="font-weight: 500; font-size: 0.875rem;">Info / Keterangan Transaksi</label>
                    <input type="text" class="form-control" name="information" id="edit_information" required style="border-radius: 8px;">
                </div>
                <div class="mb-3">
                    <label class="form-label" style="font-weight: 500; font-size: 0.875rem;">Nominal (Rp)</label>
                    <input type="number" class="form-control" name="nominal" id="edit_nominal" required style="border-radius: 8px;">
                </div>
                <div class="mb-3">
                    <label class="form-label" style="font-weight: 500; font-size: 0.875rem;">Tanggal & Waktu</label>
                    <input type="date" class="form-control" name="date" id="edit_date" required style="border-radius: 8px;">
                </div>
                <div class="mb-3">
                    <label class="form-label" style="font-weight: 500; font-size: 0.875rem;">Alasan / Catatan Perubahan</label>
                    <textarea class="form-control" name="keterangan" rows="2" placeholder="Tuliskan catatan kenapa data ini diubah..." style="border-radius: 8px;"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #f1f5f9; padding: 16px 24px; background-color: #ffffff;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Batal</button>
                <button type="submit" class="btn btn-primary" style="border-radius: 8px; background-color: #3b82f6; border: none;">Simpan Perubahan</button>
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