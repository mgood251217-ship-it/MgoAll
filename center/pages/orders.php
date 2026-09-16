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



<div class="page-header">
    <h2>Data Pesanan</h2>
</div>

<div class="filter-card">
    <form method="get" action="/orders" class="filter-form">
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
                    <th>Nomorator</th>
                    <th>Customer</th>
                    <th>Item</th>
                    <th>Total Biaya</th>
                    <th>Terbayar</th>
                    <th>Pembayaran</th>
                    <th class="migrated-style-79">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['orders'])): ?>
                    <tr>
                        <td class="migrated-style-80" colspan="8">Tidak ada pesanan ditemukan</td>
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
                            <td class="migrated-style-9">
                                <a href="<?= $orderUrl ?>" class="btn-action btn-open me-1" title="Buka Order">
                                    <i class="fas fa-folder-open"></i>
                                </a>
                                <button type="button" class="btn-action btn-warning me-1" title="Clear Item Order" onclick="clearOrderItems(<?= $row['order_id'] ?>)">
                                    <i class="fas fa-eraser"></i>
                                </button>
                                <button type="button" class="btn-action btn-danger" title="Hapus Order" onclick="deleteOrder(<?= $row['order_id'] ?>)">
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