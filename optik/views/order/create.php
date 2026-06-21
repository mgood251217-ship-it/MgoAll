<?php
// views/order/create.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

global $pdo; 
if (!isset($pdo) || $pdo === null) {
    if(file_exists('config.php')) require_once 'config.php';
    elseif(file_exists('koneksi.php')) require_once 'koneksi.php';
}

require_once 'models/Product.php';
if (!isset($pdo) || $pdo === null) {
    die("<b>CRITICAL ERROR:</b> Koneksi Database (\$pdo) tidak terdeteksi.");
}

$productModel = new Product($pdo);
$store_id = $_SESSION['store_id'] ?? 0;

$limit  = 14; 
$hal    = isset($_GET['hal']) ? (int)$_GET['hal'] : 1;
if ($hal < 1) { $hal = 1; }
$offset = ($hal - 1) * $limit;

$brand_id = isset($_GET['brand']) ? $_GET['brand'] : '';
$search   = isset($_GET['search']) ? $_GET['search'] : '';

$brands         = $productModel->getBrandsByStore($store_id);
$total_products = $productModel->countProductsByStore($store_id, $brand_id, $search);
$total_pages    = ceil($total_products / $limit);
$produk_db      = $productModel->getProductsByStorePaginated($store_id, $brand_id, $search, $limit, $offset);

if (!is_array($brands)) $brands = [];
if (!is_array($produk_db)) $produk_db = [];

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
}, $produk_db);

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

<script src="https://unpkg.com/html5-qrcode"></script>

<style>
    .pos-container { display: flex; width: 100%; height: calc(100vh - 70px); }
    
    .main-content { flex: 1; padding: 20px 30px; display: flex; flex-direction: column; overflow-y: auto; overflow-x: hidden; background: #f8fafc; }
    
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-shrink: 0; }
    .brand-filter-wrapper { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 10px; margin-bottom: 20px; scrollbar-width: none; flex-shrink: 0; }
    .brand-filter-wrapper::-webkit-scrollbar { display: none; }
    
    .brand-badge { min-width: 65px; height: 65px; background: white; border-radius: 16px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; border: 2px solid #e2e8f0; transition: 0.2s; text-decoration: none; padding: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .brand-badge:hover { border-color: #94a3b8; transform: translateY(-2px); }
    .brand-badge.active { border-color: #2b6cb0; background: #eff6ff; }
    .brand-badge img { width: 35px; height: 35px; object-fit: contain; }
    .brand-badge span { font-size: 10px; color: #475569; font-weight: 600; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 55px; text-align: center;}

    .search-form { display: flex; width: 100%; max-width: 450px; gap: 10px; align-items: center; }
    .search-bar { background: white; padding: 12px 20px; border-radius: 15px; border: 1px solid #e2e8f0; width: 100%; box-shadow: 0 2px 10px rgba(0,0,0,0.02); outline: none; font-size: 14px; }
    .btn-search { background: #2b6cb0; color: white; border: none; padding: 0 20px; border-radius: 15px; cursor: pointer; font-weight: bold; }
    
    .btn-scan-mobile { display: none; background: #10b981; color: white; border: none; padding: 0 15px; border-radius: 15px; cursor: pointer; font-weight: bold; height: 43px; }

    .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 15px; align-content: start; }
    .product-card { background-color: #ffffff; border-radius: 16px; padding: 12px; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; transition: transform 0.15s; cursor: pointer; position: relative; }
    .product-card:hover { transform: translateY(-4px); border-color: #2b6cb0; box-shadow: 0 10px 15px -3px rgba(43, 108, 176, 0.2); }
    .product-card:active { transform: translateY(0); }
    
    .product-card.out-of-stock { opacity: 0.5; filter: grayscale(100%); cursor: not-allowed; transform: none; box-shadow: none; border-color: #e2e8f0; }
    .stock-badge { position: absolute; top: 8px; right: 8px; background: rgba(0,0,0,0.6); color: white; font-size: 10px; padding: 2px 6px; border-radius: 8px; font-weight: bold; backdrop-filter: blur(4px); transition: 0.3s; }
    .stock-badge.empty { background: #ef4444; }

    .pagination { display: flex; justify-content: center; flex-wrap: wrap; gap: 8px; margin-top: auto; padding-top: 15px; padding-bottom: 15px; border-top: 1px solid #e2e8f0; flex-shrink: 0; }
    .page-link { padding: 8px 14px; border-radius: 8px; background: white; border: 1px solid #e2e8f0; color: #475569; text-decoration: none; font-size: 13px; font-weight: 600; cursor: pointer; }
    .page-link.active { background: #2b6cb0; color: white; border-color: #2b6cb0; }

    .order-sidebar { width: 350px; background: white; padding: 20px; display: flex; flex-direction: column; box-shadow: -5px 0 15px rgba(0,0,0,0.03); border-left: 1px solid #f1f5f9; }
    .order-header { font-size: 18px; font-weight: 600; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .btn-back { color: #64748b; text-decoration: none; font-size: 14px; }
    .order-items { flex: 1; overflow-y: auto; padding-right: 5px; }
    .order-summary { margin-top: 20px; padding-top: 20px; border-top: 1px dashed #ccc; }
    .summary-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; color: #666; }
    .summary-total { display: flex; justify-content: space-between; font-size: 18px; font-weight: 700; color: #333; margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee; }
    .btn-place-order { width: 100%; padding: 15px; background: #2b6cb0; color: white; border: none; border-radius: 15px; font-size: 16px; font-weight: 600; margin-top: 20px; cursor: pointer; transition: 0.2s;}
    .btn-place-order:hover { background: #1e4e8c; }

    @media (max-width: 1024px) {
        .pos-container { flex-direction: column; height: 100%; overflow-y: auto; }
        .main-content { padding: 15px; overflow: visible; flex: none; }
        .search-form { width: 100%; max-width: 100%; }
        .btn-scan-mobile { display: block; }
        
        .order-sidebar { width: 100%; border-left: none; border-top: 3px solid #f1f5f9; border-radius: 20px 20px 0 0; margin-top: 10px; overflow: visible; flex: none; }
        .modal-box { width: 90%; max-width: 400px; padding: 20px; }
    }

    .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: none; justify-content: center; align-items: center; }
    .modal-box { background: white; width: 400px; padding: 30px; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
    .modal-title { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 20px; display: flex; justify-content: space-between; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 5px; }
    .form-group input { width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; outline: none; }
    .modal-actions { display: flex; gap: 10px; margin-top: 25px; }
    .btn-cancel { flex: 1; padding: 12px; background: #f1f5f9; color: #475569; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; }
    .btn-submit { flex: 1; padding: 12px; background: #2b6cb0; color: white; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; }

    #reader { width: 100%; border-radius: 10px; overflow: hidden; margin-bottom: 15px; }
</style>

<div class="pos-container">
    <div class="main-content">
        
        <div class="brand-filter-wrapper">
            <button type="button" onclick="loadData('', document.getElementById('search-input').value)" class="brand-badge <?= ($brand_id === '') ? 'active' : '' ?>" data-brand="">
                <div style="font-size:20px; color:#94a3b8; margin-bottom:2px;">❖</div>
                <span>Semua</span>
            </button>
            <?php foreach($brands as $b): ?>
                <button type="button" onclick="loadData('<?= $b['id'] ?>', document.getElementById('search-input').value)" class="brand-badge <?= ($brand_id == $b['id']) ? 'active' : '' ?>" data-brand="<?= $b['id'] ?>">
                    <img src="/assets/brands/<?= $b['img'] ?>" onerror="this.src='/assets/web/default.png'; this.style.opacity='0.3';">
                    <span><?= htmlspecialchars($b['name']) ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="header">
            <form class="search-form" onsubmit="event.preventDefault(); loadData(currentBrand, document.getElementById('search-input').value);">
                <input type="text" id="search-input" class="search-bar" placeholder="Cari kode frame/info..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                <button type="submit" class="btn-search">Cari</button>
                <button type="button" class="btn-scan-mobile" onclick="openCameraScanner()">📷 Scan</button>
            </form>
        </div>

        <div class="product-grid" id="product-grid-container">
            <?php if(!empty($produk_db)): ?>
                <?php foreach($produk_db as $item): ?>
                    <div class="product-card" id="card-<?= $item['id']; ?>" onclick="addToCart(<?= $item['id']; ?>)">
                        
                        <div class="stock-badge" id="stock-badge-<?= $item['id']; ?>"><?= $item['stock']; ?></div>
                        
                        <img src="/assets/products/<?= $item['img']; ?>" 
                            style="width: 100%; height: 90px; object-fit: contain; margin-bottom: 8px; background: #f1f5f9; border-radius: 10px;" 
                            onerror="this.src='/assets/web/default.png'; this.style.opacity='0.3';">
                        
                        <div style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($item['name']) ?>">
                            <?= htmlspecialchars($item['name']); ?>
                        </div>
                        <div style="font-size: 10px; color: #64748b; margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?= htmlspecialchars($item['info']); ?>
                        </div>
                        <div style="font-size: 13px; color: #2b6cb0; font-weight:800;">
                            Rp <?= number_format($item['price'], 0, ',', '.'); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #94a3b8;">Tidak ada produk yang ditemukan.</div>
            <?php endif; ?>
        </div>

        <div class="pagination" id="pagination-container">
            <?php if($total_pages > 1): ?>
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <button type="button" onclick="loadData(currentBrand, document.getElementById('search-input').value, <?= $i ?>)" class="page-link <?= ($hal == $i) ? 'active' : '' ?>">
                        <?= $i ?>
                    </button>
                <?php endfor; ?>
            <?php endif; ?>
        </div>

    </div>

    <div class="order-sidebar">
        <div class="order-header">
            Keranjang
            <a href="/order" class="btn-back">✕ Batal</a>
        </div>
        <div class="order-items" id="cart-items">
            <p style="text-align:center; color:#888; font-size:13px; margin-top:50px;">Keranjang masih kosong</p>
        </div>
        <div class="order-summary">
            <div class="summary-row"><span>Subtotal</span><span id="cart-subtotal">Rp 0</span></div>
            <div class="summary-total"><span>Total</span><span id="cart-total">Rp 0</span></div>
            <button class="btn-place-order" id="btn-proses">Simpan Transaksi</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="checkoutModal">
    <div class="modal-box">
        <div class="modal-title">Selesaikan Pesanan</div>
        <div class="form-group">
            <label>Nomor Invoice (Otomatis)</label>
            <input type="text" id="input_inv_no" readonly style="background:#f8fafc; font-weight:bold; color:#2b6cb0;">
        </div>
        <div class="form-group">
            <label>Nama Pelanggan</label>
            <input type="text" id="input_customer" placeholder="Masukkan nama pelanggan" autocomplete="off">
        </div>
        <div class="form-group">
            <label>Nomor Konsumen (HP/WA)</label>
            <input type="text" id="input_nomor" placeholder="Contoh: 08123456789" autocomplete="off">
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal('checkoutModal')">Batal</button>
            <button class="btn-submit" onclick="submitTransaction()">Simpan Transaksi</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="scannerModal">
    <div class="modal-box">
        <div class="modal-title">
            Scan Barcode Produk
            <span style="color:#ef4444; cursor:pointer;" onclick="closeCameraScanner()">✕</span>
        </div>
        <div id="reader"></div>
        <div style="text-align:center; font-size:12px; color:#64748b;">Arahkan kamera ke barcode/QR Code produk</div>
    </div>
</div>

<script>
    let cart = []; 
    let html5QrcodeScanner = null;

    let currentBrand = '<?= htmlspecialchars($brand_id) ?>';
    let currentSearch = '<?= htmlspecialchars($search) ?>';
    let currentHal = <?= $hal ?>;

    let globalProducts = <?= json_encode($mapped_products) ?>;

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    }

    function loadData(brand, search, hal = 1) {
        currentBrand = brand;
        currentSearch = search;
        currentHal = hal;

        document.querySelectorAll('.brand-badge').forEach(el => {
            if (el.getAttribute('data-brand') == brand) {
                el.classList.add('active');
            } else {
                el.classList.remove('active');
            }
        });

        let currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('hal', hal);
        currentUrl.searchParams.set('brand', brand);
        currentUrl.searchParams.set('search', search);

        window.history.pushState({}, '', currentUrl.toString());

        currentUrl.searchParams.set('ajax', '1');

        fetch(currentUrl.toString())
            .then(res => res.json())
            .then(data => {
                globalProducts = data.products; 
                renderGrid(data.products);
                renderPagination(data.total_pages, data.current_page);
                syncStockUI(); 
            })
            .catch(err => {
                console.error("Gagal mengambil data JSON: ", err);
                alert("Koneksi bermasalah saat memuat filter barang.");
            });
    }

    function renderGrid(products) {
        const grid = document.getElementById('product-grid-container');
        grid.innerHTML = '';
        
        if (products.length === 0) {
            grid.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #94a3b8;">Tidak ada produk yang ditemukan.</div>';
            return;
        }

        products.forEach(item => {
            grid.innerHTML += `
                <div class="product-card" id="card-${item.id}" onclick="addToCart(${item.id})">
                    <div class="stock-badge" id="stock-badge-${item.id}">${item.maxStock}</div>
                    <img src="/assets/products/${item.img || ''}" 
                         style="width: 100%; height: 90px; object-fit: contain; margin-bottom: 8px; background: #f1f5f9; border-radius: 10px;" 
                         onerror="this.src='/assets/web/default.png'; this.style.opacity='0.3';">
                    
                    <div style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${item.name}">
                        ${item.name}
                    </div>
                    <div style="font-size: 10px; color: #64748b; margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        ${item.info || ''}
                    </div>
                    <div style="font-size: 13px; color: #2b6cb0; font-weight:800;">
                        Rp ${new Intl.NumberFormat('id-ID').format(item.price)}
                    </div>
                </div>
            `;
        });
    }

    function renderPagination(totalPages, currentHal) {
        const container = document.getElementById('pagination-container');
        container.innerHTML = '';
        if (totalPages <= 1) return;

        for (let i = 1; i <= totalPages; i++) {
            let activeClass = (i == currentHal) ? 'active' : '';
            container.innerHTML += `<button type="button" onclick="loadData(currentBrand, document.getElementById('search-input').value, ${i})" class="page-link ${activeClass}">${i}</button>`;
        }
    }

    function addToCart(id) {
        let product = globalProducts.find(p => p.id === id);
        if(!product) return;

        let existingItem = cart.find(item => item.product_id == id);
        let currentQtyInCart = existingItem ? existingItem.quantity : 0;

        if (currentQtyInCart < product.maxStock) {
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                cart.push({ 
                    product_id: product.id, 
                    name: product.name, 
                    price: product.price, 
                    quantity: 1, 
                    maxStock: product.maxStock,
                    discount: 0 
                });
            }
            updateCartUI();
        } else {
            alert("Stok Habis! Tidak bisa menambah lebih dari " + product.maxStock);
        }
    }

    function syncStockUI() {
        globalProducts.forEach(p => {
            let badgeEl = document.getElementById('stock-badge-' + p.id);
            let cardEl = document.getElementById('card-' + p.id);
            
            if(badgeEl && cardEl) {
                let cartItem = cart.find(c => c.product_id == p.id);
                let qtyInCart = cartItem ? cartItem.quantity : 0;
                
                let remainingStock = p.maxStock - qtyInCart;
                
                badgeEl.innerText = remainingStock;
                
                if(remainingStock <= 0) {
                    badgeEl.classList.add('empty');
                    cardEl.classList.add('out-of-stock');
                } else {
                    badgeEl.classList.remove('empty');
                    cardEl.classList.remove('out-of-stock');
                }
            }
        });
    }

    function updateCartUI() {
        let cartContainer = document.getElementById('cart-items');
        let cartTotal = document.getElementById('cart-total');
        let cartSubtotal = document.getElementById('cart-subtotal');
        
        cartContainer.innerHTML = '';
        let total = 0;

        if (cart.length === 0) {
            cartContainer.innerHTML = '<p style="text-align:center; color:#888; font-size:13px; margin-top:50px;">Keranjang masih kosong</p>';
        } else {
            cart.forEach((item, index) => {
                let discountAmount = item.price * (item.discount / 100);
                let finalPrice = item.price - discountAmount;
                let amount = finalPrice * item.quantity;
                total += amount;

                let isDiscounted = item.discount > 0;

                cartContainer.innerHTML += `
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; border-bottom: 1px dashed #f1f5f9; padding-bottom: 10px;">
                        <div style="flex: 1; padding-right: 10px;">
                            <div style="font-size: 13px; color: #1e293b; font-weight:700;">${item.name}</div>
                            <div style="font-size: 12px; color: #64748b; text-decoration: ${isDiscounted ? 'line-through' : 'none'};">
                                ${formatRupiah(item.price)}
                            </div>
                            ${isDiscounted ? `<div style="font-size: 13px; color: #ef4444; font-weight:bold;">${formatRupiah(finalPrice)}</div>` : ''}
                        </div>
                        
                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                            <div style="display:flex; align-items:center; gap:4px;">
                                <span style="font-size:10px; color:#94a3b8; font-weight:600;">Disc %</span>
                                <input type="number" min="0" max="100" value="${item.discount}" 
                                       onchange="changeDiscount(${index}, this.value)" 
                                       style="width:40px; padding:2px; font-size:11px; text-align:center; border:1px solid #cbd5e1; border-radius:4px; outline:none;">
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px; background: #f1f5f9; border-radius: 8px; padding: 2px;">
                                <button onclick="changeQty(${index}, -1)" style="width:26px; height:26px; border:none; background:transparent; font-weight:bold; color:#475569; cursor:pointer;">-</button>
                                <span style="font-size:13px; font-weight:bold; min-width: 15px; text-align: center;">${item.quantity}</span>
                                <button onclick="changeQty(${index}, 1)" style="width:26px; height:26px; border:none; background:transparent; font-weight:bold; color:#475569; cursor:pointer;">+</button>
                            </div>
                        </div>
                    </div>
                `;
            });
        }

        total = Math.floor(total / 500) * 500;

        cartSubtotal.innerText = formatRupiah(total);
        cartTotal.innerText = formatRupiah(total);
        cartContainer.scrollTop = cartContainer.scrollHeight;
        
        syncStockUI();
    }

    function changeQty(index, change) {
        let item = cart[index];
        let newQty = item.quantity + change;

        if (newQty > item.maxStock) {
            alert("Maksimal stok adalah " + item.maxStock);
            return;
        }

        item.quantity = newQty;
        if (item.quantity <= 0) { cart.splice(index, 1); }
        
        updateCartUI();
    }

    function changeDiscount(index, value) {
        let val = parseFloat(value);
        if (isNaN(val) || val < 0) val = 0;
        if (val > 100) val = 100;
        
        cart[index].discount = val;
        updateCartUI();
    }

    let barcodeBuffer = '';
    let barcodeTimeout = null;

    document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

        if (e.key === 'Enter') {
            if (barcodeBuffer.length > 0) {
                processScannedCode(barcodeBuffer);
                barcodeBuffer = '';
            }
        } else {
            if (e.key.length === 1) { 
                barcodeBuffer += e.key;
                clearTimeout(barcodeTimeout);
                // Delay dinaikkan ke 250ms agar scanner yang sedikit lambat tidak error
                barcodeTimeout = setTimeout(() => { barcodeBuffer = ''; }, 250); 
            }
        }
    });

    let isFetchingBarcode = false;

    function processScannedCode(code) {
        if (!code || code.trim() === '') return;
        if (isFetchingBarcode) return;

        let matchedProduct = globalProducts.find(p => p.code === code);
        
        if (matchedProduct) {
            addToCart(matchedProduct.id);
        } else {
            isFetchingBarcode = true;
            document.getElementById('search-input').value = code;
            
            let currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('hal', 1);
            currentUrl.searchParams.set('brand', '');
            currentUrl.searchParams.set('search', code);
            
            window.history.pushState({}, '', currentUrl.toString());
            currentUrl.searchParams.set('ajax', '1');

            fetch(currentUrl.toString())
                .then(res => res.json())
                .then(data => {
                    globalProducts = data.products; 
                    renderGrid(data.products);
                    renderPagination(data.total_pages, data.current_page);
                    
                    let fetchedProduct = globalProducts.find(p => p.code === code);
                    
                    if (fetchedProduct) {
                        addToCart(fetchedProduct.id);
                        document.getElementById('search-input').value = '';
                        setTimeout(() => loadData('', ''), 500);
                    } else {
                        alert("Barcode '" + code + "' tidak terdaftar di database!");
                        document.getElementById('search-input').value = '';
                        loadData('', ''); 
                    }
                    syncStockUI(); 
                    isFetchingBarcode = false;
                })
                .catch(err => {
                    console.error("Gagal mencari barcode: ", err);
                    alert("Koneksi bermasalah saat mencari produk ke database.");
                    isFetchingBarcode = false;
                });
        }
    }
    function openCameraScanner() {
        document.getElementById('scannerModal').style.display = 'flex';

        Html5Qrcode.getCameras().then(cameras => {
            let cameraId = null;
            if (cameras && cameras.length) {
                let backCam = cameras.find(cam => cam.label.toLowerCase().includes('back'));
                if (backCam) {
                    cameraId = backCam.id;
                } else {
                    cameraId = cameras[0].id;
                }
            }

            html5QrcodeScanner = new Html5Qrcode("reader");
            html5QrcodeScanner.start(
                cameraId,
                { fps: 10, qrbox: { width: 250, height: 250 } },
                onScanSuccess,
                onScanFailure
            ).catch(err => {
                alert('Tidak dapat mengakses kamera: ' + err);
                closeCameraScanner();
            });
        }).catch(err => {
            alert('Tidak dapat mendeteksi kamera: ' + err);
            closeCameraScanner();
        });
    }

    function closeCameraScanner() {
        if (html5QrcodeScanner && html5QrcodeScanner.stop) {
            html5QrcodeScanner.stop().then(() => {
                html5QrcodeScanner.clear();
                document.getElementById('scannerModal').style.display = 'none';
                html5QrcodeScanner = null; // PENTING: Bebaskan kamera
            }).catch(() => {
                document.getElementById('scannerModal').style.display = 'none';
                html5QrcodeScanner = null; // PENTING: Bebaskan kamera meskipun error
            });
        } else {
            document.getElementById('scannerModal').style.display = 'none';
            html5QrcodeScanner = null;
        }
    }

    function onScanSuccess(decodedText, decodedResult) {
        closeCameraScanner();
        processScannedCode(decodedText);
    }
    function onScanFailure(error) {  }

    const checkoutModal = document.getElementById('checkoutModal');
    
    document.getElementById('btn-proses').addEventListener('click', () => {
        if (cart.length === 0) return alert("Keranjang kosong! Silakan klik produk terlebih dahulu.");
        
        let date = new Date();
        let invNo = "INV-" + date.getFullYear() + (date.getMonth()+1).toString().padStart(2, '0') + date.getDate().toString().padStart(2, '0') + "-" + Math.floor(Math.random() * 1000);
        
        document.getElementById('input_inv_no').value = invNo;
        document.getElementById('input_customer').value = '';
        document.getElementById('input_nomor').value = '';
        checkoutModal.style.display = 'flex';
    });

    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    function submitTransaction() {
        let invNo = document.getElementById('input_inv_no').value;
        let customer = document.getElementById('input_customer').value;
        let nomor = document.getElementById('input_nomor').value;

        if (customer.trim() === '') return alert("Nama pelanggan wajib diisi!");

        let payload = {
            inv_no: invNo,
            customer_name: customer,
            nomor: nomor,
            items: cart
        };

        fetch('/order/store', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                cart = []; 
                updateCartUI();
                closeModal('checkoutModal');
                window.location.href = '/order'; 
            } else {
                alert('Gagal: ' + data.message);
            }
        })
        .catch(error => alert('Terjadi kesalahan sistem: ' + error.message));
    }

    window.onload = () => { syncStockUI(); };
</script>