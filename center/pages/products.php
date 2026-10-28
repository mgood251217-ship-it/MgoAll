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
$returnFilters = [
    'store_id' => $data['current_store_id'],
    'category_id' => $data['current_category_id'],
    'search' => $data['search'],
    'limit' => $data['limit'],
    'page' => $data['current_page'],
];
$returnInputs = '';
foreach ($returnFilters as $key => $value) {
    $returnInputs .= '<input type="hidden" name="return_filters[' . $key . ']" value="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '">';
}

$_SESSION['product_migration_csrf'] = $_SESSION['product_migration_csrf'] ?? bin2hex(random_bytes(32));
?>



<div class="page-header">
    <h2>Data Produk & Finishing</h2>
    <div class="migrated-style-88">
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
        <div class="form-group migrated-style-3">
            <label for="store_id">Pilih Toko</label>
            <select name="store_id" id="store_id" class="form-control" onchange="this.form.elements.category_id.value = '0'; this.form.submit()">
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
        
        <div class="form-group migrated-style-3">
            <label for="filter_category_id">Kategori</label>
            <select name="category_id" id="filter_category_id" class="form-control" onchange="this.form.submit()">
                <option value="0">Semua Kategori</option>
                <?php foreach ($data['categories'] as $category): ?>
                    <option value="<?= (int)$category['category_id'] ?>" <?= $data['current_category_id'] == $category['category_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group migrated-style-89">
            <label for="search">Cari Data</label>
            <input type="text" name="search" id="search" class="form-control" value="<?= htmlspecialchars($data['search']) ?>" placeholder="Ketik nama produk / finishing...">
        </div>
        
        <div class="form-group migrated-style-4">
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
    <h3 class="table-header-title"><i class="fas fa-box migrated-style-27"></i> Daftar Produk</h3>
    <div class="table-scroll">
        <table class="table-modern">
            <thead>
                <tr>
                    <th class="migrated-style-78">No</th>
                    <th>Product ID</th>
                    <th>Kategori</th>
                    <th>Nama Produk</th>
                    <th>Harga</th>
                    <th>Stok</th>
                    <th title="Jumlah baris order_items pada toko ini, bukan total quantity">Jumlah Item Order</th>
                    <th class="migrated-style-9">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['products'])): ?>
                    <tr>
                        <td class="migrated-style-80" colspan="8">Tidak ada produk ditemukan</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = ($data['current_page'] - 1) * $data['limit'] + 1;
                    foreach ($data['products'] as $row): 
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= (int)$row['product_id'] ?></td>
                            <td><span class="badge-category"><?= htmlspecialchars($row['category'] ?? 'Tanpa Kategori') ?></span></td>
                            <td>
                                <strong><?= htmlspecialchars($row['name']) ?></strong>
                                <?php if (!empty($row['unit_type']) && $row['unit_type'] !== '~'): ?>
                                    <span class="migrated-style-90">(<?= htmlspecialchars($row['unit_type']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>Rp <?= number_format($row['price'] ?? 0, 0, ',', '.') ?></td>
                            <td><?= number_format($row['stock'] ?? 0, 0, ',', '.') ?></td>
                            <td><?= number_format($row['order_item_count'] ?? 0, 0, ',', '.') ?></td>
                            <td class="migrated-style-9">
                                <button type="button" class="btn-action btn-warning me-1"
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
                                <button type="button" class="btn-action btn-danger" onclick="deleteData('product', <?= $row['product_id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button type="button" class="btn-action btn-kelola btn-open" data-bs-toggle="modal" data-bs-target="#migrateProductModal"
                                    data-id="<?= (int)$row['product_id'] ?>" data-category="<?= htmlspecialchars((string)($row['category_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>" data-count="<?= (int)$row['order_item_count'] ?>"
                                    <?= empty($row['order_item_count']) ? 'disabled' : '' ?>>
                                    Migrasi
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($data['total_pages'] > 1): ?>
        <div class="pagination-wrapper migrated-style-91">
            <div class="migrated-style-92">
                Menampilkan total <?= number_format($data['total_items'], 0, ',', '.') ?> produk
            </div>
            
            <ul class="migrated-style-93">
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
                    <a href="<?= $prevUrl ?>" class="pagination-link <?= $currentPage <= 1 ? 'disabled' : '' ?>" <?= $currentPage <= 1 ? 'onclick="return false;"' : '' ?>>
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
                        <a href="?<?= http_build_query($queryParams) ?>" class="pagination-link <?= $isActive ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <li>
                    <a href="<?= $nextUrl ?>" class="pagination-link <?= $currentPage >= $totalPages ? 'disabled' : '' ?>" <?= $currentPage >= $totalPages ? 'onclick="return false;"' : '' ?>>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<div class="table-container">
    <h3 class="table-header-title"><i class="fas fa-layer-group migrated-style-14"></i> Daftar Finishing</h3>
    <div class="table-scroll">
        <table class="table-modern">
            <thead>
                <tr>
                    <th class="migrated-style-78">No</th>
                    <th>Kategori</th>
                    <th>Nama Finishing</th>
                    <th>Harga</th>
                    <th>Stok</th>
                    <th class="migrated-style-9">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['finishings'])): ?>
                    <tr>
                        <td class="migrated-style-80" colspan="6">Tidak ada finishing ditemukan</td>
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
                                    <span class="migrated-style-90">(<?= htmlspecialchars($row['unit_type']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>Rp <?= number_format($row['price'] ?? 0, 0, ',', '.') ?></td>
                            <td><?= number_format($row['stock'] ?? 0, 0, ',', '.') ?></td>
                            <td class="migrated-style-9">
                                <button type="button" class="btn-action btn-warning me-1"
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
                                <button type="button" class="btn-action btn-danger" onclick="deleteData('finishing', <?= $row['finishing_id'] ?>)">
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
            <?= $returnInputs ?>
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
                <button type="button" class="btn btn-secondary migrated-style-20" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-primary-custom migrated-style-94">Simpan Produk</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEditProduk" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content modal-content-custom" method="post" action="/action?action=edit_product">
            <?= $returnInputs ?>
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
                <button type="button" class="btn btn-secondary migrated-style-20" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-primary-custom migrated-style-94">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalTambahFinishing" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content modal-content-custom" method="post" action="/action?action=add_finishing">
            <?= $returnInputs ?>
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
                <button type="button" class="btn btn-secondary migrated-style-20" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-success-custom migrated-style-94">Simpan Finishing</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEditFinishing" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content modal-content-custom" method="post" action="/action?action=edit_finishing">
            <?= $returnInputs ?>
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
                <button type="button" class="btn btn-secondary migrated-style-20" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-success-custom migrated-style-94">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="migrateProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="migrateProductForm" class="modal-content modal-content-custom" method="post" action="/action?action=migrate_product">
            <?= $returnInputs ?>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['product_migration_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="store_id" value="<?= (int)$data['current_store_id'] ?>">
            <input type="hidden" name="source_product_id">
            <input type="hidden" name="confirmed" value="0">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title-custom">Migrasi Item Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-body-custom">
                <p id="migrationSummary"></p>
                <div class="mb-3">
                    <label for="migrationTarget" class="form-label-custom">Produk pengganti (kategori yang sama)</label>
                    <select id="migrationTarget" name="target_product_id" class="form-control-custom form-select" required>
                        <option value="">-- Pilih Produk Pengganti --</option>
                        <?php foreach ($data['migration_products'] as $product): ?>
                            <option value="<?= (int)$product['product_id'] ?>" data-category="<?= htmlspecialchars((string)($product['category_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                #<?= (int)$product['product_id'] ?> - <?= htmlspecialchars($product['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small id="migrationEmpty" hidden>Tidak ada produk pengganti pada kategori ini.</small>
                </div>
                <div class="mb-3">
                    <label for="migrationPassword" class="form-label-custom">Sandi akun Anda</label>
                    <input id="migrationPassword" name="password" type="password" class="form-control-custom" autocomplete="current-password" required>
                </div>
                <p class="mb-0">Semua item order produk lama di toko ini akan memakai produk pengganti. Quantity, harga, dan total nota tetap. Produk lama tidak dihapus.</p>
            </div>
            <div class="modal-footer modal-footer-custom">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-primary-custom">Migrasi</button>
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
    const migrationModal = document.getElementById('migrateProductModal');
    const migrationForm = document.getElementById('migrateProductForm');
    const migrationTarget = document.getElementById('migrationTarget');
    const migrationSubmit = migrationForm.querySelector('button[type="submit"]');
    migrationModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        if (!button) return;
        migrationForm.reset();
        delete migrationForm.dataset.busy;
        migrationForm.elements.source_product_id.value = button.dataset.id;
        document.getElementById('migrationSummary').textContent = `Produk #${button.dataset.id} - ${button.dataset.name}: ${button.dataset.count} item order.`;
        let available = 0;
        Array.from(migrationTarget.options).forEach(option => {
            if (!option.value) return;
            const allowed = option.value !== button.dataset.id && option.dataset.category === button.dataset.category;
            option.hidden = !allowed;
            option.disabled = !allowed;
            if (allowed) available++;
        });
        document.getElementById('migrationEmpty').hidden = available > 0;
        migrationSubmit.disabled = available === 0;
        migrationSubmit.textContent = 'Migrasi';
    });
    migrationModal.addEventListener('hidden.bs.modal', function() {
        migrationForm.elements.password.value = '';
    });
    migrationForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        if (migrationForm.dataset.busy || !migrationForm.reportValidity()) return;
        migrationForm.dataset.busy = '1';
        migrationSubmit.disabled = true;
        try {
            const result = await Swal.fire({
                icon: 'warning', title: 'Konfirmasi Migrasi',
                text: `${document.getElementById('migrationSummary').textContent} Pindahkan semua item order tersebut ke ${migrationTarget.selectedOptions[0].textContent.trim()}?`,
                showCancelButton: true, confirmButtonText: 'Ya, Migrasikan', cancelButtonText: 'Batal', focusCancel: true
            });
            if (result.isConfirmed) {
                migrationForm.elements.confirmed.value = '1';
                migrationSubmit.textContent = 'Memigrasikan...';
                migrationForm.submit();
                return;
            }
        } finally {
            if (migrationForm.elements.confirmed.value !== '1') {
                delete migrationForm.dataset.busy;
                migrationSubmit.disabled = false;
            }
        }
    });
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
