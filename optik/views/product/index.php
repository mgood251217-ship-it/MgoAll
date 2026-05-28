<?php
require_once 'models/Product.php';
$productModel = new Product($pdo);

$store_id = $_SESSION['store_id'];

$limit  = 14; 
$hal    = isset($_GET['hal']) ? (int)$_GET['hal'] : 1;
if ($hal < 1) { $hal = 1; }
$offset = ($hal - 1) * $limit;
$brand_id = isset($_GET['brand']) ? $_GET['brand'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// $products = $productModel->getProductsByBrand($store_id, $brand_id);
$products = $productModel->getProductsByStorePaginated($store_id, $brand_id, $search, $limit, $offset);

$brands   = $productModel->getBrandsByStore($store_id);
$total_products = $productModel->countProductsByStore($store_id, $brand_id, $search);
$total_pages    = ceil($total_products / $limit);
$cats     = $productModel->getCategoriesGlobal();

$mapped_products = array_map(function($p) {
    return [
        'id' => $p['id'],
        'code' => $p['product_code'] ?? '',
        'name' => $p['name'] ?? '',
        'price' => $p['price'] ?? 0,
        'maxStock' => $p['stock'] ?? 0,
        'img' => $p['img'] ?? '',
        'info' => $p['info'] ?? ''
    ];
}, $products);

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    echo json_encode([
        'products' => $mapped_products,
        'total_pages' => $total_pages,
        'current_page' => $hal
    ]);
    exit;
}
?>

<style>
    .product-container { padding: 30px; }
    .header-action { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; gap: 15px; }

    .search-box{ position: relative; min-width: 280px; }
    .search-box input{ width: 100%; padding: 12px 16px 12px 42px; border-radius: 14px; border: 1px solid #e2e8f0; outline: none; font-size: 14px; background: white; transition: 0.2s; }
    .search-box input:focus{ border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59,130,246,0.1);}
    .search-box svg{ position: absolute; left: 14px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: #94a3b8;}
    .btn-search {position: absolute;right: 5px;top: 50%;transform: translateY(-50%);height: calc(100% - 10px);padding: 0 16px;border: none;background: #3b82f6;color: white;font-size: 13px;font-weight: 600;cursor: pointer;border-radius: 10px;transition: 0.2s;z-index: 2;}
    .btn-search:hover{ background: #2563eb; }

    .btn-group { display: flex; gap: 10px; flex-wrap: wrap; }
    .btn-primary { background: #2b6cb0; color: white; padding: 10px 20px; border-radius: 12px; border: none; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.3s ease; box-shadow: 0 4px 6px rgba(43, 108, 176, 0.2); }
    .btn-primary:hover { background: #1e4e8c; transform: translateY(-1px); box-shadow: 0 6px 12px rgba(43, 108, 176, 0.3); }
    
    .brand-section-title { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 15px; }
    
    .brand-flex { 
        display: flex; 
        gap: 16px; 
        overflow-x: auto; 
        padding: 10px 5px 25px 5px; /* Jarak bawah agar shadow hover tidak terpotong */
        scrollbar-width: thin; 
    }
    .brand-flex::-webkit-scrollbar { height: 6px; }
    .brand-flex::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .brand-flex::-webkit-scrollbar-track { background: #f8fafc; }

    .brand-card {
        position: relative;
        min-width: 130px; 
        background: #ffffff; 
        padding: 16px; 
        border-radius: 20px; 
        text-align: center; 
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        border: 2px solid #f1f5f9;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-decoration: none;
        color: inherit;
    }

    .brand-card:hover { 
        transform: translateY(-5px); 
        box-shadow: 0 12px 20px rgba(0,0,0,0.06);
        border-color: #e2e8f0;
    }
    
    .brand-card.active { 
        border-color: #3b82f6; 
        background: linear-gradient(145deg, #ffffff 0%, #eff6ff 100%);
        box-shadow: 0 8px 15px rgba(59, 130, 246, 0.15);
    }
    
    .brand-card img { 
        width: 65px; height: 65px; 
        object-fit: contain; 
        margin-bottom: 12px; 
        border-radius: 12px;
        transition: transform 0.3s ease;
    }
    .brand-card:hover img { transform: scale(1.05); }

    .brand-card span { font-size: 14px; font-weight: 700; color: #334155; display: block; }
    .brand-card small { color: #94a3b8; font-size: 11px; font-weight: 600; margin-top: 4px; letter-spacing: 0.5px;}

    /* Tombol Aksi Melayang yang Modern */
    .brand-actions { 
        position: absolute; 
        top: -8px; left: -8px; right: -8px; 
        display: flex; 
        justify-content: space-between; 
        opacity: 0; 
        transition: all 0.2s ease-in-out; 
        pointer-events: none; 
        z-index: 10;
    }
    .brand-card:hover .brand-actions { 
        opacity: 1; 
        pointer-events: auto; 
        transform: translateY(4px);
    }
    
    .brand-actions button { 
        background: #ffffff; 
        border: 1px solid #e2e8f0; 
        border-radius: 50%; 
        width: 30px; height: 30px; 
        display: flex; align-items: center; justify-content: center; 
        cursor: pointer; 
        color: #64748b; 
        box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
        transition: all 0.2s;
    }
    .btn-edit-brand:hover { color: #ffffff !important; background: #3b82f6; border-color: #3b82f6; }
    .btn-del-brand:hover { color: #ffffff !important; background: #ef4444; border-color: #ef4444; }
    
    .table-container { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); overflow-x: auto; }
    .table-modern { width: 100%; border-collapse: collapse; min-width: 800px; }
    .table-modern th { background: #f8fafc; color: #475569; padding: 15px; text-align: left; font-size: 13px; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
    .table-modern td { padding: 15px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-size: 14px; }
    
    .badge-stock { background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }

    .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 2000; display: none; justify-content: center; align-items: center; padding: 20px; }
    .modal-box { background: white; width: 100%; max-width: 500px; padding: 30px; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); max-height: 90vh; overflow-y: auto; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; font-weight: 700; font-size: 18px; }
    .close-btn { background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b; }

    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 5px; }
    .form-group input, .input-select { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #e2e8f0; outline: none; font-size: 14px; }

    .pagination { display: flex; justify-content: center; flex-wrap: wrap; gap: 8px; margin-top: auto; padding-top: 15px; padding-bottom: 15px; border-top: 1px solid #e2e8f0; flex-shrink: 0; }
    .page-link { padding: 8px 14px; border-radius: 8px; background: white; border: 1px solid #e2e8f0; color: #475569; text-decoration: none; font-size: 13px; font-weight: 600; cursor: pointer; }
    .page-link.active { background: #2b6cb0; color: white; border-color: #2b6cb0; }

    @media (max-width: 768px) {
        .header-action { flex-direction: column; align-items: stretch; }
        .product-container { padding: 15px; }
    }
    .action-group { display: flex; gap: 8px; align-items: center; }
    .btn-icon {
        display: inline-flex; align-items: center; justify-content: center;
        width: 32px; height: 32px; border-radius: 8px; border: none; cursor: pointer; transition: 0.2s; color: white; text-decoration: none;
    }
    .btn-icon svg { width: 16px; height: 16px; stroke-width: 2; stroke: currentColor; fill: none; stroke-linecap: round; stroke-linejoin: round; }
    
    .btn-add-stock { background: #10b981; } .btn-add-stock:hover { background: #059669; }
    .btn-edit-item { background: #3b82f6; } .btn-edit-item:hover { background: #2563eb; }
    .btn-delete-item { background: #ef4444; } .btn-delete-item:hover { background: #dc2626; }
    .btn-print-label { background: #f59e0b; } .btn-print-label:hover { background: #d97706; }
    .print-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.85); z-index: 9999; display: none; justify-content: center; align-items: center; flex-direction: column; }
    .spinner { width: 50px; height: 50px; border: 5px solid #e2e8f0; border-top: 5px solid #f59e0b; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 15px; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>

<div class="product-container">
    <div class="header-action">
        <div>
            <h2 style="color: #1e293b;">Manajemen Produk</h2>
            <p style="color: #64748b; font-size: 14px;">Total: <?= count($products) ?> Produk terdaftar</p>
        </div>
        <div class="btn-group">
        <div class="search-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" id="searchProduct" placeholder="Cari produk...">
            <button class="btn-search" onclick="loadData(currentBrand, document.getElementById('searchProduct').value)">Cari</button>
        </div>
            <button class="btn-primary" onclick="openModal('modalCat')" style="background:#10b981;">+ Kategori</button>
            <button class="btn-primary" onclick="openModal('modalBrand')" style="background:#8b5cf6;">+ Brand</button>
            <button class="btn-primary" onclick="openModal('modalProduct')">+ Produk Baru</button>
        </div>
    </div>

    <div class="brand-section">
    <h3 class="brand-section-title">Daftar Brand Tersedia</h3>
    <div class="brand-flex">
        
        <a href="?" class="brand-card <?= ($brand_id === '') ? 'active' : '' ?>">
            <div style="font-size:28px; color:#94a3b8; height:65px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">❖</div>
            <span>Semua Brand</span>
            <small style="color:transparent;">-</small>
        </a>

        <?php if(!empty($brands)): ?>
            <?php foreach($brands as $b): ?>
                <a onclick="loadData('<?= $b['id'] ?>', document.getElementById('searchProduct').value)" class="brand-card <?= ($brand_id == $b['id']) ? 'active' : '' ?>" data-brandcode="<?= htmlspecialchars($b['brand_code'] ?? '') ?>">
                    
                    <div class="brand-actions">
                        <button type="button" class="btn-edit-brand" title="Edit Brand" onclick="event.preventDefault(); openEditBrand(<?= $b['id'] ?>, '<?= htmlspecialchars($b['name']) ?>', '<?= htmlspecialchars($b['brand_code'] ?? '') ?>')">
                            <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2" fill="none"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        </button>
                        <button type="button" class="btn-del-brand" title="Hapus Brand" onclick="event.preventDefault(); if(confirm('Yakin hapus brand <?= htmlspecialchars($b['name']) ?>?')) window.location.href='/product/delete_brand?id=<?= $b['id'] ?>'">
                            <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2" fill="none"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </button>
                    </div>

                    <img src="/assets/brands/<?= $b['img'] ?>" onerror="this.src='/assets/web/default.png'; this.style.opacity='0.3';">
                    <span><?= htmlspecialchars($b['name']) ?></span>
                    <small><?= htmlspecialchars($b['brand_code'] ?? '-') ?></small>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#94a3b8; font-size:14px; padding: 20px;">Belum ada brand terdaftar.</p>
        <?php endif; ?>
    </div>
</div>

    <div class="table-container">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Foto</th>
                    <th>Info Produk</th>
                    <th>Brand</th>
                    <th>Kategori</th>
                    <th>Warna</th>
                    <th>Tahun</th> <th>Stok</th>
                    <th>Harga</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; ?>
                <?php if(!empty($products)): ?>
                    <?php foreach($products as $p): ?>
                    <tr>
                        <td>
                            <?= $i; $i++ ?>
                        </td>
                        <td>
                            <img src="/assets/products/<?= $p['img'] ?>" width="55" height="55" style="border-radius:10px; object-fit: contain;" onerror="this.src='/assets/web/default.png'; this.style.opacity='0.3';">
                        </td>
                        <td>
                            <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($p['name']) ?></div>
                            <small style="color:#64748b;"><?= htmlspecialchars($p['product_code']) ?></small><br>
                            <small style="color:#64748b;"><?= htmlspecialchars($p['info']) ?> | <?= htmlspecialchars($p['color']) ?></small>
                        </td>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                 <span style="font-weight:500;"><?= htmlspecialchars($p['brand_name'] ?? '-') ?></span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($p['cat_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p['color'] ?? '-') ?></td>
                        
                        <td>
                            <span style="font-weight:600; color:#475569; background:#f1f5f9; padding:4px 8px; border-radius:6px; font-size:12px;">
                                <?= htmlspecialchars($p['year'] ?? '-') ?>
                            </span>
                        </td>
                        
                        <td><span class="badge-stock"><?= $p['stock'] ?> Pcs</span></td>
                        <td style="font-weight:700; color: #2b6cb0;">Rp <?= number_format($p['price'], 0, ',', '.') ?></td>
                        <td>
                            <div class="action-group">
                                <button class="btn-icon btn-add-stock" title="Edit Stok" onclick="openEditStock(<?= $p['id'] ?>, <?= $p['stock'] ?>)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                                    </svg>
                                </button>
                                
                                <button class="btn-icon btn-edit-item" title="Edit Produk" onclick="openEditProduct(<?= $p['id'] ?>)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                </button>
                                
                                <a href="/product/delete_product?id=<?= $p['id'] ?>" class="btn-icon btn-delete-item" title="Hapus Produk" onclick="return confirm('Peringatan: Apakah Anda yakin ingin menghapus produk ini secara permanen?')">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        <line x1="10" y1="11" x2="10" y2="17"></line>
                                        <line x1="14" y1="11" x2="14" y2="17"></line>
                                    </svg>
                                </a>
                                
                                <button type="button" onclick="downloadLabel(<?= $p['id'] ?>)" class="btn-icon btn-print-label" title="Download Label 3x2">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"></path>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align:center; padding: 50px; color: #94a3b8;">Belum ada produk. Klik "Produk Baru" untuk menambahkan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination" id="pagination-container">
        <?php if($total_pages > 1): ?>
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <button type="button" onclick="loadData(currentBrand, document.getElementById('searchProduct').value, <?= $i ?>)" class="page-link <?= ($hal == $i) ? 'active' : '' ?>">
                    <?= $i ?>
                </button>
            <?php endfor; ?>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="modalCat">
    <div class="modal-box">
        <div class="modal-header"><span>Tambah Kategori</span><button class="close-btn" onclick="closeModal('modalCat')">✕</button></div>
        <form action="/product/store_cat" method="POST">
            <div class="form-group">
                <label>Nama Kategori</label>
                <input type="text" name="name" placeholder="Misal: Frame, Liquid" required>
            </div>
            <button type="submit" class="btn-primary" style="width:100%; padding: 15px;">Simpan Kategori</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalBrand">
    <div class="modal-box">
        <div class="modal-header"><span>Tambah Brand</span><button type="button" class="close-btn" onclick="closeModal('modalBrand')">✕</button></div>
        <form action="/product/store_brand" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Kode Brand</label>
                <input type="text" name="brand_code" placeholder="Misal: RB (Singkatan Brand)" required>
            </div>
            <div class="form-group">
                <label>Nama Brand / Merk</label>
                <input type="text" name="name" placeholder="Misal: Ray-Ban" required>
            </div>
            <div class="form-group">
                <label>Logo Brand</label>
                <input type="file" name="img" accept="image/*">
            </div>
            <button type="submit" class="btn-primary" style="width:100%; padding: 15px;">Simpan Brand</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalProduct">
    <div class="modal-box" style="max-width: 650px;">
        <div class="modal-header"><span>Tambah Produk Baru</span><button type="button" class="close-btn" onclick="closeModal('modalProduct')">✕</button></div>
        <form action="/product/store_product" method="POST" enctype="multipart/form-data">
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="form-group">
                    <label>Kode Produk</label>
                    <input type="text" value="Otomatis (9 Angka Random)" readonly style="background:#f8fafc; color:#94a3b8; font-weight:600; cursor:not-allowed;">
                </div>
                <div class="form-group"><label>Nama Produk</label><input type="text" name="name" required></div>
                <div class="form-group"><label>Info</label><input type="text" name="info" required></div>
                <div class="form-group"><label>Tahun (Year)</label><input type="number" name="year" placeholder="Misal: 2026" required></div>
                
                <div class="form-group">
                    <label>Warna</label>
                    <select name="color" class="input-select" required>
                        <option value="">-- Pilih Warna --</option>
                        <option value="HITAM">HITAM</option>
                        <option value="COKLAT">COKLAT</option>
                        <option value="UNGU">UNGU</option>
                        <option value="BIRU">BIRU</option>
                        <option value="MERAH">MERAH</option>
                        <option value="KUNING">KUNING</option>
                        <option value="SILVER">SILVER</option>
                        <option value="ABU">ABU</option>
                        <option value="PEACH">PEACH</option>
                        <option value="PINK">PINK</option>
                        <option value="GOLD">GOLD</option>
                        <option value="HITAM SILVER">HITAM SILVER</option>
                        <option value="BENING">BENING</option>
                        <option value="ORANGE">ORANGE</option>
                        <option value="PUTIH">PUTIH</option>
                        <option value="HIJAU">HIJAU</option>
                        <option value="GUN">GUN</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Brand</label>
                    <select name="brand_id" class="input-select" required>
                        <option value="0">-- Pilih Brand --</option>
                        <?php foreach($brands as $b): ?><option value="<?= $b['id'] ?>"><?= $b['name'] ?></option><?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Kategori</label>
                    <select name="category_id" class="input-select" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach($cats as $c): ?><option value="<?= $c['id'] ?>"><?= $c['name'] ?></option><?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group"><label>Stok Awal</label><input type="number" name="stock" value="0" required></div>
                <div class="form-group"><label>Harga Jual (Rp)</label><input type="number" name="price" required></div>
            </div>
            
            <div class="form-group" style="margin-top:10px;">
                <label>Foto Produk</label>
                <input type="file" name="img" accept="image/*">
            </div>
            
            <button type="submit" class="btn-primary" style="width:100%; padding: 15px; margin-top: 10px;">Simpan Produk Ke Katalog</button>
            <br><br><br>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalEditProduct">
    <div class="modal-box" style="max-width: 650px;">
        <div class="modal-header"><span>Edit Data Produk</span><button type="button" class="close-btn" onclick="closeModal('modalEditProduct')">✕</button></div>
        <form action="/product/update_product" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="product_id" id="edit_product_id">
            
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="form-group"><label>Kode Produk</label><input type="text" name="product_code" id="edit_product_code" style="background:#f1f5f9; color:#94a3b8; cursor:not-allowed;" readonly></div>
                <div class="form-group"><label>Nama Produk</label><input type="text" name="name" id="edit_name" required></div>
                <div class="form-group"><label>Info</label><input type="text" name="info" id="edit_info" required></div>
                <div class="form-group"><label>Tahun (Year)</label><input type="number" name="year" id="edit_year" required></div>
                
                <div class="form-group">
                    <label>Warna</label>
                    <select name="color" id="edit_color" class="input-select" required>
                        <option value="">-- Pilih Warna --</option>
                        <option value="HITAM">HITAM</option>
                        <option value="COKLAT">COKLAT</option>
                        <option value="UNGU">UNGU</option>
                        <option value="BIRU">BIRU</option>
                        <option value="MERAH">MERAH</option>
                        <option value="KUNING">KUNING</option>
                        <option value="SILVER">SILVER</option>
                        <option value="ABU">ABU</option>
                        <option value="PEACH">PEACH</option>
                        <option value="PINK">PINK</option>
                        <option value="GOLD">GOLD</option>
                        <option value="HITAM SILVER">HITAM SILVER</option>
                        <option value="BENING">BENING</option>
                        <option value="ORANGE">ORANGE</option>
                        <option value="PUTIH">PUTIH</option>
                        <option value="HIJAU">HIJAU</option>
                        <option value="GUN">GUN</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Brand</label>
                    <select name="brand_id" id="edit_brand_id" class="input-select" required>
                        <option value="0">-- Pilih Brand --</option>
                        <?php foreach($brands as $b): ?><option value="<?= $b['id'] ?>"><?= $b['name'] ?></option><?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Kategori</label>
                    <select name="category_id" id="edit_category_id" class="input-select" required>
                        <?php foreach($cats as $c): ?><option value="<?= $c['id'] ?>"><?= $c['name'] ?></option><?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group"><label>Harga Jual (Rp)</label><input type="number" name="price" id="edit_price" required></div>
            </div>
            
            <div class="form-group" style="margin-top:10px;">
                <label>Update Foto (Kosongkan jika tidak mengubah foto)</label>
                <input type="file" name="img" accept="image/*">
            </div>
            
            <button type="submit" class="btn-primary" style="width:100%; padding: 15px; margin-top: 10px; background:#3b82f6;">Simpan Perubahan</button>
        </form>
    </div>
</div>
<div class="modal-overlay" id="modalEditStock">
    <div class="modal-box" style="max-width: 400px;">
        <div class="modal-header">
            <span>Edit Stok Aktual</span>
            <button type="button" class="close-btn" onclick="closeModal('modalEditStock')">✕</button>
        </div>
        <form action="/product/edit_stock" method="POST">
            <input type="hidden" name="product_id" id="stock_product_id">
            <div class="form-group">
                <label>Jumlah Stok Saat Ini</label>
                <input type="number" name="new_stock" id="stock_current_qty" required>
            </div>
            <button type="submit" class="btn-primary" style="width:100%; padding: 15px;">Simpan Perubahan Stok</button>
        </form>
    </div>
</div>
<div class="modal-overlay" id="modalEditBrand">
    <div class="modal-box">
        <div class="modal-header"><span>Edit Brand</span><button type="button" class="close-btn" onclick="closeModal('modalEditBrand')">✕</button></div>
        <form action="/product/update_brand" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="brand_id" id="edit_brand_id_val">
            <div class="form-group">
                <label>Kode Brand</label>
                <input type="text" name="brand_code" id="edit_brand_code_val" required>
            </div>
            <div class="form-group">
                <label>Nama Brand / Merk</label>
                <input type="text" name="name" id="edit_brand_name_val" required>
            </div>
            <div class="form-group">
                <label>Update Logo (Opsional)</label>
                <input type="file" name="img" accept="image/*">
            </div>
            <button type="submit" class="btn-primary" style="width:100%; padding: 15px;">Simpan Perubahan Brand</button>
        </form>
    </div>
</div>
<div class="print-overlay" id="loadingPrint">
    <div class="spinner"></div>
    <h2 style="color: #1e293b; font-size: 18px;">Memproses Label 3x2cm...</h2>
    <p style="color: #64748b; font-size: 14px;">Mohon tunggu, file PDF sedang dibuat.</p>
</div>

<script>

    let currentBrand = '<?= htmlspecialchars($brand_id) ?>';
    let currentSearch = '<?= htmlspecialchars($search) ?>';
    let currentHal = <?= $hal ?>;

    function loadData(brand, search, hal = 1){
        let currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('hal', hal);
        currentUrl.searchParams.set('brand', brand);
        currentUrl.searchParams.set('search', search);

        window.location.href = currentUrl.toString();
    }
    function openEditBrand(id, name, code) {
        document.getElementById('edit_brand_id_val').value = id;
        document.getElementById('edit_brand_name_val').value = name;
        document.getElementById('edit_brand_code_val').value = code;
        openModal('modalEditBrand');
    }
    function openEditStock(id, currentStock) {
        document.getElementById('stock_product_id').value = id;
        document.getElementById('stock_current_qty').value = currentStock;
        openModal('modalEditStock');
    }
    
    function openEditProduct(id) {
        fetch('/product/api_detail?id=' + id)
        .then(res => res.json())
        .then(data => {
            if(data) {
                document.getElementById('edit_product_id').value = data.id;
                document.getElementById('edit_product_code').value = data.product_code;
                document.getElementById('edit_name').value = data.name;
                document.getElementById('edit_info').value = data.info;
                document.getElementById('edit_year').value = data.year; 
                document.getElementById('edit_color').value = data.color;
                document.getElementById('edit_price').value = data.price;
                document.getElementById('edit_brand_id').value = data.brand_id;
                document.getElementById('edit_category_id').value = data.category_id;
                
                openModal('modalEditProduct');
            }
        })
        .catch(err => alert('Gagal mengambil data produk.'));
    }
</script>

<script>
    function openModal(id) { 
        document.getElementById(id).style.display = 'flex'; 
        document.body.style.overflow = 'hidden';
    }
    function closeModal(id) { 
        document.getElementById(id).style.display = 'none'; 
        document.body.style.overflow = 'auto'; 
    }
    
    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }

    function downloadLabel(id) {
        document.getElementById('loadingPrint').style.display = 'flex';

        fetch('/product/api_detail?id=' + id)
        .then(res => res.json())
        .then(data => {
            if (!data || !data.product_code) {
                alert('Gagal mengambil data produk!');
                document.getElementById('loadingPrint').style.display = 'none';
                return;
            }

            const formatRp = (angka) => new Intl.NumberFormat('id-ID').format(angka);

            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            canvas.width = 472;
            canvas.height = 354; 

            ctx.fillStyle = '#FFFFFF';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            ctx.strokeStyle = '#000000';
            ctx.lineWidth = 1;
            ctx.strokeRect(2, 2, canvas.width - 4, canvas.height - 4);

            ctx.save();
            
            ctx.translate(236, 88.5);
            ctx.rotate(Math.PI);
            ctx.translate(-236, -88.5);

            ctx.fillStyle = '#000000';
            ctx.font = 'bold 30px Calibri';
            ctx.textAlign = 'left';
            ctx.fillText(data.name || '-', 15, 38, 200); 
            
            ctx.font = 'bold 26px Calibri';
            ctx.textAlign = 'right';
            ctx.fillText((data.info || '-').substring(0, 20), 457, 38, 200); 

            ctx.font = 'bold 45px Courier New';
            ctx.textAlign = 'center';
            ctx.fillText("Rp " + formatRp(data.price), 236, 126, 300);

            ctx.restore();
            ctx.fillStyle = '#000000';

            let barcodeCanvas = document.createElement("canvas");
            JsBarcode(barcodeCanvas, data.product_code, {
                format: "CODE128",
                displayValue: true,
                fontSize: 40,
                margin: 0,
                width: 4, 
                height: 90 
            });
            ctx.drawImage(barcodeCanvas, 180, 225, 275, 100);

            let qrCanvas = document.createElement('canvas');
            let qrData = data.brand_code ? data.brand_code : 'NO';
            
            new QRious({
                element: qrCanvas,
                value: qrData,
                size: 87,
                level: 'M'
            });
            ctx.drawImage(qrCanvas, 40, 225, 80, 80);

            let shortYear = data.year ? String(data.year).slice(-2) : '00';
            let brandText = qrData + ' ' + shortYear;
            
            ctx.font = 'bold 18px Calibri';
            ctx.textAlign = 'center';
            ctx.fillText(brandText, 80, 332, 140);

            let imgURL = canvas.toDataURL("image/jpeg", 1.0);
            let link = document.createElement('a');
            link.href = imgURL;
            link.download = "Label_Fold_40x30_" + data.stock + "pcs_" + data.product_code + ".jpg";
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            setTimeout(() => {
                document.getElementById('loadingPrint').style.display = 'none';
            }, 500);

        })
        .catch(err => {
            alert('Terjadi kesalahan sistem saat membuat gambar label.');
            console.error(err);
            document.getElementById('loadingPrint').style.display = 'none';
        });
    }

    let barcodeBuffer = '';
    let barcodeTimeout = null;

    document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
            return;
        }

        if (e.key === 'Enter') {
            if (barcodeBuffer.length > 0) {
                processBrandScan(barcodeBuffer.trim());
                barcodeBuffer = '';
            }
        } else {
            if (e.key.length === 1) { 
                barcodeBuffer += e.key;
                clearTimeout(barcodeTimeout);
                barcodeTimeout = setTimeout(() => { barcodeBuffer = ''; }, 100); 
            }
        }
    });

    function processBrandScan(scannedCode) {
        let cards = document.querySelectorAll('.brand-card');
        let foundUrl = null;

        cards.forEach(card => {
            let code = card.getAttribute('data-brandcode');
            if (code && code.toLowerCase() === scannedCode.toLowerCase()) {
                foundUrl = card.getAttribute('href');
            }
        });

        if (foundUrl) {
            window.location.href = foundUrl;
        } else {
            alert("QR Code / Kode Brand '" + scannedCode + "' tidak ditemukan di daftar Brand Anda.");
        }
    }
</script>