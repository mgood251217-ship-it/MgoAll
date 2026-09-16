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
require_once __DIR__ . '/../controllers/OrderController.php';
require_once __DIR__ . '/../controllers/StoreController.php';
require_once __DIR__ . '/../controllers/PaymentController.php';

$encrypted_id = isset($_GET['id']) ? $_GET['id'] : '';
$encrypted_store = isset($_GET['store_id']) ? $_GET['store_id'] : '';

$order_id = $encrypted_id ? (int)startEnk('dek', $encrypted_id) : 0;
$store_id = $encrypted_store ? (int)startEnk('dek', $encrypted_store) : 0;

if ($order_id === 0) {
    header("Location: /orders");
    exit;
}

$orderController = new OrderController($koneksi);
$productController = new ProductController($koneksi);
$storeController = new StoreController($koneksi);
$paymentController = new PaymentController($koneksi);

$order = $orderController->getOrderById($order_id);

if (!$order) {
    die("Data Order tidak ditemukan.");
}

$store_id = $order['store_id'];
$categories = $productController->getCategoryByStoreId($store_id);

if (isset($_GET['ajax_payment'])) {
    $payments = $paymentController->getPaymentByOrderId($order_id);
    $totalPaid = $paymentController->getPaidByOrderId($order_id);
    $totalOrder = $order['total'] ?? 0;
    $sisaTagihan = max(0, $totalOrder - $totalPaid);
    
    header('Content-Type: application/json');
    echo json_encode([
        'totalOrder' => $totalOrder,
        'totalPaid' => $totalPaid,
        'sisaTagihan' => $sisaTagihan,
        'payments' => $payments
    ]);
    exit;
}
?>



<div class="page-header">
    <h2>Detail Order: <span class="migrated-style-27"><?= sanitize($order['nomorator']) ?></span></h2>
    <a href="/orders" class="btn-secondary-custom">
        <i class="fas fa-arrow-left"></i> Kembali
    </a>
</div>

<div class="top-info-grid">
    <div class="info-box">
        <span class="migrated-style-28">Nama Customer</span>
        <strong class="migrated-style-29"><?= sanitize(title_case($order['customer_name'])) ?></strong>
    </div>
    <div class="info-box">
        <span class="migrated-style-28">Tanggal Order</span>
        <strong class="migrated-style-29"><i class="far fa-calendar-alt me-1"></i> <?= date('d M Y, H:i', strtotime($order['date'])) ?></strong>
    </div>
    <div class="info-box migrated-style-30">
        <span class="migrated-style-28">Tenggat Waktu (Deadline)</span>
        <strong class="migrated-style-31"><i class="far fa-clock me-1"></i> <?= date('d M Y, H:i', strtotime($order['deadline'])) ?></strong>
    </div>
    <div class="info-box migrated-style-32">
        <span class="migrated-style-28">Operator Input</span>
        <div class="badge-info mt-1"><?= sanitize($order['operator_initial']) ?></div>
    </div>
</div>

<div class="main-layout">
    
    <div class="left-panel">
        <div class="filter-card">
            <h3 class="table-header-title migrated-style-33"><i class="fas fa-cart-plus migrated-style-27"></i> Tambah Item</h3>
            <form id="addItemForm">
                <input type="hidden" name="order_id" value="<?= sanitize($order_id) ?>">
                
                <div class="form-group">
                    <label for="kategori">Kategori</label>
                    <select id="kategori" name="kategori" class="form-control select2" required>
                        <option value="" selected>-- Pilih Kategori --</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= sanitize($category['category_id']) ?>" data-name="<?= sanitize($category['name']) ?>">
                                <?= sanitize($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="judul">Judul Produk</label>
                    <select id="judul" name="judul" class="form-control select2" required>
                        <option value="" selected>-- Pilih Judul --</option>
                    </select>
                </div>

                <div class="form-group migrated-style-34" id="ukuranInputs">
                    <div class="migrated-style-4">
                        <label>Panjang (m)</label>
                        <input type="number" step="0.01" min="0" id="panjang" class="form-control" placeholder="Pjg">
                    </div>
                    <div class="migrated-style-35">x</div>
                    <div class="migrated-style-4">
                        <label>Lebar (m)</label>
                        <input type="number" step="0.01" min="0" id="lebar" class="form-control" placeholder="Lbr">
                    </div>
                </div>

                <div class="form-group migrated-style-36" id="ukuranJerseyRow">
                    <label for="ukuranJersey">Ukuran Jersey</label>
                    <select id="ukuranJersey" name="ukuran_jersey" class="form-control select2">
                        <option value="">-- Pilih Ukuran --</option>
                        <option value="XS">XS</option>
                        <option value="S">S</option>
                        <option value="M">M</option>
                        <option value="L">L</option>
                        <option value="XL">XL</option>
                        <option value="2XL">2XL</option>
                        <option value="3XL">3XL</option>
                        <option value="4XL">4XL</option>
                        <option value="5XL">5XL</option>
                    </select>
                </div>

                <div class="form-group migrated-style-36" id="bahanSublim">
                    <label>Kiloan (kg)</label>
                    <input type="number" step="0.01" min="0" id="kiloan" class="form-control" placeholder="Berat (kg)">
                </div>

                <div class="form-group migrated-style-36" id="settingDesain">
                    <label>Waktu Pengerjaan</label>
                    <input type="number" min="00:00" max="23:59" id="waktu" class="form-control" placeholder="Menit">
                </div>

                <div class="form-group migrated-style-36" id="ukuranDropdownRow">
                    <label for="ukuranDropdown">Variasi Ukuran</label>
                    <select id="ukuranDropdown" name="ukuran_variasi" class="form-control select2">
                        <option value="">-- Pilih Ukuran --</option>
                    </select>
                </div>

                <div class="form-group migrated-style-34" id="ukuranSublimRow">
                    <div class="migrated-style-4">
                        <label>Panjang (m)</label>
                        <input type="number" step="0.01" min="0" id="panjangSublim" class="form-control" placeholder="Pjg">
                    </div>
                    <div class="migrated-style-35">x</div>
                    <div class="migrated-style-4">
                        <label>Lbr Bahan</label>
                        <select id="lebarSublim" class="form-control select2">
                            <option value="">Pilih</option>
                            <option value="1.1">1.1</option>
                            <option value="1.2">1.2</option>
                            <option value="1.5">1.5</option>
                            <option value="1.6">1.6</option>
                            <option value="1.8">1.8</option>
                        </select>
                    </div>
                </div> 

                <div class="form-group">
                    <label for="qty">Quantity</label>
                    <input type="number" id="qty" name="qty" class="form-control" min="1" required>
                </div>

                <div class="form-group" id="finishingRow">
                    <label>Finishing</label>
                    <div class="migrated-style-37" id="finishing"></div>
                </div>

                <div class="form-group migrated-style-38">
                    <label class="migrated-style-39">
                        <input class="migrated-style-40" type="checkbox" id="enableDiskon">
                        <span>Diskon Item</span>
                    </label>
                    <input type="number" class="form-control migrated-style-41" id="diskonInput" min="0" placeholder="Nominal Rp">
                </div>

                <hr class="migrated-style-42">

                <div class="migrated-style-9">
                    <div class="migrated-style-43" id="priceDisplay">Total Harga: Rp 0</div>
                    <button type="button" class="btn-primary-custom migrated-style-44" id="btnTambah">
                        <i class="fas fa-plus"></i> Tambah ke Order
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="right-panel">
        
        <div class="table-container">
            <h3 class="table-header-title"><i class="fas fa-list-ul migrated-style-27"></i> Daftar Item Order</h3>
            <div class="table-scroll">
                <table class="table-modern" id="orderItemsTable">
                    <thead>
                        <tr>
                            <th>Judul</th>
                            <th>Finishing</th>
                            <th>Ukuran</th>
                            <th>Qty</th>
                            <th>Sat</th>
                            <th>Jumlah</th>
                            <th class="migrated-style-9">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bottom-split">
            
            <div class="filter-card migrated-style-7">
                <h3 class="table-header-title migrated-style-45"><i class="fas fa-sticky-note migrated-style-46"></i> Catatan Internal</h3>
                <div class="migrated-style-47" id="noteDisplay"></div>
                <form id="addNote">
                    <div class="form-group">
                        <textarea class="form-control" id="exampleFormControlTextarea1" rows="2" name="note" placeholder="Ketik catatan khusus untuk order ini..."></textarea>
                        <input type="hidden" name="order_id" value="<?= $order_id ?>">
                    </div>
                    <button type="submit" class="btn-primary-custom migrated-style-48">
                        <i class="fas fa-save"></i> Simpan Catatan
                    </button>
                </form>
            </div>
            
            <div class="payment-card filter-card migrated-style-7" id="payment-card-body">
                <h3 class="table-header-title migrated-style-45">
                    <i class="fas fa-wallet migrated-style-14"></i> Detail Pembayaran
                </h3>
                
                <div class="payment-summary">
                    <div>
                        <span class="payment-label">Total Tagihan</span>
                        <strong id="total-tagihan-text">Rp 0</strong>
                    </div>
                    <div>
                        <span class="payment-label">Terbayar</span>
                        <strong class="payment-paid" id="terbayar-text">Rp 0</strong>
                    </div>
                    <div>
                        <span class="payment-label">Kekurangan</span>
                        <strong class="payment-due" id="kekurangan-text">Rp 0</strong>
                    </div>
                </div>

                <div id="payment-list-container"></div>
                
            </div>

        </div>
    </div>
</div>

<script>
window.sisaTagihanGlobal = 0;

document.addEventListener('DOMContentLoaded', function () {
    const store_id = <?= (int) $store_id ?>;
    let ukuranMap = {};

    const elKategori = document.getElementById('kategori');
    const elJudul = document.getElementById('judul');
    const elPanjang = document.getElementById('panjang');
    const elLebar = document.getElementById('lebar');
    const elQty = document.getElementById('qty');
    const elFinishing = document.getElementById('finishing');
    const elBtnTambah = document.getElementById('btnTambah');
    const elEnableDiskon = document.getElementById('enableDiskon');
    const elDiskonInput = document.getElementById('diskonInput');

    const bahanSublim = document.getElementById('bahanSublim');
    const ukuranInputs = document.getElementById('ukuranInputs');
    const finishingRow = document.getElementById('finishingRow');
    const elUkuranJersey = document.getElementById('ukuranJersey');
    const elKiloan = document.getElementById('kiloan');
    const elWaktu = document.getElementById('waktu');
    const size = document.getElementById('ukuranDropdown');

    function setValueAndDispatch(el, value) {
        if (!el) return;
        el.value = value;
        el.dispatchEvent(new Event('change'));
    }

    function getKategoriName() {
        if (!elKategori) return '';
        const opt = elKategori.options[elKategori.selectedIndex];
        return opt ? (opt.dataset.name || '') : '';
    }

    function loadOrderItems() {
        fetch(`/action?action=get_order_items&order_id=<?= (int)$order_id ?>`)
            .then(res => res.json())
            .then(res => {
                if (!res.data) return;

                const items = res.data.items || [];
                const total = res.data.total || 0;
                const diskonPerProduk = res.data.diskon_per_produk || {};
                const tbody = document.querySelector('#orderItemsTable tbody');
                tbody.innerHTML = '';

                const itemsByJudul = {};
                items.forEach(item => {
                    if (!itemsByJudul[item.judul]) itemsByJudul[item.judul] = [];
                    itemsByJudul[item.judul].push(item);
                });

                for (const judul in itemsByJudul) {
                    const grup = itemsByJudul[judul];
                    grup.forEach(item => {
                        tbody.insertAdjacentHTML('beforeend', `
                            <tr class="order-item-row" 
                                data-order-item-id="${item.order_item_id}"
                                data-kategori="${item.type || ''}"
                                data-judul="${item.product_name || item.judul}"
                                data-qty="${item.quantity}"
                                data-unit="${item.unit}"
                                data-size="${item.size || ''}"
                                data-finishing="${item.finishing || ''}"
                                data-finishing-utama="${item.finishing_utama || ''}">
                                <td><strong>${item.judul}</strong></td>
                                <td>${item.finishing_names || '-'}</td>
                                <td>${item.size || '-'}</td>
                                <td>${item.quantity}</td>
                                <td>${item.unit}</td>
                                <td>Rp ${Number(item.amount).toLocaleString('id-ID')}</td>
                                <td class="migrated-style-51">
                                    <button class="btn-action btn-danger migrated-style-52">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr> 
                        `);
                    });
                }

                for (const judulKey in diskonPerProduk) {
                    const nilaiDiskon = Number(diskonPerProduk[judulKey]);
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr class="migrated-style-53">
                            <td class="migrated-style-54" colspan="5"><strong>Diskon ${judulKey}</strong></td>
                            <td class="migrated-style-55" colspan="2"><strong>- Rp ${nilaiDiskon.toLocaleString('id-ID')}</strong></td>
                        </tr>
                    `);
                }

                tbody.insertAdjacentHTML('beforeend', `
                    <tr class="migrated-style-56">
                        <td class="migrated-style-57" colspan="5"><strong>TOTAL:</strong></td>
                        <td class="migrated-style-58" colspan="2"><strong>Rp ${Number(total).toLocaleString('id-ID')}</strong></td>
                    </tr>
                `);

                loadPayments();
            });
    }

    function loadProdukByKategori(kategoriId, kategoriName) {
        if (elJudul) {
            elJudul.innerHTML = '<option value="">-- Pilih Judul --</option>';
            elJudul.dispatchEvent(new Event('change'));
        }
        ukuranMap = {};
        const seen = new Set();

        fetch(`/action?action=get_product&category_id=${encodeURIComponent(kategoriId)}`)
            .then(response => response.json())
            .then(data => {
                if (!data.data) return;

                data.data.forEach(product => {
                    let nameOnly = product.name;
                    if (kategoriName === 'PAKET INDOOR OUTDOOR') {
                        nameOnly = nameOnly.replace(/\s*\d+(\.\d+)?\s*[x├ùX]\s*\d+(\.\d+)?/gi, '').trim();
                    }
                    const ukuranMatch = product.name.match(/(\d+(\.\d+)?\s*[x├ùX]\s*\d+(\.\d+)?)/i);
                    const ukuran = ukuranMatch ? ukuranMatch[0].replace(/├ù/gi, 'x') : null;

                    if (!ukuranMap[nameOnly]) ukuranMap[nameOnly] = [];
                    if (ukuran && !ukuranMap[nameOnly].includes(ukuran)) {
                        ukuranMap[nameOnly].push(ukuran);
                    }

                    if (!seen.has(nameOnly)) {
                        seen.add(nameOnly);
                        const opt = document.createElement('option');
                        opt.value = product.product_id;
                        opt.dataset.name = nameOnly;
                        opt.dataset.unit = product.unit_type || product.unit;
                        opt.dataset.price = product.price;
                        opt.textContent = nameOnly;
                        if (elJudul) elJudul.appendChild(opt);
                    }
                });
            });

        if (elUkuranJersey) setValueAndDispatch(elUkuranJersey, '');
        if (elKiloan) elKiloan.value = '';
        if (elWaktu) elWaktu.value = '';
        loadFinishingOptions(kategoriId);
    }

    function loadFinishingOptions(kategori) {
        if (elFinishing) {
            elFinishing.innerHTML = '';
        }

        if (kategori) {
            fetch(`/action?action=get_finishing&category_id=${encodeURIComponent(kategori)}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.data) return;

                    const seenFinishing = new Set();
                    data.data.forEach(finishing => {
                        if (!seenFinishing.has(finishing.name)) {
                            seenFinishing.add(finishing.name);
                            elFinishing.insertAdjacentHTML('beforeend', `
                                <label class="migrated-style-59">
                                    <input class="finishing-checkbox" type="checkbox" id="fin_${finishing.finishing_id}" value="${finishing.finishing_id}" data-name="${finishing.name}" data-price="${finishing.price}">
                                    <span class="migrated-style-60">${finishing.name}</span>
                                </label>
                            `);
                        }
                    });
                });
        }
    }

    function toggleFinishingDisplay(kategori) {
        const row = document.getElementById('finishingRow');
        if (row) row.style.display = '';

        switch (kategori) {
            case 'PAKET INDOOR OUTDOOR':
                if (row) row.style.display = 'none';
                return;
            case 'STAMP':
            case 'MERCENDISE':
            case 'MERCENDISE AKRILIK':
                if (row) row.style.display = 'none';
                if (elFinishing) elFinishing.innerHTML = '';
                return;
        }

        if (kategori === 'AKRILIK') {
            if (elPanjang) elPanjang.placeholder = 'Pjg (cm)';
            if (elLebar) elLebar.placeholder = 'Lbr (cm)';
        } else {
            if (elPanjang) elPanjang.placeholder = 'Pjg (m)';
            if (elLebar) elLebar.placeholder = 'Lbr (m)';
        }

        if (kategori === 'DTF') {
            if (elLebar) { elLebar.value = '0.58'; elLebar.readOnly = true; }
            if (elPanjang) { elPanjang.value = ''; elPanjang.readOnly = false; }
        } else {
            if (elLebar) { elLebar.value = ''; elLebar.readOnly = false; }
            if (elPanjang) { elPanjang.value = ''; elPanjang.readOnly = false; }
        }
    }

    function updateUkuranView(name) {
        const nameStr = name ? String(name) : '';
        const kategori = getKategoriName();
        const selectedOption = elJudul ? elJudul.options[elJudul.selectedIndex] : null;
        const unit = (selectedOption && selectedOption.dataset.unit) ? selectedOption.dataset.unit : '';
        const judul = (selectedOption && selectedOption.dataset.name) ? selectedOption.dataset.name : '';

        const idsToHide = ['ukuranSublimRow', 'ukuranInputs', 'ukuranJerseyRow', 'bahanSublim', 'settingDesain'];
        idsToHide.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });

        if (nameStr.includes('TRANSFERPAPER') || nameStr.includes('PRINT PRES')) {
            const subRow = document.getElementById('ukuranSublimRow');
            if (subRow) subRow.style.display = 'flex';
        } else if (kategori === 'JERSEY') {
            const jerRow = document.getElementById('ukuranJerseyRow');
            if (jerRow) jerRow.style.display = 'flex';
        } else if (unit === 'M2' || unit === 'CM2') {
            const inpRow = document.getElementById('ukuranInputs');
            if (inpRow) inpRow.style.display = 'flex';
        } else if (kategori === 'JASA' && (judul === 'SETTING' || judul === 'POTONG AKRILIK')) {
            const setRow = document.getElementById('settingDesain');
            if (setRow) setRow.style.display = 'flex';
        }

        if (judul.includes('BAHAN') && unit === 'PCS') {
            if (bahanSublim) bahanSublim.style.display = 'flex';
            if (ukuranInputs) ukuranInputs.style.display = 'none';
            if (finishingRow) finishingRow.style.display = 'none';
        }
    }

    if (elKategori) {
        elKategori.addEventListener('change', function () {
            const kategoriId = this.value;
            const kategoriName = getKategoriName();
            toggleFinishingDisplay(kategoriName);
            loadProdukByKategori(kategoriId, kategoriName);
        });
    }

    if (elJudul) {
        elJudul.addEventListener('change', function () {
            const selectedOption = this.options[this.selectedIndex];
            const name = (selectedOption && selectedOption.dataset && selectedOption.dataset.name) ? selectedOption.dataset.name : '';
            const ukuranList = ukuranMap[name] || [];
            const ukuranDropdown = document.getElementById('ukuranDropdown');
            const ukuranDropdownRow = document.getElementById('ukuranDropdownRow');

            if (ukuranList.length > 0) {
                if (ukuranDropdown) {
                    ukuranDropdown.innerHTML = '<option value="">-- Pilih Ukuran --</option>';
                    ukuranList.forEach(uk => {
                        const opt = document.createElement('option');
                        opt.value = uk;
                        opt.textContent = uk;
                        ukuranDropdown.appendChild(opt);
                    });
                    ukuranDropdown.dispatchEvent(new Event('change'));
                }
                if (ukuranDropdownRow) ukuranDropdownRow.style.display = 'flex';
            } else {
                if (ukuranDropdownRow) ukuranDropdownRow.style.display = 'none';
            }
            updateUkuranView(name);
        });
    }

    function updatePricePreview() {
        if (!elJudul) return;
        const judulValue = elJudul.value || '';
        const kategoriValue = elKategori ? elKategori.value : '';
        const qtyValue = elQty ? elQty.value : '';
        if (!judulValue.trim() || !kategoriValue.trim() || !qtyValue.trim()) return;

        const selectedOption = elJudul.options[elJudul.selectedIndex];
        const judulAsli = selectedOption?.dataset?.name || '';
        const kategoriAsli = getKategoriName();

        let panjangAsli = elPanjang ? parseFloat(elPanjang.value) || 0 : 0;
        let lebarAsli = elLebar ? parseFloat(elLebar.value) || 0 : 0;
        if ((judulAsli.includes('TRANSFERPAPER') || judulAsli.includes('PRINT PRES')) && kategoriAsli === 'SUBLIM') {
            panjangAsli = parseFloat(document.getElementById('lebarSublim')?.value) || 0;
            lebarAsli = parseFloat(document.getElementById('panjangSublim')?.value) || 0;
        }

        const finishingIds = Array.from(document.querySelectorAll('.finishing-checkbox:checked')).map(cb => cb.value);

        const formData = new FormData();
        formData.append('order_id', <?= $order_id ?>);
        formData.append('product_id', elJudul.value);
        formData.append('judul', judulAsli);
        formData.append('quantity', parseInt(elQty?.value) || 1);
        formData.append('finishing', finishingIds.join(','));
        formData.append('panjang', panjangAsli);
        formData.append('lebar', lebarAsli);
        formData.append('kiloan', parseFloat(elKiloan?.value) || 0);
        formData.append('waktu', parseFloat(elWaktu?.value) || 0);
        formData.append('ukuranJersey', elUkuranJersey?.value || '');
        formData.append('size', size?.value || elUkuranJersey?.value || '');
        formData.append('diskon', elEnableDiskon?.checked ? (parseFloat(elDiskonInput?.value) || 0) : 0);
        formData.append('store', '<?= $encrypted_store ?>');

        fetch('/action?action=price', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(response => {
                const priceDisplay = document.getElementById('priceDisplay');
                if (priceDisplay) {
                    priceDisplay.textContent = response.success ? 'Total Harga: Rp ' + Number(response.data.total || 0).toLocaleString('id-ID') : 'Rp 0';
                }
            }).catch(() => {});
    }

    const inputsToWatch = ['kategori', 'judul', 'ukuranDropdown', 'panjang', 'lebar', 'kiloan', 'waktu', 'ukuranJersey', 'qty', 'enableDiskon', 'lebarSublim', 'panjangSublim', 'diskonInput'];
    inputsToWatch.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            if (el.tagName === 'SELECT') {
                el.addEventListener('change', updatePricePreview);
            } else {
                el.addEventListener('input', updatePricePreview);
            }
        }
    });
    document.addEventListener('change', (e) => {
        if (e.target.classList.contains('finishing-checkbox')) updatePricePreview();
    });

    if (elEnableDiskon) {
        elEnableDiskon.addEventListener('change', function () {
            elDiskonInput.style.display = this.checked ? 'block' : 'none';
            if (!this.checked) {
                elDiskonInput.value = '';
                updatePricePreview();
            }
        });
    }

    if (elBtnTambah) {
        elBtnTambah.addEventListener('click', function () {
            if (!elJudul.value || !elQty.value) {
                Swal.fire('Peringatan', 'Judul dan Qty wajib diisi', 'warning');
                return;
            }

            const btn = this;
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
            btn.disabled = true;

            const selectedOpt = elJudul.options[elJudul.selectedIndex];
            const selectedJudulName = selectedOpt?.dataset?.name || '';

            let panjang = parseFloat(elPanjang?.value) || 0;
            let lebar = parseFloat(elLebar?.value) || 0;
            if (selectedJudulName.includes('TRANSFERPAPER') || selectedJudulName.includes('PRINT PRES')) {
                panjang = parseFloat(document.getElementById('panjangSublim')?.value) || 0;
                lebar = parseFloat(document.getElementById('lebarSublim')?.value) || 0;
            }
            const finishingIds = Array.from(document.querySelectorAll('.finishing-checkbox:checked')).map(cb => cb.value);

            const formData = new FormData();
            formData.append('order_id', <?= $order_id ?>);
            formData.append('product_id', elJudul.value);
            formData.append('judul', selectedJudulName);
            formData.append('quantity', parseInt(elQty?.value) || 1);
            formData.append('finishing', finishingIds.join(','));
            formData.append('panjang', panjang);
            formData.append('lebar', lebar);
            formData.append('kiloan', parseFloat(elKiloan?.value) || 0);
            formData.append('waktu', parseFloat(elWaktu?.value) || 0);
            formData.append('size', size?.value || elUkuranJersey?.value || '');
            formData.append('store', '<?= $encrypted_store ?>');
            if (elEnableDiskon?.checked) formData.append('diskon', parseFloat(elDiskonInput?.value) || 0);

            const orderItemId = document.getElementById('addItemForm').getAttribute('data-order-item-id');
            if (orderItemId) formData.append('order_item_id', orderItemId);

            fetch("/action?action=create_item", {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Item disimpan', showConfirmButton: false, timer: 1500 });
                        loadOrderItems();

                        document.getElementById('addItemForm').reset();
                        document.getElementById('addItemForm').removeAttribute('data-order-item-id');
                        document.querySelectorAll('.finishing-checkbox').forEach(cb => cb.checked = false);
                        setValueAndDispatch(elKategori, '');
                        document.getElementById('priceDisplay').textContent = 'Total Harga: Rp 0';
                    } else {
                        Swal.fire('Gagal', data.message, 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('Error', 'Terjadi kesalahan jaringan', 'error');
                })
                .finally(() => {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                });
        });
    }

    document.querySelector('#orderItemsTable').addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-danger');
        if (btn) {
            const row = btn.closest('tr');
            const orderItemId = row.dataset.orderItemId;
            if (!orderItemId) return;
            const store = '<?= $encrypted_store ?>';

            fetch('/action?action=delete_item', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `order_item_id=${encodeURIComponent(orderItemId)}&store=${encodeURIComponent(store)}`
            })
                .then(res => res.json())
                .then(response => {
                    if (response.success) {
                        loadOrderItems();
                        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Item dihapus', showConfirmButton: false, timer: 1500 });
                    } else {
                        Swal.fire('Gagal', response.message, 'error');
                    }
                });
        }
    });

    document.querySelector('#orderItemsTable').addEventListener('dblclick', function (e) {
        const row = e.target.closest('.order-item-row');
        if (row) {
            const kategoriValue = row.dataset.kategori;
            const judul = row.dataset.judul;
            const qty = row.dataset.qty;
            const sizeData = row.dataset.size;
            const finishing = row.dataset.finishing;
            const orderItemId = row.dataset.orderItemId;

            document.getElementById('addItemForm').setAttribute('data-order-item-id', orderItemId);

            if (elKategori && kategoriValue) {
                let matchedOpt = null;
                Array.from(elKategori.options).forEach(opt => {
                    if (opt.value === kategoriValue || opt.dataset.name === kategoriValue) {
                        matchedOpt = opt.value;
                    }
                });
                if (matchedOpt) {
                    setValueAndDispatch(elKategori, matchedOpt);
                }
            }

            setTimeout(() => {
                if (elJudul) {
                    const options = Array.from(elJudul.options);
                    const optionMatch = options.find(opt => opt.text.trim().toLowerCase() === judul.trim().toLowerCase());
                    if (optionMatch) {
                        setValueAndDispatch(elJudul, optionMatch.value);
                    }
                }

                if (finishing && elFinishing) {
                    const finishingNames = finishing.toString().split(',').map(s => s.trim().toLowerCase());
                    document.querySelectorAll('.finishing-checkbox').forEach(cb => {
                        const text = (cb.dataset.name || '').toLowerCase();
                        const val = (cb.value || '').toLowerCase();
                        cb.checked = finishingNames.includes(text) || finishingNames.includes(val);
                    });
                }

                if (elQty) elQty.value = qty;

                if (sizeData && sizeData.includes('x')) {
                    const sizeParts = sizeData.split('x').map(s => parseFloat(s.trim()));
                    if (elPanjang) elPanjang.value = sizeParts[0];
                    if (elLebar) elLebar.value = sizeParts[1];
                }

                updatePricePreview();

                window.scrollTo({
                    top: document.querySelector(".filter-card").offsetTop - 50,
                    behavior: 'smooth'
                });
            }, 500);
        }
    });

    function loadNote() {
        fetch(`/action?action=get_note&order_id=<?= $order_id ?>`)
            .then(res => res.json())
            .then(response => {
                if (response.data && response.data.note) {
                    document.getElementById('noteDisplay').innerHTML = `
                        <div class="migrated-style-61">
                            <i class="fas fa-info-circle migrated-style-62"></i> ${response.data.note}
                        </div>`;
                    document.getElementById('exampleFormControlTextarea1').value = response.data.note;
                }
            }).catch(err => {});
    }

    const elAddNote = document.getElementById('addNote');
    if (elAddNote) {
        elAddNote.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new URLSearchParams(new FormData(this));
            formData.append('store', '<?= $encrypted_store ?>');
            fetch('/action?action=save_note', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(response => {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Catatan disimpan', showConfirmButton: false, timer: 1500 });
                    loadNote();
                });
        });
    }

    function addPayment() {
        const sisa = window.sisaTagihanGlobal;

        Swal.fire({
            title: 'Tambah Pembayaran',
            html: `
                <div class="migrated-style-63">
                    <label class="migrated-style-64">Tipe Pembayaran</label>
                    <select id="swal-tipe" class="form-control mb-3 migrated-style-65">
                        <option value="LUNAS">LUNAS (Rp ${Number(sisa).toLocaleString('id-ID')})</option>
                        <option value="DP">DP / Sebagian</option>
                    </select>

                    <div class="migrated-style-66" id="div-nominal">
                        <label class="migrated-style-64">Nominal Pembayaran (Rp)</label>
                        <input type="number" id="swal-nominal" class="form-control mb-3 migrated-style-65" placeholder="Maks: ${sisa}">
                    </div>

                    <label class="migrated-style-64">Metode Pembayaran</label>
                    <select id="swal-method" class="form-control mb-3 migrated-style-65">
                        <option value="CASH">CASH</option>
                        <option value="TF">TF</option>
                    </select>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Proses Pembayaran',
            customClass: { popup: 'rounded-4' },
            didOpen: () => {
                const tipeEl = document.getElementById('swal-tipe');
                const divNominal = document.getElementById('div-nominal');
                const swalNominal = document.getElementById('swal-nominal');

                tipeEl.addEventListener('change', () => {
                    if (tipeEl.value === 'DP') {
                        divNominal.style.display = 'block';
                        swalNominal.focus();
                    } else {
                        divNominal.style.display = 'none';
                    }
                });
            },
            preConfirm: () => {
                const tipe = document.getElementById('swal-tipe').value;
                const method = document.getElementById('swal-method').value;
                const nominal = document.getElementById('swal-nominal').value;

                if (tipe === 'DP') {
                    if (!nominal || parseFloat(nominal) <= 0) {
                        Swal.showValidationMessage('Nominal DP wajib diisi dan lebih dari 0');
                        return false;
                    }
                    if (parseFloat(nominal) > sisa) {
                        Swal.showValidationMessage('Nominal DP tidak boleh lebih dari sisa tagihan');
                        return false;
                    }
                }
                return { tipe, method, nominal };
            }
        }).then((res) => {
            if (res.isConfirmed) {
                const formData = new URLSearchParams();
                formData.append('order_id', <?= $order_id ?>);
                formData.append('store_id', <?= $store_id ?>);

                if (res.value.tipe === 'LUNAS') {
                    formData.append('lunas_method', res.value.method);
                } else {
                    formData.append('nominal', res.value.nominal);
                    formData.append('payment_method', res.value.method);
                }

                fetch('/action?action=add_payment', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData
                })
                    .then(r => r.text())
                    .then(text => {
                        let resp;
                        try {
                            resp = JSON.parse(text);
                        } catch (e) {
                            console.error("Response bukan JSON valid:", text);
                            throw new Error("Format response server tidak valid");
                        }

                        if (resp.success) {
                            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: resp.message, showConfirmButton: false, timer: 1500 });
                            loadOrderItems();
                        } else {
                            Swal.fire('Gagal', resp.message, 'error');
                        }
                    }).catch(err => Swal.fire('Error', err.message || 'Gagal menghubungi server', 'error'));
            }
        });
    }

    function editPayment(paymentId, nominal, method, dateStr) {
        Swal.fire({
            title: 'Ubah Data Pembayaran',
            html: `
                <div class="migrated-style-63">
                    <label class="migrated-style-64">Nominal Pembayaran</label>
                    <input type="number" id="swal-nominal" class="form-control mb-3 migrated-style-65" value="${nominal}">
                    
                    <label class="migrated-style-64">Metode Pembayaran</label>
                    <select id="swal-method" class="form-control mb-3 migrated-style-65">
                        <option value="CASH" ${method == 'CASH' ? 'selected' : ''}>CASH</option>
                        <option value="TF" ${method == 'TF' ? 'selected' : ''}>TF</option>
                    </select>
                    
                    <label class="migrated-style-64">Tanggal Pembayaran</label>
                    <input type="datetime-local" id="swal-date" class="form-control mb-3 migrated-style-65" value="${dateStr}">
                    
                    <label class="migrated-style-64">Keterangan / Alasan Ubah <span class="migrated-style-67">*</span></label>
                    <input type="text" id="swal-ket" class="form-control migrated-style-65" placeholder="Cth: Koreksi metode pembayaran">
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Simpan Perubahan',
            customClass: { popup: 'rounded-4' },
            preConfirm: () => {
                const n = document.getElementById('swal-nominal').value;
                const m = document.getElementById('swal-method').value;
                const d = document.getElementById('swal-date').value;
                const k = document.getElementById('swal-ket').value;

                if (!n || !m || !d || !k) {
                    Swal.showValidationMessage('Semua field wajib diisi, termasuk Keterangan');
                    return false;
                }
                return { nominal: n, method: m, date: d, ket: k };
            }
        }).then((res) => {
            if (res.isConfirmed) {
                const formData = new URLSearchParams();
                formData.append('payment_id', paymentId);
                formData.append('order_id', <?= $order_id ?>);
                formData.append('store_id', <?= $store_id ?>);
                formData.append('nominal', res.value.nominal);
                formData.append('payment_method', res.value.method);
                formData.append('tanggal', res.value.date);
                formData.append('keterangan', res.value.ket);

                fetch('/action?action=edit_payment', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData
                })
                    .then(r => r.text())
                    .then(text => {
                        let resp;
                        try {
                            resp = JSON.parse(text);
                        } catch (e) {
                            console.error("Response bukan JSON valid:", text);
                            throw new Error("Format response server tidak valid");
                        }

                        if (resp.success) {
                            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: resp.message, showConfirmButton: false, timer: 1500 });
                            loadOrderItems();
                        } else {
                            Swal.fire('Gagal', resp.message, 'error');
                        }
                    }).catch(err => Swal.fire('Error', err.message || 'Gagal menghubungi server', 'error'));
            }
        });
    }

    function deletePayment(paymentId) {
        Swal.fire({
            title: 'Hapus Pembayaran?',
            text: "Masukkan keterangan / alasan penghapusan:",
            input: 'text',
            inputPlaceholder: 'Contoh: Salah input nominal',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Ya, Hapus',
            preConfirm: (keterangan) => {
                if (!keterangan) {
                    Swal.showValidationMessage('Keterangan wajib diisi!');
                }
                return keterangan;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new URLSearchParams();
                formData.append('payment_id', paymentId);
                formData.append('order_id', <?= $order_id ?>);
                formData.append('store_id', <?= $store_id ?>);
                formData.append('keterangan_hapus', result.value);

                fetch('/action?action=delete_payment', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData
                })
                    .then(r => r.text())
                    .then(text => {
                        let res;
                        try {
                            res = JSON.parse(text);
                        } catch (e) {
                            console.error("Response bukan JSON valid:", text);
                            throw new Error("Format response server tidak valid");
                        }

                        if (res.success) {
                            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: res.message, showConfirmButton: false, timer: 1500 });
                            loadOrderItems();
                        } else {
                            Swal.fire('Gagal', res.message, 'error');
                        }
                    }).catch(err => Swal.fire('Error', err.message || 'Gagal menghubungi server', 'error'));
            }
        });
    }

    function loadPayments() {
        fetch(window.location.pathname + window.location.search + '&ajax_payment=1')
            .then(r => r.json())
            .then(res => {
                document.getElementById('total-tagihan-text').innerText = 'Rp ' + Number(res.totalOrder).toLocaleString('id-ID');
                document.getElementById('terbayar-text').innerText = 'Rp ' + Number(res.totalPaid).toLocaleString('id-ID');
                document.getElementById('kekurangan-text').innerText = 'Rp ' + Number(res.sisaTagihan).toLocaleString('id-ID');

                const listContainer = document.getElementById('payment-list-container');
                if (res.payments.length === 0) {
                    listContainer.innerHTML = `
                        <div class="payment-empty">
                            <i class="fas fa-receipt"></i>
                            <span>Belum ada pembayaran.</span>
                        </div>`;
                } else {
                    let html = '<div class="payment-list">';
                    res.payments.forEach(pay => {
                        const dateObj = new Date(pay.date.replace(' ', 'T'));
                        const dateStr = dateObj.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + dateObj.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                        const isLunas = pay.status === 'LUNAS' ? '&nbsp;<span class="payment-status">LUNAS</span>' : '';
                        const rawDate = pay.date.replace(' ', 'T').slice(0, 16);

                        html += `
                            <div class="payment-row"
                                data-payment-id="${pay.payment_id}"
                                data-nominal="${pay.nominal}"
                                data-method="${pay.payment_method}"
                                data-date="${rawDate}">
                                <div class="payment-amount">Rp ${Number(pay.nominal).toLocaleString('id-ID')}</div>
                                <div class="payment-date"><i class="far fa-calendar-alt"></i> ${dateStr}</div>
                                <div class="payment-method"><strong>${pay.payment_method}</strong>${isLunas}</div>
                                <div class="payment-actions">
                                    <button type="button" class="btn-action btn-warning btn-edit-payment" title="Edit pembayaran">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn-action btn-danger btn-delete-payment" title="Hapus pembayaran">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>`;
                    });
                    html += '</div>';
                    listContainer.innerHTML = html;
                }

                const btnTambah = document.getElementById('btn-tambah-pembayaran');
                if (res.sisaTagihan > 0) {
                    if (!btnTambah) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.id = 'btn-tambah-pembayaran';
                        btn.className = 'btn-primary-custom payment-add-button';
                        btn.innerHTML = '<i class="fas fa-plus"></i> Tambah Pembayaran';
                        btn.addEventListener('click', addPayment);
                        document.getElementById('payment-card-body').appendChild(btn);
                    }
                } else {
                    if (btnTambah) btnTambah.remove();
                }

                window.sisaTagihanGlobal = res.sisaTagihan;
            });
    }

    const paymentListContainer = document.getElementById('payment-list-container');
    if (paymentListContainer) {
        paymentListContainer.addEventListener('click', function (e) {
            const row = e.target.closest('.payment-row');
            if (!row) return;

            const paymentId = row.dataset.paymentId;
            const nominal = row.dataset.nominal;
            const method = row.dataset.method;
            const dateStr = row.dataset.date;

            if (e.target.closest('.btn-edit-payment')) {
                editPayment(paymentId, nominal, method, dateStr);
            } else if (e.target.closest('.btn-delete-payment')) {
                deletePayment(paymentId);
            }
        });
    }

    loadOrderItems();
    loadNote();
});
</script>