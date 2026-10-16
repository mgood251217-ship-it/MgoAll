<?php

$action = isset($url_parts[1]) ? $url_parts[1] : 'list';

if ($action === 'create') {
    include 'views/order/create.php';
    return;
}

if ($action === 'store') {
    include 'views/order/store.php';
    return; 
}

require_once 'models/Order.php';
$orderModel = new Order($pdo);

$start_date = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01'); // Awal bulan
$end_date = isset($_GET['end']) ? $_GET['end'] : date('Y-m-t'); // Akhir bulan
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

$orders = $orderModel->getOrders($start_date, $end_date, $search_query);

$stmtSum = $pdo->prepare("SELECT SUM(nominal) FROM payments WHERE order_id = :id");
?>


<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<div class="page-container">
    <div class="header-action">
        <div>
            <h2 class="page-title">Riwayat Pesanan</h2>
            <p class="page-subtitle">Daftar transaksi kasir optik</p>
        </div>
        <a href="/order/create" class="btn-primary">+ Buat Pesanan Baru</a>
    </div>

    <form class="filter-card" method="GET" action="/order">
        <div class="form-group">
            <label>Dari Tanggal</label>
            <input type="date" name="start" value="<?= htmlspecialchars($start_date) ?>">
        </div>
        <div class="form-group">
            <label>Sampai Tanggal</label>
            <input type="date" name="end" value="<?= htmlspecialchars($end_date) ?>">
        </div>
        <div class="form-group flex-1">
            <label>Pencarian</label>
            <input type="text" name="search" placeholder="Cari No. Invoice, Nama..." value="<?= htmlspecialchars($search_query) ?>">
        </div>
        <button type="submit" class="btn-primary">Cari Data</button>
    </form>

    <div class="table-container">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>No. Invoice</th>
                    <th>Pelanggan</th>
                    <th>Total Tagihan</th>
                    <th class="text-center">Status</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($orders)): ?>
                    <?php foreach($orders as $order): ?>
                        <?php 
                            $stmtSum->execute(['id' => $order['id']]);
                            $total_dibayar = (float) $stmtSum->fetchColumn();

                            $sisa_tagihan = $order['total'] - $total_dibayar;

                            if ($sisa_tagihan <= 0) {
                                $status_text = 'Lunas';
                                $badge_class = 'badge-paid';
                                $is_lunas = true;
                            } elseif ($total_dibayar > 0) {
                                $status_text = 'DP';
                                $badge_class = 'badge-partial';
                                $is_lunas = false;
                            } else {
                                $status_text = 'Belum Bayar';
                                $badge_class = 'badge-unpaid';
                                $is_lunas = false;
                            }
                        ?>
                        <tr>
                            <td class="text-semibold-primary"><?= htmlspecialchars($order['inv_no']) ?></td>
                            <td class="font-medium">
                                <?= htmlspecialchars($order['customer_name']) ?><br>
                                <small class="text-muted"><?= htmlspecialchars($order['nomor']) ?></small>
                            </td>
                            <td class="font-semibold">Rp <?= number_format($order['total'], 0, ',', '.') ?></td>
                            
                            <td class="text-center">
                                <span class="status-badge <?= $badge_class ?>">
                                    <?= $status_text ?>
                                </span>
                            </td>
                            
                            <td><?= date('d M Y, H:i', strtotime($order['create_at'])) ?></td>
                            <td>
                                <?php 
                                    $wa_number = isset($order['nomor']) ? $order['nomor'] : ''; 
                                    $wa_number = preg_replace('/[^0-9]/', '', $wa_number);
                                    
                                    if (substr($wa_number, 0, 1) === '0') {
                                        $wa_number = '62' . substr($wa_number, 1);
                                    }
                                ?>
                                <div class="action-group">  
                                    <a href="https://wa.me/<?= $wa_number ?>" target="_blank" class="btn-icon btn-wa" title="Chat WhatsApp Pelanggan">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M3 21l1.65 -3.8a9 9 0 1 1 3.4 2.9l-5.05 .9"></path>
                                            <path d="M9 10a.5 .5 0 0 0 1 0v-1a.5 .5 0 0 0 -1 0v1a5 5 0 0 0 5 5h1a.5 .5 0 0 0 0 -1h-1a.5 .5 0 0 0 0 1"></path>
                                        </svg>
                                    </a>

                                    <button class="btn-icon btn-info" title="Detail Pesanan" onclick="openDetail(<?= $order['id'] ?>, '<?= $order['inv_no'] ?>')">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                    
                                    <?php if($is_lunas): ?>
                                        <button class="btn-icon btn-disabled" disabled title="Sudah Lunas">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-icon btn-success" title="Bayar / DP" onclick="openPayment(<?= $order['id'] ?>, <?= $sisa_tagihan ?>)">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button class="btn-icon btn-danger" title="Download PDF Struk" onclick="downloadStrukPDF(<?= $order['id'] ?>)">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><polyline points="9 15 12 18 15 15"></polyline></svg>
                                    </button>

                                    <button class="btn-icon btn-warning" title="Print Thermal Fisik" onclick="printThermal(<?= $order['id'] ?>)">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                    </button>
                                    <!-- <button class="btn-icon btn-delete" title="Hapus Order" onclick="deleteOrder(<?= $order['id'] ?>, '<?= $order['inv_no'] ?>')">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path<path d="M14 11v6"></path><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path></svg>
                                    </button> -->
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td class="table-empty" colspan="6">Data tidak ditemukan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="detailModal">
    <div class="modal-box modal-detail">
        <div class="modal-header">
            <span id="detailTitle">Detail Pesanan</span>
            <button class="close-btn" onclick="closeModal('detailModal')">✕</button>
        </div>
        <table class="table-modern mb-20">
            <thead><tr><th>Item</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead>
            <tbody id="detailBody"></tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="paymentModal">
    <div class="modal-box">
        <div class="modal-header">
            <span>Form Pembayaran</span>
            <button class="close-btn" onclick="closeModal('paymentModal')">✕</button>
        </div>
        
        <input type="hidden" id="pay_order_id">
        <input type="hidden" id="pay_sisa_tagihan"> 

        <div class="form-group mb-15">
            <label>Sisa Tagihan yang Belum Dibayar</label>
            <input class="payment-total-readonly" type="text" id="pay_total" readonly>
        </div>

        <div class="form-group mb-15">
            <label>Nominal Bayar</label>
            <div class="payment-entry">
                <input class="flex-1" type="number" id="pay_nominal" placeholder="Masukkan jumlah bayar" oninput="checkPaymentStatus()">
                <button type="button" class="btn-success" onclick="setLunas()">Lunaskan</button>
            </div>
        </div>
        
        <div class="form-group mb-15">
            <label>Metode Pembayaran</label>
            <select id="pay_method" class="input-select">
                <option value="Cash">Cash</option>
                <option value="TF">TF</option>
            </select>
        </div>
        
        <div class="form-group mb-20">
            <label>Status Pembayaran (Otomatis)</label>
            <input class="payment-info-readonly" type="text" id="pay_info" readonly placeholder="Lunas / DP">
        </div>

        <button class="btn-primary w-full" id="submit_payment" onclick="submitPayment()">Simpan Pembayaran</button>
    </div>
</div>
<iframe class="is-hidden" id="thermalFrame"></iframe>

<div class="print-overlay" id="loadingPrint">
    <div class="spinner"></div>
    <h2 class="loading-title" id="loadingText">Memproses Struk...</h2>
</div>

<div id="global-loading" class="global-loading is-hidden">
    <div class="loading-content">
    <div class="spinner-border text-light" role="status"></div>
    <div class="mt-2">Loading...</div>
    </div>
</div>

<script>

function showGlobalLoading() {
  document.getElementById('global-loading').classList.remove('is-hidden');
}

function hideGlobalLoading() {
  document.getElementById('global-loading').classList.add('is-hidden');
}
    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    
    const formatRp = (angka) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);

    function openDetail(order_id, inv_no) {
        document.getElementById('detailTitle').innerText = 'Detail Pesanan: ' + inv_no;
        document.getElementById('detailBody').innerHTML = '<tr><td class="text-center" colspan="4">Memuat data...</td></tr>';
        openModal('detailModal');

        fetch('/order/api_detail?id=' + order_id)
        .then(res => res.json())
        .then(data => {
            let html = '';
            data.forEach(item => {
                html += `<tr>
                    <td>${item.name}</td>
                    <td>${item.quantity}</td>
                    <td>${formatRp(item.price)}</td>
                    <td class="font-semibold">${formatRp(item.amount)}</td>
                </tr>`;
            });
            document.getElementById('detailBody').innerHTML = html;
        }).catch(err => alert("Gagal memuat detail"));
    }

    function openPayment(id, sisaTagihanAngka) {
        document.getElementById('pay_order_id').value = id;
        document.getElementById('pay_sisa_tagihan').value = sisaTagihanAngka; 
        
        document.getElementById('pay_total').value = formatRp(sisaTagihanAngka);
        
        document.getElementById('pay_nominal').value = '';
        document.getElementById('pay_info').value = '';
        
        openModal('paymentModal');
    }

    function setLunas() {
        let sisaTagihan = parseFloat(document.getElementById('pay_sisa_tagihan').value) || 0;
        document.getElementById('pay_nominal').value = sisaTagihan;
        checkPaymentStatus();
    }

    function checkPaymentStatus() {
        let sisaTagihan = parseFloat(document.getElementById('pay_sisa_tagihan').value) || 0;
        let nominalBayar = parseFloat(document.getElementById('pay_nominal').value) || 0;
        let inputInfo = document.getElementById('pay_info');

        if (nominalBayar <= 0 || isNaN(nominalBayar)) {
            inputInfo.value = "";
            inputInfo.style.color = "#64748b";
        } else if (nominalBayar >= sisaTagihan) {
            inputInfo.value = "Lunas";
            inputInfo.style.color = "#166534";
        } else {
            inputInfo.value = "DP";
            inputInfo.style.color = "#b45309";
        }
    }

    function submitPayment() {
        let payload = {
            order_id: document.getElementById('pay_order_id').value,
            nominal: document.getElementById('pay_nominal').value,
            payment_method: document.getElementById('pay_method').value,
            information: document.getElementById('pay_info').value
        };

        if(!payload.nominal) return alert("Nominal bayar wajib diisi");
        const btn = document.getElementById('submit_payment');
        showGlobalLoading();
        btn.disabled = true;
        btn.textContent = "Processing.....";

        fetch('/order/api_pay', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success'){
                location.reload(); 
            } else {
                alert("Gagal menyimpan: " + data.message);
            }
        }).catch(err => alert("Terjadi kesalahan sistem."));
    }

    function deleteOrder(id){
        if(!confirm("Yakin ingin menghapus order ini?")){
            return;
        }
        fetch('/order/api_delete?id=' + id)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            }else{
                alert("Gagal hapus Order");
            }
        }).catch(err => {
            console.log(err);
        });
    }

    function printThermal(id) {
        document.getElementById('loadingText').innerText = "Menghubungkan ke Printer...";
        document.getElementById('loadingPrint').style.display = 'flex';

        fetch('/order/print?id=' + id)
        .then(res => res.json())
        .then(data => {
            if(data.status === 'error') { alert(data.message); return; }

            let logoHTML = '';
            if (data.store.img) {
                logoHTML = `<img class="receipt-logo" src="/assets/stores/${data.store.img}" onerror="this.style.display='none'">`;
            }

            let html = `
            <html>
            <head>
                <link rel="stylesheet" href="${document.getElementById('pageStyles').href}">
            </head>
            <body class="thermal-page">
                <div class="text-center">
                    ${logoHTML}
                    <div class="font-bold receipt-store-name">${data.store.name}</div>
                    <div class="receipt-address">${data.store.address}</div>
                </div>
                
                <div class="dashed-line mt-8"></div>

                <div class="text-left receipt-details">
                    <table class="w-full">
                        <tr><td class="receipt-label">Inv</td><td>: ${data.order.inv_no}</td></tr>
                        <tr><td>Nama</td><td>: ${data.order.customer_name}</td></tr>
                        <tr><td>Waktu</td><td>: ${data.order.create_at}</td></tr>
                    </table>
                </div>
                
                <div class="dashed-line"></div>
                
                <table>`;

            data.items.forEach(item => {
                html += `<tr><td colspan="2" class="font-bold">${item.name} | ${item.info} | ${item.color}</td></tr>
                         <tr><td>${item.quantity}x ${formatRp(item.price)}</td><td class="text-right">${formatRp(item.amount)}</td></tr>`;
            });

            html += `
                </table>
                <div class="dashed-line"></div>
                <table>
                    <tr><td class="font-bold">Total</td><td class="text-right font-bold">${formatRp(data.order.total)}</td></tr>
                    <tr><td>Dibayar</td><td class="text-right">${formatRp(data.payment.total_dibayar)}</td></tr>
                    <tr><td>Sisa</td><td class="text-right">${formatRp(data.payment.sisa)}</td></tr>
                </table>
                <div class="dashed-line"></div>
                <div class="text-center mt-10">Terima Kasih Atas Kunjungan Anda!</div>
            </body>
            </html>`;

            let iframe = document.getElementById('thermalFrame');
            // Tunggu iframe selesai render sebelum print
            iframe.onload = function() {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
                document.getElementById('loadingPrint').style.display = 'none';
                iframe.onload = null; // Hapus event supaya tidak double
            };
            let doc = iframe.contentWindow.document;
            doc.open();
            doc.write(html);
            doc.close();

        }).catch(err => { alert("Error Server"); document.getElementById('loadingPrint').style.display = 'none'; });
    }

    function downloadStrukPDF(id) {
        document.getElementById('loadingText').innerText = "Membuat File PDF...";
        document.getElementById('loadingPrint').style.display = 'flex';

        fetch('/order/print?id=' + id)
        .then(res => res.json())
        .then(data => {
            if(data.status === 'error') {
                alert(data.message);
                document.getElementById('loadingPrint').style.display = 'none';
                return;
            }

            const { jsPDF } = window.jspdf;

            function renderPDF(logoDataUrl = null) {
                // Kalkulasi tinggi kertas otomatis agar presisi
                let pdfHeight = 55 + (data.items.length * 15) + 35; 
                if (logoDataUrl) pdfHeight += 15; 
                if (data.store.address) pdfHeight += 8;

                const doc = new jsPDF({ orientation: "portrait", unit: "mm", format: [58, pdfHeight] });

                let y = 5;

                if (logoDataUrl) {
                    doc.addImage(logoDataUrl, 'PNG', 14, y, 30, 10);
                    y += 14;
                } else {
                    y += 5;
                }

                doc.setFont("helvetica", "bold"); 
                doc.setFontSize(12);
                doc.text(data.store.name.toUpperCase(), 29, y, { align: "center" });
                y += 4;

                if (data.store.address) {
                    doc.setFont("helvetica", "normal"); 
                    doc.setFontSize(8);
                    let splitAddress = doc.splitTextToSize(data.store.address, 50);
                    doc.text(splitAddress, 29, y, { align: "center" });
                    y += (splitAddress.length * 3) + 2;
                } else {
                    y += 2;
                }

                doc.setLineWidth(0.3);
                doc.setLineDashPattern([1, 1], 0);
                doc.line(3, y, 55, y); 
                y += 4;

                doc.setFont("helvetica", "normal"); 
                doc.setFontSize(8);
                doc.text("Inv   : " + data.order.inv_no, 3, y); y += 4;
                doc.text("Nama  : " + data.order.customer_name, 3, y); y += 4;
                doc.text("Waktu : " + data.order.create_at, 3, y); y += 3;

                doc.line(3, y, 55, y);
                y += 4;

                data.items.forEach(item => {
                    doc.setFont("helvetica", "bold");
                    let itemNameInfo = `${item.name} | ${item.info} | ${item.color}`;
                    let splitItemName = doc.splitTextToSize(itemNameInfo, 52); 
                    
                    doc.text(splitItemName, 3, y);
                    y += (splitItemName.length * 4);
                    
                    doc.setFont("helvetica", "normal");
                    doc.text(`${item.quantity}x ${formatRp(item.price)}`, 3, y);
                    doc.text(formatRp(item.amount), 55, y, { align: "right" });
                    y += 5;
                });

                y -= 2;
                doc.line(3, y, 55, y);
                y += 4;
                
                doc.setFont("helvetica", "bold");
                doc.text("Total", 3, y);
                doc.text(formatRp(data.order.total), 55, y, { align: "right" });
                y += 5;
                
                doc.setFont("helvetica", "normal");
                doc.text("Dibayar", 3, y);
                doc.text(formatRp(data.payment.total_dibayar), 55, y, { align: "right" });
                y += 5;
                
                doc.text("Sisa", 3, y);
                doc.text(formatRp(data.payment.sisa), 55, y, { align: "right" });
                y += 4;

                doc.line(3, y, 55, y);
                y += 5;

                doc.text("Terima Kasih Atas", 29, y, { align: "center" });
                y += 3;
                doc.text("Kunjungan Anda!", 29, y, { align: "center" });

                // SIMPAN
                doc.save("Struk_" + data.order.inv_no + ".pdf");
                document.getElementById('loadingPrint').style.display = 'none';
            }

            if (data.store.img) {
                const img = new Image();
                img.crossOrigin = "Anonymous";
                img.src = "/assets/stores/" + data.store.img;
                img.onload = function() {
                    const canvas = document.createElement("canvas");
                    canvas.width = img.width;
                    canvas.height = img.height;
                    const ctx = canvas.getContext("2d");
                    ctx.drawImage(img, 0, 0);
                    const dataURL = canvas.toDataURL("image/png");
                    renderPDF(dataURL);
                };
                img.onerror = function() {
                    renderPDF(null);
                };
            } else {
                renderPDF(null);
            }

        }).catch(err => { 
            alert("Error Server"); 
            console.error(err);
            document.getElementById('loadingPrint').style.display = 'none'; 
        });
    }
</script>