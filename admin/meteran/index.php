<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';



// Gunakan GET untuk filter
$start_date_full = isset($_GET['start_date']) ? $_GET['start_date'] . " 00:00:00" : date('Y-m-d 00:00:00');
$end_date_full   = isset($_GET['end_date']) ? $_GET['end_date'] . " 23:59:59" : date('Y-m-d 23:59:59');

$start_date_only = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date_only   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

?>


<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Data Meteran</title>
  <?php include BASE_PATH . '/header.php'; ?>
  <style>
    .excel-container {
      display: flex;
      gap: 0;
      align-items: flex-start;
      font-family: Arial, sans-serif;
      flex-wrap: nowrap;
      overflow-x: auto;
      white-space: nowrap;
      margin: 0;
      padding: 0;
    }
    .excel-table {
      border-collapse: collapse;
      margin: 0 5px 0 0;
      font-size: 14px;
      min-width: 180px;
    }
    .excel-table th, .excel-table td {
      border: 1px solid #000;
      text-align: center;
      padding: 5px 8px;
      min-width: 50px;
      height: 30px;
    }

    .excel-table thead tr:first-child th {
      background-color:rgb(17, 47, 92);
      color: #ffffff;
    }
  </style>
  
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/content.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dark_mode.css">
    <script src="https://cdn.jsdelivr.net/npm/exceljs/dist/exceljs.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/file-saver@2.0.5/dist/FileSaver.min.js"></script>
    <?php if (isset($username) && ($username == 'zannia' || $username == 'vikialvian')) { ?>
      <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/pink_mode.css">
    <?php } ?>
</head>
<body>
  <div id="main-wrapper" <?= ($mode === 1) ? 'class="dark-mode"' : '' ?>>
    <?php include '../navbar.php'; ?>

    <div id="main-content" <?= ($mode === 1) ? 'class="dark-mode"' : '' ?>>
      <?php include '../sidebar.php'; ?>

      <div id="page-content-wrapper" class="flex-grow-1 p-6">

        
        
        <div style="display:block; cursor: pointer;">
          <form method="get" id="filterTanggal" class="d-flex align-items-center justify-content-end gap-2 mb-3" style="left: 0;">
            <label class="form-label mb-0">Dari:</label>
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date_only) ?>">
            <label class="form-label mb-0">Sampai:</label>
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date_only) ?>">
          </form>
        </div>

        <!-- OUTDOOR -->
        <div class="data-accordion">
          <h3 class="btn-open" style="display:inline-block; cursor: pointer;">Data Produksi Outdoor</h3>
          <div class="data-open" style="display: none;" data-target="outdoor">

          </div>
        </div>

        <!-- INDOOR -->
        <div class="data-accordion">
          <h3 class="btn-open" style="display:inline-block; cursor: pointer;">Data Produksi Indoor</h3>
          <div class="data-open" style="display: none;" data-target="indoor">

          </div>
        </div>

        <!-- SUBLIM -->
        <div class="data-accordion">
          <h3 class="btn-open" style="display:inline-block; cursor: pointer;">Data Produksi Sublim</h3>
          <div class="data-open" style="display: none;" data-target="sublim">

          </div>
        </div>

        <!-- BAHAN -->
         <div class="data-accordion">
          <h3 class="btn-open" style="display:inline-block; cursor: pointer;">Data Pemakaian Bahan Sublim</h3>
          <div class="data-open" style="display: none;" data-target="bahan">

          </div>
         </div>

        <!-- JERSEY -->
        <div class="row">
          <div class="col-12 col-md-6 data-accordion">
            <h3 class="btn-open" style="cursor: pointer;">Data Produksi Jersey</h3>
            <div class="data-open" style="display: none;" data-target="jersey">

            </div>
          </div>

          <div class="col-12 col-md-6 data-accordion">
            <h3 class="btn-open" style="cursor: pointer;">Data Finishing Jersey</h3>
            <div class="data-open" style="display: none;" data-target="finishing_jersey">

            </div>
          </div>
        </div>
        <!-- DTF -->
        <div class="data-accordion">
          <h3 class="btn-open" style="display:inline-block; cursor: pointer;">Data Produksi DTF</h3>
          <div class="data-open" style="display: none;" data-target="dtf">

          </div>
        </div>

        <!-- LASER A3 dan MERCENDISE -->
        <div class="row">
          <div class="col-12 col-md-6 data-accordion">
            <h3 class="btn-open" style="cursor: pointer;">Data Produksi Laser A3</h3>
            <div class="data-open" style="display: none; " data-target="laser_a3">

            </div>
          </div>

          <div class="col-12 col-md-6 data-accordion">
            <h3 class="btn-open" style="cursor: pointer;">Data Laser Lainnya</h3>
            <div class="data-open" style="display: none;" data-target="laser_a3_lainya">

            </div>
          </div>

        </div>

        <!-- AKRILIK dan MERCENDISE AKRILIK -->
        <div class="row">
          <div class="col-12 col-md-6 data-accordion">
            <h3 class="btn-open" style="cursor: pointer;">Data Produksi Akrilik</h3>
            <div class="data-open" style="display: none; " data-target="akrilik">

            </div>
          </div>

          <div class="col-12 col-md-6 data-accordion">
            <h3 class="btn-open" style="cursor: pointer;">Mercendise Akrilik Lainnya</h3>
            <div class="data-open" style="display: none;" data-target="mercendise_akrilik">

            </div>
          </div>

        </div>

        <div class="row" >
          <div class="col-12 col-md-6 data-accordion">
            <h3 class="btn-open" style="cursor: pointer;">Data Produksi Cetakan</h3>
            <div class="data-open" style="display: none;" data-target="cetakan">

            </div>
          </div>
        </div>
    <br>
    <br>
    </div>

    <?php include '../footer.php'; ?>
  </div>

  <script>
    document.querySelectorAll('#filterTanggal input').forEach(el => {
      el.addEventListener('change', () => {
        document.getElementById('filterTanggal').submit();
      });
    });
    

    let accordion = document.querySelectorAll('.data-accordion');
    accordion.forEach(row => {
      let btnOpen = row.querySelector('.btn-open');
      let dataOpen = row.querySelector('.data-open');
      btnOpen.addEventListener('click', () => {
        let fecc = dataOpen.dataset.target;
        fetch('get_' + fecc + '?start_date=' + '<?= $start_date_only ?>' + '&end_date=' + '<?= $end_date_only ?>')
          .then(res => res.text())
          .then(html => {
            dataOpen.innerHTML = html;
            
          })
          .catch(err => {
            console.error('Gagal buka fect lur:', err);
          });
        
        if (dataOpen.style.display == 'none') {
          dataOpen.style.display = 'block';
        }else{
          dataOpen.style.display = 'none';
        }
      })
    })
  </script>

</body>


</html>
