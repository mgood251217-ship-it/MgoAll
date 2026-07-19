<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/components/Table.php';
require_once BASE_PATH . '/controllers/ReportController.php';

$reportController = new ReportController($koneksi);
$dataPemakaian = $reportController->productUsed();

$htmlTablePemakaian = renderTable([
    'id'          => 'tabelPemakaian',
    'data'        => $dataPemakaian,
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
            'header' => 'Jumlah Pemakaian',
            'render' => function($row) {
                return ($row['satuan'] === 'M2') 
                    ? number_format($row['total_pemakaian'], 2) 
                    : number_format($row['total_pemakaian']);
            }
        ]
    ]
]);
?>




<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Pemakaian Bahan Harian</title>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">Pemakaian Bahan Harian</h1>
            <?php $showExport = true; include BASE_PATH . '/interval_date.php'; ?>
        </div>

        <div class="table-responsive">
            <?= $htmlTablePemakaian ?>
        </div>
    </div>
  </div>
  <?php include BASE_PATH . '/footer.php'; ?>
</div>
<script>
document.getElementById('btnExportExcel').addEventListener('click', async function () {
    const toko = "<?= addslashes($storeName) ?>";
    const alamat = "<?= addslashes($storeAddress) ?>";

    const startDate = "<?= isset($start_date) ? $start_date : date('Y-m-d') ?>";
    const endDate = "<?= isset($end_date) ? $end_date : date('Y-m-d') ?>";

    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet("Pemakaian Barang");

    sheet.pageSetup.paperSize = 9;
    sheet.pageSetup.orientation = "portrait";

    sheet.mergeCells("A1:D1");
    sheet.getCell("A1").value = toko;
    sheet.getCell("A1").alignment = { horizontal: 'center' };
    sheet.getCell("A1").font = { bold: true, size: 16 };

    sheet.mergeCells("A2:D2");
    sheet.getCell("A2").value = alamat;
    sheet.getCell("A2").alignment = { horizontal: 'center' };

    sheet.addRow([]);
    sheet.mergeCells("A4:D4");
    sheet.getCell("A4").value = "Laporan Pemakaian Barang";
    sheet.getCell("A4").alignment = { horizontal: 'center' };
    sheet.getCell("A4").font = { bold: true, size: 14 };

    sheet.mergeCells("A5:D5");
    sheet.getCell("A5").value = `Tanggal: Dari ${startDate} sampai ${endDate}`;
    sheet.getCell("A5").alignment = { horizontal: 'center' };

    sheet.addRow([]);

    const headerRow = sheet.addRow(['No', 'Nama Barang', 'Satuan', 'Jumlah Pemakaian']);
    headerRow.font = { bold: true };
    headerRow.eachCell(cell => {
        cell.fill = {
            type: 'pattern',
            pattern: 'solid',
            fgColor: { argb: 'FFCCE5FF' }
        };
        cell.alignment = { horizontal: 'center' };
        cell.border = {
            top: { style: 'thin' }, bottom: { style: 'thin' },
            left: { style: 'thin' }, right: { style: 'thin' }
        };
    });

    const rows = document.querySelectorAll("table tbody tr");
    rows.forEach(tr => {
        const tds = tr.querySelectorAll("td");
        if (tds.length >= 4) {
            const jumlah = tds[3].innerText.trim();

            const row = sheet.addRow([
                tds[0].innerText.trim(),
                tds[1].innerText.trim(),
                tds[2].innerText.trim(),
                jumlah 
            ]);

            row.eachCell(cell => {
                cell.alignment = { vertical: 'middle' };
                cell.border = {
                    top: { style: 'thin' }, bottom: { style: 'thin' },
                    left: { style: 'thin' }, right: { style: 'thin' }
                };
            });
        }
    });


    sheet.columns = [
        { width: 6 }, { width: 30 }, { width: 10 }, { width: 20 }
    ];

    const fileName = `Pemakaian_Barang_${startDate}_sampai_${endDate}.xlsx`;
    const blob = await workbook.xlsx.writeBuffer();
    saveAs(new Blob([blob]), fileName);
});


document.getElementById("btnExportWord").addEventListener("click", async function () {
    const { Document, Packer, Paragraph, Table, TableRow, TableCell, TextRun, AlignmentType, WidthType, BorderStyle, PageOrientation } = window.docx;

    const toko = "<?= addslashes($storeName) ?>";
    const alamat = "<?= addslashes($storeAddress) ?>";
    const startDate = "<?= isset($start_date) ? $start_date : date('Y-m-d') ?>";
    const endDate = "<?= isset($end_date) ? $end_date : date('Y-m-d') ?>";

    const headerToko = new Paragraph({
        children: [new TextRun({ text: toko, bold: true, size: 32 })],
        alignment: AlignmentType.CENTER
    });
    const headerAlamat = new Paragraph({
        children: [new TextRun({ text: alamat, size: 24 })],
        alignment: AlignmentType.CENTER
    });
    const judul = new Paragraph({
        children: [new TextRun({ text: "Laporan Pemakaian Barang", bold: true, size: 28 })],
        alignment: AlignmentType.CENTER
    });
    const tanggalPar = new Paragraph({
        children: [new TextRun({ text: `Tanggal: Dari ${startDate} sampai ${endDate}`, size: 24 })],
        alignment: AlignmentType.CENTER
    });

    const tableRows = [];

    const headers = ['No', 'Nama Barang', 'Satuan', 'Jumlah Pemakaian'];
    tableRows.push(new TableRow({
        children: headers.map(h =>
            new TableCell({
                children: [new Paragraph({ text: h, bold: true })],
                borders: { top: { style: BorderStyle.SINGLE } }
            })
        )
    }));

    const rows = document.querySelectorAll("#tabelPemakaian tbody tr");
    if (rows.length === 0) {
        alert("Data tidak ditemukan atau ID tabel salah.");
        return;
    }

    rows.forEach(tr => {
        const tds = tr.querySelectorAll("td");
        if (tds.length >= 4) {
            const cells = Array.from(tds).map(td => {
                return new TableCell({
                    children: [new Paragraph(td.textContent.trim())],
                });
            });
            tableRows.push(new TableRow({ children: cells }));
        }
    });

    const table = new Table({
        rows: tableRows,
        width: { size: 100, type: WidthType.PERCENTAGE },
        borders: {
            top: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
            bottom: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
            left: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
            right: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
            insideHorizontal: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
            insideVertical: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
        }
    });

    const doc = new Document({
        sections: [{
            properties: {
                page: {
                    size: { orientation: PageOrientation.PORTRAIT }
                }
            },
            children: [headerToko, headerAlamat, judul, tanggalPar, new Paragraph(""), table]
        }]
    });

    const blob = await Packer.toBlob(doc);
    const fileName = `Pemakaian_Barang_${startDate}_sampai_${endDate}.docx`;
    saveAs(blob, fileName);
});

</script>
<script>
document.getElementById('start_date').addEventListener('change', function() {
    document.getElementById('filterForm').submit();
});
document.getElementById('end_date').addEventListener('change', function() {
    document.getElementById('filterForm').submit();
});
</script>
<script>
  document.querySelectorAll('#day, #month, #year').forEach(function(el) {
    el.addEventListener('change', function() {
      document.getElementById('filterForm').submit();
    });
  });

</script>
</body>
</html>
