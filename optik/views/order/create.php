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


<div class="pos-container">
    <div class="main-content">
        
        <div class="brand-filter-wrapper">
            <button type="button" onclick="loadData('', document.getElementById('search-input').value)" class="brand-badge <?= ($brand_id === '') ? 'active' : '' ?>" data-brand="">
                <div class="brand-all-icon-small">❖</div>
                <span>Semua</span>
            </button>
            <?php foreach($brands as $b): ?>
                <button type="button" onclick="loadData('<?= $b['id'] ?>', document.getElementById('search-input').value)" class="brand-badge <?= ($brand_id == $b['id']) ? 'active' : '' ?>" data-brand="<?= $b['id'] ?>">
                    <img src="/assets/brands/<?= $b['img'] ?>" onerror="this.src='/assets/web/default.png'; this.style.opacity='0.3';">
                    <span><?= htmlspecialchars($b['name']) ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="header-action">
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
                        
                        <img class="catalog-product-image" src="/assets/products/<?= $item['img']; ?>"
                            onerror="this.src='/assets/web/default.png'; this.style.opacity='0.3';">
                        
                        <div class="catalog-product-name" title="<?= htmlspecialchars($item['name']) ?>">
                            <?= htmlspecialchars($item['name']); ?>
                        </div>
                        <div class="catalog-product-info">
                            <?= htmlspecialchars($item['info']); ?>
                        </div>
                        <div class="catalog-product-price">
                            Rp <?= number_format($item['price'], 0, ',', '.'); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="catalog-empty">Tidak ada produk yang ditemukan.</div>
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
            <p class="cart-empty">Keranjang masih kosong</p>
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
        <div class="modal-header">Selesaikan Pesanan</div>
        <div class="form-group">
            <label>Nomor Invoice (Otomatis)</label>
            <input class="invoice-readonly" type="text" id="input_inv_no" readonly>
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
        <div class="modal-header">
            Scan Barcode Produk
            <span class="close-btn" onclick="closeCameraScanner()">✕</span>
        </div>
        <div id="reader"></div>
        <div class="scanner-help">Arahkan kamera ke barcode/QR Code produk</div>
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
            grid.innerHTML = '<div class="catalog-empty">Tidak ada produk yang ditemukan.</div>';
            return;
        }

        products.forEach(item => {
            grid.innerHTML += `
                <div class="product-card" id="card-${item.id}" onclick="addToCart(${item.id})">
                    <div class="stock-badge" id="stock-badge-${item.id}">${item.maxStock}</div>
                    <img class="catalog-product-image" src="/assets/products/${item.img || ''}"
                         onerror="this.src='/assets/web/default.png'; this.style.opacity='0.3';">
                    
                    <div class="catalog-product-name" title="${item.name}">
                        ${item.name}
                    </div>
                    <div class="catalog-product-info">
                        ${item.info || ''}
                    </div>
                    <div class="catalog-product-price">
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
            cartContainer.innerHTML = '<p class="cart-empty">Keranjang masih kosong</p>';
        } else {
            cart.forEach((item, index) => {
                let discountAmount = item.price * (item.discount / 100);
                let finalPrice = item.price - discountAmount;
                let amount = finalPrice * item.quantity;
                total += amount;

                let isDiscounted = item.discount > 0;

                cartContainer.innerHTML += `
                    <div class="cart-item">
                        <div class="cart-item-info">
                            <div class="cart-item-name">${item.name}</div>
                            <div class="cart-original-price ${isDiscounted ? 'is-discounted' : ''}">
                                ${formatRupiah(item.price)}
                            </div>
                            ${isDiscounted ? `<div class="cart-discount-price">${formatRupiah(finalPrice)}</div>` : ''}
                        </div>
                        
                        <div class="cart-item-controls">
                            <div class="cart-discount-control">
                                <span class="cart-discount-label">Disc %</span>
                                <input class="cart-discount-input" type="number" min="0" max="100" value="${item.discount}"
                                       onchange="changeDiscount(${index}, this.value)">
                            </div>
                            <div class="cart-quantity-control">
                                <button class="cart-quantity-button" onclick="changeQty(${index}, -1)">-</button>
                                <span class="cart-quantity">${item.quantity}</span>
                                <button class="cart-quantity-button" onclick="changeQty(${index}, 1)">+</button>
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

        fetch('/order/next-invoice')
            .then(response => response.json())
            .then(data => {
                document.getElementById('input_inv_no').value = data.inv_no;
                document.getElementById('input_customer').value = '';
                document.getElementById('input_nomor').value = '';
                checkoutModal.style.display = 'flex';
            })
            .catch(error => alert('Gagal mengambil nomor invoice: ' + error.message));
    });

    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    function submitTransaction() {
        let customer = document.getElementById('input_customer').value;
        let nomor = document.getElementById('input_nomor').value;

        if (customer.trim() === '') return alert("Nama pelanggan wajib diisi!");

        let payload = {
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