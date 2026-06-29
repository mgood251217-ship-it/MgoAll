<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/functions/helpers.php';
require_once BASE_PATH . '/components/Alert.php';

$scrl_id = $_GET['scrl_id'] ?? '';
$access = startEnk('dek', $_COOKIE['admin_access'] ?? '');

$start_date = ($_GET['start_date'] ?? date('Y-m-d')) . ' 00:00:00';
$end_date = ($_GET['end_date'] ?? date('Y-m-d')) . ' 23:59:59';

$sql = "SELECT o.order_id, o.nomorator, o.nomor, o.customer_name, o.date, o.total, o.system, u.name AS operator
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.user_id
        WHERE o.store_id = ? AND o.date BETWEEN ? AND ?
        ORDER BY o.system ASC, o.order_id DESC";

$stmt = $koneksi->prepare($sql);
$stmt->bind_param("iss", $store_id, $start_date, $end_date);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$itemsByOrder = [];
$paymentsByOrder = [];
$transfersByOrder = [];
$notesByOrder = [];

$orderIds = array_column($orders, 'order_id');

if (!empty($orderIds)) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $types = str_repeat('i', count($orderIds));
    
    $itemQuery = $koneksi->prepare("
        SELECT order_id, judul, finishing, size, quantity, unit, amount,
               (SELECT GROUP_CONCAT(fp.name SEPARATOR ', ') 
                FROM products fp 
                WHERE FIND_IN_SET(fp.product_id, REPLACE(order_items.finishing, ' ', '')) > 0
               ) AS finishing_names
        FROM order_items 
        WHERE order_id IN ($placeholders)
    ");
    $itemQuery->bind_param($types, ...$orderIds);
    $itemQuery->execute();
    $items = $itemQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($items as $item) {
        $itemsByOrder[$item['order_id']][] = $item;
    }
    $itemQuery->close();

    $paymentQuery = $koneksi->prepare("SELECT order_id, payment_id, date, nominal, payment_method, status FROM payment WHERE order_id IN ($placeholders)");
    $paymentQuery->bind_param($types, ...$orderIds);
    $paymentQuery->execute();
    $payments = $paymentQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($payments as $payment) {
        $paymentsByOrder[$payment['order_id']][] = $payment;
    }
    $paymentQuery->close();

    $transferQuery = $koneksi->prepare("SELECT order_id, transfer_id, img FROM transfers WHERE order_id IN ($placeholders)");
    $transferQuery->bind_param($types, ...$orderIds);
    $transferQuery->execute();
    $transfers = $transferQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($transfers as $transfer) {
        $transfersByOrder[$transfer['order_id']][] = $transfer;
    }
    $transferQuery->close();

    $noteQuery = $koneksi->prepare("SELECT order_id, note FROM note_orders WHERE order_id IN ($placeholders) AND note_for = 'OP'");
    $noteQuery->bind_param($types, ...$orderIds);
    $noteQuery->execute();
    $notes = $noteQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($notes as $note) {
        $notesByOrder[$note['order_id']] = $note['note'];
    }
    $noteQuery->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Transaksi Detil</title>
  <?php include BASE_PATH . '/header.php'; ?>
  <?php include BASE_PATH . '/export_libraries.php'; ?>
  <style>
    .nota-block { margin-bottom: 40px; border: 1px solid #ccc; padding: 20px; border-radius: 10px; }
    .nota-header { display: flex; justify-content: space-between; flex-wrap: wrap; }
    .payment-info > div {
      border: 1px solid #dee2e6;
      border-radius: 6px;
      padding: 10px;
      margin-right: 10px;
      min-width: 180px;
      background-color: #f8f9fa;
    }
    .payment-info {
      display: flex;
      gap: 10px;
      overflow-x: auto;
      padding-top: 10px;
    }
    .payment-info::-webkit-scrollbar { height: 6px; }
    .payment-info::-webkit-scrollbar-thumb {
      background-color: rgba(0,0,0,0.1);
      border-radius: 3px;
    }
    .dropZone {
      border: 2px dashed #ccc;
      border-radius: 10px;
      padding: 20px;
      text-align: center;
      cursor: pointer;
      color: #555;
      transition: background 0.3s, border-color 0.3s;
    }
    .dropZone.dragover { background: #f0f8ff; border-color: #007bff; }
    #picture { position: absolute; left: -9999px; visibility: hidden; }
    .custom-file-upload { border: 1px solid #ccc; display: inline-block; padding: 6px 12px; cursor: pointer; }
    .conimg{ margin: 0 !important; padding: 0 !important; max-width: 120px; max-height: 120px; }
    .payimg{ width: 100%; height: 100%; object-fit: cover; }
    .payimg:hover{ filter: blur(3px); transition: opacity 0.2s ease; }
    .btn-delete-img {
      background-color: rgba(255, 255, 255, 0.7); color: #dc3545; border: none; transition: all 0.2s ease-in-out;
    }
    .btn-delete-img:hover { background-color: #dc3545; color: white; transform: scale(1.1); }
    .btn-paste { white-space: nowrap; }
  </style>
    <script>
      function showImageModal(src) {
        document.getElementById('modalImage').src = src;
        const modal = new bootstrap.Modal(document.getElementById('imageModal'));
        modal.show();
      }
    </script>
</head>

<body>
<div id="main-wrapper" >
  <?php include BASE_PATH . '/navbar.php'; ?>

  <div id="main-content" <?= (isset($mode) && $mode === 1) ? 'class="dark-mode"' : '' ?>>
    <?php include BASE_PATH . '/sidebar.php'; ?>

    <div id="page-content-wrapper">
      <?php require 'summary_cards.php'; ?>

      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <h1 class="mb-0">Transaksi Detil</h1>
        <div class="row g-2 align-items-end justify-content-end flex-nowrap" style="margin-bottom:0;">
          <?php $showExport = true; include BASE_PATH . '/interval_date.php'; ?>
        </div>
      </div>

      <div class="table-responsive">
        <?php
        foreach ($orders as $order):
            $order_id = $order['order_id'];
        ?>
        <div class="nota-block" id="<?= sanitize($order_id) ?>">
          <div class="nota-header">
            <div class="d-flex mb-2 gap-3 align-items-center">
              <div>
                <strong>Nomorator:</strong> <?= sanitize($order['nomorator']) ?><br>
                <strong>Nama:</strong> <?= sanitize($order['customer_name']) ?>
              </div>
              <div>
                <strong>Operator:</strong> <?= sanitize($order['operator']) ?><br>
                <strong>Tanggal:</strong> <?= sanitize($order['date']) ?>
              </div>
              <div>
                <strong>Nomor:</strong> <?= sanitize($order['nomor']) ?>
                <form action="<?= BASE_URL ?>/customer?start_date=<?= date('Y-m-d', strtotime($order['date'])) ?>&end_date=<?= date('Y-m-d', strtotime($order['date'])) ?>" method="post" target="_blank">
                  <input type="hidden" name="order_id" value="<?= $order_id ?>">
                  <input type="submit" value="▶️Cek Nota" class="btn btn-sm btn-success">
                </form> 
              </div>
            </div>
            <div>
              <button type="button" class="btn btn-primary btnNote" data-order-id="<?= sanitize($order_id)?>">Update Catatan 📝</button>
            </div>
          </div>

          <table class="table table-bordered table-sm">
            <thead <?= ($order['system'] == 'OFFLINE') ? 'class="table-primary"' : 'class="table-danger"' ?>>
              <tr>
                <th>No</th>
                <th>Bahan</th>
                <th>Finishing</th>
                <th>Ukuran</th>
                <th>Qty</th>
                <th>Satuan</th>
                <th>Jumlah</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $orderItems = $itemsByOrder[$order_id] ?? [];
              $no = 1;
              foreach ($orderItems as $item):
                  $finishingNamesStr = $item['finishing_names'] ?: '-';
              ?>
              <tr>
                <td><?= $no++ ?></td>
                <td><?= sanitize($item['judul']) ?></td>
                <td><?= sanitize($finishingNamesStr) ?></td>
                <td><?= sanitize($item['size']) ?></td>
                <td><?= sanitize($item['quantity']) ?></td>
                <td><?= sanitize($item['unit']) ?></td>
                <td><?= format_rupiah($item['amount']) ?></td>
              </tr>
              <?php endforeach; ?>
              <tr class="fw-bold">
                <td colspan="5" class="text-end"></td>
                <td>Total</td>
                <td><?= format_rupiah($order['total']) ?></td>
              </tr>
            </tbody>
          </table>

          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="payment-info mb-1" data-order-id="<?= $order_id ?>" >
              <?php
                $total_bayar = 0;
                $ada_tf = false;
                $orderPayments = $paymentsByOrder[$order_id] ?? [];
                $isLunas = false;

                if (!empty($orderPayments)):
                  foreach ($orderPayments as $payment):
                    $payment_id = (int)$payment['payment_id'];
                    $tanggal = sanitize($payment['date']);
                    $nominal = (int)$payment['nominal'];
                    $method = sanitize($payment['payment_method']);
                    $status = sanitize($payment['status']);
                    
                    if ($status == 'LUNAS') { $isLunas = true; } else { $isLunas = false; }
                    if ($method == 'TF') { $ada_tf = true; }
                    
                    $total_bayar += $nominal;
                ?>
                <div class="editable-payment border p-2 rounded bg-light" <?= (isset($mode) && $mode === 1) ? 'style="background-color: #333 !important; color: #e0e0e0 !important;"' : '' ?>
                    data-payment-id="<?= $payment_id ?>" data-order="<?= $order_id ?>" data-nominal="<?= $nominal ?>" data-metode="<?= $method ?>" data-tanggal="<?= $tanggal ?>">
                  <div><strong>Tanggal:</strong> <?= $tanggal ?></div>
                  <div><strong>Nominal:</strong> <?= format_rupiah($nominal) ?></div>
                  <div><strong>Metode Pembayaran:</strong> <?= $method ?></div>
                  <div><strong>Status:</strong> <?= $status ?> </div>
                  <div class="d-flex justify-content-end gap-2">
                  <?php if (isset($mobile) && $mobile) { ?>
                    <button class="btn btn-sm btn-primary editPembayaran" id="editPembayaran">Edit</button>
                    <button class="btn btn-sm btn-danger hapusPembayaran" id="hapusPembayaran">Hapus</button>
                  <?php } ?>
                  </div>
                </div>
                <?php
                  endforeach;
                else:
                ?>
                  <div <?= (isset($mode) && $mode === 1) ? 'style="background-color: #333 !important; color: #e0e0e0 !important;"' : '' ?>>Belum ada pembayaran.</div>
                <?php endif; ?>
              
                <?php 
                  $noproff = false;
                  $storeNameUpload = preg_replace('/[^a-zA-Z0-9_-]/', '_', $storeName ?? 'Toko');
                  
                  $fotos = $transfersByOrder[$order_id] ?? [];

                  if (!empty($fotos)) {
                    foreach ($fotos as $f) {
                      $imgUrl = BASE_URL . '/assets/img/buktitf/'. $storeNameUpload . "/" . $f['img'];
                    ?>
                    <div class="conimg position-relative d-inline-block me-2 mb-2" id="img-<?= $f['transfer_id'] ?>">
                      <img src="<?= $imgUrl ?>" onclick="showImageModal('<?= $imgUrl ?>')" alt="Bukti Transfer" class="payimg rounded img-fluid shadow-sm border" style="object-fit: cover; max-height: 120px;">
                      <button type="button" class="btn btn-sm btn-light rounded-circle shadow-sm btn-delete-img position-absolute top-0 end-0 m-1" data-transfer-id="<?= $f['transfer_id'] ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16">
                          <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/>
                        </svg>  
                      </button>
                    </div>
                    <?php
                    }
                  } elseif (empty($fotos) && $ada_tf == true) {
                    $noproff = true;
                  }
                ?>
            </div>
            
            <div class="upload-container d-flex gap-1 mb-1" style="height: 120px;">
              <button type="button" class="btn btn-outline-secondary btn-sm btn-paste" title="Tempel bukti transfer">
                📋 Paste <br> Foto
              </button>
          
              <div class="dropZone">
                🖼️ Drop atau upload <br> Untuk simpan bukti TF
              </div>

              <input class="picture" type="file" name="picture" accept="image/*" hidden>
              <input type="hidden" class="orderId" value="<?= sanitize($order_id) ?>">

              <div class="uploadStatus mb-1 text-muted"></div>
              <?php if ($isLunas == false) { ?>
              <button type="button" class="btn btn-danger btn-pay" data-order-id="<?= sanitize($order_id) ?>">Bayar</button>
              <?php } ?>
            </div>

          </div>
          
          <div id="note_result_<?= $order_id; ?>" class="position-absolute start-50 translate-middle-x d-flex gap-2">
            <?php
              $note_isi = $notesByOrder[$order_id] ?? '';
            ?>
              <?php if ($ada_tf == true && $noproff == true) {?>
                <div class="bg-warning text-white py-2 px-3 rounded-3">
                  ⚠️ Belum Ada Bukti TF
                </div>
              <?php } ?>
              
              <?php if ($total_bayar > $order['total']) { ?>
                <div class="bg-danger text-white py-2 px-3 rounded-3">
                  ⛔ Kelebihan Bayar
                </div>
              <?php } ?>
              
              <?php if ($note_isi != '') {?>
                <div class="bg-primary text-white py-2 px-3 rounded-3">
                  ℹ️ <?= sanitize($note_isi) ?>
                </div>
              <?php } ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>



    </div>
    <!-- Modal Payment -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true" >
      <div class="modal-dialog modal-dialog-centered" >
        <form id="paymentForm" class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="paymentModalLabel">Pembayaran Order</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="order_id" id="payment-order-id" value="">

            <div class="mb-3">
              <label for="payment-nominal" class="form-label">Nominal</label>
              <input type="text" class="form-control" id="payment-nominal" name="nominal_formatted">
              <input type="hidden" id="payment-nominal-raw" name="nominal">
            </div>

            <!-- Bayar Sebagian -->
            <div class="mb-3">
              <label class="form-label">Bayar Sebagian</label><br>
              <div class="d-flex">
                <button type="button" class="btn btn-primary w-50" data-method="TF" data-lunas="false">Transfer</button>
                <button type="button" class="btn btn-success w-50 ms-2" data-method="CASH" data-lunas="false">Cash</button>
              </div>
            </div>
                  

            <!-- Bayar Lunas -->
            <div class="mb-3">
              <label class="form-label">Lunas Langsung</label><br>
              <div class="d-flex">
                <button type="button" class="btn btn-primary w-50" data-method="TF" data-lunas="true">Lunas Transfer</button>
                <button type="button" class="btn btn-success w-50 ms-2" data-method="CASH" data-lunas="true">Lunas Cash</button>
              </div>
            </div>

            <div id="paymentFeedback" class="text-danger"></div>
          </div>
        </form>
      </div>
    </div>
    <!-- Modal Edit Payment -->
    <div class="modal fade" id="editPaymentModal" tabindex="-1" aria-labelledby="editPaymentLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered ">
        <form id="editPaymentForm" class="modal-content" method="POST" action="edit_payment.php">
          <div class="modal-header">
            <h5 class="modal-title" id="editPaymentLabel">Edit Pembayaran</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="order_id" id="edit-order-id">
            <input type="hidden" id="edit-payment-id" name="payment_id">
            <div class="mb-3">
              <label class="form-label">Nominal</label>
              <input type="number" class="form-control" name="nominal" id="edit-nominal" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Metode Pembayaran</label>
              <select class="form-select" name="payment_method" id="edit-metode" required>
                <option value="TF">Transfer</option>
                <option value="CASH">Cash</option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Tanggal & Jam</label>
              <input type="datetime-local" class="form-control" name="tanggal" id="edit-tanggal" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Keterangan</label>
              <textarea type="text" class="form-control" name="keterangan" id="edit-keterangan" placeholder="Keterangan Edit" require></textarea>
            </div>
          </div>

          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
          </div>
        </form>
      </div>
    </div>
    <!-- Modal Konfirmasi Hapus Pembayaran -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title" id="confirmDeleteLabel">Konfirmasi Hapus Pembayaran</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            Apakah Anda yakin ingin menghapus pembayaran ini?
            <br>
            <div class="mb-1">
              <label class="form-label">Keterangan</label>
              <textarea type="text" class="form-control" name="keterangan_hapus" placeholder="Keterangan Hapus" require></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Hapus</button>
          </div>
        </div>
      </div>
    </div>
    <!-- Modal Tambah Catatan -->
    <div class="modal fade" id="noteModal" tabindex="-1" aria-labelledby="noteModal" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">Update Catatan 📝</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body d-flex">
              <input type="hidden" name="noteInputId" id="noteInputId" value="">
              <input class="form-control" type="text" name="noteInput" id="noteInput">
              <input type="submit" class="btn btn-primary ms-4" id="confirmNoteInput" value="Tambah">
          </div>
        </div>
      </div>
    </div>
    <!-- Modal Konfirmasi Hapus Bukti Pembayaran -->
    <div class="modal fade" id="confirmDeleteTFModal" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title" id="confirmDeleteLabel">Konfirmasi Hapus Bukti Pembayaran</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            Apakah Anda yakin ingin menghapus bukti pembayaran ini?
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-danger" id="confirmDeleteTFBtn">Hapus</button>
          </div>
        </div>
      </div>
    </div>
    <!-- Modal Gambar -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:transparent; border:none;">
          <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-2" data-bs-dismiss="modal"></button>
          <img id="modalImage" src="" alt="Preview Bukti" 
            style=" max-height:80vh; object-fit:contain; display:block; margin:auto; border-radius:10px;">
        </div>
      </div>
    </div>
  </div>
  <?php include BASE_PATH . '/footer.php'; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> 
<script>
let access = '<?= $access ?>';
var hapusFoto = 0;
document.addEventListener('click', function(e) {
  if (e.target.closest('.btn-delete-img')) {
    const button = e.target.closest('.btn-delete-img');
    const transferId = button.getAttribute('data-transfer-id');
      const modal = new bootstrap.Modal(document.getElementById('confirmDeleteTFModal'));
      modal.show();
    hapusFoto = transferId;
  }
});

  document.getElementById('confirmDeleteTFBtn').addEventListener('click', function () {
    const modalEl = document.getElementById('confirmDeleteTFModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    modal.hide();
    fetch('finance_action.php?action=delete_tf', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `transfer_id=${hapusFoto}`
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const imgContainer = document.getElementById(`img-${hapusFoto}`);
        if (imgContainer) imgContainer.remove();
      } else {
        alert(data.message || 'Gagal menghapus gambar.');
      }
    })
    .catch(() => alert('Terjadi kesalahan koneksi.'));
  });

document.querySelectorAll('.upload-container').forEach(container => {
  const dropZone = container.querySelector('.dropZone');
  const fileInput = container.querySelector('.picture');
  const uploadStatus = container.querySelector('.uploadStatus');
  const orderId = container.querySelector('.orderId').value;
  const pasteBtn = container.querySelector('.btn-paste');

  dropZone.addEventListener('click', () => fileInput.click());

  fileInput.addEventListener('change', e => {
    if (e.target.files.length > 0) {
      uploadFile(e.target.files[0]);
    }
  });

  dropZone.addEventListener('dragover', e => {
    e.preventDefault();
    dropZone.classList.add('dragover');
  });

  dropZone.addEventListener('dragleave', e => {
    dropZone.classList.remove('dragover');
  });

  dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    const file = e.dataTransfer.files[0];
    if (file) uploadFile(file);
  });

  pasteBtn.addEventListener('click', async () => {
    try {
      const items = await navigator.clipboard.read();
      let found = false;

      for (const item of items) {
        for (const type of item.types) {
          if (type.startsWith('image/')) {
            const blob = await item.getType(type);
            uploadFile(new File([blob], 'clipboard_image.png', { type }));
            found = true;
            break;
          }
        }
      }

      if (!found) {
        uploadStatus.textContent = '📋 Tidak ada gambar di clipboard.';
      }
    } catch (err) {
      console.error(err);
      uploadStatus.textContent = '⚠️ Gagal membaca clipboard (izin browser?).';
    }
  });

  function uploadFile(file) {

    const formData = new FormData();
    formData.append('picture', file);
    formData.append('order_id', orderId);

    fetch('finance_action.php?action=create_tf', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      const container = document.querySelector(`.payment-info[data-order-id="${orderId}"]`);
      if (container) loadPaymentInfo(orderId, container);
    })
    .catch(err => {
      console.error(err);
      uploadStatus.textContent = '❌ Upload gagal.';
    });
  }
});

function loadPaymentInfo(orderId, container) {
  container.classList.add('loading');
  container.innerHTML = '<div class="text-center text-muted py-2">⏳ Memuat data pembayaran...</div>';

  fetch('get_payment.php?order_id=' + orderId)
    .then(res => res.text())
    .then(html => {
      container.innerHTML = html;
      container.classList.remove('loading'); 
    })
    .catch(err => {
      console.error('Gagal memuat data pembayaran:', err);
      container.innerHTML = '<div class="text-danger py-2">Gagal memuat data pembayaran.</div>';
      container.classList.remove('loading');
    });
}

  document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.btn-pay').forEach(button => {
    button.addEventListener('click', () => {
      document.getElementById('payment-order-id').value = button.getAttribute('data-order-id');
      document.getElementById('payment-nominal').value = '';
      new bootstrap.Modal(document.getElementById('paymentModal')).show();
    });
  });

  const paymentForm = document.getElementById('paymentForm');
  const nominalInput = document.getElementById('payment-nominal');
  const nominalRaw = document.getElementById('payment-nominal-raw');
  const feedback = document.getElementById('paymentFeedback');2000

  function formatRupiah(angka) {
    let numberString = angka.replace(/[^,\d]/g, '').toString();
    let split = numberString.split(',');
    let sisa = split[0].length % 3;
    let rupiah = split[0].substr(0, sisa);
    let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

    if (ribuan) {
      let separator = sisa ? '.' : '';
      rupiah += separator + ribuan.join('.');
    }

    rupiah = split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
    return rupiah ? 'Rp ' + rupiah : '';
  }

  nominalInput.addEventListener('input', function(e) {
    let cursorPos = nominalInput.selectionStart;
    let originalLength = nominalInput.value.length;

    let numericValue = nominalInput.value.replace(/[^,\d]/g, '');
    nominalRaw.value = numericValue.replace(/\./g, '');

    nominalInput.value = formatRupiah(numericValue);

    let updatedLength = nominalInput.value.length;
    cursorPos = cursorPos + (updatedLength - originalLength);
    nominalInput.setSelectionRange(cursorPos, cursorPos);
  });

  paymentForm.querySelectorAll('button[data-method]').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();

      feedback.textContent = '';
      const orderId = document.getElementById('payment-order-id').value;
      const nominal = nominalRaw.value;
      const method = this.getAttribute('data-method');
      const isLunas = this.dataset.lunas === "true";

      if (!orderId) {
        feedback.textContent = 'Order ID tidak valid';
        return;
      }

      if (!isLunas && (!nominal || parseInt(nominal) <= 0)) {
        feedback.textContent = 'Nominal harus diisi dan lebih dari 0';
        return;
      }

      const formData = new FormData();
      formData.append('order_id', orderId);
      formData.append(isLunas ? 'lunas_method' : 'payment_method', method);
      if (!isLunas) {
        formData.append('nominal', nominal);
      }

      fetch('../customer/order_action.php?order=payment', { 
        method: 'POST',
        body: formData,
      })
      .then(resp => resp.json())
      .then(data => {
      if (data.success) {
        feedback.style.color = 'green';
        feedback.textContent = data.message || 'Pembayaran berhasil';

          const modal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
          modal.hide();
          feedback.textContent = '';
          const container = document.querySelector(`.payment-info[data-order-id="${orderId}"]`);
          if (container) loadPaymentInfo(orderId, container);

      } else {
        feedback.style.color = 'red';
        feedback.textContent = data.message || 'Gagal melakukan pembayaran';
      }

      })
      .catch(err => {
        feedback.style.color = 'red';
        feedback.textContent = 'Kesalahan jaringan atau server';
      });
    });
  });

    const order_id = <?= json_encode($scrl_id) ?>;

    const target = document.getElementById(order_id);
    if (target) {
      target.style.borderColor = "yellow";
      target.style.borderWidth = "3px";
      const offsetTop = target.getBoundingClientRect().top + window.pageYOffset - 80;
      window.scrollTo({ top: offsetTop, behavior: 'instant' });
    }
    function kedip(){
      if (target.style.borderColor === "yellow") {
        target.style.borderColor = "red";
      }else if(target.style.borderColor === "red"){
        target.style.borderColor = "black";
      }else{
        target.style.borderColor = "yellow";
      }

    }
    if (<?= json_encode($scrl_id) ?>) {
      setInterval(kedip, 200);
    }
    
  });
</script>

<script>
document.querySelectorAll('.btnNote').forEach(div => {
  div.addEventListener('click', () => {
      const modal = new bootstrap.Modal(document.getElementById('noteModal'));
      modal.show();
      const noteOrderId = div.dataset.orderId;
      document.getElementById('noteInputId').value = noteOrderId;
  });
});
document.getElementById('confirmNoteInput').addEventListener('click', function(){
  const noteInputId = document.getElementById('noteInputId').value;
  const noteInput = document.getElementById('noteInput').value;
  fetch('finance_action.php?action=create_note_detail', {
    method : 'POST',
    headers : { 'Content-type': 'application/x-www-form-urlencoded'},
    body: `note=${noteInput}&order_id=${noteInputId}&access=${access}`,
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      const noteContainer = `<div class="bg-primary text-white py-2 px-3 rounded-3">ℹ️ ${res.data.value}</div>`;
      document.getElementById('note_result_' + noteInputId).innerHTML = noteContainer;
      showAlert('success', res.message);
    }else {
      showAlert('error', res.message || 'Gagal menambahkan catatan');
    }
    const modalEl = document.getElementById('noteModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    modal.hide();
  })
});
function editPembayaranAsli(div) {
  if (access == 'all') {
    const paymentId = div.dataset.paymentId;
    const orderId = div.dataset.order;
    const nominal = div.dataset.nominal;
    const metode = div.dataset.metode;
    const tanggal = div.dataset.tanggal;

    // Isi input form
    document.getElementById('edit-payment-id').value = paymentId;
    document.getElementById('edit-order-id').value = orderId;
    document.getElementById('edit-nominal').value = nominal;
    document.getElementById('edit-metode').value = metode;

    // Format tanggal ke datetime-local
    const d = new Date(tanggal);
    const localDateTime = new Date(d.getTime() - d.getTimezoneOffset() * 60000)
                            .toISOString().slice(0,16);
    document.getElementById('edit-tanggal').value = localDateTime;

    // Tampilkan modal
    const modal = new bootstrap.Modal(document.getElementById('editPaymentModal'));
    modal.show();
  }else{
    Swal.fire({
      icon: "error",
      title: "Tidak ada akses, Hubungi Administrator",
      theme: '<?= ($mode === 1) ? 'dark' : '' ?>'
    });
  }
}
document.querySelectorAll('.editable-payment').forEach(div => {
 const editPembayaran = div.querySelector('.editPembayaran');

  if (editPembayaran) {
    editPembayaran.addEventListener("click", function() {
      editPembayaranAsli(div)
    });
  }

  div.addEventListener('dblclick', () => {
    editPembayaranAsli(div)
  });
});

</script>
<script>
  let selectedPayment = null;
  let deletePaymentId = null;
  let deleteOrderId = null;

  // Delegasi event agar tetap berfungsi walau elemen dibuat dinamis
  document.addEventListener('click', function (e) {
    const target = e.target.closest('.editable-payment');
    if (target) {
      // Hapus border dari semua
      document.querySelectorAll('.editable-payment').forEach(d => d.classList.remove('border-danger'));
      // Tambahkan border ke elemen yang diklik
      target.classList.add('border-danger');
      selectedPayment = target;

      const hapusButton = e.target.closest('.hapusPembayaran');
      if (hapusButton) {
        const payment = hapusButton.closest('.editable-payment');
        if (payment) {
          hapusPembayaranAsli()
        }
      }
    }

  });

  // Tangkap tombol Delete
  document.addEventListener('keydown', function (e) {
      if (e.key === 'Delete' && selectedPayment) {
        hapusPembayaranAsli()
      }
  });
function hapusPembayaranAsli() {
  if (access == 'all') {
    deletePaymentId = selectedPayment.dataset.paymentId;
    deleteOrderId = selectedPayment.dataset.order;
    

    // Tampilkan modal konfirmasi
    const modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
    modal.show();
  }else{
    Swal.fire({
      icon: "error",
      title: "Tidak ada akses, Hubungi Administrator",
      theme: '<?= ($mode === 1) ? 'dark' : '' ?>'
    });
  }
}


  // Saat tombol konfirmasi hapus diklik
  document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
    let keteranganHapus = document.querySelector("[name='keterangan_hapus']").value;
    if (!deletePaymentId || !deleteOrderId || keteranganHapus == "") return;
    
    fetch('finance_action.php?action=delete_payment', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `payment_id=${deletePaymentId}&order_id=${deleteOrderId}&keterangan_hapus=${keteranganHapus}`
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        if (selectedPayment) {
          selectedPayment.remove();
          keteranganHapus.value = "";
        }
        const modalEl = document.getElementById('confirmDeleteModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
          const container = document.querySelector(`.payment-info[data-order-id="${deleteOrderId}"]`);
          if (container) loadPaymentInfo(deleteOrderId, container);
      } else {
        alert('Gagal menghapus pembayaran: ' + data.message);
      }
    });
  });
</script>

<script>
function initExportButtons() {
  const btnExcel = document.getElementById('btnExportExcel');
  const btnWord = document.getElementById('btnExportWord');

  if (!btnExcel || !btnWord || btnExcel.hasAttribute('data-initialized')) return;

  btnExcel.setAttribute('data-initialized', 'true');
  btnWord.setAttribute('data-initialized', 'true');

  btnExcel.addEventListener('click', async function () {
    const toko = "<?= addslashes($storeName) ?>";
    const alamat = "<?= addslashes($storeAddress) ?>";
    const startDate = document.getElementById('start_date')?.value || "";
    const endDate = document.getElementById('end_date')?.value || "";
    const tanggal = (startDate && endDate) ? ` ${startDate} s.d. ${endDate}` : 'Tanggal -';


    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet('Laporan');

    sheet.properties.defaultRowHeight = 15;
    sheet.columns = [
      { width: 6 }, { width: 20 }, { width: 20 }, { width: 15 },
      { width: 8 }, { width: 10 }, { width: 15 }
    ];

    sheet.mergeCells('A1', 'G1');
    sheet.getCell('A1').value = toko;
    sheet.getCell('A1').alignment = { horizontal: 'center', vertical: 'middle' };
    sheet.getCell('A1').font = { size: 12, bold: true };

    sheet.mergeCells('A2', 'G2');
    sheet.getCell('A2').value = alamat;
    sheet.getCell('A2').alignment = { horizontal: 'center', vertical: 'middle' };
    sheet.getCell('A2').font = { size: 10 };

    sheet.addRow([]);
    const titleRow = sheet.addRow(['Laporan Transaksi Detil']);
    titleRow.font = { size: 12, bold: true };
    sheet.mergeCells(`A${titleRow.number}:G${titleRow.number}`);
    titleRow.alignment = { horizontal: 'center' };

    const dateRow = sheet.addRow([`Tanggal: ${tanggal}`]);
    sheet.mergeCells(`A${dateRow.number}:G${dateRow.number}`);
    dateRow.alignment = { horizontal: 'center' };
    dateRow.font = { size: 10 };
    sheet.addRow([]);

    let currentRow = sheet.lastRow.number + 1;

    document.querySelectorAll('.nota-block').forEach((block, index) => {
      const strongs = block.querySelectorAll('.nota-header strong');
      const nomorator = strongs[0]?.nextSibling?.textContent.trim() || '-';
      const nama = strongs[1]?.nextSibling?.textContent.trim() || '-';
      const operator = strongs[2]?.nextSibling?.textContent.trim() || '-';
      const tanggalOrder = strongs[3]?.nextSibling?.textContent.trim() || '-';

      const borderRow = sheet.addRow([]);
      for (let i = 1; i <= 7; i++) {
        borderRow.getCell(i).border = { bottom: { style: 'medium' } };
      }
      currentRow = sheet.lastRow.number + 1;

      const r1 = sheet.getRow(currentRow++);
      r1.getCell(1).value = `#${index + 1} - ${nomorator}`;
      r1.font = { size: 11, bold: true };
      sheet.mergeCells(`A${r1.number}:G${r1.number}`);
      r1.alignment = { vertical: 'middle' };
      r1.height = 18;

      ['Nama', 'Operator', 'Tanggal'].forEach((label, i) => {
        const r = sheet.getRow(currentRow++);
        r.getCell(1).value = `${label}: ${[nama, operator, tanggalOrder][i]}`;
        r.font = { size: 10 };
        r.height = 15;
        sheet.mergeCells(`A${r.number}:G${r.number}`);
      });

      sheet.addRow([]);
      currentRow++;

      const headers = ['No', 'Bahan', 'Finishing', 'Ukuran', 'Qty', 'Satuan', 'Jumlah'];
      const headerRow = sheet.getRow(currentRow++);
      headers.forEach((h, i) => {
        const cell = headerRow.getCell(i + 1);
        cell.value = h;
        cell.font = { bold: true, size: 10 };
        cell.alignment = { horizontal: 'center', vertical: 'middle', wrapText: true };
        cell.border = { top: 'thin', bottom: 'thin', left: 'thin', right: 'thin' };
      });
      headerRow.height = 18;

      const trs = block.querySelectorAll('table tbody tr');
      trs.forEach(tr => {
        const tds = tr.querySelectorAll('td');
        const row = sheet.getRow(currentRow++);
        Array.from(tds).forEach((td, i) => {
          const cell = row.getCell(i + 1);
          cell.value = td.innerText.trim();
          cell.font = { size: 10 };
          cell.alignment = { vertical: 'middle', wrapText: true };
          cell.border = { top: 'thin', bottom: 'thin', left: 'thin', right: 'thin' };
        });
        row.height = 15;
      });

      sheet.addRow([]);
      currentRow++;

      const paymentBoxes = block.querySelectorAll('.payment-info > div');
      if (paymentBoxes.length > 0) {
        const rPayTitle = sheet.getRow(currentRow);
        rPayTitle.getCell(1).value = "Riwayat Pembayaran:";
        rPayTitle.font = { italic: true, size: 10 };
        sheet.mergeCells(`A${currentRow}:G${currentRow}`);
        currentRow++;

        paymentBoxes.forEach(div => {
          const lines = div.querySelectorAll('div');
          lines.forEach(line => {
            const r = sheet.getRow(currentRow);
            r.getCell(1).value = line.innerText.trim();
            r.font = { size: 10 };
            sheet.mergeCells(`A${currentRow}:G${currentRow}`);
            currentRow++;
          });
          sheet.addRow([]); currentRow++;
        });
      } else {
        const rPayNone = sheet.getRow(currentRow);
        rPayNone.getCell(1).value = "Belum ada pembayaran.";
        rPayNone.font = { italic: true, size: 10 };
        sheet.mergeCells(`A${currentRow}:G${currentRow}`);
        currentRow += 2;
      }

    });

    sheet.pageSetup = {
      paperSize: 9,
      orientation: 'portrait',
      margins: { left: 0.3, right: 0.3, top: 0.75, bottom: 0.75, header: 0.3, footer: 0.3 }
    };

    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    saveAs(blob, `Transaksi_Detil_${tanggal}.xlsx`);
  });

  btnWord.addEventListener('click', function () {
    const {
      Document, Packer, Paragraph, Table, TableRow, TableCell,
      HeadingLevel, AlignmentType, WidthType, BorderStyle
    } = window.docx;

    const toko = "<?= addslashes($storeName) ?>";
    const alamat = "<?= addslashes($storeAddress) ?>";
    const startDate = document.getElementById('start_date')?.value || "";
    const endDate = document.getElementById('end_date')?.value || "";
    const tanggal = (startDate && endDate) ? `${startDate} s.d. ${endDate}` : 'Tanggal -';

    const children = [
      new Paragraph({ text: toko, heading: HeadingLevel.TITLE, alignment: AlignmentType.CENTER }),
      new Paragraph({ text: alamat, alignment: AlignmentType.CENTER, spacing: { after: 300 } }),
      new Paragraph({ text: "Laporan Transaksi Detil", heading: HeadingLevel.HEADING_1, alignment: AlignmentType.CENTER, spacing: { after: 150 } }),
      new Paragraph({ text: `Tanggal: ${tanggal}`, alignment: AlignmentType.CENTER, spacing: { after: 300 } }),
    ];

    document.querySelectorAll('.nota-block').forEach((block, index) => {
      const strongs = block.querySelectorAll('.nota-header strong');
      const nomorator = strongs[0]?.nextSibling?.textContent.trim() || '-';
      const nama = strongs[1]?.nextSibling?.textContent.trim() || '-';
      const operator = strongs[2]?.nextSibling?.textContent.trim() || '-';
      const tanggalOrder = strongs[3]?.nextSibling?.textContent.trim() || '-';

      children.push(new Paragraph({
        border: { bottom: { color: "auto", space: 1, size: 6, style: BorderStyle.SINGLE } },
        spacing: { before: 300, after: 300 }
      }));

      children.push(
        new Paragraph({ text: `#${index + 1} - ${nomorator}`, heading: HeadingLevel.HEADING_2, spacing: { before: 200, after: 100 } }),
        new Paragraph({ text: `Nama: ${nama}`, spacing: { after: 50 } }),
        new Paragraph({ text: `Operator: ${operator}`, spacing: { after: 50 } }),
        new Paragraph({ text: `Tanggal: ${tanggalOrder}`, spacing: { after: 200 } }),
      );

      const headers = ['No', 'Bahan', 'Finishing', 'Ukuran', 'Qty', 'Satuan', 'Jumlah'];
      const rows = [new TableRow({
        children: headers.map(h => new TableCell({
          width: { size: 100 / headers.length, type: WidthType.PERCENTAGE },
          borders: fullBorder(),
          children: [new Paragraph({ text: h, bold: true, alignment: AlignmentType.CENTER })]
        }))
      })];

      block.querySelectorAll('table tbody tr').forEach(tr => {
        const cells = Array.from(tr.querySelectorAll('td')).map(td => new TableCell({
          borders: fullBorder(),
          children: [new Paragraph(td.innerText.trim())]
        }));
        rows.push(new TableRow({ children: cells }));
      });

      children.push(new Table({ rows, width: { size: 100, type: WidthType.PERCENTAGE } }));

      const paymentBoxes = block.querySelectorAll('.payment-info > div');
      if (paymentBoxes.length > 0) {
        children.push(new Paragraph({ text: "Riwayat Pembayaran:", spacing: { before: 200, after: 100 } }));
        paymentBoxes.forEach(div => {
          const lines = div.querySelectorAll('div');
          lines.forEach(line => {
            children.push(new Paragraph({ text: line.innerText.trim(), spacing: { after: 20 } }));
          });
          children.push(new Paragraph({ text: "" }));
        });
      } else {
        children.push(new Paragraph({ text: "Belum ada pembayaran.", spacing: { before: 200, after: 200 } }));
      }


      children.push(new Paragraph({ text: "", spacing: { after: 300 } }));
    });

    const doc = new Document({ sections: [{ children }] });

    Packer.toBlob(doc).then(blob => {
      saveAs(blob, `Transaksi_Detil_${tanggal}.docx`);
    });

    function fullBorder() {
      return {
        top: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
        bottom: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
        left: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
        right: { style: BorderStyle.SINGLE, size: 1, color: "000000" }
      };
    }
  });
}

// Jalankan initExportButtons setiap 1 detik sampai tombol ditemukan
const exportInterval = setInterval(() => {
  initExportButtons();

  if (document.getElementById('btnExportExcel')?.hasAttribute('data-initialized')) {
    clearInterval(exportInterval);
  }
}, 1000);
</script>

</body>
</html>
