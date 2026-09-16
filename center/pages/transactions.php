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
require_once __DIR__ . '/../controllers/PaymentController.php';

$access = isset($_SESSION['admin_logged_in']['access']) ? startEnk('dek', $_SESSION['admin_logged_in']['access']) : '';

$controller = new PaymentController($koneksi);
$data = $controller->getIndexData($access);
?>



<div class="page-header">
    <h2>Data Transaksi / Pembayaran</h2>
</div>

<div class="filter-card">
    <form method="get" action="/transactions" class="filter-form">
        <div class="form-group migrated-style-3">
            <label for="store_id">Pilih Toko</label>
            <select name="store_id" id="store_id" class="form-control" onchange="this.form.submit()">
                <?php if (empty($data['stores'])): ?>
                    <option value="">Tidak ada toko tersedia</option>
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
            <label for="start_date">Dari Tanggal</label>
            <input type="date" name="start_date" id="start_date" class="form-control" value="<?= htmlspecialchars($data['start_date']) ?>" onchange="this.form.submit()">
        </div>

        <div class="form-group">
            <label for="end_date">Sampai Tanggal</label>
            <input type="date" name="end_date" id="end_date" class="form-control" value="<?= htmlspecialchars($data['end_date']) ?>" onchange="this.form.submit()">
        </div>
        
        <div class="form-group migrated-style-3">
            <label for="search">Cari Nomorator / Customer</label>
            <input type="text" name="search" id="search" class="form-control" value="<?= htmlspecialchars($data['search']) ?>" placeholder="Ketik pencarian...">
        </div>
        
        <div class="form-group migrated-style-4">
            <label for="limit">Tampilkan</label>
            <select name="limit" id="limit" class="form-control" onchange="this.form.submit()">
                <option value="25" <?= $data['limit'] == 25 ? 'selected' : '' ?>>25</option>
                <option value="50" <?= $data['limit'] == 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $data['limit'] == 100 ? 'selected' : '' ?>>100</option>
            </select>
        </div>

        <div>
            <button type="submit" class="btn-search">
                <i class="fas fa-search"></i> Cari
            </button>
        </div>
    </form>
</div>

<div class="table-container">
        <div class="table-scroll">
        <table class="table-modern">
            <thead>
                <tr>
                    <th class="migrated-style-78">No</th>
                    <th>ID / Ref</th>
                    <th>Customer</th>
                    <th>Metode</th>
                    <th>Nominal</th>
                    <th>Status</th>
                    <th>Tanggal</th>
                    <th class="migrated-style-98">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['payments'])): ?>
                    <tr>
                        <td class="migrated-style-80" colspan="8">Tidak ada pembayaran ditemukan</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = ($data['current_page'] - 1) * $data['limit'] + 1;
                    foreach ($data['payments'] as $row): 
                        $tanggal = date('d/m/Y H:i', strtotime($row['date']));
                        $paymentBadge = $row['status'] == 'LUNAS' ? 'bg-success-light' : 'bg-warning-light';
                        $isoDate = date('Y-m-d\TH:i', strtotime($row['date']));
                        $encryptedStoreId = startEnk('enk', $row['store_id']);
                        $encryptedOrderId = startEnk('enk', $row['order_id']);
                        $orderUrl = "/order?store_id=" . urlencode($encryptedStoreId) . "&id=" . urlencode($encryptedOrderId);
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <strong>#<?= $row['payment_id'] ?></strong>
                                <span class="text-muted-small"><?= htmlspecialchars($row['nomorator']) ?></span>
                            </td>
                            <td><strong><?= htmlspecialchars($row['customer_name']) ?></strong></td>
                            <td><strong><?= htmlspecialchars($row['payment_method']) ?></strong></td>
                            <td>Rp <?= number_format($row['nominal'] ?? 0, 0, ',', '.') ?></td>
                            <td><span class="badge-pill <?= $paymentBadge ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                            <td><?= $tanggal ?></td>
                            <td class="migrated-style-9">
                                <a href="<?= $orderUrl ?>" class="btn-action btn-open me-1" title="Buka Order">
                                    <i class="fas fa-folder-open"></i>
                                </a>
                                <button type="button" class="btn-action btn-warning me-1" title="Edit Pembayaran"
                                    onclick="openEditModal(<?= $row['payment_id'] ?>, <?= $row['order_id'] ?>, '<?= $row['payment_method'] ?>', <?= $row['nominal'] ?>, '<?= $isoDate ?>')">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="btn-action btn-danger" title="Hapus Pembayaran"
                                    onclick="deletePayment(<?= $row['payment_id'] ?>, <?= $row['order_id'] ?>, <?= $row['store_id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($data['total_pages'] > 1): ?>
        <div class="pagination-wrapper">
            <div class="pagination-info">
                Menampilkan total <?= number_format($data['total_items'], 0, ',', '.') ?> pembayaran
            </div>
            
            <ul class="pagination">
                <?php 
                $queryParams = $_GET;
                $currentPage = $data['current_page'];
                $totalPages = $data['total_pages'];
                
                $queryParams['page'] = $currentPage - 1;
                $prevUrl = '?' . http_build_query($queryParams);
                
                $queryParams['page'] = $currentPage + 1;
                $nextUrl = '?' . http_build_query($queryParams);
                ?>
                
                <li>
                    <a href="<?= $prevUrl ?>" class="pagination-link <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                
                <?php 
                $startPage = max(1, $currentPage - 2);
                $endPage = min($totalPages, $currentPage + 2);
                
                if ($startPage > 1): 
                    $queryParams['page'] = 1;
                ?>
                    <li><a href="?<?= http_build_query($queryParams) ?>" class="pagination-link">1</a></li>
                    <?php if ($startPage > 2): ?>
                        <li><span class="pagination-link disabled">...</span></li>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $startPage; $i <= $endPage; $i++): 
                    $queryParams['page'] = $i;
                ?>
                    <li>
                        <a href="?<?= http_build_query($queryParams) ?>" class="pagination-link <?= $i == $currentPage ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($endPage < $totalPages): 
                    $queryParams['page'] = $totalPages;
                ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <li><span class="pagination-link disabled">...</span></li>
                    <?php endif; ?>
                    <li><a href="?<?= http_build_query($queryParams) ?>" class="pagination-link"><?= $totalPages ?></a></li>
                <?php endif; ?>
                
                <li>
                    <a href="<?= $nextUrl ?>" class="pagination-link <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Edit Payment -->
<div class="modal fade" id="modalEditPayment" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content migrated-style-15" id="formEditPayment">
            <input type="hidden" name="store_id" value="<?= htmlspecialchars($data['current_store_id']) ?>">
            <input type="hidden" name="payment_id" id="edit_payment_id">
            <input type="hidden" name="order_id" id="edit_order_id">
            
            <div class="modal-header migrated-style-16">
                <h5 class="modal-title migrated-style-17">Edit Pembayaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body migrated-style-18">
                <div class="mb-3">
                    <label class="form-label migrated-style-19">Nominal Pembayaran (Rp)</label>
                    <input type="number" class="form-control migrated-style-20" name="nominal" id="edit_nominal" required>
                </div>
                <div class="mb-3">
                    <label class="form-label migrated-style-19">Metode Pembayaran</label>
                    <select name="payment_method" id="edit_payment_method" class="form-control form-select migrated-style-20" required>
                        <option value="CASH">CASH</option>
                        <option value="TF">TRANSFER</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label migrated-style-19">Tanggal & Waktu</label>
                    <input type="datetime-local" class="form-control migrated-style-20" name="tanggal" id="edit_tanggal" required>
                </div>
                <div class="mb-3">
                    <label class="form-label migrated-style-19">Keterangan Perubahan</label>
                    <textarea class="form-control migrated-style-20" name="keterangan" rows="2" placeholder="Catatan perubahan..."></textarea>
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
function openEditModal(paymentId, orderId, method, nominal, isoDate) {
    document.getElementById('edit_payment_id').value = paymentId;
    document.getElementById('edit_order_id').value = orderId;
    document.getElementById('edit_payment_method').value = method;
    document.getElementById('edit_nominal').value = nominal;
    document.getElementById('edit_tanggal').value = isoDate;
    
    var modal = new bootstrap.Modal(document.getElementById('modalEditPayment'));
    modal.show();
}

document.getElementById('formEditPayment').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new URLSearchParams(new FormData(this));

    fetch('/action?action=edit_payment', {
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

function deletePayment(paymentId, orderId, storeId) {
    Swal.fire({
        title: 'Hapus Pembayaran',
        text: 'Masukkan alasan / keterangan hapus:',
        input: 'text',
        inputPlaceholder: 'Keterangan...',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Hapus Sekarang',
        cancelButtonText: 'Batal',
        customClass: { popup: 'rounded-4' },
        preConfirm: (keterangan) => {
            if (!keterangan) Swal.showValidationMessage('Keterangan wajib diisi!');
            return keterangan;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('/action?action=delete_payment', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ 
                    payment_id: paymentId,
                    order_id: orderId,
                    store_id: storeId,
                    keterangan_hapus: result.value 
                })
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