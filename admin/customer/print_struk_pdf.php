<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/global_functions.php';
require_once BASE_PATH . '/models/User.php';
require_once BASE_PATH . '/models/Store.php';
require_once BASE_PATH . '/models/Order.php';
require_once BASE_PATH . '/models/Payment.php';
require_once BASE_PATH . '/models/Setting.php';

$storeModel   = new Store($koneksi);
$orderModel   = new Order($koneksi);
$paymentModel = new Payment($koneksi);
$userModel    = new User($koneksi);
$settingModel = new Setting($koneksi);

$order_id = $_GET['order_id'] ?? 0;
$store    = $storeModel->getStoreById($store_id);
$order    = $orderModel->getOrderById($order_id);
$order_items_raw = $orderModel->getOrderItemsWithDetails($order_id);

if (!$order) {
    die("Order tidak ditemukan");
}

$operator_name = $userModel->getOneValue($order['user_id'], 'name');
$initial_user  = $userModel->getOneValue($order['user_id'], 'initial');
$initial       = strtoupper(str_replace(' ', '_', $initial_user ?? 'NO_INITIAL'));
$customer_name_clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '_', $order['customer_name'] ?? 'NO_NAME'));

$outdoor = [];
$luas_kurang_dari_satu = [];
foreach ($order_items_raw as $item) {
    if (strtoupper($item['type'] ?? '') === 'OUTDOOR' && $item['product_id']) {
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
$sisa = $order['total'] - $total_bayar;

$noted = $orderModel->getNoteOrder((object)['order_id' => $order_id, 'note_for' => 'CTM']);
$printed_price_for = [];

$preview_print = $settingModel->getOneValue($user_id, 'preview_print') ?? 0;

$tanggal = formatTanggalIndo($order['date']);
$nomorator = htmlspecialchars($order['nomorator']);
$filename = "{$customer_name_clean}_{$initial}_{$tanggal}_{$nomorator}.pdf";
$logo_url = BASE_URL . '/assets/img/store/' . ($store["logo_print"] ?: $store['logo']);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($filename) ?></title>
    <style>
      body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; margin: 0; padding: 0; line-height: 1.3; background-color: #f5f5f5; }
      .action-container { text-align: center; padding: 15px; background-color: #e0e0e0; }
      .btn-download { padding: 8px 16px; font-size: 14px; font-weight: bold; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
      .btn-download:hover { background-color: #218838; }
      #nota-printable { width: 302px; background: #fff; margin: 15px auto; padding: 8px; box-sizing: border-box; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
      .center { text-align: center; }
      .line { border-top: 1px dashed #000; margin: 6px 0; }
      table { width: 100%; border-collapse: collapse; }
      td { padding: 2px 0; vertical-align: top; }
      .text-end { text-align: right; }
      .branch-script { font-family: 'Brush Script MT', cursive, Arial, sans-serif; font-size: 16px; }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>

<?php if ($preview_print === 1): ?>
<div class="action-container">
    <button id="downloadBtn" class="btn-download">Download / Cetak PDF</button>
</div>
<?php endif; ?>

<div id="nota-printable">
    <div class="center">
      <img src="<?= $logo_url ?>" alt="Logo" style="height:30px; display: block; margin: 0 auto 2px;">
      <div class="branch-script"><?= ucwords(strtolower(htmlspecialchars($store['branch']))) ?></div>
    </div>

    <table class="center">
      <tr><td style="font-size:10px;"><?= ($store_id == 8) ? "Print Sublim | Jersey | DTF | Spanduk | Stiker" : "Spanduk | Banner Kain | Baligho | Stiker One Way | Stiker Outdoor | Backlite | X-Banner | Roll Banner | ID Card | dll" ?></td></tr>
      <tr><td style="font-size:10px;"><?= nl2br(htmlspecialchars($store['address'])) ?></td></tr>
      <tr><td style="font-size:12px;">Telp: <?= htmlspecialchars($store['nomor']) ?></td></tr>
    </table>

    <div class="line"></div>

    <table style="width:100%; table-layout: fixed;">
      <tr>
        <td style="width:85%; vertical-align:top;">
          
          <table style="width:100%; table-layout: fixed;">
            <colgroup>
              <col style="width: 75px;">
              <col style="width: auto;">
            </colgroup>
            <tr><td style="white-space: nowrap;">Tanggal</td><td>: <?= formatTanggalIndo($order['date']) ?></td></tr>
            <tr><td style="white-space: nowrap;">Kepada Yth</td><td style="word-break: break-word; vertical-align: top;">: <?= htmlspecialchars($order['customer_name']) ?></td></tr>
            <tr><td style="white-space: nowrap;">Nota No.</td><td>: <?= htmlspecialchars($order['nomorator']) ?></td></tr>
            <tr><td style="white-space: nowrap;">Deadline</td><td style="word-break: break-word; vertical-align: top;">: <?= formatTanggalIndo($order['deadline']) . ' ' . date('H.i', strtotime($order['deadline'])) ?></td></tr>
            <tr><td style="white-space: nowrap;">Operator</td><td>: <?= htmlspecialchars($operator_name ?? '-') ?></td></tr>
          </table>

        </td>
        <td style="width:15%; text-align:center; vertical-align:middle; padding:0;">
          <div style="transform: rotate(270deg); font-weight: bold; font-size: 15px; white-space: nowrap;">
            <?= $ada_lunas ? 'LUNAS' : ' ' ?>
          </div>
        </td>
      </tr>
    </table>

    <div class="line"></div>

    <table class="table" style="width: 100%; font-size: 11px; margin-top: 10px;"> 
      <?php foreach ($order_items_raw as $item): 
        $pid = $item['product_id'] ?? null;
        $hide_price = false; $custom_price = null;

        if (strtoupper($item['type'] ?? '') === 'OUTDOOR' && isset($luas_kurang_dari_satu[$pid])) {
            if (in_array($pid, $printed_price_for)) $hide_price = true;
            else { $custom_price = $luas_kurang_dari_satu[$pid]; $printed_price_for[] = $pid; }
        }
        $harga = $custom_price ?? $item['amount'];
      ?>
        <tr style="border-top: 0.5px solid #000;">
          <td width="45%"><?= htmlspecialchars($item['judul'] ?: ($item['product_name'] ?? '-')) ?></td>
          <td width="20%"><?= htmlspecialchars($item['size']) ?></td>
          <td width="35%" class="text-end"><?= $hide_price ? '' : 'Rp ' . number_format($harga, 0, ',', '.') ?></td>
        </tr>
        <tr style="border-bottom: 0.5px solid #000;">
          <td colspan="3">
            <?= ($item['finishing_names'] !== ' ') ? htmlspecialchars($item['finishing_names']) . ' | ' : '' ?>
            <?= $item['quantity'] ?> x <?= $hide_price ? '' : 'Rp ' . number_format($harga / $item['quantity'], 0, ',', '.') ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>

    <?php if ($noted): ?>
        <div style="font-style: italic; width: 100%;">Catatan : <?= $noted['note']; ?></div>
    <?php endif; ?>

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
        <td style="font-size: 9px; line-height: 1.4; padding-left: 10px;">
          - Periksalah kembali barang pesanan anda saat pengambilan, kami tidak menerima komplen setelah barang diambil<br>
          - Apabila pesanan di atas tidak diambil setelah satu bulan, kami tidak bertanggung jawab atas kehilangan/kerusakan barang tersebut<br>
          Terima kasih
        </td>
      </tr>
    </table>

    <table style="width:100%; text-align:center; font-size:11px; margin-top:10px;">
      <tr><td width="50%">Hormat Kami</td><td width="50%">TTD Pemesan</td></tr>
      <tr>
        <td style="height:80px;"><div style="border-top: 1px solid #000; width: 80%; margin: 35px auto 0;"></div></td>
        <td style="height:80px;"><div style="border-top: 1px solid #000; width: 80%; margin: 35px auto 0;"></div></td>
      </tr>
    </table>
</div>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const { jsPDF } = window.jspdf;
    const element = document.getElementById('nota-printable');
    const previewPrint = <?= $preview_print ?>;
    const filename = "<?= $filename ?>";

    function generatePDF() {
        html2canvas(element, {
            useCORS: true,
            scale: 3,
            logging: false
        }).then(canvas => {
            const imgData = canvas.toDataURL('image/jpeg', 1.0);
            const pdfWidth = 226.77;
            const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

            const doc = new jsPDF({
                orientation: 'portrait',
                unit: 'pt',
                format: [pdfWidth, pdfHeight]
            });

            doc.addImage(imgData, 'JPEG', 0, 0, pdfWidth, pdfHeight);
            doc.save(filename);
        });
    }

    if (previewPrint === 0) {
        generatePDF();
    } else {
        const downloadBtn = document.getElementById('downloadBtn');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', () => {
                generatePDF();
            });
        }
    }
});
</script>
</body>
</html>