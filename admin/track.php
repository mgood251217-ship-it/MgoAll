<?php
require_once 'connect.php';
require_once 'global_functions.php';
$order_id = $_GET['id'] ?? 0;
date_default_timezone_set('Asia/Jakarta');

$stmt = $koneksi->prepare(" 
    SELECT o.*, u.initial AS operator_initial 
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    WHERE o.order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

$store_id = $order['store_id'];
$stmt = $koneksi->prepare("SELECT name, address FROM stores WHERE store_id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$stmt->bind_result($store_name, $store_address);
$stmt->fetch();
$stmt->close();

// Ambil semua order_items + join produk dan diskon
$stmt = $koneksi->prepare("
    SELECT 
        oi.*,
        p.name AS judul_bahan,
        p.type,
        p.price,
        doi.diskon
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.product_id
    LEFT JOIN diskon_order_items doi ON doi.order_id = oi.order_id AND doi.product_id = oi.product_id
    WHERE oi.order_id = ? AND oi.store_id = ?
");
$stmt->bind_param("ii", $order_id, $store_id);
$stmt->execute();
$result = $stmt->get_result();
$order_items_raw = [];
while ($row = $result->fetch_assoc()) {
    // Tangani multiple finishing
    $finishing_ids = array_filter(explode(',', $row['finishing']));
    $finishing_names = [];

    if (count($finishing_ids)) {
        $placeholders = implode(',', array_fill(0, count($finishing_ids), '?'));
        $types = str_repeat('i', count($finishing_ids));
        $stmt2 = $koneksi->prepare("SELECT name FROM products WHERE product_id IN ($placeholders)");
        $stmt2->bind_param($types, ...$finishing_ids);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($f = $res2->fetch_assoc()) {
            $finishing_names[] = $f['name'];
        }
        $stmt2->close();
    }

    $row['finishing_names'] = implode(' ', $finishing_names) ?: '-';
    $order_items_raw[] = $row;
}
$stmt->close();

// Kelompokkan produk OUTDOOR
$grouped_outdoor = [];
foreach ($order_items_raw as $item) {
    $type = strtoupper($item['type'] ?? '');
    $product_id = $item['product_id'] ?? null;

    if ($type === 'OUTDOOR' && $product_id) {
        if (!isset($grouped_outdoor[$product_id])) {
            $grouped_outdoor[$product_id] = [
                'items' => [],
                'total_luas' => 0,
                'min_price_after_diskon' => null,
            ];
        }

        // Hitung luas
        if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $item['size'], $m)) {
            $luas = floatval($m[1]) * floatval($m[2]);
        } else {
            $luas = 0;
        }

        $grouped_outdoor[$product_id]['items'][] = $item;
        $grouped_outdoor[$product_id]['total_luas'] += $luas * $item['quantity'];

        // Hitung harga - diskon
        $price = isset($item['price']) ? (int)$item['price'] : 0;
        $diskon = isset($item['diskon']) ? (int)$item['diskon'] : 0;
        $price_after_diskon = max($price - $diskon, 0);

        if (
            $grouped_outdoor[$product_id]['min_price_after_diskon'] === null ||
            $price_after_diskon < $grouped_outdoor[$product_id]['min_price_after_diskon']
        ) {
            $grouped_outdoor[$product_id]['min_price_after_diskon'] = $price_after_diskon;
        }
    }
}

// Produk OUTDOOR dengan total luas < 1
$luas_kurang_dari_satu = [];
foreach ($grouped_outdoor as $product_id => $group) {
    if ($group['total_luas'] < 1) {
        $luas_kurang_dari_satu[$product_id] = $group['min_price_after_diskon'];
    }
}

// Inisialisasi array untuk menandai produk OUTDOOR mana yang harga sudah dicetak
$printed_price_for = [];



// Ambil status pesanan dari projects.process terbaru
$stmt = $koneksi->prepare("SELECT process FROM projects WHERE order_id = ? ORDER BY date DESC LIMIT 1");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$stmt->bind_result($status_pesanan);
$stmt->fetch();
$stmt->close();

$status_raw = strtoupper(trim($status_pesanan ?? 'BELUM DIPROSES'));
switch ($status_raw) {
    case 'DIPROSES':
        $status_label = 'Diproses';
        $boxColor = '#fff9e6';
        $badgeClass = 'warning';
        break;
    case 'SELESAI':
        $status_label = 'Selesai';
        $boxColor = '#e6ffee';
        $badgeClass = 'success';
        break;
    case 'DIAMBIL':
        $status_label = 'Diambil';
        $boxColor = '#e6f0ff';
        $badgeClass = 'primary';
        break;
    default:
        $status_label = $status_raw;
        $boxColor = '#f7f7f7';
        $badgeClass = 'secondary';
        break;
}

function formatTanggal($date) {
    $bulan = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
        '04' => 'April', '05' => 'Mei', '06' => 'Juni',
        '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
        '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];
    $tgl = date('d', strtotime($date));
    $bln = $bulan[date('m', strtotime($date))];
    $thn = date('Y', strtotime($date));
    return "$tgl $bln $thn";
}

$stmt = $koneksi->prepare("SELECT payment_method, status, date, nominal FROM payment WHERE order_id = ? ORDER BY date ASC");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $koneksi->prepare("SELECT process, user_id, date FROM projects WHERE order_id = ? ORDER BY date ASC");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$rating_message = '';
$existing_rating = null;

$cekRating = $koneksi->prepare("SELECT value, review FROM ratings WHERE order_id = ?");
$cekRating->bind_param("i", $order_id);
$cekRating->execute();
$cekRating->bind_result($existing_value, $existing_review);
$hasRating = $cekRating->fetch();
$cekRating->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasRating && isset($_POST['rating'], $_POST['review'])) {
    $rating_value = (int) $_POST['rating'];
    $review_text = trim($_POST['review']);
    $date_now = date('Y-m-d H:i:s');

    $insert = $koneksi->prepare("INSERT INTO ratings (order_id, store_id, value, review, date) VALUES (?, ?, ?, ?, ?)");
    $insert->bind_param("iisss", $order_id, $store_id, $rating_value, $review_text, $date_now);
    if ($insert->execute()) {
        $rating_message = 'Terima kasih atas rating dan ulasan Anda!';
        $existing_value = $rating_value;
        $existing_review = $review_text;
        $hasRating = true;
    } else {
        $rating_message = 'Terjadi kesalahan saat menyimpan rating.';
    }
    $insert->close();
}

  $stmt = $koneksi->prepare("SELECT note FROM note_orders WHERE order_id = ? ORDER BY note_order_id DESC LIMIT 1");
  $stmt->bind_param("i", $order_id);
  $stmt->execute();
  $note = $stmt->get_result();
  $noted = $note->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Tracking Order #<?= htmlspecialchars($order_id) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    td, th { white-space: nowrap; vertical-align: middle; }
    .badge-status { font-size: 0.85rem; padding: 0.4em 0.6em; }
    .table th { background-color: #f8f9fa; }
    .section-title { font-size: 18px; font-weight: bold; margin-bottom: 1rem; }
    .info-box { border: 1px solid #dee2e6; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; }
    .rating {
      display: flex;
      flex-direction: row-reverse;
      justify-content: center;
      gap: 0.4rem;
    }
    .rating input[type="radio"] {
      display: none;
    }
    .rating label i {
      font-size: 2rem;
      color: #ddd;
      cursor: pointer;
      transition: color 0.2s ease;
    }
    .rating input[type="radio"]:checked ~ label i,
    .rating label:hover i,
    .rating label:hover ~ label i {
      color: #ffc107;
    }

  </style>
</head>
<body class="bg-light">

<div class="container py-4">
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="text-center mb-3">
        <h4 class="mb-0"><?= htmlspecialchars($store_name) ?></h4>
        <small class="text-muted"><?= htmlspecialchars($store_address) ?></small>
      </div>

      <h5 class="section-title">📦 Tracking Order</h5>

      <div class="info-box" style="background-color: <?= $boxColor ?>;">
        <div class="row mb-2">
          <div class="col-md-4"><strong>Nomorator:</strong> <?= htmlspecialchars($order['nomorator']) ?></div>
          <div class="col-md-4"><strong>Nama:</strong> <?= htmlspecialchars($order['customer_name']) ?></div>
          <div class="col-md-4"><strong>Tanggal Order:</strong> <?= formatTanggal($order['date']) ?></div>
        </div>
        <div class="row">
          <div class="col-md-4"><strong>Deadline:</strong> <?= $order['deadline'] ?></div>
          <div class="col-md-4"><strong>Operator:</strong> <?= htmlspecialchars($order['operator_initial']) ?></div>
          <div class="col-md-4">
            <strong>Status:</strong> 
            <span class="badge bg-<?= $badgeClass ?> badge-status"><?= $status_label ?></span>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-bordered table-sm" style="width: 100%; font-size: 11px; margin-top: 10px;">
          <thead class="table-primary">
            <tr>
              <th><strong>Bahan</strong></th>
              <th><strong>Ukuran</strong></th>
              <th class="text-end"><strong>Jumlah</strong></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($order_items_raw as $item): 
              $product_id = $item['product_id'] ?? null;
              $type = strtoupper($item['type'] ?? '');
              
              $hide_price = false;
              $custom_price = null;

              // Jika OUTDOOR dan total luas < 1, hanya tampilkan harga sekali dengan harga min setelah diskon
              if ($type === 'OUTDOOR' && $product_id && isset($luas_kurang_dari_satu[$product_id])) {
                  if (in_array($product_id, $printed_price_for)) {
                      $hide_price = true;
                  } else {
                      $custom_price = $luas_kurang_dari_satu[$product_id];
                      $printed_price_for[] = $product_id;
                  }
              }
          ?>
            <tr style="border-top: 0.5px solid #000;">
              <td><?= htmlspecialchars($item['judul_bahan'] ?: $item['judul']) ?></td>
              <td><?= htmlspecialchars($item['size']) ?></td>
              <td class="text-end">
                <?php if ($hide_price): ?>
                  <!-- kosongkan harga -->
                <?php else: ?>
                  Rp <?= number_format($custom_price ?? $item['amount'], 0, ',', '.') ?>
                <?php endif; ?>
              </td>
            </tr>
            <tr style="border-bottom: 0.5px solid #000;" class="text-muted small">
              <td colspan="3">
                <?= ($item['finishing_names'] !== '-' && $item['finishing_names'] !== '') ? htmlspecialchars($item['finishing_names']) . ' | ' : '' ?>
                <?= $item['quantity'] ?> x 
                <?php if ($hide_price): ?>
                  <!-- kosongkan harga -->
                <?php else: ?>
                  Rp <?= number_format((($custom_price ?? $item['amount']) / max(1, $item['quantity'])), 0, ',', '.') ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php
    if ($noted !== NULL) {
    ?>
    <div style="font-style: italic; widtf: 100%;">Catatan : <?= $noted['note']; ?></div>
    <?php } ?>
      <?php if (count($payments) > 0): ?>
      <h6 class="mt-4 mb-3">💰 Riwayat Pembayaran</h6>
      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Tanggal</th>
              <th>Metode</th>
              <th>Status</th>
              <th class="text-end">Nominal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $pay): ?>
            <tr>
              <td><?= formatTanggal($pay['date']) ?></td>
              <td><?= htmlspecialchars($pay['payment_method']) ?></td>
              <td><span class="badge bg-<?= strtoupper($pay['status']) === 'LUNAS' ? 'success' : 'secondary' ?>">
                <?= strtoupper($pay['status']) ?></span>
              </td>
              <td class="text-end">Rp <?= number_format($pay['nominal'], 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <?php if (!empty($projects)): ?>
      <h6 class="mt-4 mb-3">🔄 Riwayat Proses Produksi</h6>
      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Tanggal</th>
              <th>Jam</th>
              <th>Operator</th>
              <th>Keterangan</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($projects as $log): ?>
            <tr>
              <td><?= date('d-m-Y', strtotime($log['date'])) ?></td>
              <td><?= date('H:i', strtotime($log['date'])) ?></td>
              <?php
              $stmt = $koneksi->prepare("SELECT initial FROM users WHERE user_id = ?");
              $stmt->bind_param("i", $log['user_id']);
              $stmt->execute();
              $stmt->bind_result($initial);
              $stmt->fetch();
              $stmt->close();
              ?>
              <td><?= htmlspecialchars($initial) ?></td>
              <td><?= htmlspecialchars($log['process']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
      <hr class="my-4">
      <h6 class="mb-3 text-center">⭐ Berikan Rating untuk Layanan Kami</h6>

      <?php if ($hasRating): ?>
        <div class="text-center mb-3">
          <div class="rating">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <i class="bi bi-star-fill" style="font-size: 2rem; color: <?= ($i <= $existing_value) ? '#ffc107' : '#ddd' ?>;"></i>
            <?php endfor; ?>
          </div>
          <p class="mt-3"><em>"<?= htmlspecialchars($existing_review) ?>"</em></p>
        </div>
      <?php else: ?>
        <?php if (!empty($rating_message)): ?>
          <div class="alert alert-info text-center"><?= htmlspecialchars($rating_message) ?></div>
        <?php endif; ?>
        <form method="POST">
          <div class="mb-4 text-center">
            <div class="rating">
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" required />
                <label for="star<?= $i ?>" title="<?= $i ?> bintang">
                  <i class="bi bi-star-fill"></i>
                </label>
              <?php endfor; ?>
            </div>
          </div>
          <div class="mb-3">
            <textarea name="review" class="form-control" rows="3" placeholder="Tulis ulasan Anda di sini..." required></textarea>
          </div>
          <div class="text-center">
            <button type="submit" class="btn btn-primary btn-sm">Kirim Rating & Ulasan</button>
          </div>
        </form>
      <?php endif; ?>




    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>