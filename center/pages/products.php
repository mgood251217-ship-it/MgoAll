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
$_SESSION['category_csrf'] = $_SESSION['category_csrf'] ?? bin2hex(random_bytes(32));
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
$_SESSION['product_cache_csrf'] = $_SESSION['product_cache_csrf'] ?? bin2hex(random_bytes(32));
$_SESSION['product_category_csrf'] = $_SESSION['product_category_csrf'] ?? bin2hex(random_bytes(32));
$_SESSION['product_copy_csrf'] = $_SESSION['product_copy_csrf'] ?? bin2hex(random_bytes(32));
$_SESSION['product_add_csrf'] = $_SESSION['product_add_csrf'] ?? bin2hex(random_bytes(32));
?>



<div class="page-header">
    <h2>Data Produk & Finishing</h2>
    <div class="migrated-style-88">
        <?php if ($data['current_store_id']): ?>
            <form method="post" action="/action?action=refresh_product_cache" style="display:inline-flex" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Memperbarui...';">
                <input type="hidden" name="store_id" value="<?= (int)$data['current_store_id'] ?>">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['product_cache_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                <?= $returnInputs ?>
                <button type="submit" class="btn-secondary-custom" title="Perbarui cache toko yang dipilih setelah perubahan melalui SQL"><i class="fas fa-sync-alt"></i> Update Cache</button>
            </form>
            <a class="btn-primary-custom" href="/price-tiers?store_id=<?= (int)$data['current_store_id'] ?>&amp;category_id=<?= (int)$data['current_category_id'] ?>">Harga Bertingkat</a>
            <a class="btn-primary-custom" href="/promotions?store_id=<?= (int)$data['current_store_id'] ?>&amp;category_id=<?= (int)$data['current_category_id'] ?>">Promo Beli Gratis</a>
            <a class="btn-primary-custom" href="/product-variants?store_id=<?= (int)$data['current_store_id'] ?>&amp;category_id=<?= (int)$data['current_category_id'] ?>">Varian Produk</a>
            <a class="btn-primary-custom" href="/package-prices?store_id=<?= (int)$data['current_store_id'] ?>">Harga Paket</a>
            <a class="btn-primary-custom" href="/laser-conversions?store_id=<?= (int)$data['current_store_id'] ?>">Konversi Print Laser</a>
            <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalTambahKategori">
                <i class="fas fa-tags"></i> Tambah Kategori
            </button>
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

<div class="table-container" id="daftar-produk">
    <h3 class="table-header-title"><i class="fas fa-box migrated-style-27"></i> Daftar Produk</h3>
    <?php if (!empty($data['products'])): ?>
        <form id="bulkProductCategoryForm" method="post" action="/action?action=bulk_update_product_category" class="filter-form" style="padding:16px; gap:12px">
            <?= $returnInputs ?>
            <input type="hidden" name="store_id" value="<?= (int)$data['current_store_id'] ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['product_category_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group">
                <label for="bulkProductCategory">Kategori tujuan</label>
                <select id="bulkProductCategory" name="category_id" class="form-control" required>
                    <option value="">Pilih kategori</option>
                    <?php foreach ($data['categories'] as $category): ?>
                        <option value="<?= (int)$category['category_id'] ?>"><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button id="bulkUpdateCategory" type="submit" class="btn-primary-custom" disabled>Ganti Kategori</button>
            <button id="bulkMigrateProducts" type="button" class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#migrateProductModal" disabled>Migrasi Terpilih</button>
            <button id="bulkCopyProducts" type="button" class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#copyProductsModal" disabled>Salin ke Cabang</button>
            <button id="bulkDeleteProducts" type="submit" class="btn btn-danger" formaction="/action?action=bulk_delete_products" formnovalidate disabled>Hapus Terpilih</button>
            <small id="bulkProductSelectionCount" aria-live="polite">0 produk dipilih pada halaman ini.</small>
        </form>
    <?php endif; ?>
    <div class="table-scroll">
        <table class="table-modern">
            <thead>
                <tr>
                    <th class="product-selection-cell"><label class="product-selection-hit" title="Pilih semua produk pada halaman ini"><input id="selectAllProducts" type="checkbox" class="form-check-input" aria-label="Pilih semua produk pada halaman ini" <?= empty($data['products']) ? 'disabled' : '' ?>></label></th>
                    <th class="migrated-style-78">No</th>
                    <th>Product ID</th>
                    <th>Kategori</th>
                    <th>Nama Produk</th>
                    <th>Harga</th>
                    <th>Stok</th>
                    <th>Jumlah Varian</th>
                    <th title="Jumlah baris order_items pada toko ini, bukan total quantity">Jumlah Item Order</th>
                    <th class="migrated-style-9">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['products'])): ?>
                    <tr>
                        <td class="migrated-style-80" colspan="10">Tidak ada produk ditemukan</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = ($data['current_page'] - 1) * $data['limit'] + 1;
                    foreach ($data['products'] as $row): 
                    ?>
                        <tr>
                            <td class="product-selection-cell"><label class="product-selection-hit"><input type="checkbox" class="form-check-input product-category-checkbox" name="product_ids[]" value="<?= (int)$row['product_id'] ?>" form="bulkProductCategoryForm" data-category="<?= htmlspecialchars((string)($row['category_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>" data-price="<?= htmlspecialchars((string)($row['price'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" data-count="<?= (int)$row['order_item_count'] ?>" aria-label="Pilih produk <?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>"></label></td>
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
                            <td><?= number_format($row['variant_count'] ?? 0, 0, ',', '.') ?></td>
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
                                    data-price="<?= htmlspecialchars((string)($row['price'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"
                                    <?= empty($row['order_item_count']) ? 'disabled' : '' ?>>
                                    Migrasi
                                </button>
                                <button type="button" class="btn-action btn-kelola btn-open" data-bs-toggle="modal" data-bs-target="#copyProductsModal"
                                    data-id="<?= (int)$row['product_id'] ?>" data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>" title="Salin produk beserta varian ke cabang lain">Salin</button>
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

<div class="table-container">
    <h3 class="table-header-title"><i class="fas fa-tags migrated-style-27"></i> Daftar Kategori</h3>
    <div class="table-scroll">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Category ID</th>
                    <th>Nama Kategori</th>
                    <th>Jumlah Produk</th>
                    <th>Jumlah Finishing</th>
                    <th class="migrated-style-9">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['categories'])): ?>
                    <tr><td class="migrated-style-80" colspan="6">Tidak ada kategori pada toko ini</td></tr>
                <?php else: ?>
                    <?php foreach ($data['categories'] as $index => $category): ?>
                        <?php
                        $categoryUrl = '/products?' . http_build_query([
                            'store_id' => $data['current_store_id'],
                            'category_id' => $category['category_id'],
                            'search' => $data['search'],
                            'limit' => $data['limit'],
                            'page' => 1,
                        ]) . '#daftar-produk';
                        ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= (int)$category['category_id'] ?></td>
                            <td><?= htmlspecialchars($category['name']) ?></td>
                            <td><?= number_format($category['product_count'] ?? 0, 0, ',', '.') ?></td>
                            <td><?= number_format($category['finishing_count'] ?? 0, 0, ',', '.') ?></td>
                            <td class="migrated-style-9">
                                <a class="btn btn-sm btn-primary" href="<?= htmlspecialchars($categoryUrl, ENT_QUOTES, 'UTF-8') ?>">Lihat Produk</a>
                                <?php $canDeleteCategory = (int)($category['product_count'] ?? 0) === 0 && (int)($category['finishing_count'] ?? 0) === 0; ?>
                                <form method="post" action="/action?action=delete_category" style="display:inline-flex" onsubmit="const button = this.querySelector('button'); button.disabled = true; button.textContent = 'Menghapus...';">
                                    <?= $returnInputs ?>
                                    <input type="hidden" name="store_id" value="<?= (int)$data['current_store_id'] ?>">
                                    <input type="hidden" name="category_id" value="<?= (int)$category['category_id'] ?>">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['category_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" <?= !$canDeleteCategory ? 'disabled' : '' ?> title="<?= $canDeleteCategory ? 'Hapus kategori kosong' : 'Jumlah produk dan finishing harus sama-sama 0' ?>">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalTambahKategori" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content modal-content-custom" method="post" action="/action?action=add_category" id="addCategoryForm">
            <?= $returnInputs ?>
            <input type="hidden" name="store_id" value="<?= (int)$data['current_store_id'] ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['category_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title-custom">Tambah Kategori</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-body-custom">
                <?php foreach ($data['stores'] as $store): ?>
                    <?php if ($store['store_id'] == $data['current_store_id']): ?>
                        <p>Toko: <strong><?= htmlspecialchars($store['name']) ?></strong></p>
                    <?php endif; ?>
                <?php endforeach; ?>
                <label for="new_category_name" class="form-label-custom">Nama Kategori</label>
                <input type="text" name="name" id="new_category_name" class="form-control-custom" placeholder="Masukkan nama kategori" required>
            </div>
            <div class="modal-footer modal-footer-custom">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-primary-custom">Simpan Kategori</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalTambahProduk" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content modal-content-custom" method="post" action="/action?action=add_product">
            <?= $returnInputs ?>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['product_add_csrf'], ENT_QUOTES, 'UTF-8') ?>">
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

<div class="modal fade" id="copyProductsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="copyProductsForm" class="modal-content modal-content-custom" method="post" action="/action?action=copy_products">
            <?= $returnInputs ?>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['product_copy_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="store_id" value="<?= (int)$data['current_store_id'] ?>">
            <div id="copyProductSources"></div>
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title-custom">Salin Produk ke Cabang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-body-custom">
                <p id="copyProductsSummary"></p>
                <ul id="copyProductsNames" style="max-height:160px;overflow-y:auto"></ul>
                <div class="mb-3">
                    <label for="copyProductsTarget" class="form-label-custom">Cabang tujuan</label>
                    <select id="copyProductsTarget" name="target_store_id" class="form-control-custom form-select" required>
                        <option value="">-- Pilih Cabang Tujuan --</option>
                        <?php foreach ($data['stores'] as $store): if ((int)$store['store_id'] === (int)$data['current_store_id']) continue; ?>
                            <option value="<?= (int)$store['store_id'] ?>"><?= htmlspecialchars($store['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small id="copyProductsNoTarget" hidden>Tidak ada cabang tujuan yang tersedia.</small>
                </div>
                <p>Produk, harga varian, dan komponen meteran ikut disalin. Kategori yang belum tersedia dibuat otomatis. Stok produk baru <strong>0</strong>.</p>
                <small>Produk yang sudah ada pada kategori yang sama tidak ditimpa. Hanya varian yang belum ada yang ditambahkan; harga dan stok cabang tujuan tetap.</small>
            </div>
            <div class="modal-footer modal-footer-custom">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-primary-custom">Salin Produk</button>
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
            <div id="migrationSources"></div>
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title-custom">Migrasi Item Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-body-custom">
                <div id="migrationSummary" class="mb-3"></div>
                <div class="mb-3">
                    <label for="migrationTarget" class="form-label-custom">Produk pengganti (kategori yang sama)</label>
                    <select id="migrationTarget" name="target_product_id" class="form-control-custom form-select" required>
                        <option value="">-- Pilih Produk Pengganti --</option>
                        <?php foreach ($data['migration_products'] as $product): ?>
                            <option value="<?= (int)$product['product_id'] ?>" data-category="<?= htmlspecialchars((string)($product['category_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                #<?= (int)$product['product_id'] ?> - <?= htmlspecialchars($product['name']) ?> — Rp <?= number_format($product['price'] ?? 0, 0, ',', '.') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small id="migrationEmpty" hidden>Tidak ada produk pengganti pada kategori ini.</small>
                    <small class="d-block">Harga yang ditampilkan adalah harga dasar katalog. Harga pada nota tetap dipertahankan saat migrasi.</small>
                </div>
                <div id="migrationVariantWrap" class="mb-3" hidden>
                    <label for="migrationVariant" class="form-label-custom">Varian tujuan</label>
                    <select id="migrationVariant" name="target_variant_id" class="form-control-custom form-select" disabled></select>
                    <small id="migrationVariantInfo" class="d-block"></small>
                </div>
                <p id="migrationVariantStatus" class="text-muted" hidden></p>
                <div class="mb-3">
                    <div id="migrationFinishingLabel" class="form-label-custom">Finishing tambahan (opsional)</div>
                    <div id="migrationFinishings" class="d-flex flex-wrap" style="gap:4px 10px" role="group" aria-labelledby="migrationFinishingLabel">
                        <?php foreach ($data['migration_finishings'] as $finishing): ?>
                            <div data-category="<?= (int)$finishing['category_id'] ?>">
                                <label style="display:flex;align-items:center;gap:5px;padding:2px 0;margin:0;font-size:13px;line-height:20px;cursor:pointer">
                                    <input type="checkbox" class="form-check-input migration-finishing-checkbox" name="target_finishing_ids[]" value="<?= (int)$finishing['finishing_id'] ?>" style="width:14px;height:14px;margin:0;flex-shrink:0">
                                    <span><?= htmlspecialchars($finishing['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <small id="migrationFinishingEmpty" hidden>Belum ada finishing pada kategori ini.</small>
                    <small class="d-block">Bisa pilih beberapa finishing. Tidak perlu dicentang jika tidak ingin menambah. Finishing lama tetap dipertahankan tanpa duplikat. Harga nota tetap.</small>
                </div>
                <label class="d-flex align-items-center mb-2" style="gap:8px;cursor:pointer">
                    <input type="checkbox" class="form-check-input" name="delete_source_products" value="1" style="width:20px;height:20px;margin:0;flex-shrink:0">
                    <span>Hapus produk asal setelah migrasi</span>
                </label>
                <small class="d-block mb-3">Berlaku untuk semua produk asal yang dipilih. Produk dihapus hanya jika migrasi berhasil dan sudah tidak dipakai item order.</small>
                <p class="mb-0">Semua item order terpilih memakai produk dan varian tujuan yang sama. Migrasikan ATASAN, CELANA, dan SETELAN secara terpisah. Quantity, harga, diskon, dan total nota tetap.</p>
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
    const bulkCategoryForm = document.getElementById('bulkProductCategoryForm');
    if (bulkCategoryForm) {
        const selectAll = document.getElementById('selectAllProducts');
        const checkboxes = Array.from(document.querySelectorAll('.product-category-checkbox'));
        const category = document.getElementById('bulkProductCategory');
        const submit = document.getElementById('bulkUpdateCategory');
        const migrate = document.getElementById('bulkMigrateProducts');
        const copy = document.getElementById('bulkCopyProducts');
        const remove = document.getElementById('bulkDeleteProducts');
        const updateSelection = () => {
            const selected = checkboxes.filter(checkbox => checkbox.checked);
            const count = selected.length;
            const sameCategory = new Set(selected.map(checkbox => checkbox.dataset.category)).size === 1;
            document.getElementById('bulkProductSelectionCount').textContent = `${count} produk dipilih pada halaman ini.${count > 0 && !sameCategory ? ' Pilih kategori yang sama untuk migrasi.' : ''}`;
            selectAll.checked = count === checkboxes.length;
            selectAll.indeterminate = count > 0 && count < checkboxes.length;
            submit.disabled = count === 0 || !category.value;
            migrate.disabled = count === 0 || !sameCategory;
            copy.disabled = count === 0;
            remove.disabled = count === 0;
        };
        selectAll.addEventListener('change', () => {
            checkboxes.forEach(checkbox => { checkbox.checked = selectAll.checked; });
            updateSelection();
        });
        checkboxes.forEach(checkbox => checkbox.addEventListener('change', updateSelection));
        category.addEventListener('change', updateSelection);
        bulkCategoryForm.addEventListener('submit', event => {
            if (bulkCategoryForm.dataset.busy || !checkboxes.some(checkbox => checkbox.checked)) {
                event.preventDefault();
                return;
            }
            bulkCategoryForm.dataset.busy = '1';
            const button = event.submitter || submit;
            submit.disabled = migrate.disabled = copy.disabled = remove.disabled = true;
            button.textContent = button === remove ? 'Menghapus...' : 'Menyimpan...';
        });
        updateSelection();
    }
    document.getElementById('addCategoryForm').addEventListener('submit', function() {
        const button = this.querySelector('button[type="submit"]');
        button.disabled = true;
        button.textContent = 'Menyimpan...';
    });
    const copyProductsModal = document.getElementById('copyProductsModal');
    const copyProductsForm = document.getElementById('copyProductsForm');
    const copyProductsTarget = document.getElementById('copyProductsTarget');
    copyProductsModal.addEventListener('show.bs.modal', event => {
        const button = event.relatedTarget;
        if (!button) return;
        copyProductsForm.reset(); delete copyProductsForm.dataset.busy;
        const sources = button.id === 'bulkCopyProducts'
            ? Array.from(document.querySelectorAll('.product-category-checkbox:checked')).map(checkbox => ({ id: checkbox.value, name: checkbox.dataset.name }))
            : [{ id: button.dataset.id, name: button.dataset.name }];
        const hidden = document.getElementById('copyProductSources'), names = document.getElementById('copyProductsNames');
        hidden.replaceChildren(); names.replaceChildren();
        sources.forEach(source => {
            const input = document.createElement('input'); input.type = 'hidden'; input.name = 'product_ids[]'; input.value = source.id; hidden.appendChild(input);
            const item = document.createElement('li'); item.textContent = '#' + source.id + ' - ' + source.name; names.appendChild(item);
        });
        document.getElementById('copyProductsSummary').textContent = sources.length + ' produk beserta seluruh variannya akan disalin.';
        const hasTarget = copyProductsTarget.options.length > 1;
        document.getElementById('copyProductsNoTarget').hidden = hasTarget;
        const submit = copyProductsForm.querySelector('button[type="submit"]'); submit.disabled = !sources.length || !hasTarget; submit.textContent = 'Salin Produk';
    });
    copyProductsForm.addEventListener('submit', event => {
        if (copyProductsForm.dataset.busy || !copyProductsForm.reportValidity() || !document.getElementById('copyProductSources').children.length) { event.preventDefault(); return; }
        copyProductsForm.dataset.busy = '1';
        const submit = copyProductsForm.querySelector('button[type="submit"]'); submit.disabled = true; submit.textContent = 'Menyalin...';
    });
    const migrationModal = document.getElementById('migrateProductModal');
    const migrationForm = document.getElementById('migrateProductForm');
    const migrationTarget = document.getElementById('migrationTarget');
    const migrationFinishings = document.getElementById('migrationFinishings');
    const migrationSubmit = migrationForm.querySelector('button[type="submit"]');
    const migrationVariant = document.getElementById('migrationVariant');
    const migrationVariantWrap = document.getElementById('migrationVariantWrap');
    const migrationVariantInfo = document.getElementById('migrationVariantInfo');
    const migrationVariantStatus = document.getElementById('migrationVariantStatus');
    const migrationPriceFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
    let migrationRequest = 0, migrationVariants = [], migrationReady = true;
    const resetMigrationVariant = () => {
        migrationRequest++; migrationVariants = []; migrationReady = true;
        migrationVariant.replaceChildren(); migrationVariant.disabled = true; migrationVariant.required = false;
        migrationVariantWrap.hidden = true; migrationVariantInfo.textContent = '';
        migrationVariantStatus.hidden = true; migrationVariantStatus.textContent = '';
    };
    migrationTarget.addEventListener('change', async () => {
        resetMigrationVariant();
        const productId = migrationTarget.value, request = migrationRequest;
        if (!productId) return;
        migrationReady = false; migrationSubmit.disabled = true;
        migrationVariantStatus.hidden = false; migrationVariantStatus.textContent = 'Memuat varian tujuan...';
        try {
            const query = new URLSearchParams({ action: 'get_product_variants', store_id: migrationForm.elements.store_id.value, product_id: productId });
            const response = await fetch('/action?' + query, { headers: { Accept: 'application/json' } });
            const result = await response.json();
            if (request !== migrationRequest) return;
            if (!response.ok || !result.success || !Array.isArray(result.data)) throw new Error('Gagal memuat varian. Pilih ulang produk tujuan.');
            migrationVariants = result.data;
            if (migrationVariants.length) {
                migrationVariant.appendChild(new Option('-- Pilih Varian Tujuan --', ''));
                migrationVariants.forEach(v => migrationVariant.appendChild(new Option(Object.entries(v.options).map(([name, value]) => name + ': ' + value).join(' / ').toUpperCase() + ' — Rp ' + migrationPriceFormat.format(Number(v.price)), v.id)));
                migrationVariant.disabled = false; migrationVariant.required = true; migrationVariantWrap.hidden = false;
            }
            migrationReady = true; migrationSubmit.disabled = false; migrationVariantStatus.hidden = true;
        } catch (error) {
            if (request !== migrationRequest) return;
            migrationVariantStatus.textContent = 'Gagal memuat varian. Pilih ulang produk tujuan.';
        }
    });
    migrationVariant.addEventListener('change', () => {
        const variant = migrationVariants.find(v => String(v.id) === migrationVariant.value);
        migrationVariantInfo.textContent = variant ? 'Meteran per 1 satuan jual: ' + (variant.components.map(c => c.name + ' = ' + c.quantity).join(', ') || 'mengikuti nama varian') : '';
    });
    migrationModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        if (!button) return;
        migrationForm.reset();
        resetMigrationVariant();
        delete migrationForm.dataset.busy;
        const sources = button.id === 'bulkMigrateProducts'
            ? Array.from(document.querySelectorAll('.product-category-checkbox:checked')).map(checkbox => ({ id: checkbox.value, category: checkbox.dataset.category, name: checkbox.dataset.name, price: Number(checkbox.dataset.price) || 0, count: Number(checkbox.dataset.count) || 0 }))
            : [{ id: button.dataset.id, category: button.dataset.category, name: button.dataset.name, price: Number(button.dataset.price) || 0, count: Number(button.dataset.count) || 0 }];
        const sourceIds = sources.map(source => String(source.id));
        const sameCategory = sources.length > 0 && new Set(sources.map(source => source.category)).size === 1;
        migrationForm.elements.source_product_id.value = sourceIds[0] || '';
        const sourceInputs = document.getElementById('migrationSources');
        sourceInputs.replaceChildren();
        sources.forEach(source => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'source_product_ids[]';
            input.value = source.id;
            sourceInputs.appendChild(input);
        });
        const count = sources.reduce((sum, source) => sum + source.count, 0);
        const summary = document.getElementById('migrationSummary');
        summary.textContent = `Produk asal (${sources.length} produk, ${count} item order):`;
        sources.forEach(source => {
            const line = document.createElement('div');
            line.textContent = `#${source.id} - ${source.name} — Rp ${migrationPriceFormat.format(source.price)} (${source.count} item order)`;
            summary.appendChild(line);
        });
        let available = 0;
        Array.from(migrationTarget.options).forEach(option => {
            if (!option.value) return;
            const allowed = sameCategory && !sourceIds.includes(option.value) && option.dataset.category === sources[0].category;
            option.hidden = !allowed;
            option.disabled = !allowed;
            if (allowed) available++;
        });
        let availableFinishings = 0;
        migrationFinishings.querySelectorAll('[data-category]').forEach(entry => {
            const checkbox = entry.querySelector('input');
            const allowed = sameCategory && entry.dataset.category === sources[0].category;
            entry.hidden = !allowed; checkbox.disabled = !allowed; checkbox.checked = false;
            if (allowed) availableFinishings++;
        });
        document.getElementById('migrationFinishingEmpty').hidden = availableFinishings > 0;
        document.getElementById('migrationEmpty').hidden = available > 0;
        migrationSubmit.disabled = available === 0;
        migrationSubmit.textContent = 'Migrasi';
    });
    migrationModal.addEventListener('hide.bs.modal', resetMigrationVariant);
    migrationForm.addEventListener('submit', function(event) {
        if (!migrationReady || migrationForm.dataset.busy || !migrationForm.reportValidity()) {
            event.preventDefault();
            return;
        }
        migrationForm.dataset.busy = '1';
        migrationSubmit.disabled = true;
        migrationSubmit.textContent = 'Memigrasikan...';
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
