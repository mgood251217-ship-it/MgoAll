<?php

require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/components/Table.php';
require_once BASE_PATH . '/controllers/ReportController.php';

$reportController = new ReportController($koneksi);

$start_date = ($_GET['start_date'] ?? date('Y-m-d')) . ' 00:00:00';
$end_date = ($_GET['end_date'] ?? date('Y-m-d')) . ' 23:59:59';

$dataMaklun = $reportController->maklun();
$dataMaklunMasuk = $dataMaklun['maklunIn'];
$dataMaklunKeluar = $dataMaklun['maklunOut'];

?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Maklun</title>
  <?php include BASE_PATH . '/header.php'; ?>
  <?php include BASE_PATH . '/export_libraries.php'; ?>
</head>

<body>
<div id="main-wrapper">
<?php include BASE_PATH . '/navbar.php'; ?>

<div id="main-content" <?= ($mode === 1) ? 'class="dark-mode"' : '' ?>>
<?php include BASE_PATH . '/sidebar.php'; ?>

  <div id="page-content-wrapper">
    <div class="d-flex justify-content-between align-items-end mb-4">
      <h1>Maklun</h1>
      <div class="d-flex gap-2 align-items-end">
        <?php $showExport = true; include BASE_PATH . '/interval_date.php'; ?>
      </div>
    </div>

    <div class="row">

      <?php
      $htmlMaklunMasuk = renderTable([
          'data'          => $dataMaklunMasuk,
          'empty_message' => 'Tidak ada data maklun masuk.',
          'table_class'   => 'table table-bordered table-striped" id="tableMaklun',
          'columns'       => [
              ['header' => 'No', 'type' => 'number'],
              ['header' => 'Judul', 'field' => 'judul'],
              ['header' => 'Ukuran', 'field' => 'size'],
              ['header' => 'Finishing', 'field' => 'finishing_names'],
              ['header' => 'Qty', 'field' => 'quantity'],
              ['header' => 'Satuan', 'field' => 'harga_satuan_calc'],
              ['header' => 'Jumlah', 'field' => 'jumlah_harga_calc'],
              ['header' => 'Dari Cabang', 'field' => 'branch_name'],
              [
                  'header' => 'Tanggal',
                  'render' => function($row) {
                      return date('Y-m-d', strtotime($row['date']));
                  }
              ]
          ]
      ]);

      $htmlMaklunKeluar = renderTable([
          'data'          => $dataMaklunKeluar,
          'empty_message' => 'Tidak ada data maklun keluar.',
          'table_class'   => 'table table-bordered table-striped" id="tableNgemaklun',
          'columns'       => [
              ['header' => 'No', 'type' => 'number'],
              ['header' => 'Judul', 'field' => 'judul'],
              ['header' => 'Ukuran', 'field' => 'size'],
              ['header' => 'Finishing', 'field' => 'finishing_names_str'],
              ['header' => 'Qty', 'field' => 'quantity'],
              ['header' => 'Satuan', 'field' => 'harga_satuan_calc'],
              ['header' => 'Jumlah', 'field' => 'jumlah_harga_calc'],
              ['header' => 'Ke Cabang', 'field' => 'branch_name'],
              [
                  'header' => 'Tanggal',
                  'render' => function($row) {
                      return date('Y-m-d', strtotime($row['date']));
                  }
              ]
          ]
      ]);
      ?>

      <div class="col-md-6">
        <h4>Data Maklun Masuk</h4>
        <?= $htmlMaklunMasuk ?>
      </div>

      <div class="col-md-6">
        <h4>Data Maklun Keluar</h4>
        <?= $htmlMaklunKeluar ?>
      </div>

    </div>
  </div>
</div>

<?php include BASE_PATH . '/footer.php'; ?>
</div>

<script>
document.getElementById('btnExportExcel').addEventListener('click', async () => {
  const workbook = new ExcelJS.Workbook();

  const toko   = "<?= addslashes($storeName) ?>";
  const alamat = "<?= addslashes($storeAddress) ?>";
  const startDate = "<?= $start_date ?>";
  const endDate   = "<?= $end_date ?>";

  const sheetUtama = workbook.addWorksheet("Laporan Maklun");
  let rowIndex = 1;

  sheetUtama.mergeCells(`A${rowIndex}:I${rowIndex}`);
  sheetUtama.getCell(`A${rowIndex}`).value = toko;
  sheetUtama.getCell(`A${rowIndex}`).font = { bold: true, size: 16 };
  sheetUtama.getCell(`A${rowIndex}`).alignment = { horizontal: 'center' };
  rowIndex++;

  sheetUtama.mergeCells(`A${rowIndex}:I${rowIndex}`);
  sheetUtama.getCell(`A${rowIndex}`).value = alamat;
  sheetUtama.getCell(`A${rowIndex}`).alignment = { horizontal: 'center' };
  rowIndex++;

  sheetUtama.mergeCells(`A${rowIndex}:I${rowIndex}`);
  sheetUtama.getCell(`A${rowIndex}`).value = `Periode ${startDate} s.d ${endDate}`;
  sheetUtama.getCell(`A${rowIndex}`).alignment = { horizontal: 'center' };
  rowIndex += 2;

  function addTableUtama(title, tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;

    sheetUtama.mergeCells(`A${rowIndex}:H${rowIndex}`);
    sheetUtama.getCell(`A${rowIndex}`).value = title;
    sheetUtama.getCell(`A${rowIndex}`).font = { bold: true };
    rowIndex++;

    const headers = [...table.querySelectorAll("thead th")].map(th => th.innerText);
    const headerRow = sheetUtama.addRow(headers);
    headerRow.font = { bold: true };

    headerRow.eachCell(cell => {
      cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFCCE5FF' } };
      cell.border = {
        top: { style: 'thin' }, left: { style: 'thin' },
        bottom: { style: 'thin' }, right: { style: 'thin' }
      };
      cell.alignment = { horizontal: 'center' };
    });

    rowIndex++;

    table.querySelectorAll("tbody tr").forEach(tr => {
      const row = sheetUtama.addRow([...tr.children].map(td => td.innerText));
      row.eachCell(cell => {
        cell.border = {
          top: { style: 'thin' }, left: { style: 'thin' },
          bottom: { style: 'thin' }, right: { style: 'thin' }
        };
      });
      rowIndex++;
    });

    rowIndex += 2;
  }

  addTableUtama("Data Maklun Masuk", "tableMaklun");
  addTableUtama("Data Maklun Keluar", "tableNgemaklun");

  sheetUtama.columns = [
    { width: 3 }, { width: 25 }, { width: 7 }, { width: 14 },
    { width: 6 }, { width: 10 }, { width: 14 }, { width: 20 }, { width: 12 }
  ];

  const IDX_CABANG = 7;
  const tableMasuk  = document.getElementById("tableMaklun");
  const tableKeluar = document.getElementById("tableNgemaklun");

  const headersMasuk  = tableMasuk ? [...tableMasuk.querySelectorAll("thead th")].map(th => th.innerText) : [];
  const headersKeluar = tableKeluar ? [...tableKeluar.querySelectorAll("thead th")].map(th => th.innerText) : [];

  const dataCabang = {};

  function collect(table, type) {
    if (!table) return;
    table.querySelectorAll("tbody tr").forEach(tr => {
      const row = [...tr.children].map(td => td.innerText.trim());
      const cabang = row[IDX_CABANG] || "LAINNYA";

      if (!dataCabang[cabang]) {
        dataCabang[cabang] = { masuk: [], keluar: [] };
      }
      dataCabang[cabang][type].push(row);
    });
  }

  collect(tableMasuk, "masuk");
  collect(tableKeluar, "keluar");

  function createCabangSheet(namaCabang, data) {
    const sheet = workbook.addWorksheet(namaCabang.substring(0, 31));
    let r = 1;

    sheet.mergeCells(`A${r}:I${r}`);
    sheet.getCell(`A${r}`).value = toko;
    sheet.getCell(`A${r}`).font = { bold: true, size: 16 };
    sheet.getCell(`A${r}`).alignment = { horizontal: 'center' };
    r++;

    sheet.mergeCells(`A${r}:I${r}`);
    sheet.getCell(`A${r}`).value = alamat;
    sheet.getCell(`A${r}`).alignment = { horizontal: 'center' };
    r++;

    sheet.mergeCells(`A${r}:I${r}`);
    sheet.getCell(`A${r}`).value = `Periode ${startDate} s.d ${endDate}`;
    sheet.getCell(`A${r}`).alignment = { horizontal: 'center' };
    r += 2;

    function addTable(title, headers, rows) {
      if (!rows || rows.length === 0) return;
      
      sheet.mergeCells(`A${r}:H${r}`);
      sheet.getCell(`A${r}`).value = title;
      sheet.getCell(`A${r}`).font = { bold: true };
      r++;

      const headerRow = sheet.addRow(headers);
      headerRow.font = { bold: true };

      headerRow.eachCell(cell => {
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFCCE5FF' } };
        cell.border = {
          top: { style: 'thin' }, left: { style: 'thin' },
          bottom: { style: 'thin' }, right: { style: 'thin' }
        };
        cell.alignment = { horizontal: 'center' };
      });

      r++;

      rows.forEach(rowData => {
        const row = sheet.addRow(rowData);
        row.eachCell(cell => {
          cell.border = {
            top: { style: 'thin' }, left: { style: 'thin' },
            bottom: { style: 'thin' }, right: { style: 'thin' }
          };
        });
        r++;
      });

      r += 2;
    }

    addTable("Data Maklun Masuk", headersMasuk, data.masuk);
    addTable("Data Maklun Keluar", headersKeluar, data.keluar);

    sheet.columns = [
      { width: 3 }, { width: 25 }, { width: 7 }, { width: 14 },
      { width: 6 }, { width: 10 }, { width: 14 }, { width: 20 }, { width: 12 }
    ];
  }

  Object.keys(dataCabang).forEach(cabang => {
    createCabangSheet(cabang, dataCabang[cabang]);
  });

  const buffer = await workbook.xlsx.writeBuffer();
  saveAs(
    new Blob([buffer]),
    `Laporan_Maklun_${startDate}_sd_${endDate}.xlsx`
  );
});
</script>
</body>
</html>