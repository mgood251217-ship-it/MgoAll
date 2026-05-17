<?php
require_once '../connect.php';
require_once BASE_PATH . '/functions/tampilkanTabelOrders.php';
require_once BASE_PATH . '/session.php';

$is_admin_like = in_array($role, ['SETTING']);
$is_all_access = in_array($role, ['PRODUKSI', 'MANAGER', 'ADMIN']);

// Default system untuk filter
if ($is_admin_like) {
    $system = 'OFFLINE';
} elseif ($role === 'ONLINE') {
    $system = 'ONLINE';
} elseif ($is_all_access) {
    $system = 'OFFLINE';
} else {
    $system = 'ONLINE';
}

// Ambil filter tanggal dari GET
$start_input = $_GET['start_date'] ?? date('Y-m-d');
$end_input = $_GET['end_date'] ?? date('Y-m-d');
$search_text = trim($_GET['search'] ?? '');

$start_dt = DateTime::createFromFormat('Y-m-d', $start_input);
$end_dt = DateTime::createFromFormat('Y-m-d', $end_input);

if (!$start_dt) $start_input = date('Y-m-d');
if (!$end_dt) $end_input = date('Y-m-d');

$start_date = $start_input . ' 00:00:00';
$end_date = $end_input . ' 23:59:59';

$customerLimit = 0;

if ($user_id) {
    $stmt = $koneksi->prepare("SELECT customer_limit FROM user_setting WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($cl);
    if ($stmt->fetch()) {
        $customerLimit = (int)$cl;
    }
    $stmt->close();
}

// Ambil semua order, pisahkan nanti
if ($is_all_access) {
    if ($search_text !== '') {
        $query = "SELECT * FROM orders WHERE store_id = ? AND (customer_name LIKE ? OR nomorator LIKE ?) AND date BETWEEN ? AND ? ORDER BY order_id DESC";
        $params = [$store_id, "%$search_text%", "%$search_text%", $start_date, $end_date];
        $types = "issss";
    } else {
        if ($customerLimit > 0) {
          $query = "(SELECT * FROM orders WHERE store_id = ? AND system = 'OFFLINE' AND date BETWEEN ? AND ? ORDER BY order_id DESC LIMIT ?)
                    UNION ALL
                    (SELECT * FROM orders WHERE store_id = ? AND system = 'ONLINE' AND date BETWEEN ? AND ? ORDER BY order_id DESC LIMIT ?)";
          $params = [$store_id, $start_date, $end_date, $customerLimit, $store_id, $start_date, $end_date, $customerLimit];
          $types = "issiissi";
        }else {
          $query = "SELECT * FROM orders WHERE store_id = ? AND date BETWEEN ? AND ? ORDER BY order_id DESC";
          $params = [$store_id, $start_date, $end_date];
          $types = "iss";
        }

    }
} else {
    if ($search_text !== '') {
        $query = "SELECT * FROM orders WHERE store_id = ? AND system = ? AND (customer_name LIKE ? OR nomorator LIKE ?) AND date BETWEEN ? AND ? ORDER BY order_id DESC";
        $params = [$store_id, $system, "%$search_text%", "%$search_text%", $start_date, $end_date];
        $types = "isssss";
    } else {
      if ($customerLimit > 0) {
        $query = "SELECT * FROM orders WHERE store_id = ? AND system = ? AND date BETWEEN ? AND ? ORDER BY order_id DESC LIMIT " . $customerLimit;
        $params = [$store_id, $system, $start_date, $end_date];
        $types = "isss";
      }else {
        $query = "SELECT * FROM orders WHERE store_id = ? AND system = ? AND date BETWEEN ? AND ? ORDER BY order_id DESC";
        $params = [$store_id, $system, $start_date, $end_date];
        $types = "isss";
      }

    }
}

$stmt = $koneksi->prepare($query);
if ($stmt === false) {
    die("Prepare failed: " . $koneksi->error);
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$ordersOnline = [];
$ordersOffline = [];

while ($row = $result->fetch_assoc()) {
    if ($row['system'] === 'ONLINE') {
        $ordersOnline[] = $row;
    } else {
        $ordersOffline[] = $row;
    }
}
$stmt->close();

if (isset($_SESSION['users'])) {
  $users = $_SESSION['users'];
} else {
  $stmtUser = $koneksi->prepare("SELECT user_id, initial FROM users WHERE store_id = ?");
  $stmtUser->bind_param("i", $store_id);
  $stmtUser->execute();
  $userResult = $stmtUser->get_result();
  $users = [];
  while ($u = $userResult->fetch_assoc()) {
      $users[$u['user_id']] = $u['initial'];
  }
  $stmtUser->close();
  $_SESSION['users'] = $users ;
}


?>


  <!DOCTYPE html>
  <html lang="id">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer - Mgood</title>
    <?php include BASE_PATH . '/header.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/customer.css">
  <style>
    .global-loading {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.45);
      z-index: 9999;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .global-loading .loading-content {
      color: #fff;
      text-align: center;
    }

<?php if (isset($username) && ($username == 'zannia' || $username == 'vikialvian')) { ?>
    .dark-mode .table-primary tr th {
      background-color:rgb(192, 22, 155) !important;
      color: white !important;
    }
    .dark-mode .table-danger tr th {
      background-color:rgb(192, 22, 155) !important;
      color: white !important;
    }
    .dark-mode .table-prim > tbody > .order-row:nth-child(even) > td{
      background-color: #ffcce5 !important;
      color: rgb(115, 0, 90) !important;
    }
    .dark-mode .table-prim > tbody > .order-row:nth-child(odd) > td{
      background-color:rgb(255, 244, 249) !important;
      color: rgb(115, 0, 90) !important;
    }
    .dark-mode .table-dan > tbody > .order-row:nth-child(even) > td{
      background-color: #ffcce5 !important;
      color: rgb(115, 0, 90) !important;
    }
    .dark-mode .table-dan > tbody > .order-row:nth-child(odd) > td{
      background-color: rgb(255, 244, 249) !important;
      color: rgb(115, 0, 90) !important;
    }
    .dark-mode .table-prim > tbody > .order-row.row-selected > td,
    .dark-mode .table-dan > tbody > .order-row.row-selected > td {
        background-color: rgb(255, 151, 232) !important;
        color: white !important;
    }
<?php } ?>

  </style>
  </head>
  <body <?= ($mode === 1) ? 'class="dark-mode"' : '' ?>>
  <div id="main-wrapper" <?= ($mode === 1) ? 'class="dark-mode"' : '' ?>>
    <?php include BASE_PATH . '/navbar.php'; ?>
    <div id="main-content" <?= (isset($mode) && $mode === 1) ? 'class="dark-mode"' : '' ?>>
      <?php include BASE_PATH . '/sidebar.php'; ?>
      <div id="page-content-wrapper">
        <div class="row align-items-end mb-4">
          <div class="col-md-auto">
            <h1 class="mb-0" style="font-size:1.7rem;">Data Customer / Order</h1>
          </div>
          <div class="col">
            <form method="post" action="toggle_preview_print.php" style="display:inline;">
              <button 
                type="submit" 
                name="toggle_preview_print" 
                value="1"
                style="border:none; background:none; padding:0; cursor:pointer;"
                title="Toggle Preview Print"
              >
                <img 
                  src="<?= BASE_URL ?>/assets/img/prt.svg" 
                  alt="Print Preview" 
                  style="
                    width:30px; height:30px; 
                    filter: invert(1) sepia(1) saturate(5) hue-rotate(180deg);
                    opacity: <?= ($preview_print === 1) ? '1' : '0.4' ?>;
                    transition: opacity 0.3s ease;
                  "
                >
              </button>
            </form>
          </div>
          <div class="col">
          <form method="post" action="update_limit.php" class="row g-2 " id="limitForm" style="margin-bottom:0;">
            <div class="col-auto">
                <select class="form-select" aria-label="Default select example"
                name="limit"
                id="limit"
                onchange="this.form.submit()
                ">
                  <option selected value="0">Limit Order</option>
                  <option value="1">1</option>
                  <option value="5">5</option>
                  <option value="10">10</option>
                  <option value="15">15</option>
                  <option value="0">Unlimited</option>
                </select>
            </div>
          </form>
          </div>
          <div class="col">
          <form method="get" class="row g-2 align-items-end justify-content-end flex-nowrap" id="filterForm" style="margin-bottom:0;">
            <div class="col-auto">
              <label for="start_date" class="form-label">Dari</label>
              <input
                type="date"
                name="start_date"
                id="start_date"
                class="form-control"
                value="<?= htmlspecialchars($_GET['start_date'] ?? date('Y-m-d')) ?>"
                onchange="this.form.submit()"
              >
            </div>
            <div class="col-auto">
              <label for="end_date" class="form-label">Sampai</label>
              <input
                type="date"
                name="end_date"
                id="end_date"
                class="form-control"
                value="<?= htmlspecialchars($_GET['end_date'] ?? date('Y-m-d')) ?>"
                onchange="this.form.submit()"
              >
            </div>
            <div class="col-auto">
              <label for="search" class="form-label">Cari</label>
              <input
                type="text"
                name="search"
                id="search"
                value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                class="form-control"
                placeholder="Nama / Nomorator"
                oninput="debouncedSubmit()"
              >
            </div>
          </form>
          </div>
        </div>

      <!-- Tampilkan Tabel -->
      <?php if ($is_all_access): ?>
        <h5 id="order_offline">Order OFFLINE</h5>
        <?php tampilkanTabelOrders($ordersOffline, $koneksi, $users, $role, 'OFFLINE'); ?>

        <h5 class="mt-4" id="order_online">Order ONLINE</h5>
        <?php tampilkanTabelOrders($ordersOnline, $koneksi, $users, $role, 'ONLINE'); ?>
      <?php else: ?>
        <h5 id="order_section">Data <?= $system ?></h5>
        <?php
          $orders = ($system === 'ONLINE') ? $ordersOnline : $ordersOffline;
          tampilkanTabelOrders($orders, $koneksi, $users, $role, 'ONLINE');
        ?>
      <?php endif; ?>


      <?php if ($role !== 'PRODUKSI'): ?>
        <!-- Tombol Tambah Order Baru di kanan bawah dengan margin -->
        <div style="position: fixed; bottom: 50px; right: 20px; z-index: 999;">
          <button class="btn btn-primary" id="btnShowAddOrderModal">+ Tambah Order Baru</button>
        </div>
      <?php endif; ?>
        <!-- Modal Tambah Order Baru -->
        <div class="modal fade" id="addOrderModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog">
            <form action="add_order.php" method="post" class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Tambah Order Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3 position-relative">
                  <label class="form-label">Nama Customer</label>
                  <input 
                    type="text" 
                    name="customer_name" 
                    id="customerNameInput" 
                    class="form-control" 
                    required 
                    autocomplete="off"
                    style="text-transform:uppercase"
                    oninput="this.value = this.value.toUpperCase();" 
                  >

                  <div id="customerDropdown" 
                      class="list-group position-absolute" 
                      style="z-index:1050; width:100%; max-height:150px; overflow-y:auto; display:none;">
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Nomor</label>
                  <input type="text" name="nomor" id="nomorInput" class="form-control" required placeholder="0812xxxx">
                </div>

                <div class="mb-3">
                  <label class="form-label">Deadline</label>
                <?php
                // Jam sekarang dibulatkan ke atas, menit 00
                $value = date('Y-m-d\TH:00', strtotime('+1 hour'));
                ?>
                <input
                  type="datetime-local"
                  name="deadline"
                  class="form-control"
                  required
                  step="60"
                  value="<?= $value ?>"
                >
                </div>
                <div class="mb-3">
                  <label class="form-label">Operator</label>
                  <?php if ($role === 'ONLINE'): ?>
                    <input type="hidden" name="user_id" value="<?= $user_id ?>">
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['initial']) ?>" readonly>
                  <?php else: ?>
                    <select name="user_id" class="form-select" required>
                      <option value="">-- Pilih Operator --</option>
                      <?php foreach ($users as $id => $initial): ?>
                        <option value="<?= $id ?>"><?= htmlspecialchars($initial) ?></option>
                      <?php endforeach; ?>
                    </select>
                  <?php endif; ?>
                </div>

              </div>
              <div class="modal-footer">
                <button type="submit" id="addOrderBtn" class="btn btn-primary">Tambah Order</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              </div>
            </form>
          </div>
        </div>

        <!-- Modal Edit -->
        <div class="modal fade" id="editOrderModal" tabindex="-1" aria-hidden="true" >
          <div class="modal-dialog" >
            <form action="edit_order.php" method="post" class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Edit Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="order_id" id="edit-id">
                <div class="mb-3">
                  <label for="edit-nomorator" class="form-label">Nomorator</label>
                  <input type="number" name="nomorator" id="edit-nomorator" class="form-control" required readonly>
                </div>
                <div class="mb-3">
                  <label for="edit-customer_name" class="form-label">Nama Customer</label>
                  <input type="text" name="customer_name" id="edit-customer_name" class="form-control" required style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase();">
                </div>
                <div class="mb-3">
                  <label for="edit-deadline" class="form-label">Deadline</label>
                  <input type="datetime-local" name="deadline" id="edit-deadline" class="form-control" required>
                </div>
                <div class="mb-3">
                  <label for="edit-nomor" class="form-label">Nomor</label>
                  <input type="text" name="nomor" id="edit-nomor" class="form-control" required style="text-transform:uppercase">
                </div>
                <div class="mb-3">
                  <label for="edit-date" class="form-label">Tanggal</label>
                  <input type="datetime-local" name="date" id="edit-date" class="form-control">
                </div>
                <div class="mb-3">
                  <label for="edit-user_id" class="form-label">Operator</label>
                  <select name="user_id" id="edit-user_id" class="form-select" required>
                    <?php foreach ($users as $id => $initial): ?>
                      <option value="<?= $id ?>"><?= htmlspecialchars($initial) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="mb-3">
                  <label for="edit-sistem" class="form-label">Sistem</label>
                  <select name="sistem" id="edit-sistem" class="form-select" required>
                      <option value="OFFLINE">OFFLINE</option>
                      <option value="ONLINE">ONLINE</option>
                  </select>
                </div>
              </div>
              <div class="modal-footer">
                <button type="submit" class="btn btn-success">Simpan Perubahan</button>
              </div>
            </form>
          </div>
        </div>
        <!-- Modal Payment -->
        <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
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

        <!-- Modal Proses Massal -->
        <div class="modal fade" id="modalProsesMassal" tabindex="-1">
          <div class="modal-dialog">
            <form method="post" action="status_process_massal.php" id="formProsesMassal">
              <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                  <h5 class="modal-title">🛠 Proses Status Order Massal</h5>
                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <!-- Container untuk menyimpan list order_id yang dipilih -->
                  <input type="hidden" name="order_ids" id="massOrderIds">
                  <input type="hidden" id="statusInputMassal" name="status" value="">

                  <label class="form-label fw-bold">Status Order:</label>
                  <div class="btn-group w-100 mb-3" role="group" aria-label="Pilih Status">
                    <button type="button" class="btn btn-outline-primary status-btn" data-status="BELUM_DIPROSES">BELUM DIPROSES</button>
                    <button type="button" class="btn btn-outline-primary status-btn" data-status="DIPROSES">DIPROSES</button>
                    <button type="button" class="btn btn-outline-primary status-btn" data-status="SELESAI">SELESAI</button>
                    <button type="button" class="btn btn-outline-primary status-btn" data-status="DIAMBIL">DIAMBIL</button>
                    <button type="button" class="btn btn-outline-primary status-btn" data-status="LAINNYA">LAINNYA</button>
                  </div>

                  <!-- Input status manual -->
                  <div class="mb-3 d-none" id="customStatusWrapperMassal">
                    <label for="customStatusMassal" class="form-label">Isi Status Manual</label>
                    <input type="text" id="customStatusMassal" class="form-control" placeholder="Contoh: REVISI, DALAM PENGIRIMAN">
                  </div>

                  <div id="storeUserSelectorMassal" class="mb-3 d-none">
                    <label for="userInitialMassal" class="form-label fw-bold">Pilih User Initial</label>
                    <select name="user_initial" id="userInitialMassal" class="form-select">
                      
                    </select>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="submit" class="btn btn-success w-100" id="submitBtnMassal" disabled>💾 Simpan</button>
                </div>
              </div>
            </form>
          </div>
        </div>

      <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-danger text-white">
              <h5 class="modal-title" id="confirmDeleteLabel">Konfirmasi Hapus Orderan</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              Apakah Anda yakin ingin menghapus Orderan ini?
              <br>
              <div class="mb-1">
                <label class="form-label">Keterangan</label>
                <textarea type="text" class="form-control" name="keterangan_hapus" placeholder="Keterangan" require></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Hapus</button>
            </div>
          </div>
        </div>
      </div>

      <div id="global-loading" class="global-loading d-none">
        <div class="loading-content">
          <div class="spinner-border text-light" role="status"></div>
          <div class="mt-2">Loading...</div>
        </div>
      </div>


      </div>
    </div>
    <?php include BASE_PATH . '/footer.php'; ?>
  </div>
<div id="hover-tooltip" style="
  display: none;
  position: absolute;
  z-index: 1000;
  background: #fff;
  border: 1px solid #ccc;
  padding: 8px 12px;
  font-size: 13px;
  max-width: 300px;
  white-space: pre-wrap;
  box-shadow: 0 4px 12px hsla(0, 0%, 0%, 0.15);
  border-radius: 6px;
  pointer-events: none;
"></div>
<?php if (isset($username) && ($username == 'zannia' || $username == 'vikialvian')) { ?>
  <img src="https://mgood.my.id/assets/img/output-onlinegiftools.gif" alt="" srcset="" style="position: fixed; bottom: 0; left: 0; height: 200px; z-index: 999999;">
<?php }elseif (isset($username) && ($username == 'nada' )){ ?>
  <img src="https://media.tenor.com/d2j7YdyhtmsAAAAj/shikanoko-dance-shikanoko-meme.gif" alt="" srcset="" style="position: fixed; bottom: 0; left: 0; height: 200px; z-index: 999999;">
<?php } ?>

<script src="<?= BASE_URL ?>/assets/js/customer.js"></script>
<script>
function showGlobalLoading() {
  document.getElementById('global-loading').classList.remove('d-none');
}

function hideGlobalLoading() {
  document.getElementById('global-loading').classList.add('d-none');
}


document.addEventListener('DOMContentLoaded', function () {
  const statusButtons = document.querySelectorAll('.status-btn');
  const statusInput = document.getElementById('statusInput');
  const submitBtn = document.getElementById('submitBtn');
  const customStatusWrapper = document.getElementById('customStatusWrapper');
  const customStatusInput = document.getElementById('customStatus');

  statusButtons.forEach(button => {
    button.addEventListener('click', () => {
      statusButtons.forEach(btn => btn.classList.remove('active'));
      button.classList.add('active');
      const status = button.getAttribute('data-status');
      if (status === 'LAINNYA') {
        customStatusWrapper.classList.remove('d-none');
        customStatusInput.focus();
        customStatusInput.addEventListener('input', () => {
          statusInput.value = customStatusInput.value.trim();
          submitBtn.disabled = statusInput.value === '';
        });
        statusInput.value = customStatusInput.value.trim();
      } else {
        customStatusWrapper.classList.add('d-none');
        statusInput.value = status;
        submitBtn.disabled = false;
      }
    });
  });
});

function debouncedSubmit() {
  clearTimeout(window.debounceTimer);
  window.debounceTimer = setTimeout(() => {
    document.getElementById('filterForm').submit();
  }, 1000);
}

const tooltip = document.getElementById('hover-tooltip');

document.querySelectorAll('.order-row').forEach(row => {
  const orderId = row.dataset.orderId;
  let timeout;

  row.addEventListener('mouseenter', (e) => {
    
    if (e.target.closest('.aksi-cell')) return;

    
    tooltip.innerText = 'Memuat...';
    tooltip.style.display = 'block';

    timeout = setTimeout(() => {
      fetch(`get_order_items.php?order_id=${orderId}`)
        .then(res => res.json())
        .then(data => {
          if (data.items.length === 0) {
            tooltip.innerText = 'Tidak ada item';
            return;
          }

          tooltip.innerText = data.items.map(item => {
            const size = item.size && item.size !== '-' ? ` (${item.size})` : '';
            const finishing = item.finishing && item.finishing !== '-' ? ` + ${item.finishing}` : '';
            return `• ${item.judul}${size}${finishing} x${item.quantity}`;
          }).join('\n');
        });
    }, 150);
  });

  row.addEventListener('mousemove', (e) => {
    if (e.target.closest('.aksi-cell')) {
      tooltip.style.display = 'none'; 
      return;
    }

    tooltip.style.left = (e.pageX + 15) + 'px';
    tooltip.style.top = (e.pageY + 15) + 'px';

    if (tooltip.style.display === 'none') {
      tooltip.style.display = 'block';  
    }
  });

  row.addEventListener('mouseleave', () => {
    clearTimeout(timeout);
    tooltip.style.display = 'none';
  });

});




document.querySelectorAll('.order-row').forEach(row => {
  row.addEventListener('dblclick', function () {
    const orderId = this.dataset.orderId;
    const storeId = this.dataset.storeId;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'nota';
    form.target = '_self';
    form.innerHTML = `<input type="hidden" name="order_id" value="${orderId}">
                      <input type="hidden" name="store_id" value="${storeId}">`;
    document.body.appendChild(form);
    form.submit();
  });

  row.addEventListener('click', function () {
    if (window.selectedRow) {
      window.selectedRow.classList.remove("row-selected");
    }
    window.selectedRow = this;
    this.classList.add("row-selected");

  });
});

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Delete' && window.selectedRow) {
      let access = '<?= $access ?>';
      if (access == 'all') {
        
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
  });

  document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
    const orderId = window.selectedRow.dataset.orderId;
    let keteranganHapus = document.querySelector("[name='keterangan_hapus']").value;
    fetch('delete_order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `order_id=${encodeURIComponent(orderId)}&keterangan_hapus=${keteranganHapus}`
    }).then(res => res.json()).then(data => {
      if (data.success) {
        window.selectedRow.remove();
        window.selectedRow = null;
        const modalEl = document.getElementById('confirmDeleteModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
      } else {
        alert('Gagal menghapus order: ' + data.message);
      }
    }).catch(err => {
      alert('Terjadi kesalahan: ' + err);
    });
  });

document.getElementById('btnShowAddOrderModal')?.addEventListener('click', () => {
  const modalEl = document.getElementById('addOrderModal');
  const modal = new bootstrap.Modal(modalEl);
  modal.show();

  
  modalEl.addEventListener('shown.bs.modal', () => {
    document.getElementById('customerNameInput')?.focus();
  }, { once: true });
});
document.getElementById("addOrderBtn")?.addEventListener('click', () => {
  showGlobalLoading();
})

function printStruk(order_id, store_id) {
  const url = `print_struk?order_id=${order_id}&store_id=${store_id}`;
  window.open(url, "_blank");
}
function printStrukPDF(order_id, store_id) {
  const url = `print_struk_pdf?order_id=${order_id}&store_id=${store_id}`;
  window.open(url, "_blank");
}

const searchInput = document.getElementById('search');
const filterForm = document.getElementById('filterForm');
let searchTimeout;
searchInput?.addEventListener('input', function () {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => filterForm.submit(), 500);
});

document.querySelectorAll('.btn-edit').forEach(button => {
  button.addEventListener('click', function () {
    document.getElementById('edit-id').value = this.dataset.id;
 
    fetch('get_order.php?order_id=' + this.dataset.id)
    .then(response => response.json())
    .then(data => {
      document.getElementById('edit-nomorator').value = data.nomorator;
      document.getElementById('edit-customer_name').value = data.customer_name;
      document.getElementById('edit-nomor').value = data.nomor;
      document.getElementById('edit-deadline').value = data.deadline;
      const rawDate = data.date;
      const formattedDate = rawDate.replace(' ', 'T').slice(0, 16);
      document.getElementById('edit-date').value = formattedDate;
      document.getElementById('edit-user_id').value = data.user_id;
      document.getElementById('edit-sistem').value = data.system;


      
      new bootstrap.Modal(document.getElementById('editOrderModal')).show();
    })

  });
});

document.querySelectorAll('.btn-pay').forEach(button => {
  button.addEventListener('click', () => {
    document.getElementById('payment-order-id').value = button.getAttribute('data-order-id');
    document.getElementById('payment-nominal').value = '';
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
  });
});

document.getElementById('btn-proses-massal').addEventListener('click', function () {
  const checkedBoxes = document.querySelectorAll('.order-checkbox:checked');
  if (checkedBoxes.length === 0) {
    alert('Pilih minimal satu order terlebih dahulu.');
    return;
  }

  const orderIds = Array.from(checkedBoxes).map(cb => cb.value).join(',');
  document.getElementById('massOrderIds').value = orderIds;

  
  document.getElementById('statusInputMassal').value = '';
  document.getElementById('submitBtnMassal').disabled = true;
  document.getElementById('storeUserSelectorMassal').classList.add('d-none');
  document.getElementById('userInitialMassal').innerHTML = '';
  document.getElementById('customStatusWrapperMassal').classList.add('d-none');
  document.getElementById('customStatusMassal').value = '';

  // Tampilkan modal
  new bootstrap.Modal(document.getElementById('modalProsesMassal')).show();
});


document.querySelectorAll('.status-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const status = btn.getAttribute('data-status');
    const isMassal = btn.closest('#modalProsesMassal') !== null;

    const statusInput = document.getElementById(isMassal ? 'statusInputMassal' : 'statusInput');
    const submitBtn = document.getElementById(isMassal ? 'submitBtnMassal' : 'submitBtn');
    const customStatusWrapper = document.getElementById(isMassal ? 'customStatusWrapperMassal' : 'customStatusWrapper');
    const customStatusInput = document.getElementById(isMassal ? 'customStatusMassal' : 'customStatus');
    const storeUserSelector = document.getElementById(isMassal ? 'storeUserSelectorMassal' : 'storeUserSelector');
    const userSelect = document.getElementById(isMassal ? 'userInitialMassal' : 'userInitial');

    
    statusInput.value = status;
    submitBtn.disabled = false;

    // Toggle input status manual jika 'LAINNYA'
    if (status === 'LAINNYA') {
      customStatusWrapper.classList.remove('d-none');
      customStatusInput.focus();

      customStatusInput.addEventListener('input', () => {
        statusInput.value = customStatusInput.value.trim();
        submitBtn.disabled = statusInput.value === '';
      });

      statusInput.value = customStatusInput.value.trim();
    } else {
      customStatusWrapper.classList.add('d-none');
    }

    
    btn.closest('.btn-group').querySelectorAll('.status-btn').forEach(b => {
      b.classList.remove('btn-primary');
      b.classList.add('btn-outline-primary');
    });
    btn.classList.remove('btn-outline-primary');
    btn.classList.add('btn-primary');

    // Handle user select jika status DIAMBIL
    userSelect.innerHTML = '';
    if (status === 'DIAMBIL') {
      const keys = Object.keys(userList);
      if (keys.length > 0) {
        keys.forEach(userId => {
          const opt = document.createElement('option');
          opt.value = userId;
          opt.textContent = userList[userId];
          userSelect.appendChild(opt);
        });
      } else {
        const opt = document.createElement('option');
        opt.textContent = 'Tidak ada user';
        opt.disabled = true;
        userSelect.appendChild(opt);
      }
      storeUserSelector.classList.remove('d-none');
    } else {
      storeUserSelector.classList.add('d-none');
    }
  });
});

  const paymentForm = document.getElementById('paymentForm');
  const nominalInput = document.getElementById('payment-nominal');
  const nominalRaw = document.getElementById('payment-nominal-raw');
  const feedback = document.getElementById('paymentFeedback');

  // Format rupiah function (sama dengan yang kamu punya)
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

  // Submit via AJAX
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

      // Siapkan data form
      const formData = new FormData();
      formData.append('order_id', orderId);
      formData.append(isLunas ? 'lunas_method' : 'payment_method', method);
      if (!isLunas) {
        formData.append('nominal', nominal);
      }

      showGlobalLoading();

      fetch('payment.php', {
        method: 'POST',
        body: formData,
      })
      .then(resp => resp.json())
      .then(data => {
      if (data.success) {
        feedback.style.color = 'green';
        feedback.textContent = data.message || 'Pembayaran berhasil';
        hideGlobalLoading();

        const orderId = document.getElementById('payment-order-id').value;
        const row = document.querySelector(`tr.order-row[data-order-id="${orderId}"]`);
        if (row) {
          const role = '<?= $role ?>' ;
          let bayarCell = row.cells[8];
          let keteranganCell = row.cells[9];
          let aksiCell = row.cells[7];
          if (role != 'MANAGER' && role != 'ADMIN') {
            bayarCell = row.cells[7];
            keteranganCell = row.cells[8];
            aksiCell = row.cells[6];
          }
          if (bayarCell) {
            bayarCell.innerHTML = data.bayar || '';
          }
          if (keteranganCell) {
            keteranganCell.textContent = data.keterangan || '';
          }
          if (aksiCell && data.isLunas) {
            const bayarBtn = aksiCell.querySelector('button.btn-pay'); 
            if (bayarBtn) {
              bayarBtn.remove();
            }
          }
          nominal.innerHTML = '';
        }


          const modal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
          modal.hide();
          feedback.textContent = '';

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

  const customerInput = document.getElementById('customerNameInput');
  const nomorInput = document.getElementById('nomorInput');
  const dropdown = document.getElementById('customerDropdown');
  let history = {};
  customerInput?.addEventListener('input', function () {
    const val = customerInput.value.toUpperCase();
    fetch('get_history.php?name='+ val)
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    })
    .then(data => {
      history = data;
      dropdown.innerHTML = '';
      if (val.length === 0) return dropdown.style.display = 'none';

      const filtered = history.filter(item => item.name.toUpperCase());
      if (filtered.length === 0) return dropdown.style.display = 'none';

      filtered.forEach(item => {
        const div = document.createElement('a');
        div.className = 'list-group-item list-group-item-action';
        div.style.cursor = 'pointer';
        div.textContent = `${item.name} - ${item.nomor}`;
        div.onclick = () => {
          customerInput.value = item.name;
          nomorInput.value = item.nomor;
          dropdown.style.display = 'none';
        };
        dropdown.appendChild(div);
      });

      dropdown.style.display = 'block';
      
    }).catch(error => {
      console.error('Error fetching data:', error);
    })

  });

  document.addEventListener('click', function (e) {
    if (!customerInput.contains(e.target) && !dropdown.contains(e.target)) {
      dropdown.style.display = 'none';
    }
  });

document.addEventListener('DOMContentLoaded', () => {
  const btnProses = document.getElementById('btn-proses-massal');

  function isVisible(element) {
    return element.offsetParent !== null;
  }

  function toggleButton() {
    const allCheckboxes = Array.from(document.querySelectorAll('.order-checkbox')).filter(isVisible);
    const anyChecked = allCheckboxes.some(cb => cb.checked);
    btnProses.style.display = anyChecked ? 'block' : 'none';
  }

  document.querySelectorAll('table').forEach(table => {
    const checkAll = table.querySelector('.check-all');
    const checkboxes = Array.from(table.querySelectorAll('.order-checkbox')).filter(isVisible);

    if (!checkAll || checkboxes.length === 0) return;

    checkAll.addEventListener('change', () => {
      checkboxes.forEach(cb => {
        if (isVisible(cb)) {
          cb.checked = checkAll.checked;
        }
      });
      toggleButton();
    });

    checkboxes.forEach(cb => {
      cb.addEventListener('change', () => {
        const allChecked = checkboxes.every(cb2 => cb2.checked);
        checkAll.checked = allChecked;
        toggleButton();
      });
    });

    const allCheckedInit = checkboxes.length > 0 && checkboxes.every(cb => cb.checked);
    checkAll.checked = allCheckedInit;
  });

  toggleButton();
});

  document.addEventListener('DOMContentLoaded', function () {
    const nominalInput = document.getElementById('payment-nominal');
    const lunasButtons = document.querySelectorAll('[data-lunas="true"]');
    const modal = document.getElementById('paymentModal');

    function updateLunasButtonState() {
      const rawValue = nominalInput.value.replace(/[^\d]/g, '');
      const isPartial = rawValue && parseInt(rawValue) > 0;

      lunasButtons.forEach(button => {
        button.disabled = isPartial;
      });
    }

    modal.addEventListener('shown.bs.modal', function () {
      updateLunasButtonState();
    });

    nominalInput.addEventListener('input', updateLunasButtonState);
  });


<?php
$sistem = isset($_POST['system']) ? strtoupper($_POST['system']) : '';
?>

  document.addEventListener('DOMContentLoaded', () => {
    const sistem = <?= json_encode($sistem) ?>;

    let targetId = '';
    if (sistem === 'OFFLINE') {
      targetId = 'order_offline';
    } else if (sistem === 'ONLINE') {
      targetId = 'order_online';
    }

    const target = document.getElementById(targetId);
    if (target) {
      const offsetTop = target.getBoundingClientRect().top + window.pageYOffset - 80;
      window.scrollTo({ top: offsetTop, behavior: 'smooth' });
    }
  });
</script>
<script>
let sortDirection = {};

function sortTable(th, colIndex) {

    // ambil tabel dari header yg diklik
    const table = th.closest("table");
    const tbody = table.querySelector("tbody");
    const rows = Array.from(tbody.querySelectorAll("tr"));

    // key unik per tabel + kolom
    const tableId = table.dataset.sortId || Math.random();
    table.dataset.sortId = tableId;

    const key = tableId + "_" + colIndex;

    // toggle arah
    sortDirection[key] = !sortDirection[key];

    rows.sort((a, b) => {
        let A = getCellValue(a, colIndex);
        let B = getCellValue(b, colIndex);

        if (!isNaN(A) && !isNaN(B)) {
            return sortDirection[key] ? A - B : B - A;
        }

        let dateA = Date.parse(A);
        let dateB = Date.parse(B);
        if (!isNaN(dateA) && !isNaN(dateB)) {
            return sortDirection[key] ? dateA - dateB : dateB - dateA;
        }

        return sortDirection[key]
            ? A.localeCompare(B, 'id')
            : B.localeCompare(A, 'id');
    });

    // render ulang
    tbody.innerHTML = "";
    rows.forEach(row => tbody.appendChild(row));

    updateIcons(table, colIndex, key);
}

function getCellValue(row, index) {
    let cell = row.children[index];
    if (!cell) return "";

    let text = cell.innerText.trim();

    // bersihin angka
    return text.replace(/\./g, '').replace(/,/g, '.');
}

function updateIcons(table, activeCol, key) {
    const headers = table.querySelectorAll("th");

    headers.forEach((th, i) => {
        const icon = th.querySelector(".sort-icon");
        if (!icon) return;

        if (i === activeCol) {
            icon.textContent = sortDirection[key] ? "▲" : "▼";
        } else {
            icon.textContent = "▲▼";
        }
    });
}
</script>
  </body>
  </html>
