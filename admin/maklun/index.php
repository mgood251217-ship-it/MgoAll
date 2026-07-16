<?php

require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/components/Table.php';

$start_date_f = $_GET['start_date'] ?? date('Y-m-d');
$end_date_f   = $_GET['end_date'] ?? date('Y-m-d');

$start_date = $start_date_f . ' 00:00:00';
$end_date   = $end_date_f . ' 23:59:59';

$stmtMaklunan = $koneksi->prepare("
  SELECT
      oi.order_item_id, oi.judul, oi.product_id, oi.size, oi.quantity, oi.finishing,
      o.store_id, o.date,
      p.name AS product_name, p.unit_type, p.reasonable_price,
      c.name AS category,
      s.name AS branch_name
  FROM order_items oi
  JOIN orders o ON o.order_id = oi.order_id
  LEFT JOIN products p ON p.product_id = oi.product_id
  LEFT JOIN categories c ON c.category_id = p.category_id
  LEFT JOIN stores s ON s.store_id = o.store_id
  WHERE oi.maklun = ? AND o.date BETWEEN ? AND ? ORDER BY o.date ASC
  ");
$stmtMaklunan->bind_param("iss", $store_id, $start_date, $end_date);
$stmtMaklunan->execute();
$dataMaklunMasuk = $stmtMaklunan->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtMaklunan->close();

$stmtNgemaklun = $koneksi->prepare("
  SELECT
      oi.order_item_id, oi.judul, oi.maklun, oi.product_id, oi.size, oi.quantity, oi.finishing,
      o.date,
      p.name AS product_name, p.unit_type, p.reasonable_price,
      c.name AS category,
      s.name AS branch_name
  FROM order_items oi
  JOIN orders o ON o.order_id = oi.order_id
  LEFT JOIN products p ON p.product_id = oi.product_id
  LEFT JOIN categories c ON c.category_id = p.category_id
  LEFT JOIN stores s ON s.store_id = oi.maklun
  WHERE o.store_id = ? AND oi.maklun != 0 AND o.date BETWEEN ? AND ? ORDER BY o.date ASC
  ");
$stmtNgemaklun->bind_param("iss", $store_id, $start_date, $end_date);
$stmtNgemaklun->execute();
$dataMaklunKeluar = $stmtNgemaklun->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtNgemaklun->close();

$all_finishing_ids = [];
foreach (array_merge($dataMaklunMasuk, $dataMaklunKeluar) as $row) {
    if (!empty($row['finishing']) && $row['finishing'] !== '-') {
        $ids = explode(',', $row['finishing']);
        foreach ($ids as $id) {
            $clean_id = trim($id);
            if (ctype_digit($clean_id)) {
                $all_finishing_ids[$clean_id] = true;
            }
        }
    }
}

$finishing_names_map = [];
$finishing_prices_map = [];

if (!empty($all_finishing_ids)) {
    $ids_array = array_keys($all_finishing_ids);
    $placeholders = implode(',', array_fill(0, count($ids_array), '?'));
    $types = str_repeat('i', count($ids_array));

    $stmtF = $koneksi->prepare("SELECT finishing_id, name FROM finishings WHERE finishing_id IN ($placeholders)");
    $stmtF->bind_param($types, ...$ids_array);
    $stmtF->execute();
    $resF = $stmtF->get_result();
    while ($rF = $resF->fetch_assoc()) {
        $finishing_names_map[$rF['finishing_id']] = $rF['name'];
    }
    $stmtF->close();

    $stmtP = $koneksi->prepare("SELECT product_id, reasonable_price FROM products WHERE product_id IN ($placeholders)");
    $stmtP->bind_param($types, ...$ids_array);
    $stmtP->execute();
    $resP = $stmtP->get_result();
    while ($rP = $resP->fetch_assoc()) {
        $finishing_prices_map[$rP['product_id']] = (int)$rP['reasonable_price'];
    }
    $stmtP->close();
}

function processMaklunData(&$dataArray, $finishing_names_map, $finishing_prices_map) {
    foreach ($dataArray as &$row) {
        $finishing_price = 0;
        $f_names = [];

        if (!empty($row['finishing']) && $row['finishing'] !== '-') {
            $ids = explode(',', $row['finishing']);
            foreach ($ids as $id) {
                $clean_id = trim($id);
                if (isset($finishing_names_map[$clean_id])) {
                    $f_names[] = $finishing_names_map[$clean_id];
                }
                if (isset($finishing_prices_map[$clean_id])) {
                    $finishing_price += $finishing_prices_map[$clean_id];
                }
            }
        }
        $row['finishing_names_str'] = !empty($f_names) ? implode(', ', $f_names) : '-';

        $hargaSatuan = 0;
        if (!empty($row['product_id']) && $row['product_id'] != 0) {
            $unit_type = $row['unit_type'] ?? '';
            $type = $row['category'] ?? '';
            $product_name = $row['product_name'] ?? '';
            $reasonable_price = (float)($row['reasonable_price'] ?? 0);
            $size = $row['size'] ?? '';
            
            $base_price_plus_finishing = $reasonable_price + $finishing_price;

            if ($unit_type === 'M2') {
                if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $size, $match)) {
                    $p = floatval($match[1]);
                    $l = floatval($match[2]);
                    if ($type === 'DTF') {
                        $hargaSatuan = $p * $base_price_plus_finishing;
                    } else {
                        $hargaSatuan = $p * $l * $base_price_plus_finishing;
                    }
                }
            } elseif ($unit_type === 'PCS') {
                $hargaSatuan = $base_price_plus_finishing;
                
                if ($type === 'JERSEY') {
                    $harga_jersey = 0;
                    if ($size === '5XL') {
                        $harga_jersey += 50000;
                    } elseif ($size === '4XL') {
                        $harga_jersey += 40000;
                    } elseif ($size === '3XL') {
                        $harga_jersey += 30000;
                    } elseif ($size === '2XL') {
                        $harga_jersey += 20000;
                    } elseif ($size === 'XL') {
                        $harga_jersey += 10000;
                    }
                    $hargaSatuan += $harga_jersey;
                } elseif ($type === 'SUBLIM' && str_contains((string)$product_name, 'BAHAN')) {
                    $kata = explode(" ", $size);
                    if (isset($kata[0]) && is_numeric($kata[0])) {
                        $hargaSatuan *= (float)$kata[0];
                    }
                }
            } elseif ($product_name === 'POTONG AKRILIK') {
                $hargaSatuan = $base_price_plus_finishing;
                $kata = explode(" ", $size);
                if (isset($kata[0]) && is_numeric($kata[0])) {
                    $hargaSatuan *= (float)$kata[0];
                }
            }
        }
        
        $row['harga_satuan_calc'] = $hargaSatuan;
        $row['jumlah_harga_calc'] = $hargaSatuan * (float)($row['quantity'] ?? 0);
    }
}

processMaklunData($dataMaklunMasuk, $finishing_names_map, $finishing_prices_map);
processMaklunData($dataMaklunKeluar, $finishing_names_map, $finishing_prices_map);

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
              ['header' => 'Finishing', 'field' => 'finishing_names_str'],
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
  const startDate = "<?= $start_date_f ?>";
  const endDate   = "<?= $end_date_f ?>";

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