<?php
require_once '../connect.php';
require_once BASE_PATH . '/functions/user_validation.php';

$user_id = $_SESSION['shopee_users']['user_id'] ?? 0;

date_default_timezone_set('Asia/Jakarta');

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$date = $_GET['d'] ?? date('Y-m-d');

$stmtProducts = $koneksi->prepare("
    SELECT product_id, name, unit_type, type
    FROM products
    WHERE user_id = ?
    ORDER BY type DESC");
$stmtProducts->bind_param("i", $user_id);
$stmtProducts->execute();
$resultProducts = $stmtProducts->get_result();

$existingOrders = [];
$stmtAllOrders = $koneksi->prepare("
    SELECT id, inv, order_no, name
    FROM orders
    WHERE user_id = ? AND date = ?
    ORDER BY id DESC
    LIMIT 50");
$stmtAllOrders->bind_param("is", $user_id, $date);
$stmtAllOrders->execute();
$resultAllOrders = $stmtAllOrders->get_result();
$existingOrders = $resultAllOrders->fetch_all(MYSQLI_ASSOC);

$currentOrder = null;
$listMeterData = [];
if ($order_id > 0) {
    $stmtOrder = $koneksi->prepare("
        SELECT id, inv, order_no, name
        FROM orders
        WHERE id = ? AND user_id = ? AND date = ?");
    $stmtOrder->bind_param("iis", $order_id, $user_id, $date);
    $stmtOrder->execute();
    $currentOrder = $stmtOrder->get_result()->fetch_assoc();
    
    if ($currentOrder) {
        $stmtListMeter = $koneksi->prepare("
            SELECT lm.list_meter_id, lm.value, p.name AS product_name, p.type AS product_type
            FROM list_meters lm
            JOIN products p ON lm.product_id = p.product_id
            WHERE lm.order_id = ?
            ORDER BY p.type, p.name");
        $stmtListMeter->bind_param("i", $order_id);
        $stmtListMeter->execute();
        $resultListMeter = $stmtListMeter->get_result();
        $listMeterData = $resultListMeter->fetch_all(MYSQLI_ASSOC);
    }
}

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
            <h4>Pilih Order Existing</h4>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php foreach ($existingOrders as $order): ?>
                    <button type="button" class="btnSelectOrder" data-order-id="<?= $order['id'] ?>" 
                        style="padding: 8px 12px; background: <?= ($currentOrder && $currentOrder['id'] == $order['id']) ? '#4caf50' : '#2196f3' ?>; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        <?= str_pad($order['inv'], 6, '0', STR_PAD_LEFT) ?> - <?= htmlspecialchars($order['order_no']) ?> - <?= htmlspecialchars($order['name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($currentOrder): ?>
        <!-- Order Details -->
        <div style="background: #e8f5e9; padding: 15px; margin-bottom: 20px; border-radius: 5px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <strong>Order:</strong> <?= str_pad($currentOrder['inv'], 6, '0', STR_PAD_LEFT) ?> | 
                <strong>Order No:</strong> <?= htmlspecialchars($currentOrder['order_no']) ?> | 
                <strong>Nama Konsumen:</strong> <?= htmlspecialchars($currentOrder['name']) ?>
            </div>
            <button type="button" id="btnBackToMeteran" class="btn btn-secondary" style="margin-left: 10px;">Kembali</button>
        </div>

        <!-- Meteran Table for Current Order -->
        <h4>Daftar Meteran</h4>
        <table class="shopee-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Type Produk</th>
                    <th>Nama Produk</th>
                    <th>List Meteran</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $stmtProducts2 = $koneksi->prepare("
                    SELECT DISTINCT p.product_id, p.name, p.unit_type, p.type
                    FROM products p
                    WHERE p.user_id = ?
                    ORDER BY p.type DESC");
                $stmtProducts2->bind_param("i", $user_id);
                $stmtProducts2->execute();
                $resultProducts2 = $stmtProducts2->get_result();
                
                $no = 1;
                while ($product = $resultProducts2->fetch_assoc()) {
                    // Get total and list meters for this product in this order
                    $stmtMeterForOrder = $koneksi->prepare("
                        SELECT lm.list_meter_id, lm.value
                        FROM list_meters lm
                        WHERE lm.order_id = ? AND lm.product_id = ?");
                    $stmtMeterForOrder->bind_param("ii", $order_id, $product['product_id']);
                    $stmtMeterForOrder->execute();
                    $resultMeterForOrder = $stmtMeterForOrder->get_result();
                    $meterList = $resultMeterForOrder->fetch_all(MYSQLI_ASSOC);
                    $totalMeter = array_sum(array_column($meterList, 'value'));
                ?>
                <tr>
                    <td><?= $no ?></td>
                    <td><?= htmlspecialchars($product['type']) ?></td>
                    <td><?= htmlspecialchars($product['name']) ?></td>
                    <td>
                        <?php foreach ($meterList as $meter): ?>
                            <span class="deleteList" data-id="<?= $meter['list_meter_id'] ?>" 
                                style="display: inline-block; background: #e3f2fd; padding: 3px 8px; margin: 2px; border-radius: 3px; cursor: pointer; position: relative;" 
                                title="Klik untuk hapus">
                                <?= htmlspecialchars($meter['value']) ?> <?= htmlspecialchars($product['unit_type']) ?>
                            </span>
                        <?php endforeach; ?>
                        <?php if (empty($meterList)): ?>
                            <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($date) ?></td>
                    <td style="width: 20vw;">
                        <div class="formAddMeter" style="display: flex; gap: 5px; align-items: center;">
                            <input type="hidden" name="order_id" value="<?= $order_id ?>">
                            <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                            <input type="number" step="any" min="0" name="value" class="meterValue" placeholder="Nilai" style="padding: 3px 5px; width: 100px;" required>
                            <button type="button" class="btn-aksi btnAddMeter">Tambah</button>
                        </div>
                    </td>
                </tr>
                <?php $no++; ?>
                <?php } ?>
            </tbody>
        </table>

        <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #999;">
            <p>Silakan buat atau pilih order terlebih dahulu</p>
        </div>
        <?php endif; ?>

        <div style="margin-top: 20px;">
            <button class="btn-shopee" id="meteranBulanan">Meteran Bulanan</button>
        </div>
    </div>

  <?php include BASE_PATH . '/elements/footer.php'; ?>
</div>
<script>

document.getElementById('meteranBulanan').addEventListener('click', function() {
    window.location.href = '<?= BASE_URL ?>/indexes/meteran_bulanan.php';
});

document.getElementById('btnBackToMeteran')?.addEventListener('click', function() {
    const dateParam = document.getElementById('d').value;
    window.location.href = `<?= BASE_URL ?>/indexes/meteran.php?d=${dateParam}`;
});

document.querySelectorAll('.btnSelectOrder').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const orderId = this.getAttribute('data-order-id');
        const dateParam = document.getElementById('d').value;
        window.location.href = `<?= BASE_URL ?>/indexes/meteran.php?order_id=${orderId}&d=${dateParam}`;
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
    
    if (!orderNo || !customerName) {
        alert('Silakan isi Order No dan Nama Konsumen');
        return;
    }
    
    fetch('<?= BASE_URL ?>/functions/create_or_get_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            order_no: orderNo,
            name: customerName,
            user_id: <?= $user_id ?>
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Redirect to this page with order_id
            const dateParam = document.getElementById('d').value;
            window.location.href = `<?= BASE_URL ?>/indexes/meteran.php?order_id=${data.order_id}&d=${dateParam}`;
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
