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
require_once __DIR__ . '/../controllers/ProductController.php';

$access = isset($_SESSION['admin_logged_in']['access']) ? startEnk('dek', $_SESSION['admin_logged_in']['access']) : '';

$controller = new ProductController($koneksi);
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
    .btn-success-custom {
        background-color: #10b981;
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
    .btn-success-custom:hover {
        background-color: #059669;
        color: #ffffff;
        transform: translateY(-1px);
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
    .table-container {
        background-color: #ffffff;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        overflow: hidden;
        margin-bottom: 32px;
    }
    .table-header-title {
        padding: 20px;
        background-color: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        margin: 0;
        font-size: 1.15rem;
        font-weight: 600;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 10px;
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
        background-color: #ffffff;
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
    .badge-category {
        background-color: #f1f5f9;
        color: #475569;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
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
        max-height: 70vh;
        overflow-y: auto;
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
    <h2>Data Produk & Finishing</h2>
    <div style="display: flex; gap: 12px;">
        <?php if ($data['current_store_id']): ?>
            <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalTambahProduk">
                <i class="fas fa-box"></i> Tambah Produk
            </button>
            <button class="btn-success-custom" data-bs-toggle="modal" data-bs-target="#modalTambahFinishing">
                <i class="fas fa-layer-group"></i> Tambah Finishing
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="filter-card">
    <form method="get" action="/products" class="filter-form">
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
        
        <div class="form-group" style="flex: 3;">
            <label for="search">Cari Data</label>
            <input type="text" name="search" id="search" class="form-control" value="<?= htmlspecialchars($data['search']) ?>" placeholder="Ketik nama produk / finishing...">
        </div>
        
        <div class="form-group" style="flex: 1;">
            <label for="limit">Tampilkan (Produk)</label>
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
    <h3 class="table-header-title"><i class="fas fa-box" style="color: #3b82f6;"></i> Daftar Produk</h3>
    <div style="overflow-x: auto;">
        <table class="table-modern">
            <thead>
                <tr>
                    <th style="width: 60px;">No</th>
                    <th>Kategori</th>
                    <th>Nama Produk</th>
                    <th>Harga</th>
                    <th>Stok</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['products'])): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #64748b; padding: 32px 0;">Tidak ada produk ditemukan</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = ($data['current_page'] - 1) * $data['limit'] + 1;
                    foreach ($data['products'] as $row): 
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><span class="badge-category"><?= htmlspecialchars($row['category'] ?? 'Tanpa Kategori') ?></span></td>
                            <td>
                                <strong><?= htmlspecialchars($row['name']) ?></strong>
                                <?php if (!empty($row['unit_type']) && $row['unit_type'] !== '~'): ?>
                                    <span style="font-size: 0.75rem; color: #64748b; margin-left: 4px;">(<?= htmlspecialchars($row['unit_type']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>Rp <?= number_format($row['price'] ?? 0, 0, ',', '.') ?></td>
                            <td><?= number_format($row['stock'] ?? 0, 0, ',', '.') ?></td>
                            <td style="text-align: right;">
                                <button type="button" class="btn-action btn-edit me-1" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalEditProduk"
                                    data-id="<?= $row['product_id'] ?>"
                                    data-name="<?= htmlspecialchars($row['name']) ?>"
                                    data-category="<?= $row['category_id'] ?>"
                                    data-price="<?= $row['price'] ?>"
                                    data-reasonable-price="<?= $row['reasonable_price'] ?? 0 ?>"
                                    data-failed-price="<?= $row['failed_price'] ?? 0 ?>"
                                    data-stock="<?= $row['stock'] ?>"
                                    data-unit-type="<?= htmlspecialchars($row['unit_type'] ?? '~') ?>">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="btn-action btn-delete" onclick="deleteData('product', <?= $row['product_id'] ?>)">
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
        <div class="pagination-wrapper" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; background-color: #ffffff; border-top: 1px solid #e2e8f0;">
            <div style="font-size: 0.875rem; color: #64748b;">
                Menampilkan total <?= number_format($data['total_items'], 0, ',', '.') ?> produk
            </div>
            
            <ul style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0;">
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
                    <a href="<?= $prevUrl ?>" style="display: flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; border-radius: 8px; border: 1px solid #e2e8f0; text-decoration: none; color: #475569;" <?= $currentPage <= 1 ? 'onclick="return false;" style="pointer-events:none; opacity:0.5;"' : '' ?>>
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                
                <?php 
                $startPage = max(1, $currentPage - 2);
                $endPage = min($totalPages, $currentPage + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++): 
                    $queryParams['page'] = $i;
                    $isActive = $i == $currentPage;
                ?>
                    <li>
                        <a href="?<?= http_build_query($queryParams) ?>" style="display: flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; border-radius: 8px; border: 1px solid <?= $isActive ? '#3b82f6' : '#e2e8f0' ?>; background-color: <?= $isActive ? '#3b82f6' : '#ffffff' ?>; color: <?= $isActive ? '#ffffff' : '#475569' ?>; text-decoration: none;">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <li>
                    <a href="<?= $nextUrl ?>" style="display: flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; border-radius: 8px; border: 1px solid #e2e8f0; text-decoration: none; color: #475569;" <?= $currentPage >= $totalPages ? 'onclick="return false;" style="pointer-events:none; opacity:0.5;"' : '' ?>>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<div class="table-container">
    <h3 class="table-header-title"><i class="fas fa-layer-group" style="color: #10b981;"></i> Daftar Finishing</h3>
    <div style="overflow-x: auto;">
        <table class="table-modern">
            <thead>
                <tr>
                    <th style="width: 60px;">No</th>
                    <th>Kategori</th>
                    <th>Nama Finishing</th>
                    <th>Harga</th>
                    <th>Stok</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['finishings'])): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #64748b; padding: 32px 0;">Tidak ada finishing ditemukan</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = 1;
                    foreach ($data['finishings'] as $row): 
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><span class="badge-category"><?= htmlspecialchars($row['category'] ?? 'Tanpa Kategori') ?></span></td>
                            <td>
                                <strong><?= htmlspecialchars($row['name']) ?></strong>
                                <?php if (!empty($row['unit_type']) && $row['unit_type'] !== '~'): ?>
                                    <span style="font-size: 0.75rem; color: #64748b; margin-left: 4px;">(<?= htmlspecialchars($row['unit_type']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>Rp <?= number_format($row['price'] ?? 0, 0, ',', '.') ?></td>
                            <td><?= number_format($row['stock'] ?? 0, 0, ',', '.') ?></td>
                            <td style="text-align: right;">
                                <button type="button" class="btn-action btn-edit me-1" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalEditFinishing"
                                    data-id="<?= $row['finishing_id'] ?>"
                                    data-name="<?= htmlspecialchars($row['name']) ?>"
                                    data-category="<?= $row['category_id'] ?>"
                                    data-price="<?= $row['price'] ?>"
                                    data-reasonable-price="<?= $row['reasonable_price'] ?? 0 ?>"
                                    data-failed-price="<?= $row['failed_price'] ?? 0 ?>"
                                    data-stock="<?= $row['stock'] ?>"
                                    data-unit-type="<?= htmlspecialchars($row['unit_type'] ?? '~') ?>">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="btn-action btn-delete" onclick="deleteData('finishing', <?= $row['finishing_id'] ?>)">
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

<div class="modal fade" id="modalTambahProduk" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content modal-content-custom" method="post" action="/action?action=add_product">
            <input type="hidden" name="store_id" value="<?= htmlspecialchars($data['current_store_id']) ?>">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title-custom">Tambah Produk Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-body-custom">
                <div class="mb-3">
                    <label class="form-label-custom">Nama Produk</label>
                    <input type="text" class="form-control-custom" name="name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Kategori</label>
                    <select name="category_id" class="form-control-custom form-select">
                        <option value="">-- Tanpa Kategori --</option>
                        <?php foreach ($data['categories'] as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Satuan</label>
                    <select name="unit_type" class="form-control-custom form-select">
                        <option value="~">~</option>
                        <option value="M2">M2</option>
                        <option value="CM2">CM2</option>
                        <option value="PCS">PCS</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Harga Jual (Rp)</label>
                    <input type="number" class="form-control-custom" name="price" value="0" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Harga Maklun (Rp)</label>
                    <input type="number" class="form-control-custom" name="reasonable_price" value="0" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Harga Kegagalan (Rp)</label>
                    <input type="number" class="form-control-custom" name="failed_price" value="0" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Stok Awal</label>
                    <input type="number" class="form-control-custom" name="stock" value="0" required>
                </div>
            </div>
            <div class="modal-footer modal-footer-custom">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Batal</button>
                <button type="submit" class="btn-primary-custom" style="padding: 8px 20px;">Simpan Produk</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEditProduk" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content modal-content-custom" method="post" action="/action?action=edit_product">
            <input type="hidden" name="product_id" id="edit_product_id">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title-custom">Edit Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-body-custom">
                <div class="mb-3">
                    <label class="form-label-custom">Nama Produk</label>
                    <input type="text" class="form-control-custom" name="name" id="edit_product_name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Kategori</label>
                    <select name="category_id" id="edit_product_category" class="form-control-custom form-select">
                        <option value="">-- Tanpa Kategori --</option>
                        <?php foreach ($data['categories'] as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Satuan</label>
                    <select name="unit_type" id="edit_product_unit_type" class="form-control-custom form-select">
                        <option value="~">~</option>
                        <option value="M2">M2</option>
                        <option value="CM2">CM2</option>
                        <option value="PCS">PCS</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Harga Jual (Rp)</label>
                    <input type="number" class="form-control-custom" name="price" id="edit_product_price" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Harga Maklun (Rp)</label>
                    <input type="number" class="form-control-custom" name="reasonable_price" id="edit_product_reasonable_price" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Harga Kegagalan (Rp)</label>
                    <input type="number" class="form-control-custom" name="failed_price" id="edit_product_failed_price" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Stok</label>
                    <input type="number" class="form-control-custom" name="stock" id="edit_product_stock" required>
                </div>
            </div>
            <div class="modal-footer modal-footer-custom">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Batal</button>
                <button type="submit" class="btn-primary-custom" style="padding: 8px 20px;">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalTambahFinishing" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content modal-content-custom" method="post" action="/action?action=add_finishing">
            <input type="hidden" name="store_id" value="<?= htmlspecialchars($data['current_store_id']) ?>">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title-custom">Tambah Finishing Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-body-custom">
                <div class="mb-3">
                    <label class="form-label-custom">Nama Finishing</label>
                    <input type="text" class="form-control-custom" name="name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Kategori</label>
                    <select name="category_id" class="form-control-custom form-select">
                        <option value="">-- Tanpa Kategori --</option>
                        <?php foreach ($data['categories'] as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Satuan</label>
                    <select name="unit_type" class="form-control-custom form-select">
                        <option value="~">~</option>
                        <option value="M2">M2</option>
                        <option value="CM2">CM2</option>
                        <option value="PCS">PCS</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Harga Jual (Rp)</label>
                    <input type="number" class="form-control-custom" name="price" value="0" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Harga Maklun (Rp)</label>
                    <input type="number" class="form-control-custom" name="reasonable_price" value="0" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Harga Kegagalan (Rp)</label>
                    <input type="number" class="form-control-custom" name="failed_price" value="0" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Stok Awal</label>
                    <input type="number" class="form-control-custom" name="stock" value="0" required>
                </div>
            </div>
            <div class="modal-footer modal-footer-custom">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Batal</button>
                <button type="submit" class="btn-success-custom" style="padding: 8px 20px;">Simpan Finishing</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEditFinishing" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content modal-content-custom" method="post" action="/action?action=edit_finishing">
            <input type="hidden" name="finishing_id" id="edit_finishing_id">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title-custom">Edit Finishing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-body-custom">
                <div class="mb-3">
                    <label class="form-label-custom">Nama Finishing</label>
                    <input type="text" class="form-control-custom" name="name" id="edit_finishing_name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Kategori</label>
                    <select name="category_id" id="edit_finishing_category" class="form-control-custom form-select">
                        <option value="">-- Tanpa Kategori --</option>
                        <?php foreach ($data['categories'] as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Satuan</label>
                    <select name="unit_type" id="edit_finishing_unit_type" class="form-control-custom form-select">
                        <option value="~">~</option>
                        <option value="M2">M2</option>
                        <option value="CM2">CM2</option>
                        <option value="PCS">PCS</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Harga Jual (Rp)</label>
                    <input type="number" class="form-control-custom" name="price" id="edit_finishing_price" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Harga Maklun (Rp)</label>
                    <input type="number" class="form-control-custom" name="reasonable_price" id="edit_finishing_reasonable_price" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Harga Kegagalan (Rp)</label>
                    <input type="number" class="form-control-custom" name="failed_price" id="edit_finishing_failed_price" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">Stok</label>
                    <input type="number" class="form-control-custom" name="stock" id="edit_finishing_stock" required>
                </div>
            </div>
            <div class="modal-footer modal-footer-custom">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Batal</button>
                <button type="submit" class="btn-success-custom" style="padding: 8px 20px;">Simpan Perubahan</button>
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
    const editProdukModal = document.getElementById('modalEditProduk');
    if (editProdukModal) {
        editProdukModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('edit_product_id').value = button.getAttribute('data-id');
            document.getElementById('edit_product_name').value = button.getAttribute('data-name');
            document.getElementById('edit_product_category').value = button.getAttribute('data-category');
            document.getElementById('edit_product_price').value = button.getAttribute('data-price');
            document.getElementById('edit_product_reasonable_price').value = button.getAttribute('data-reasonable-price');
            document.getElementById('edit_product_failed_price').value = button.getAttribute('data-failed-price');
            document.getElementById('edit_product_stock').value = button.getAttribute('data-stock');
            document.getElementById('edit_product_unit_type').value = button.getAttribute('data-unit-type') || '~';
        });
    }

    const editFinishingModal = document.getElementById('modalEditFinishing');
    if (editFinishingModal) {
        editFinishingModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('edit_finishing_id').value = button.getAttribute('data-id');
            document.getElementById('edit_finishing_name').value = button.getAttribute('data-name');
            document.getElementById('edit_finishing_category').value = button.getAttribute('data-category');
            document.getElementById('edit_finishing_price').value = button.getAttribute('data-price');
            document.getElementById('edit_finishing_reasonable_price').value = button.getAttribute('data-reasonable-price');
            document.getElementById('edit_finishing_failed_price').value = button.getAttribute('data-failed-price');
            document.getElementById('edit_finishing_stock').value = button.getAttribute('data-stock');
            document.getElementById('edit_finishing_unit_type').value = button.getAttribute('data-unit-type') || '~';
        });
    }
});

function deleteData(type, id) {
    let title = type === 'product' ? 'Hapus Produk?' : 'Hapus Finishing?';
    let url = type === 'product' ? '/action?action=delete_product' : '/action?action=delete_finishing';
    let bodyData = new URLSearchParams();
    
    if (type === 'product') {
        bodyData.append('product_id', id);
    } else {
        bodyData.append('finishing_id', id);
    }

    Swal.fire({
        title: title,
        text: 'Data yang dihapus tidak dapat dikembalikan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        customClass: { popup: 'rounded-4' }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: bodyData
            })
            .then(res => res.text())
            .then(data => {
                if (data.trim() === 'OK') {
                    location.reload();
                } else {
                    Swal.fire('Gagal!', 'Terjadi kesalahan saat menghapus data.', 'error');
                }
            });
        }
    });
}
</script>