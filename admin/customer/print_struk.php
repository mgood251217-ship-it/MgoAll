<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

$order_id = (int)($_GET['order_id'] ?? 0);
$store_id = (int)($_GET['store_id'] ?? 0);

// Ambil info toko
$stmt = $koneksi->prepare("SELECT name, address, nomor, logo, logo_print, branch FROM stores WHERE store_id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Ambil nama operator
$stmt = $koneksi->prepare("
  SELECT u.name 
  FROM users u 
  JOIN orders o ON o.user_id = u.user_id 
  WHERE o.order_id = ? AND o.store_id = ?
");
$stmt->bind_param("ii", $order_id, $store_id);
$stmt->execute();
$operator = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Ambil info order
$stmt = $koneksi->prepare("SELECT customer_name, nomorator, deadline, total, date FROM orders WHERE order_id = ? AND store_id = ?");
$stmt->bind_param("ii", $order_id, $store_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

function formatTanggalIndo($tanggal) {
    $bulan = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    $dt = new DateTime($tanggal);
    $tgl = $dt->format('d');
    $bln = (int)$dt->format('m');
    $thn = $dt->format('Y');

    return $tgl . ' ' . $bulan[$bln - 1] . ' ' . $thn;
}

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

// Kelompokkan OUTDOOR
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

// Simpan produk OUTDOOR yang total luas < 1
$luas_kurang_dari_satu = [];
foreach ($grouped_outdoor as $product_id => $group) {
    if ($group['total_luas'] < 1) {
        $luas_kurang_dari_satu[$product_id] = $group['min_price_after_diskon'];
    }
}

// Inisialisasi array penanda harga telah dicetak
$printed_price_for = [];

// Ambil semua pembayaran
$stmt = $koneksi->prepare("SELECT nominal, status, date FROM payment WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$uang_muka = 0;
$total_bayar = 0;
$ada_dp = false;
$ada_lunas = false;
$tanggal_lunas = null;

foreach ($payments as $pay) {
    $nominal = (int)$pay['nominal'];
    $status = strtoupper($pay['status']);
    $total_bayar += $nominal;
    if ($status === 'DP') {
        $uang_muka += $nominal;
        $ada_dp = true;
    } elseif ($status === 'LUNAS') {
        $ada_lunas = true;
        if (!$tanggal_lunas || strtotime($pay['date']) > strtotime($tanggal_lunas)) {
            $tanggal_lunas = $pay['date'];
        }
    }
}

$sisa = $order['total'] - $total_bayar;

  $stmt = $koneksi->prepare("SELECT note FROM note_orders WHERE order_id = ? AND note_for = 'CTM' ORDER BY note_order_id DESC LIMIT 1");
  $stmt->bind_param("i", $order_id);
  $stmt->execute();
  $note = $stmt->get_result();
  $noted = $note->fetch_assoc();

$preview_print = 0; // default

if ($user_id) {
    // Prepare statement untuk menghindari SQL Injection
    $stmt = $koneksi->prepare("SELECT preview_print FROM user_setting WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($pp);
    if ($stmt->fetch()) {
        $preview_print = (int)$pp;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Cetak Struk</title>
  <style>
    /* Reset & Box Sizing agar padding tidak menambah lebar elemen */
    * {
      box-sizing: border-box;
    }
    @media print {
      @page {
        margin: 0;
      }
      body {
        margin: 0;
        padding: 2mm 0; /* Jarak aman atas bawah */
      }
    }
    body {
      font-family: Calibri, sans-serif;
      font-size: 12px;
      margin: 0 auto;
      width: 100%; 
      max-width: 100%;
      line-height: 1.3;
    }
    .center {
      text-align: center;
    }
    .line {
      border-top: 1px dashed #000;
      margin: 4px auto;
      width: 92%; /* Mengikuti lebar tabel */
    }
    table {
      width: 92%; /* Membuat tabel tidak full 100% agar ada jarak aman di kiri & kanan */
      margin: 0 auto; /* Otomatis menempatkan tabel di tengah halaman */
      border-collapse: collapse;
      table-layout: fixed; 
      word-wrap: break-word; 
    }
    td {
      padding: 2px 0;
      vertical-align: top;
    }
    .text-end {
      text-align: right;
      padding-right: 6px; /* Memberikan bantalan ekstra khusus untuk sisi kanan nominal */
    }
    .lunas-stamp {
      position: absolute;
      top: 200px;
      left: 0;
      font-weight: bold;
      z-index: 999;
      color: rgba(0, 0, 0, 0.2);
    }
    .lunas-text {
      font-size: 32px;
      text-align: center;
    }
    .lunas-date {
      font-size: 12px;
      text-align: center;
    }
  .branch-script {
    font-family: 'Brush Script MT', cursive;
    font-size: 16px;
  }
  </style>
</head>
<body onload="window.print(); window.onafterprint = () => window.close();">

<div class="center" style="display: flex; flex-direction: column; align-items: center; width: 92%; margin: 0 auto;">
  <?php
  if ($store["logo_print"] == '') {?>
    <img src="<?= BASE_URL . '/assets/img/store/' . htmlspecialchars($store['logo']); ?>" alt="Logo" style="max-height:30px; margin-bottom: 2px; max-width: 70px">
  <?php }else { ?>
    <img src="<?= BASE_URL . '/assets/img/store/' . htmlspecialchars($store['logo_print']); ?>" alt="Logo" style="max-height:30px; margin-bottom: 2px; max-width: 70px">
  <?php }
  ?>
  
  <div class="branch-script"><?= ucwords(strtolower(htmlspecialchars($store['branch']))) ?></div>
</div>

<table class="center">
  <?php 
   if ($store_id == 8) { ?>
    <tr><td style="font-size:10px;">Print Sublim | Jersey | DTF | Spanduk | Stiker</td></tr>
    <?php
   }else{ ?>
    <tr><td style="font-size:10px;">Spanduk | Banner Kain | Baligho | Stiker One Way | Stiker Outdoor | Backlite | X-Banner | Roll Banner | ID Card | dll</td></tr>
   <?php } ?>
  <tr><td style="font-size:10px;"><?= nl2br(htmlspecialchars($store['address'])) ?></td></tr>
  <tr><td style="font-size:12px;">Telp: <?= htmlspecialchars($store['nomor']) ?></td></tr>
</table>

<div class="line"></div>

<table>
  <colgroup>
    <col style="width: 25%;">
    <col style="width: 75%;">
  </colgroup>
  <tr><td>Tanggal</td><td>: <?= formatTanggalIndo($order['date']) ?></td></tr>
  <tr><td>Kepada Yth</td><td>: <?= htmlspecialchars($order['customer_name']) ?></td></tr>
  <tr><td>Nota No.</td><td>: <?= htmlspecialchars($order['nomorator']) ?></td></tr>
  <tr><td>Deadline</td><td>: <?= htmlspecialchars($order['deadline']) ?></td></tr>
  <tr><td>Operator</td><td>: <?= htmlspecialchars($operator['name'] ?? '-') ?></td></tr>
</table>

<div class="line"></div>

<table class="table" style="font-size: 11px; margin-top: 10px; table-layout: fixed;"> 
  <colgroup>
    <col style="width: 45%;">
    <col style="width: 20%;">
    <col style="width: 35%;">
  </colgroup>
  <?php foreach ($order_items_raw as $item): 
    $product_id = $item['product_id'] ?? null;
    $type = strtoupper($item['type'] ?? '');

    $hide_price = false;
    $custom_price = null;

    if ($type === 'OUTDOOR' && $product_id && isset($luas_kurang_dari_satu[$product_id])) {
        if (in_array($product_id, $printed_price_for)) {
            $hide_price = true;
        } else {
            $custom_price = $luas_kurang_dari_satu[$product_id];
            $printed_price_for[] = $product_id;
        }
    }

    // Gunakan order_items.judul sebagai prioritas, fallback ke judul_bahan dari produk
    $judul_bahan = $item['judul'] ?: ($item['judul_bahan'] ?? '-');
  ?>
    <tr style="border-top: 0.5px solid #000;">
      <td style="padding-right: 2px;"><?= htmlspecialchars($judul_bahan) ?></td>
      <td style="padding-right: 2px;"><?= htmlspecialchars($item['size']) ?></td>
      <td class="text-end">
        <?php if ($hide_price): ?>
          
        <?php else: ?>
          Rp <?= number_format($custom_price ?? $item['amount'], 0, ',', '.') ?>
        <?php endif; ?>
      </td>
    </tr>
    <tr style="border-bottom: 0.5px solid #000;">
      <td colspan="3">
        <?= ($item['finishing_names'] !== ' ') ? htmlspecialchars($item['finishing_names']) . ' | ' : '' ?>
        <?= $item['quantity'] ?> x 
        <?php if ($hide_price): ?>
          
        <?php else: ?>
          Rp <?= number_format((($custom_price ?? $item['amount']) / $item['quantity']), 0, ',', '.') ?>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<?php if ($noted !== NULL): ?>
<div style="display: flex; font-style: italic; width: 92%; margin: 5px auto 0; flex-wrap: wrap;">
    <div style="margin-right: 5px;">Catatan:</div>
    <div style="flex: 1; word-break: break-word;">
        <?= $noted['note']; ?>
    </div>
</div>
<?php endif; ?>

<div class="line"></div>

<div style="position: relative;">
  <?php if ($ada_lunas): ?>
  <?php endif; ?>
  <table style="table-layout: fixed;">
    <tr><td class="text-end"><strong>TOTAL: Rp <?= number_format($order['total'], 0, ',', '.') ?></strong></td></tr>
    <?php if ($total_bayar > 0 && !$ada_lunas): ?>
    <tr><td class="text-end"><?= $ada_dp ? 'Uang Muka' : 'Total Bayar' ?>: Rp <?= number_format($total_bayar, 0, ',', '.') ?></td></tr>
    <tr><td class="text-end">Sisa: Rp <?= number_format($sisa, 0, ',', '.') ?></td></tr>
    <?php endif; ?>
  </table>
</div>

<div class="line"></div>

<div style="display: flex; align-items: flex-start; margin: 10px auto 0; width: 92%;">
  <div style="font-size: 9px; line-height: 1.4; width: 100%;">
    - Periksalah kembali barang pesanan anda saat pengambilan, kami tidak menerima komplen setelah barang diambil<br>
    - Apabila pesanan di atas tidak diambil setelah satu bulan, kami tidak bertanggung jawab atas kehilangan/kerusakan barang tersebut<br>
    Terima kasih<br>
  </div>
</div>

<table style="text-align: center; font-size: 11px; margin-top: 10px; table-layout: fixed;">
  <tr><td>Hormat Kami</td><td>TTD Pemesan</td></tr>
  <tr><td style="height: 80px;"><div style="border-top: 1px solid #000; width: 80%; margin: 35px auto 0;"></div></td><td style="height: 80px;"><div style="border-top: 1px solid #000; width: 80%; margin: 35px auto 0;"></div></td></tr>
</table>
</body>
</html>