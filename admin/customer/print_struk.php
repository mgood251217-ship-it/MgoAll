<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/global_functions.php';
require_once BASE_PATH . '/models/User.php';
require_once BASE_PATH . '/models/Store.php';
require_once BASE_PATH . '/models/Order.php';
require_once BASE_PATH . '/models/Payment.php';
require_once BASE_PATH . '/functions/helpers.php';

$storeModel   = new Store($koneksi);
$orderModel   = new Order($koneksi);
$paymentModel = new Payment($koneksi);
$userModel    = new User($koneksi);

$order_id = $_GET['order_id'] ?? 0;
$store    = $storeModel->getStoreById($store_id);
$order    = $orderModel->getOrderById($order_id);
$operator = $userModel->getOneValue($order['user_id'], 'name');
$order_items_raw = $orderModel->getOrderItemsWithDetails($order_id);

$outdoor = [];
$luas_kurang_dari_satu = [];
foreach ($order_items_raw as $item) {
    if (strtoupper($item['category'] ?? '') === 'OUTDOOR' && $item['product_id']) {
        $pid = $item['product_id'];
        $luas = preg_match('/^([\d.]+)[xX]([\d.]+)$/', $item['size'], $m) ? ($m[1] * $m[2]) : 0;
        $pad = max(($item['price'] ?? 0) - ($item['diskon'] ?? 0), 0);
        
        $outdoor[$pid]['luas'] = ($outdoor[$pid]['luas'] ?? 0) + ($luas * $item['quantity']);
        $outdoor[$pid]['min_pad'] = min($outdoor[$pid]['min_pad'] ?? $pad, $pad);
    }
}
foreach ($outdoor as $pid => $grp) {
    if ($grp['luas'] < 1) $luas_kurang_dari_satu[$pid] = $grp['min_pad'];
}

$total_bayar = 0; $ada_dp = false; $ada_lunas = false;
foreach ($paymentModel->getPaymentByOrderId($order_id) as $pay) {
    $total_bayar += $pay['nominal'];
    if (strtoupper($pay['status']) === 'DP') $ada_dp = true;
    if (strtoupper($pay['status']) === 'LUNAS') $ada_lunas = true;
}

$noted = $orderModel->getNoteOrder((object)['order_id' => $order_id, 'note_for' => 'CTM']);
$printed_price_for = [];
?>
<!DOCcategory html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Cetak Struk</title>
  <style>
    * { box-sizing: border-box; }
    @media print { @page { margin: 0; } body { padding: 2mm 0; } }
    body { font-family: Calibri, sans-serif; font-size: 12px; margin: 0 auto; width: 100%; line-height: 1.3; }
    .center { text-align: center; }
    .line { border-top: 1px dashed #000; margin: 4px auto; width: 92%; }
    table { width: 92%; margin: 0 auto; border-collapse: collapse; table-layout: fixed; word-wrap: break-word; }
    td { padding: 2px 0; vertical-align: top; }
    .text-end { text-align: right; padding-right: 6px; }
  </style>
</head>
<body onload="window.print(); window.onafterprint = () => window.close();">

<div class="center" style="display: flex; flex-direction: column; align-items: center; width: 92%; margin: 0 auto;">
  <img src="<?= BASE_URL . '/assets/img/store/' . sanitize($store["logo_print"] ?: $store['logo']) ?>" style="max-height:30px; margin-bottom: 2px; max-width: 70px">
  <div style="font-family: 'Brush Script MT', cursive; font-size: 16px;"><?= ucwords(strtolower(sanitize($store['branch']))) ?></div>
</div>

<table class="center" style="font-size:10px;">
  <tr><td><?= ($store_id == 8 || $store_id == 25) ? "Print Sublim | Jersey | DTF | Spanduk | Stiker" : "Spanduk | Banner Kain | Baligho | Stiker One Way | Stiker Outdoor | Backlite | X-Banner | Roll Banner | ID Card | dll" ?></td></tr>
  <tr><td><?= nl2br(sanitize($store['address'])) ?></td></tr>
  <tr><td style="font-size:12px;">Telp: <?= sanitize($store['nomor']) ?></td></tr>
</table>

<div class="line"></div>
<table style="width:100%; table-layout: fixed;">
  <tr>
    <td style="width:80%; vertical-align:top;">
      <table style="width: 90%; margin: 0 auto; font-size: 13px">
        <tr><td width="25%">Tanggal</td><td>: <?= format_tanggal_id($order['date']) ?></td></tr>
        <tr><td>Kepada Yth</td><td>: <?= sanitize($order['customer_name']) ?></td></tr>
        <tr><td>Nota No.</td><td>: <?= sanitize($order['nomorator']) ?></td></tr>
        <tr><td>Deadline</td><td>: <?= format_tanggal_id($order['deadline']) . ' ' . date('H.i', strtotime($order['deadline']))  ?></td></tr>
        <tr><td>Operator</td><td>: <?= sanitize($operator ?? '-') ?></td></tr>
      </table>
    </td>
    <td style="width:20%; text-align:center; vertical-align:middle; padding:0;">
      <div style="transform: rotate(270deg); font-weight: bold; line-height: 90%; font-size: 15px; white-space: nowrap;">
        <?= $ada_lunas ? 'LUNAS' : 'BELUM <br> LUNAS' ?>
      </div>
    </td>
  </tr>
</table>


<div class="line"></div>

<table style="font-size: 11px; margin-top: 10px;"> 
  <?php foreach ($order_items_raw as $item): 
    $pid = $item['product_id'] ?? null;
    $hide_price = false; $custom_price = null;

    if (strtoupper($item['category'] ?? '') === 'OUTDOOR' && isset($luas_kurang_dari_satu[$pid])) {
        if (in_array($pid, $printed_price_for)) $hide_price = true;
        else { $custom_price = $luas_kurang_dari_satu[$pid]; $printed_price_for[] = $pid; }
    }
    $harga = $custom_price ?? $item['amount'];
  ?>
    <tr style="border-top: 0.5px solid #000;">
      <td width="45%"><?= sanitize($item['judul'] ?: ($item['product_name'] ?? '-')) ?></td>
      <td width="20%"><?= sanitize($item['size']) ?></td>
      <td width="35%" class="text-end"><?= $hide_price ? '' : format_rupiah($harga) ?></td>
    </tr>
    <tr style="border-bottom: 0.5px solid #000;">
      <td colspan="3">
        <?= ($item['finishing_names'] !== ' ') ? sanitize($item['finishing_names']) . ' | ' : '' ?>
        <?= $item['quantity'] ?> x <?= $hide_price ? '' : format_rupiah($harga / $item['quantity']) ?>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<?php if ($noted): ?>
<div style="display: flex; font-style: italic; width: 92%; margin: 5px auto 0;">
    <div style="margin-right: 5px;">Catatan:</div>
    <div style="flex: 1; word-break: break-word;"><?= sanitize($noted['note']); ?></div>
</div>
<?php endif; ?>

<div class="line"></div>

<table>
  <tr><td class="text-end"><strong>TOTAL: <?= format_rupiah($order['total']) ?></strong></td></tr>
  <?php if ($total_bayar > 0 && !$ada_lunas): ?>
  <tr><td class="text-end"><?= $ada_dp ? 'Uang Muka' : 'Total Bayar' ?>: <?= format_rupiah($total_bayar) ?></td></tr>
  <tr><td class="text-end">Sisa: <?= format_rupiah($order['total'] - $total_bayar) ?></td></tr>
  <?php endif; ?>
</table>

<div class="line"></div>

<div style="font-size: 9px; margin: 10px auto 0; width: 92%; line-height: 1.4;">
  - Periksalah kembali barang pesanan anda saat pengambilan, kami tidak menerima komplen setelah barang diambil<br>
  - Apabila pesanan di atas tidak diambil setelah satu bulan, kami tidak bertanggung jawab atas kehilangan/kerusakan barang tersebut<br>
  Terima kasih
</div>

<table style="text-align: center; font-size: 11px; margin-top: 10px;">
  <tr><td>Hormat Kami</td><td>TTD Pemesan</td></tr>
  <tr>
    <td style="height: 80px;"><div style="border-top: 1px solid #000; width: 80%; margin: 35px auto 0;"></div></td>
    <td style="height: 80px;"><div style="border-top: 1px solid #000; width: 80%; margin: 35px auto 0;"></div></td>
  </tr>
</table>

</body>
</html>