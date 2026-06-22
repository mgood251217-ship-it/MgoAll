<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/components/Table.php';

$start_date_f = $_GET['start_date'] ?? date('Y-m-d');
$end_date_f = $_GET['end_date'] ?? date('Y-m-d');

$start_date = $start_date_f . ' 00:00:00';
$end_date   = $end_date_f . ' 23:59:59';

$query = "
    SELECT 
        p.name AS nama_barang,
        p.unit_type AS satuan,
        COALESCE(
            SUM(
                CASE
                    WHEN p.unit_type = 'M2' AND oi.size LIKE '%x%' THEN 
                        oi.quantity * CAST(SUBSTRING_INDEX(oi.size, 'x', 1) AS DECIMAL(10,4)) * CAST(SUBSTRING_INDEX(oi.size, 'x', -1) AS DECIMAL(10,4))
                    WHEN p.unit_type = 'M2' THEN 
                        oi.quantity
                    ELSE 
                        oi.quantity
                END
            ), 0
        ) AS total_terjual,
        COALESCE(SUM(oi.amount), 0) AS total_omset
    FROM products p
    LEFT JOIN order_items oi ON oi.product_id = p.product_id AND oi.store_id = ?
    LEFT JOIN orders o ON oi.order_id = o.order_id
    WHERE p.store_id = ?
    AND NOT p.unit_type = '~'
    AND (o.date BETWEEN ? AND ?)
    GROUP BY p.product_id
    ORDER BY total_omset DESC
";

$stmt = $koneksi->prepare($query);
$stmt->bind_param("iiss", $store_id, $store_id, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$dataOmsetPerItem = [];
while ($row = $result->fetch_assoc()) {
    $dataOmsetPerItem[] = $row;
}

$stmt->close();

$htmlTableOmsetPerItem = renderTable([
    'id'          => 'omsetPerItem',
    'data'        => $dataOmsetPerItem,
    'table_class' => 'table table-bordered table-striped',
    'thead_class' => 'table-primary',
    'columns'     => [
        [
            'header' => 'No',
            'type'   => 'number'
        ],
        [
            'header' => 'Nama Barang',
            'field'  => 'nama_barang'
        ],
        [
            'header' => 'Satuan',
            'field'  => 'satuan'
        ],
        [
            'header' => 'Jumlah Terjual',
            'render' => function($row) {
                if ($row['satuan'] === 'M2') {
                    return number_format($row['total_terjual'], 2);
                } else {
                    return number_format($row['total_terjual']);
                }
            }
        ],
        [
            'header' => 'Jumlah Omset',
            'type'   => 'currency',
            'field'  => 'total_omset'
        ]
    ]
]);

?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Daftar Omset Produk</title>
  <?php include BASE_PATH . '/header.php'; ?>
  <?php include BASE_PATH . '/export_libraries.php'; ?>
</head>

<body>
<div id="main-wrapper">
  <?php include BASE_PATH . '/navbar.php'; ?>

  <div id="main-content" <?= (isset($mode) && $mode === 1) ? 'class="dark-mode"' : '' ?>>
    <?php include BASE_PATH . '/sidebar.php'; ?>

    <div id="page-content-wrapper">
      <?php require 'summary_cards.php'; ?>
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-start mb-3">
        <h1 class="mb-4">Daftar Omset Per Produk</h1>

        <div class="d-flex flex-wrap justify-content-end align-items-end gap-2">
          <?php $showExport = true; include BASE_PATH . '/interval_date.php'; ?>
        </div>
      </div>
    <div id="omsetPerItemWrapper">
        <?= $htmlTableOmsetPerItem ?>
    </div>
    </div>
  </div>

  <?php include BASE_PATH . '/footer.php'; ?>
</div>

<script>
document.getElementById('btnExportExcel').addEventListener('click', async () => {
  const toko = "<?= addslashes($storeName) ?>";
  const alamat = "<?= addslashes($storeAddress) ?>";
  const startDate = document.querySelector('input[name="start_date"]').value || "";
  const endDate = document.querySelector('input[name="end_date"]').value || "";
  const tanggal = (startDate && endDate) ? `Tanggal ${startDate} s.d. ${endDate}` : 'Tanggal -';

  const workbook = new ExcelJS.Workbook();
  const sheet = workbook.addWorksheet("Omset Per Item");

  // Header utama
  sheet.mergeCells("A1:E1");
  sheet.getCell("A1").value = toko;
  sheet.getCell("A1").alignment = { horizontal: 'center', vertical: 'middle' };
  sheet.getCell("A1").font = { bold: true, size: 16 };

  sheet.mergeCells("A2:E2");
  sheet.getCell("A2").value = alamat;
  sheet.getCell("A2").alignment = { horizontal: 'center' };

  sheet.addRow([]);
  sheet.mergeCells("A4:E4");
  sheet.getCell("A4").value = "Laporan Omset Per Item";
  sheet.getCell("A4").alignment = { horizontal: 'center' };
  sheet.getCell("A4").font = { bold: true, size: 14 };

  sheet.mergeCells("A5:E5");
  sheet.getCell("A5").value = tanggal;
  sheet.getCell("A5").alignment = { horizontal: 'center' };

  sheet.addRow([]);

  // Header tabel
  const headerRow = sheet.addRow(['No', 'Nama Barang', 'Satuan', 'Jumlah Terjual', 'Jumlah Omset']);
  headerRow.font = { bold: true };
  headerRow.eachCell(cell => {
    cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFCCE5FF' } };
    cell.alignment = { horizontal: 'center', vertical: 'middle' };
    cell.border = {
      top: { style: 'thin' }, left: { style: 'thin' },
      bottom: { style: 'thin' }, right: { style: 'thin' }
    };
  });

  // Ambil data dari tabel HTML #omsetPerItem
  const rows = document.querySelectorAll("#omsetPerItem tbody tr");
  rows.forEach(tr => {
    const tds = tr.querySelectorAll("td");
    if (tds.length >= 5) {
      const no = tds[0].innerText.trim();
      const nama = tds[1].innerText.trim();
      const satuan = tds[2].innerText.trim();
      const jumlahTerjual = tds[3].innerText.trim();  // string saja
      const jumlahOmset = parseInt(tds[4].innerText.replace(/Rp\s?|-|\./g, '').trim()) || 0;

      const row = sheet.addRow([no, nama, satuan, jumlahTerjual, jumlahOmset]);
      
      // Format numFmt hanya untuk kolom jumlah omset (kolom ke-5)
      row.getCell(5).numFmt = '#,##0';

      row.eachCell(cell => {
        cell.border = {
          top: { style: 'thin' }, bottom: { style: 'thin' },
          left: { style: 'thin' }, right: { style: 'thin' }
        };
        cell.alignment = { vertical: 'middle' };
      });
    }
  });


  sheet.columns = [
    { width: 6 }, { width: 30 }, { width: 12 }, { width: 15 }, { width: 18 }
  ];

  // Save dan download
  const buffer = await workbook.xlsx.writeBuffer();
  saveAs(new Blob([buffer]), `Laporan_Omset_Per_Item_${startDate}_sd_${endDate}.xlsx`);
});

document.getElementById('btnExportWord').addEventListener('click', async () => {
  const { Document, Packer, Paragraph, Table, TableCell, TableRow, TextRun, WidthType, AlignmentType, BorderStyle } = window.docx;

  const toko = "<?= addslashes($storeName) ?>";
  const alamat = "<?= addslashes($storeAddress) ?>";

  const startDate = document.querySelector('input[name="start_date"]').value || "";
  const endDate = document.querySelector('input[name="end_date"]').value || "";
  const tanggal = (startDate && endDate) ? `Tanggal ${startDate} s.d. ${endDate}` : 'Tanggal -';

  const headerToko = new Paragraph({
    children: [new TextRun({ text: toko, bold: true, size: 32 })],
    alignment: AlignmentType.CENTER,
    spacing: { after: 200 }
  });

  const headerAlamat = new Paragraph({
    children: [new TextRun({ text: alamat, size: 24 })],
    alignment: AlignmentType.CENTER,
    spacing: { after: 300 }
  });

  const judul = new Paragraph({
    children: [new TextRun({ text: "Laporan Omset Per Item", bold: true, size: 28 })],
    alignment: AlignmentType.CENTER,
    spacing: { after: 100 }
  });

  const tanggalPar = new Paragraph({
    children: [new TextRun({ text: tanggal, size: 24 })],
    alignment: AlignmentType.CENTER,
    spacing: { after: 300 }
  });

  const tableRows = [
    new TableRow({
      children: [
        new TableCell({ children: [new Paragraph({ text: "No", bold: true })], shading: { fill: "CCE5FF" } }),
        new TableCell({ children: [new Paragraph({ text: "Nama Barang", bold: true })], shading: { fill: "CCE5FF" } }),
        new TableCell({ children: [new Paragraph({ text: "Satuan", bold: true })], shading: { fill: "CCE5FF" } }),
        new TableCell({ children: [new Paragraph({ text: "Jumlah Terjual", bold: true })], shading: { fill: "CCE5FF" } }),
        new TableCell({ children: [new Paragraph({ text: "Jumlah Omset", bold: true })], shading: { fill: "CCE5FF" } }),
      ]
    })
  ];

  const rows = document.querySelectorAll("#omsetPerItem tbody tr");
  rows.forEach(tr => {
    const tds = tr.querySelectorAll("td");
    if (tds.length >= 5) {
      tableRows.push(new TableRow({
        children: [
          new TableCell({ children: [new Paragraph(tds[0].innerText.trim())] }),
          new TableCell({ children: [new Paragraph(tds[1].innerText.trim())] }),
          new TableCell({ children: [new Paragraph(tds[2].innerText.trim())] }),
          new TableCell({ children: [new Paragraph(tds[3].innerText.trim())] }),
          new TableCell({ children: [new Paragraph(tds[4].innerText.trim())] }),
        ]
      }));
    }
  });

  const table = new Table({
    rows: tableRows,
    width: {
      size: 100,
      type: WidthType.PERCENTAGE,
    },
    borders: {
      top: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
      bottom: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
      left: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
      right: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
      insideHorizontal: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
      insideVertical: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
    },
  });

  const doc = new Document({
    sections: [{
      children: [
        headerToko,
        headerAlamat,
        judul,
        tanggalPar,
        table
      ],
    }],
  });

  const blob = await Packer.toBlob(doc);
  saveAs(blob, `Laporan_Omset_Per_Item_${startDate}_sd_${endDate}.docx`);
});

</script>
</body>
</html>
