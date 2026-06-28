<?php
require_once '../connect.php';
require_once BASE_PATH . '/functions/user_validation.php';

$user_id = $_SESSION['shopee_users']['user_id'] ?? 0;

date_default_timezone_set('Asia/Jakarta');

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
date_default_timezone_set('Asia/Jakarta');
$date = $_GET['d'] ?? date('Y-m-d');

$currentOrder = null;
$listMeterData = [];

if ($order_id > 0) {
    $stmtOrder = $koneksi->prepare("\n        SELECT id, inv, order_no, name, date, info\n        FROM orders\n        WHERE id = ? AND user_id = ? AND date = ?");
    $stmtOrder->bind_param("iis", $order_id, $user_id, $date);
    $stmtOrder->execute();
    $currentOrder = $stmtOrder->get_result()->fetch_assoc();
}

$stmtProducts = $koneksi->prepare("\n    SELECT product_id, name, unit_type, type\n    FROM products\n    WHERE user_id = ?\n    ORDER BY type DESC");
$stmtProducts->bind_param("i", $user_id);
$stmtProducts->execute();
$resultProducts = $stmtProducts->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Input Meteran</title>
  <?php include BASE_PATH . '/elements/header.php'; ?>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/content.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dark_mode.css">
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
    <div id="page-content-wrapper" <?= ($mode ?? 0) === 1 ? 'class="dark-mode"' : '' ?> >
        <div class="judul-page">
            <h2>Input Meteran</h2>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" id="btnBackToList">Kembali ke Daftar Order</button>
            </div>
        </div>

        <?php if (!$currentOrder): ?>
            <div style="background: #fff3e0; padding: 20px; margin-bottom: 20px; border-radius: 5px;">
                <p>Order tidak ditemukan untuk tanggal <strong><?= htmlspecialchars($date) ?></strong>. Silakan kembali ke daftar order.</p>
            </div>
        <?php else: ?>
            <div style="background: #e8f5e9; padding: 15px; margin-bottom: 20px; border-radius: 5px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong>Order:</strong> <?= str_pad($currentOrder['inv'], 6, '0', STR_PAD_LEFT) ?> | 
                    <strong>Order No:</strong> <?= htmlspecialchars($currentOrder['order_no']) ?> | 
                    <strong>Nama Konsumen:</strong> <?= htmlspecialchars($currentOrder['name']) ?> |
                    <strong>Info:</strong> <?= htmlspecialchars($currentOrder['info'] ?? '-') ?> | 
                    <strong>Tanggal:</strong> <?= htmlspecialchars($currentOrder['date']) ?>
                </div>
                <button type="button" class="btn btn-warning" id="btnEditCurrentOrder">Edit Order</button>
            </div>

            <h4>Daftar Meteran</h4>
            <table class="shopee-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Type Produk</th>
                        <th>Nama Produk</th>
                        <th>List Meteran</th>
                        <th>Tanggal Order</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; ?>
                    <?php while ($product = $resultProducts->fetch_assoc()): ?>
                        <?php
                            $stmtMeterForOrder = $koneksi->prepare("\n                                SELECT lm.list_meter_id, lm.value\n                                FROM list_meters lm\n                                WHERE lm.order_id = ? AND lm.product_id = ?");
                            $stmtMeterForOrder->bind_param("ii", $order_id, $product['product_id']);
                            $stmtMeterForOrder->execute();
                            $resultMeterForOrder = $stmtMeterForOrder->get_result();
                            $meterList = $resultMeterForOrder->fetch_all(MYSQLI_ASSOC);
                        ?>
                        <tr>
                            <td><?= $no ?></td>
                            <td><?= htmlspecialchars($product['type']) ?></td>
                            <td><?= htmlspecialchars($product['name']) ?></td>
                            <td id="meterList_<?= $product['product_id'] ?>">
                                <?php if (!empty($meterList)): ?>
                                    <?php foreach ($meterList as $meter): ?>
                                        <span class="deleteList" data-id="<?= $meter['list_meter_id'] ?>" 
                                            style="display: inline-block; background: #e3f2fd; padding: 3px 8px; margin: 2px; border-radius: 3px; cursor: pointer;"> 
                                            <?= htmlspecialchars($meter['value']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($date) ?></td>
                            <td style="width: 24rem;">
                                <div class="formAddMeter" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                    <input type="hidden" name="order_id" value="<?= $order_id ?>">
                                    <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                                    <input type="number" step="any" min="0" name="value" class="meterValue" placeholder="Nilai" style="padding: 5px; width: 100px;" required>
                                    <button type="button" class="btn btn-primary btnAddMeter">Tambah</button>
                                </div>
                            </td>
                        </tr>
                        <?php $no++; ?>
                    <?php endwhile; ?>
                </tbody>
            </table>
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
    document.getElementById('btnBackToList').addEventListener('click', function() {
        const dateParam = '<?= htmlspecialchars($date) ?>';
        window.location.href = '<?= BASE_URL ?>/indexes/meteran.php?d=' + encodeURIComponent(dateParam);
    });

    document.getElementById('meteranBulanan').addEventListener('click', function() {
        window.location.href = '<?= BASE_URL ?>/indexes/meteran_bulanan.php';
    });

    document.getElementById('btnEditCurrentOrder').addEventListener('click', function() {
        document.getElementById('editOrderId').value = '<?= $order_id ?>';
        document.getElementById('editOrderNo').value = '<?= htmlspecialchars($currentOrder['order_no']) ?>';
        document.getElementById('editCustomerName').value = '<?= htmlspecialchars($currentOrder['name']) ?>';
        document.getElementById('editOrderInfo').value = '<?= htmlspecialchars($currentOrder['info'] ?? '') ?>';
        new bootstrap.Modal(document.getElementById('editOrderModal')).show();
    });

    document.getElementById('formEditOrder').addEventListener('submit', function(e) {
        e.preventDefault();
        const orderId = document.getElementById('editOrderId').value;
        const orderNo = document.getElementById('editOrderNo').value.trim();
        const customerName = document.getElementById('editCustomerName').value.trim();
        const info = document.getElementById('editOrderInfo').value.trim();
        
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
                    const meterlistContainer = document.getElementById('meterList_' + productId);
                    const newMeterSpan = document.createElement('span');
                    newMeterSpan.className = 'deleteList';
                    newMeterSpan.style.display = 'inline-block';
                    newMeterSpan.style.background = '#e3f2fd';
                    newMeterSpan.style.padding = '3px 8px';
                    newMeterSpan.style.margin = '2px';
                    newMeterSpan.style.borderRadius = '3px';
                    newMeterSpan.style.cursor = 'pointer';
                    newMeterSpan.innerText = form.querySelector('input[name="value"]').value;
                    newMeterSpan.setAttribute('data-id', data.list_meter_id);
                    meterlistContainer.appendChild(newMeterSpan);
                    form.querySelector('input[name="value"]').value = '';
                    form.querySelector('input[name="value"]').focus();
                    
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
                    const dateParam = '<?= htmlspecialchars($date) ?>';
                    const orderId = <?= $order_id ?>;
                    window.location.href = `<?= BASE_URL ?>/indexes/meteran_input.php?order_id=${orderId}&d=${encodeURIComponent(dateParam)}`;
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

    // Event listener untuk Enter key pada input meteran
    document.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && e.target.classList.contains('meterValue')) {
            e.preventDefault();
            const form = e.target.closest('.formAddMeter');
            const btn = form.querySelector('.btnAddMeter');
            btn.click();
        }
    });
</script>
</body>
</html>
