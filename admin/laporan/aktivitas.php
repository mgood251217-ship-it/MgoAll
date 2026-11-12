<?php
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../middleware/init_auth.php';
require_once __DIR__ . '/../components/Table.php';
require_once __DIR__ . '/../controllers/ReportController.php';

$reportController = new ReportController($koneksi);
$activity = $reportController->activity();

$htmlTableAktivitas = renderTable([
    'data'        => $activity,
    'table_class' => 'table table-bordered table-striped',
    'thead_class' => 'table-primary',
    'tbody_tr_class' => 'activity-row',
    'columns'     => [
        [
            'header' => 'No',
            'type'   => 'number'
        ],
        [
            'header' => 'Judul',
            'field'  => 'title'
        ],
        [
            'header' => 'Pesan',
            'field'  => 'message'
        ],
        [
            'header' => 'Keterangan',
            'field'  => 'information'
        ],
        [
            'header' => 'Order ID',
            'field'  => 'order_id'
        ],
        [
            'header' => 'Tanggal Order',
            'field'  => 'order_date'
        ],
        [
            'header' => 'Tanggal',
            'field'  => 'date'
        ],

    ]
]);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Aktivitas</title>
  <?php include __DIR__ . '/../header.php'; ?>

</head>

<body>
<div id="main-wrapper" >
  <?php include __DIR__ . '/../navbar.php'; ?>

  <div id="main-content" <?= ($mode ?? 0) === 1 ? 'class="dark-mode"' : '' ?>>
    <?php include __DIR__ . '/../sidebar.php'; ?>

    <div id="page-content-wrapper">
      <?php require 'summary_cards.php'; ?>

      <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Transaksi per Item</h1>
        <?php $showExport = true; include __DIR__ . '/../components/interval_date.php'; ?>
      </div>
      <div class="table-responsive">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5>Daftar Aktivitas</h5>
      </div>
            <?= $htmlTableAktivitas ?>
        </div>


    </div>
  </div>

  <?php include __DIR__ . '/../footer.php'; ?>
</div>



</body>
</html>
