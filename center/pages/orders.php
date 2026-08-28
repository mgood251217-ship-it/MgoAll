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
require_once __DIR__ . '/../controllers/OrderController.php';

$access = isset($_SESSION['admin_logged_in']['access']) ? startEnk('dek', $_SESSION['admin_logged_in']['access']) : '';

$controller = new OrderController($koneksi);
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
        min-width: 150px;
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
    .pagination-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        background-color: #ffffff;
        border-top: 1px solid #e2e8f0;
    }
    .pagination-info {
        font-size: 0.875rem;
        color: #64748b;
    }
    .pagination {
        display: flex;
        gap: 8px;
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .pagination-link {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background-color: #ffffff;
        color: #475569;
        text-decoration: none;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s;
        padding: 0 12px;
    }
    .pagination-link:hover {
        background-color: #f1f5f9;
        border-color: #cbd5e1;
    }
    .pagination-link.active {
        background-color: #3b82f6;
        border-color: #3b82f6;
        color: #ffffff;
    }
    .pagination-link.disabled {
        background-color: #f8fafc;
        color: #94a3b8;
        cursor: not-allowed;
        pointer-events: none;
    }
    .badge-pill {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
    }
    .bg-success-light { background-color: #dcfce3; color: #15803d; }
    .bg-warning-light { background-color: #fef3c7; color: #b45309; }
    .bg-danger-light { background-color: #fee2e2; color: #b91c1c; }
    .bg-info-light { background-color: #e0f2fe; color: #0369a1; }
    .bg-secondary-light { background-color: #f1f5f9; color: #475569; }
    .text-muted-small { font-size: 0.8rem; color: #64748b; display: block; margin-top: 4px; }
    
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
        text-decoration: none;
    }
    .btn-open { background-color: #3b82f6; }
    .btn-open:hover { background-color: #2563eb; color: #ffffff; }
    .btn-clear { background-color: #f59e0b; }
    .btn-clear:hover { background-color: #d97706; }
    .btn-delete { background-color: #ef4444; }
    .btn-delete:hover { background-color: #dc2626; }
</style>

<div class="page-header">
    <h2>Data Pesanan</h2>
</div>

<div class="filter-card">
    <form method="get" action="/orders" class="filter-form">
        <div class="form-group" style="flex: 2;">
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
        
        <div class="form-group" style="flex: 2;">
            <label for="search">Cari Nomorator / Customer</label>
            <input type="text" name="search" id="search" class="form-control" value="<?= htmlspecialchars($data['search']) ?>" placeholder="Ketik pencarian...">
        </div>
        
        <div class="form-group" style="flex: 1;">
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
    <div style="overflow-x: auto;">
        <table class="table-modern">
            <thead>
                <tr>
                    <th style="width: 60px;">No</th>
                    <th>Nomorator</th>
                    <th>Customer</th>
                    <th>Item</th>
                    <th>Total Biaya</th>
                    <th>Terbayar</th>
                    <th>Pembayaran</th>
                    <th style="text-align: right; min-width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['orders'])): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: #64748b; padding: 32px 0;">Tidak ada pesanan ditemukan</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = ($data['current_page'] - 1) * $data['limit'] + 1;
                    foreach ($data['orders'] as $row): 
                        $tanggal = date('d/m/Y H:i', strtotime($row['date']));
                        
                        $paymentStatus = 'BELUM';
                        $paymentBadge = 'bg-danger-light';
                        if ($row['is_lunas']) {
                            $paymentStatus = 'LUNAS';
                            $paymentBadge = 'bg-success-light';
                        } elseif ($row['total_paid'] > 0) {
                            $paymentStatus = 'DP';
                            $paymentBadge = 'bg-warning-light';
                        }

                        $encryptedStoreId = startEnk('enk', $data['current_store_id']);
                        $encryptedOrderId = startEnk('enk', $row['order_id']);
                        $orderUrl = "/order?store_id=" . urlencode($encryptedStoreId) . "&id=" . urlencode($encryptedOrderId);
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($row['nomorator']) ?></strong>
                                <span class="text-muted-small"><?= $tanggal ?></span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($row['customer_name']) ?></strong>
                                <span class="text-muted-small">OP: <?= htmlspecialchars($row['op_initial']) ?></span>
                            </td>
                            <td><span class="badge-pill bg-info-light"><?= (int)$row['item_count'] ?> Item</span></td>
                            <td>Rp <?= number_format($row['total'] ?? 0, 0, ',', '.') ?></td>
                            <td>Rp <?= number_format($row['total_paid'] ?? 0, 0, ',', '.') ?></td>
                            <td><span class="badge-pill <?= $paymentBadge ?>"><?= $paymentStatus ?></span></td>
                            <td style="text-align: right;">
                                <a href="<?= $orderUrl ?>" class="btn-action btn-open me-1" title="Buka Order">
                                    <i class="fas fa-folder-open"></i>
                                </a>
                                <button type="button" class="btn-action btn-clear me-1" title="Clear Item Order" onclick="clearOrderItems(<?= $row['order_id'] ?>)">
                                    <i class="fas fa-eraser"></i>
                                </button>
                                <button type="button" class="btn-action btn-delete" title="Hapus Order" onclick="deleteOrder(<?= $row['order_id'] ?>)">
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
                Menampilkan total <?= number_format($data['total_items'], 0, ',', '.') ?> pesanan
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

<script>
function deleteOrder(orderId) {
    Swal.fire({
        title: 'Hapus Order',
        text: 'Masukkan alasan / keterangan hapus order ini:',
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
            if (!keterangan) {
                Swal.showValidationMessage('Keterangan wajib diisi!');
            }
            return keterangan;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('/action?action=delete_order', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ 
                    order_id: orderId,
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
                    }).then(() => {
                        location.reload();
                    });
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

function clearOrderItems(orderId) {
    Swal.fire({
        title: 'Kosongkan Item?',
        text: 'Semua item dan diskon pada order ini akan dihapus permanen. Total bayar akan di-reset menjadi 0.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Kosongkan',
        cancelButtonText: 'Batal',
        customClass: { popup: 'rounded-4' }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('/action?action=clear_order_items', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ order_id: orderId })
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
                    }).then(() => {
                        location.reload();
                    });
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