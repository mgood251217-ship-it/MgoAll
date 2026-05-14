<?php
require_once '../connect.php';
require_once 'dompdf/autoload.inc.php'; // arahkan sesuai lokasi dompdf

use Dompdf\Dompdf;
use Dompdf\Options;

require_once BASE_PATH . '/session.php';

$order_id = (int)($_GET['order_id'] ?? 0);

// Ambil info toko
$stmt = $koneksi->prepare("SELECT name, address, nomor, logo, branch FROM stores WHERE store_id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Ambil info order dan user_id
$stmt = $koneksi->prepare("SELECT customer_name, nomorator, deadline, total, date, user_id FROM orders WHERE order_id = ? AND store_id = ?");
$stmt->bind_param("ii", $order_id, $store_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die("Order tidak ditemukan");
}

// Ambil initial user dari tabel users
$stmt = $koneksi->prepare("SELECT initial FROM users WHERE user_id = ?");
$stmt->bind_param("i", $order['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$initial = strtoupper(str_replace(' ', '_', $user['initial'] ?? 'NO_INITIAL'));
$customer_name_clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '_', $order['customer_name'] ?? 'NO_NAME'));


// Ambil nama operator sesuai user_id (opsional, bisa pakai initial atau name)
$operator_name = $user['initial'] ?? '-';

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

// Pembayaran
$stmt = $koneksi->prepare("SELECT nominal, status, date FROM payment WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function formatTanggalIndo($tanggal) {
    $bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $dt = new DateTime($tanggal);
    return $dt->format('d') . ' ' . $bulan[(int)$dt->format('m') - 1] . ' ' . $dt->format('Y');
}

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
$qr_url = BASE_URL . "/customer/qrcode.php?order_id=$order_id";
$logo_url = BASE_URL . '/assets/img/store/' . $store['logo'];

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

ob_start();
?>

<style>
  @page {
    margin: 0;
    padding: 0;
  }
  body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12px;
    margin: 0;
    padding: 0;
    line-height: 1.3;
    
  }
  .center {
    text-align: center;
  }
  .line {
    border-top: 1px dashed #000;
    margin: 4px 0;
  }
  table {
    width: 100%;
    border-collapse: collapse;
  }
  td {
    padding: 2px 0;
    vertical-align: top;
  }
  .text-end {
    text-align: right;
  }
  .branch-script {
    font-family: 'Brush Script MT', cursive, Arial, sans-serif;
    font-size: 16px;
  }


</style>

<div class="center" style="display: flex; flex-direction: column; align-items: center;">
  <img src="<?= $logo_url ?>" alt="Logo" style="height:30px; margin-bottom: 2px;">
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

<table style="width:100%;">
  <tr>
    <td style="width:85%; vertical-align:top;">
      <table style="width:100%;">
        <tr><td>Tanggal</td><td>: <?= formatTanggalIndo($order['date']) ?></td></tr>
        <tr><td>Kepada Yth</td><td>: <?= htmlspecialchars($order['customer_name']) ?></td></tr>
        <tr><td>Nota No.</td><td>: <?= htmlspecialchars($order['nomorator']) ?></td></tr>
        <tr><td>Deadline</td><td>: <?= htmlspecialchars($order['deadline']) ?></td></tr>
        <tr><td>Operator</td><td>: <?= htmlspecialchars($operator_name) ?></td></tr>
      </table>
    </td>

    <!-- KOLOM STATUS -->
    <td style="width:7%; text-align:center; vertical-align:middle; right:0; padding:0;">
      <div style="
        transform: rotate(270deg);
        font-weight: bold;
        font-size: 15px;
        white-space: nowrap;
      ">
        <?= $ada_lunas ? 'LUNAS' : ' ' ?>
      </div>
    </td>
  </tr>
</table>


<div class="line"></div>

<table class="table" style="width: 100%; font-size: 11px; margin-top: 10px;"> 
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
      <td><?= htmlspecialchars($judul_bahan) ?></td>
      <td><?= htmlspecialchars($item['size']) ?></td>
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
    <?php
    if ($noted !== NULL) {
    ?>
    <div style="font-style: italic; widtf: 100%;">Catatan : <?= $noted['note']; ?></div>
    <?php } ?>
<div class="line"></div>

<table>
  <tr><td class="text-end"><strong>TOTAL: Rp <?= number_format($order['total'], 0, ',', '.') ?></strong></td></tr>
  <?php if ($total_bayar > 0 && !$ada_lunas): ?>
  <tr><td class="text-end"><?= $ada_dp ? 'Uang Muka' : 'Total Bayar' ?>: Rp <?= number_format($total_bayar, 0, ',', '.') ?></td></tr>
  <tr><td class="text-end">Sisa: Rp <?= number_format($sisa, 0, ',', '.') ?></td></tr>
  <?php endif; ?>
</table>

<div class="line"></div>

<table style="width: 100%; margin-top: 10px;">
  <tr>
    <!-- <td style="width: 90px; vertical-align: top;">
      <img src="<?= $qr_url ?>" alt="QR Code" style="height: 90px;"><br>
      <div style="font-size: 8px; text-align: center;">Check & Track</div>
    </td> -->
    <td style="font-size: 9px; line-height: 1.4; padding-left: 10px;">
      - Periksalah kembali barang pesanan anda saat pengambilan, kami tidak menerima komplen setelah barang diambil<br>
      - Apabila pesanan di atas tidak diambil setelah satu bulan, kami tidak bertanggung jawab atas kehilangan/kerusakan barang tersebut<br>
      Terima kasih
    </td>
  </tr>
</table>


<table style="width:100%; text-align:center; font-size:11px; margin-top:10px;">
  <tr><td>Hormat Kami</td><td>TTD Pemesan</td></tr>
  <tr>
    <td style="height:80px;">
      <div style="border-top: 1px solid #000; width: 80%; margin: 35px auto 0;"></div>
    </td>
    <td style="height:80px;">
      <div style="border-top: 1px solid #000; width: 80%; margin: 35px auto 0;"></div>
    </td>
  </tr>
</table>

<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$dompdf->setPaper([0, 0, 226.77, 999], 'portrait'); // 75mm * 2.835pt/mm lebar, tinggi auto
$dompdf->loadHtml($html);
$dompdf->render();
$tanggal = formatTanggalIndo($order['date']);
$nomorator = htmlspecialchars($order['nomorator']);
$filename = "{$customer_name_clean}_{$initial}_{$tanggal}_{$nomorator}.pdf";
$attachment = ($preview_print == 0) ? true : false;
$dompdf->stream($filename, ['Attachment' => $attachment]);

exit;
