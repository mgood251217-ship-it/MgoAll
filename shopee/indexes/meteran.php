<?php
require_once '../connect.php';
require_once BASE_PATH . '/functions/user_validation.php';

$user_id = $_SESSION['shopee_users']['user_id'] ?? 0;

date_default_timezone_set('Asia/Jakarta');

$date = $_GET['d'] ?? date('Y-m-d');

$existingOrders = [];
$stmtAllOrders = $koneksi->prepare("
    SELECT id, inv, order_no, name, date, info
    FROM orders
    WHERE user_id = ? AND date = ?
    ORDER BY id DESC
    LIMIT 50");
$stmtAllOrders->bind_param("is", $user_id, $date);
$stmtAllOrders->execute();
$resultAllOrders = $stmtAllOrders->get_result();
$existingOrders = $resultAllOrders->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard</title>
  <?php include BASE_PATH . '/elements/header.php'; ?>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/content.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dark_mode.css">
    <script src="https://cdn.jsdelivr.net/npm/exceljs/dist/exceljs.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/file-saver@2.0.5/dist/FileSaver.min.js"></script>
    <style>
        .loading {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>

<body>
<div id="main-wrapper" <?= ($mode ?? 0) === 1 ? 'class="dark-mode"' : '' ?>>
  <?php include BASE_PATH . '/elements/navbar.php'; ?>
    <div id="page-content-wrapper" <?= ($mode ?? 0) === 1 ? 'class="dark-mode"' : '' ?>>
        <div class="judul-page">
            <h2>Meteran</h2>
            <div class="d-flex gap-2">
                <button class="btn btn-success" id="btnExportExcel">Export Excel</button>
                <form class="shopee-form" id="formTanggal" method="get">
                    <input type="date" name="d" id="d" value="<?= htmlspecialchars($date) ?>" onchange="updateDateParam()">
                </form>
            </div>
        </div>

        <!-- Form Input Order -->
        <div style="background: #f5f5f5; padding: 20px; margin-bottom: 20px; border-radius: 5px;">
            <h4>Input Order Baru</h4>
            <form id="formInputOrder" style="display: flex; gap: 10px; align-items: flex-end;">
                <div>
                    <label for="inputOrderNo">Order No:</label>
                    <input type="text" id="inputOrderNo" name="order_no" placeholder="Misal: ORD-001" required style="padding: 5px; width: 150px;">
                </div>
                <div>
                    <label for="inputCustomerName">Nama Konsumen:</label>
                    <input type="text" id="inputCustomerName" name="name" placeholder="Nama Konsumen" required style="padding: 5px; width: 200px;">
                </div>
                <button type="submit" class="btn btn-primary">Buat Order</button>
            </form>
        </div>

        <!-- Pilih Order Existing -->
        <?php if (!empty($existingOrders)): ?>
        <div style="background: #fff3e0; padding: 20px; margin-bottom: 20px; border-radius: 5px;">
            <h4>Daftar Order untuk tanggal <?= htmlspecialchars($date) ?></h4>
            <table class="shopee-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Order Inv</th>
                        <th>Order No</th>
                        <th>Nama Konsumen</th>
                        <th>Info</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; ?>
                    <?php foreach ($existingOrders as $order): ?>
                        <tr>
                            <td><?= $no ?></td>
                            <td><?= htmlspecialchars(str_pad($order['inv'], 6, '0', STR_PAD_LEFT)) ?></td>
                            <td><?= htmlspecialchars($order['order_no']) ?></td>
                            <td><?= htmlspecialchars($order['name']) ?></td>
                            <td><?= htmlspecialchars($order['info'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($order['date']) ?></td>
                            <td style="display: flex; gap: 8px;">
                                <button type="button" class="btn btn-primary btnSelectOrder" data-order-id="<?= $order['id'] ?>">
                                    Input Meter
                                </button>
                                <button type="button" class="btn btn-warning btnEditOrder" data-order-id="<?= $order['id'] ?>" data-order-no="<?= htmlspecialchars($order['order_no']) ?>" data-name="<?= htmlspecialchars($order['name']) ?>" data-info="<?= htmlspecialchars($order['info'] ?? '') ?>">
                                    Edit
                                </button>
                            </td>
                        </tr>
                        <?php $no++; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #999;">
            <p>Belum ada order untuk tanggal ini.</p>
        </div>
        <?php endif; ?>

        <div style="margin-top: 20px;">
            <button class="btn-shopee" id="meteranBulanan">Meteran Bulanan</button>
        </div>

        <!-- Modal Edit Order -->
        <div class="modal fade" id="editOrderModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" id="formEditOrder">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="editOrderId">
                        <div class="mb-3">
                            <label class="form-label">Order No:</label>
                            <input type="text" id="editOrderNo" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Konsumen:</label>
                            <input type="text" id="editCustomerName" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Info:</label>
                            <input type="text" id="editOrderInfo" class="form-control" placeholder="Opsional">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

  <?php include BASE_PATH . '/elements/footer.php'; ?>
</div>
<script>

document.getElementById('meteranBulanan').addEventListener('click', function() {
    window.location.href = '<?= BASE_URL ?>/indexes/meteran_bulanan.php';
});

document.querySelectorAll('.btnSelectOrder').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const orderId = this.getAttribute('data-order-id');
        const dateParam = document.getElementById('d').value;
        window.location.href = `<?= BASE_URL ?>/indexes/meteran_input.php?order_id=${orderId}&d=${dateParam}`;
    });
});

document.querySelectorAll('.btnEditOrder').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('editOrderId').value = this.getAttribute('data-order-id');
        document.getElementById('editOrderNo').value = this.getAttribute('data-order-no');
        document.getElementById('editCustomerName').value = this.getAttribute('data-name');
        document.getElementById('editOrderInfo').value = this.getAttribute('data-info');
        new bootstrap.Modal(document.getElementById('editOrderModal')).show();
    });
});

document.getElementById('formEditOrder').addEventListener('submit', function(e) {
    e.preventDefault();
    const orderId = document.getElementById('editOrderId').value;
    const orderNo = document.getElementById('editOrderNo').value.trim();
    const customerName = document.getElementById('editCustomerName').value.trim();
    const info = document.getElementById('editOrderInfo').value.trim();
    const dateParam = document.getElementById('d').value;
    
    if (!orderNo || !customerName) {
        alert('Order No dan Nama Konsumen wajib diisi');
        return;
    }
    
    fetch('<?= BASE_URL ?>/functions/update_order.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            order_id: orderId,
            order_no: orderNo,
            name: customerName,
            info: info
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('editOrderModal')).hide();
            location.reload();
        } else {
            alert(data.message || 'Gagal mengupdate order');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Terjadi kesalahan sistem');
    });
});

function updateDateParam() {
    const dateInput = document.getElementById('d').value;
    const params = new URLSearchParams(window.location.search);
    params.set('d', dateInput);
    window.location.href = '<?= BASE_URL ?>/indexes/meteran.php?' + params.toString();
}

// Form Input Order
document.getElementById('formInputOrder').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const orderNo = document.getElementById('inputOrderNo').value.trim();
    const customerName = document.getElementById('inputCustomerName').value.trim();
    const dateParam = document.getElementById('d').value;
    
    if (!orderNo || !customerName) {
        alert('Silakan isi Order No dan Nama Konsumen');
        return;
    }
    
    fetch('<?= BASE_URL ?>/functions/create_or_get_order.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            order_no: orderNo,
            name: customerName,
            date: dateParam
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = `<?= BASE_URL ?>/indexes/meteran_input.php?order_id=${data.order_id}&d=${encodeURIComponent(dateParam)}`;
        } else {
            alert(data.message || 'Terjadi kesalahan');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Terjadi kesalahan sistem');
    });
});

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('btnAddMeter')) {
        const btn = e.target;
        
        if (btn.classList.contains('loading')) return;
        btn.classList.add('loading');
        
        const form = btn.closest('.formAddMeter');
        const orderId = form.querySelector('input[name="order_id"]').value;
        const productId = form.querySelector('input[name="product_id"]').value;
        const value = form.querySelector('input[name="value"]').value;
        
        if (!value || isNaN(value) || Number(value) < 0) {
            alert('Masukkan nilai meteran yang valid');
            btn.classList.remove('loading');
            return;
        }
        
        const originalText = btn.innerText;
        btn.innerText = 'Loading...';
        btn.disabled = true;
        
        fetch('<?= BASE_URL ?>/functions/add_meter_to_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order_id: orderId,
                product_id: productId,
                value: value
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Refresh halaman untuk update list
                const dateParam = document.getElementById('d').value;
                window.location.href = `<?= BASE_URL ?>/indexes/meteran.php?order_id=<?= $order_id ?>&d=${dateParam}`;
            } else {
                alert(data.message || 'Gagal menambah meteran');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Terjadi kesalahan sistem');
        })
        .finally(() => {
            btn.classList.remove('loading');
            btn.innerText = originalText;
            btn.disabled = false;
        });
    }
});

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('deleteList')) {
        const btn = e.target;
        const listMeterId = btn.getAttribute('data-id');

        if (!confirm('Yakin hapus data list meteran ini?')) return;

        if (btn.classList.contains('loading')) return;
        btn.classList.add('loading');

        const originalText = btn.innerText;
        btn.innerText = '...';
        btn.style.pointerEvents = 'none';

        fetch('<?= BASE_URL ?>/functions/delete_list_meter.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ list_meter_id: listMeterId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const dateParam = document.getElementById('d').value;
                window.location.href = `<?= BASE_URL ?>/indexes/meteran.php?order_id=<?= $order_id ?>&d=${dateParam}`;
            } else {
                alert(data.message || 'Gagal menghapus meteran');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Terjadi kesalahan');
        })
        .finally(() => {
            btn.classList.remove('loading');
            btn.innerText = originalText;
            btn.style.pointerEvents = 'auto';
        });
    }
});

document.getElementById('btnExportExcel').addEventListener('click', function() {
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Data Meteran');

    worksheet.columns = [
        { header: 'No', key: 'no', width: 10 },
        { header: 'Type Produk', key: 'type_produk', width: 30 },
        { header: 'Nama Produk', key: 'nama_produk', width: 30 },
        { header: 'List Meteran', key: 'list_meteran', width: 40 },
        { header: 'Tanggal', key: 'tanggal', width: 20 }
    ];

    const rows = document.querySelectorAll('.shopee-table tbody tr');
    rows.forEach((row, index) => {
        const cells = row.querySelectorAll('td');
        worksheet.addRow({
            no: cells[0].innerText,
            type_produk: cells[1].innerText,
            nama_produk: cells[2].innerText,
            list_meteran: cells[3].innerText,
            tanggal: cells[4].innerText
        });
    });

    workbook.xlsx.writeBuffer().then(function(buffer) {
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        saveAs(blob, 'data_meteran.xlsx');
    });
});

document.querySelectorAll('.shopee-table tbody tr').forEach(row => {
    row.addEventListener('click', () => {
        document.querySelectorAll('.shopee-table tbody tr').forEach(r => r.classList.remove('selected'));
        row.classList.toggle('selected');
    });

    const inputValue = row.querySelector('input.meterValue');
    const btnAdd = row.querySelector('.btnAddMeter');
    if (inputValue && btnAdd) {
        inputValue.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                btnAdd.click();
            }
        });
    }
});

</script>
</body>
</html>
