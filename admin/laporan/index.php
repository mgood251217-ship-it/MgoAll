<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require BASE_PATH . '/access_rights.php';

$startMonth  = date('Y-m-01 00:00:00');
$endMonth    = date('Y-m-t 23:59:59');
$today       = date('Y-m-d');

$cashTotal = $tfTotal = $jumlahPaymentHarian = $jumlahPaymentBulanan = 0;
$pendapatanHarian = $pendapatanBulanan = $total_qty_all_products = 0;
$max_qty = $totalOmsetSemuaProduk = $topSalesOmset = 0;
$jumlah_pelanggan_belum_bayar = $total_hutang = $omset_offline = $omset_online = 0;

$top_product_name = $topSalesName = $topUserName = $topKonsumenName = '-';

$digunakan_short = [];
$tidak_short = [];

$stmt = $koneksi->prepare("
    SELECT 
        SUM(CASE WHEN DATE(p.date) = ? THEN 1 ELSE 0 END) AS jml_harian,
        SUM(CASE WHEN DATE(p.date) = ? THEN p.nominal ELSE 0 END) AS nom_harian,
        COUNT(p.payment_id) AS jml_bulanan,
        SUM(p.nominal) AS nom_bulanan,
        SUM(CASE WHEN UPPER(p.payment_method) = 'CASH' THEN p.nominal ELSE 0 END) AS cash_total,
        SUM(CASE WHEN UPPER(p.payment_method) IN ('TF', 'TRANSFER') THEN p.nominal ELSE 0 END) AS tf_total
    FROM payment p
    JOIN orders o ON p.order_id = o.order_id
    WHERE o.store_id = ? AND p.date BETWEEN ? AND ?
");
$stmt->bind_param("ssiss", $today, $today, $store_id, $startMonth, $endMonth);
$stmt->execute();
$stmt->bind_result($jumlahPaymentHarian, $pendapatanHarian, $jumlahPaymentBulanan, $pendapatanBulanan, $cashTotal, $tfTotal);
$stmt->fetch();
$stmt->close();

$pendapatanHarian = (int)$pendapatanHarian;
$pendapatanBulanan = (int)$pendapatanBulanan;
$cashTotal = (int)$cashTotal;
$tfTotal = (int)$tfTotal;

$product_ids = [];
$stmt = $koneksi->prepare("
    SELECT 
        p.product_id,
        p.name, 
        SUM(oi.quantity) AS total_qty,
        SUM(CASE WHEN p.unit_type <> '~' THEN oi.amount ELSE 0 END) AS total_omset
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    JOIN products p ON p.product_id = oi.product_id
    WHERE o.store_id = ? AND o.date BETWEEN ? AND ?
    GROUP BY p.product_id, p.name
");
$stmt->bind_param("iss", $store_id, $startMonth, $endMonth);
$stmt->execute();
$stmt->bind_result($pid, $prod_name, $qty, $omset);

while ($stmt->fetch()) {
    $product_ids[] = $pid;
    
    if (count($digunakan_short) < 3) {
        $digunakan_short[] = $prod_name;
    }

    $total_qty_all_products += (int)$qty;
    if ((int)$qty > $max_qty) {
        $max_qty = (int)$qty;
        $top_product_name = $prod_name;
    }

    $totalOmsetSemuaProduk += (int)$omset;
    if ((int)$omset > $topSalesOmset) {
        $topSalesOmset = (int)$omset;
        $topSalesName = $prod_name;
    }
}
$stmt->close();

$not_in = !empty($product_ids) ? implode(',', array_map('intval', $product_ids)) : '0';
$q = "SELECT name FROM products WHERE store_id = $store_id AND product_id NOT IN ($not_in) LIMIT 3";
$r = $koneksi->query($q);
while ($d = $r->fetch_assoc()) {
    $tidak_short[] = $d['name'];
}

$stmt = $koneksi->prepare("
    SELECT 
        COUNT(CASE WHEN IFNULL(p.lunas, 0) = 0 AND o.total > IFNULL(p.total_dp, 0) THEN 1 END),
        SUM(CASE WHEN IFNULL(p.lunas, 0) = 0 AND o.total > IFNULL(p.total_dp, 0) THEN (o.total - IFNULL(p.total_dp, 0)) ELSE 0 END)
    FROM orders o
    LEFT JOIN (
        SELECT order_id, 
               SUM(CASE WHEN status='DP' THEN nominal ELSE 0 END) AS total_dp,
               MAX(CASE WHEN status='LUNAS' THEN 1 ELSE 0 END) AS lunas
        FROM payment GROUP BY order_id
    ) p ON p.order_id = o.order_id
    WHERE o.store_id = ?
");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$stmt->bind_result($jumlah_pelanggan_belum_bayar, $total_hutang);
$stmt->fetch();
$stmt->close();

$jumlah_pelanggan_belum_bayar = (int)$jumlah_pelanggan_belum_bayar;
$total_hutang = (int)$total_hutang;

$stmt = $koneksi->prepare("SELECT omset_offline, omset_online FROM finance WHERE store_id=? ORDER BY date DESC LIMIT 1");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$stmt->bind_result($omset_offline, $omset_online);
$stmt->fetch();
$stmt->close();

$stmt = $koneksi->prepare("
    SELECT u.name FROM projects p
    JOIN users u ON p.user_id = u.user_id
    WHERE u.store_id=? AND p.process='DIAMBIL' AND p.date BETWEEN ? AND ?
    GROUP BY p.user_id
    ORDER BY COUNT(*) DESC LIMIT 1
");
$stmt->bind_param("iss", $store_id, $startMonth, $endMonth);
$stmt->execute();
if ($stmt->fetch()) {
    $topUserName = $stmt->bind_result($name) ? $name : $topUserName;
}
$stmt->close();

$stmt = $koneksi->prepare("
    SELECT u.name FROM orders o
    JOIN users u ON o.user_id=u.user_id
    WHERE u.store_id=? AND o.date BETWEEN ? AND ?
    GROUP BY o.user_id
    ORDER BY COUNT(*) DESC LIMIT 1
");
$stmt->bind_param("iss", $store_id, $startMonth, $endMonth);
$stmt->execute();
if ($stmt->fetch()) {
    $topKonsumenName = $stmt->bind_result($name) ? $name : $topKonsumenName;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Laporan</title>
  <?php include BASE_PATH . '/header.php'; ?>
    <style>
    .laporan-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
    }
    .laporan-card {
    flex: 1 1 calc(33.333% - 20px);
    border-radius: 16px;
    padding: 20px;
    min-height: 180px;
    box-sizing: border-box;
    text-decoration: none;
    color: #fff;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    }
    .laporan-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    color: white;
    }

    .laporan-title {
    font-size: 1.3rem;
    font-weight: 700;
    text-align: center;
    margin-bottom: 12px;
    }

    .laporan-keterangan {
    font-size: 1rem;
    opacity: 0.9;
    margin-bottom: 6px;
    text-align: center;
    }

    /* Warna background langsung pada card */
    .laporan-card:nth-child(1) { background-color: #2196f3; }
    .laporan-card:nth-child(2) { background-color: #ff9800; }
    .laporan-card:nth-child(3) { background-color: #4caf50; }
    .laporan-card:nth-child(4) { background-color: #f44336; }
    .laporan-card:nth-child(5) { background-color: rgba(0, 74, 28, 1); }
    .laporan-card:nth-child(6) { background-color: #673ab7; }
    .laporan-card:nth-child(7) { background-color: #e91e63; }
    .laporan-card:nth-child(8) { background-color: #009688; }
    .laporan-card:nth-child(9) { background-color: rgb(248, 141, 230); }
    .laporan-card:nth-child(10) { background-color:rgb(0, 33, 180); }
    .laporan-card:nth-child(11) { background-color:rgb(147, 0, 0); }
    .laporan-card:nth-child(12) { background-color:rgb(44, 25, 25); }

    @media (max-width: 768px) {
    .laporan-card {
        flex: 1 1 calc(50% - 20px);
    }
    }

    @media (max-width: 500px) {
    .laporan-card {
        flex: 1 1 100%;
    }
    }
  .tooltip-wrap {
    position: relative;
    display: inline-block;
    cursor: pointer;
    max-width: 100%;
  }

  .tooltip-wrap .tooltip-content {
    visibility: hidden;
    background-color: #333;
    color: #fff;
    text-align: left;
    border-radius: 6px;
    padding: 10px;
    position: absolute;
    z-index: 10;
    top: 125%; /* sedikit di bawah */
    left: 0;
    min-width: 250px;
    white-space: pre-wrap;
    font-size: 0.85em;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
  }

  .tooltip-wrap:hover .tooltip-content {
    visibility: visible;
  }

    </style>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dark_mode.css">
</head>

<body>
<div id="main-wrapper">
  <?php include BASE_PATH . '/navbar.php'; ?>

  <div id="main-content" <?= (isset($mode) && $mode === 1) ? 'class="dark-mode"' : '' ?>>
    <?php include BASE_PATH . '/sidebar.php'; ?>

    <div id="page-content-wrapper">
      <h1 class="mb-4">Laporan</h1>
        <div class="laporan-grid">
          <a href="transaksi_detil" class="laporan-card">
            <div class="laporan-title">Transaksi Detil</div>
            <div class="laporan-keterangan">Cash: Rp<?= number_format($cashTotal, 0, ',', '.') ?></div>
            <div class="laporan-keterangan">TF: Rp<?= number_format($tfTotal, 0, ',', '.') ?></div>
          </a>
          <a href="transaksi_harian" class="laporan-card">
            <div class="laporan-title">Transaksi Harian</div>
            <div class="laporan-keterangan">Jumlah Transaksi: <?= $jumlahPaymentHarian ?></div>
            <div class="laporan-keterangan">Pendapatan: Rp<?= number_format($pendapatanHarian, 0, ',', '.') ?></div>
          </a>
          <a href="transaksi_bulanan" class="laporan-card">
              <div class="laporan-title">Transaksi Bulanan</div>
              <div class="laporan-keterangan">Jumlah Transaksi: <?= $jumlahPaymentBulanan ?></div>
              <div class="laporan-keterangan">Pendapatan: Rp<?= number_format($pendapatanBulanan, 0, ',', '.') ?></div>
          </a>
          <a href="transaksi_item" class="laporan-card">
              <div class="laporan-title">Transaksi per Item</div>
              <div class="laporan-keterangan">Produk terjual Bulan Ini: <strong><?= $total_qty_all_products ?></strong></div>
              <div class="laporan-keterangan">
                  Paling Banyak Terjual: <strong><?= htmlspecialchars($top_product_name) ?></strong> (<?= $max_qty ?>)
              </div>
          </a>
          <a href="transaksi_konsumen" class="laporan-card">
              <div class="laporan-title">Transaksi per Konsumen</div>
              <div class="laporan-keterangan">Produk terjual Bulan Ini: <strong><?= $total_qty_all_products ?></strong></div>
              <div class="laporan-keterangan">
                  Paling Banyak Terjual: <strong><?= htmlspecialchars($top_product_name) ?></strong> (<?= $max_qty ?>)
              </div>
          </a>
          <a href="omset_item" class="laporan-card">
            <div class="laporan-title">Omset per Item</div>
            <div class="laporan-keterangan">Total Omset: Rp <?= number_format($totalOmsetSemuaProduk, 0, ',', '.') ?></div>
            <div class="laporan-keterangan">Top Sales: <?= htmlspecialchars($topSalesName) ?> (Rp <?= number_format($topSalesOmset, 0, ',', '.') ?>)</div>
          </a>
          <a href="pemakaian_bahan" class="laporan-card">
              <div class="laporan-title">Daftar Pemakaian Bahan</div>

              <div class="laporan-keterangan tooltip-wrap">
                  Digunakan: <?= htmlspecialchars(implode(', ', $digunakan_short)) ?>
                  
              </div>

              <div class="laporan-keterangan tooltip-wrap">
                  Tidak Digunakan: <?= htmlspecialchars(implode(', ', $tidak_short)) ?>
                  
              </div>
          </a>
          <a href="daftar_piutang" class="laporan-card">
              <div class="laporan-title">Daftar Piutang</div>
              <div class="laporan-keterangan">Pelanggan Belum Bayar : <?= $jumlah_pelanggan_belum_bayar ?> Orang</div>
              <div class="laporan-keterangan">Total Hutang : Rp <?= number_format($total_hutang, 0, ',', '.') ?></div>
          </a>
          <a href="data_pelunasan" class="laporan-card">
              <div class="laporan-title">Data Pelunasan</div>
              <div class="laporan-keterangan">Pelanggan Belum Bayar : <?= $jumlah_pelanggan_belum_bayar ?> Orang</div>
              <div class="laporan-keterangan">Total Hutang : Rp <?= number_format($total_hutang, 0, ',', '.') ?></div>
          </a>
          <a href="keuangan" class="laporan-card">
            <div class="laporan-title">Keuangan</div>
            <div class="laporan-keterangan">Omset Offline: Rp <?= number_format($omset_offline, 0, ',', '.') ?></div>
            <div class="laporan-keterangan">Omset Online: Rp <?= number_format($omset_online, 0, ',', '.') ?></div>
          </a>
          <a href="statistik_karyawan" class="laporan-card">
              <div class="laporan-title">Statistik Karyawan</div>
              <div class="laporan-keterangan">
                  Pengambilan Terbanyak: <?= htmlspecialchars($topUserName ?? '-') ?>
              </div>
              <div class="laporan-keterangan">
                  Konsumen Terbanyak: <?= htmlspecialchars($topKonsumenName ?? '-') ?>
              </div>
          </a> 
          <a href="aktivitas" class="laporan-card">
            <div class="laporan-title">Aktivitas</div>
            <div class="laporan-keterangan">Aktivitas Terkini</div>
            <div class="laporan-keterangan">Belum Terbaca</div>
          </a>
        </div>
    </div>
    
  </div>

  <?php include BASE_PATH . '/footer.php'; ?>
</div>
</body>
</html>
