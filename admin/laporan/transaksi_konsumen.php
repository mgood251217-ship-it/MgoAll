<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/components/Table.php';
require_once BASE_PATH . '/controllers/ReportController.php';
require_once BASE_PATH . '/functions/helpers.php';

$reportController = new ReportController($koneksi);

$start_date = ($_GET['start_date'] ?? date('Y-m-d')). ' 00:00:00';
$end_date = ($_GET['end_date'] ?? date('Y-m-d')). ' 23:59:59';

$data = $reportController->allDetailOrderByIntervalDate();
$productData = $data['product'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Transaksi Per Konsumen</title>
  <?php include BASE_PATH . '/header.php'; ?>
  <?php include BASE_PATH . '/export_libraries.php'; ?>
  
  <style>
    .nota-block { margin-bottom: 40px; border: 1px solid #ccc; padding: 20px; border-radius: 10px; }
    .nota-header { display: flex; justify-content: space-between; flex-wrap: wrap; }
    .payment-info > div {
      border: 1px solid #dee2e6;
      border-radius: 6px;
      padding: 10px;
      margin-right: 10px;
      min-width: 180px;
      background-color: #f8f9fa;
    }
    .payment-info {
      display: flex;
      gap: 10px;
      overflow-x: auto;
      padding-top: 10px;
    }
    .payment-info::-webkit-scrollbar {
      height: 6px;
    }
    .payment-info::-webkit-scrollbar-thumb {
      background-color: rgba(0,0,0,0.1);
      border-radius: 3px;
    }
  </style>
</head>
<body>
<div id="main-wrapper" <?= (isset($mode) && $mode === 1) ? 'class="dark-mode"' : '' ?>>
  <?php include BASE_PATH . '/navbar.php'; ?>

  <div id="main-content" <?= (isset($mode) && $mode === 1) ? 'class="dark-mode"' : '' ?>>
    <?php include BASE_PATH . '/sidebar.php'; ?>

    <div id="page-content-wrapper">
      <?php require 'summary_cards.php'; ?>

      <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Transaksi Per Konsumen</h1>
        <?php $showExport = true; include BASE_PATH . '/interval_date.php'; ?>
      </div>

      <?php if (empty($productData)): ?>
        <div class="alert alert-warning">Tidak ada transaksi pada tanggal ini.</div>
      <?php else: ?>
        <?php foreach ($productData as $judul => $items): ?>
        <div class="produk-block">
            <div class="produk-title fw-bold mb-2"><?= sanitize($judul) ?></div>
            <div class="table-responsive">
                <?php $htmlTableKonsumen = renderTable([
                    'data'        => $items,
                    'table_class' => 'table table-bordered table-sm dataTable',
                    'thead_class' => 'table-primary',
                    'columns'     => [
                        [
                            'header' => 'No',
                            'type'   => 'number'
                        ],
                        [
                            'header' => 'Nomorator',
                            'field'  => 'nomorator'
                        ],
                        [
                            'header' => 'Tanggal',
                            'field'  => 'date',
                            'render' => function($row) {
                                return format_tanggal_id($row['date']);
                            }
                        ],
                        [
                            'header' => 'Judul',
                            'field'  => 'judul'
                        ],
                        [
                            'header' => 'Ukuran',
                            'field'  => 'size'
                        ],
                        [
                            'header' => 'Finishing',
                            'field'  => 'finishing_names'
                        ],
                        [
                            'header' => 'Harga Produk',
                            'type'   => 'currency',
                            'field'  => 'price'
                        ],
                        [
                            'header' => 'Qty',
                            'field'  => 'quantity'
                        ],
                        [
                            'header' => 'Subtotal',
                            'type'   => 'currency',
                            'field'  => 'amount'
                        ],
                        [
                            'header' => 'Cek Order',
                            'render' => function($row) {
                                $date = date('Y-m-d', strtotime($row['date']));
                                return '<a href="transaksi_detil?scrl_id=' . sanitize($row['order_id']) . '&start_date=' . $date . '&end_date=' . $date . '" target="_blank" class="btn btn-primary btn-sm" style="padding: 2px 8px;">Cek Order</a>';
                            }
                        ]
                    ]
                ]);
                
                echo $htmlTableKonsumen;
                ?>
            </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php include BASE_PATH . '/footer.php'; ?>
</div>
<script>
document.getElementById('btnExportExcel').addEventListener('click', async function () {
    const toko = "<?= addslashes($storeName) ?>";
    const alamat = "<?= addslashes($storeAddress) ?>";

    const startDate = document.getElementById('start_date')?.value ?? '';
    const endDate = document.getElementById('end_date')?.value ?? '';
    const tanggal = (startDate === endDate || !endDate) ? startDate : `${startDate} s.d. ${endDate}`;

    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet("Sortir Per Konsumen");

    // Header toko & alamat
    sheet.mergeCells("A1:H1");
    sheet.getCell("A1").value = toko;
    sheet.getCell("A1").alignment = { vertical: 'middle', horizontal: 'center' };
    sheet.getCell("A1").font = { bold: true, size: 16 };

    sheet.mergeCells("A2:H2");
    sheet.getCell("A2").value = alamat;
    sheet.getCell("A2").alignment = { vertical: 'middle', horizontal: 'center' };

    sheet.addRow([]);
    sheet.mergeCells("A4:H4");
    sheet.getCell("A4").value = "Transaksi Per Item";
    sheet.getCell("A4").alignment = { vertical: 'middle', horizontal: 'center' };
    sheet.getCell("A4").font = { bold: true, size: 14 };

    sheet.mergeCells("A5:H5");
    sheet.getCell("A5").value = `Tanggal ${tanggal}`;
    sheet.getCell("A5").alignment = { vertical: 'middle', horizontal: 'center' };

    sheet.addRow([]);

    const produkBlocks = document.querySelectorAll('.produk-block');

    produkBlocks.forEach(block => {
        const judulProdukElem = block.querySelector('.produk-title');
        const judulProduk = judulProdukElem ? judulProdukElem.innerText.trim() : "Produk";

        const lastRowNumber = sheet.lastRow ? sheet.lastRow.number + 1 : 1;
        sheet.mergeCells(`A${lastRowNumber}:H${lastRowNumber}`);
        const judulCell = sheet.getCell(`A${lastRowNumber}`);
        judulCell.value = judulProduk;
        judulCell.font = { bold: true, size: 13 };
        judulCell.alignment = { vertical: 'middle', horizontal: 'left' };

        const headerRowNumber = lastRowNumber + 1;
        const headerRow = sheet.getRow(headerRowNumber);
        headerRow.values = ['Nomorator', 'Tanggal', 'Pelanggan', 'Ukuran', 'Finishing', 'Harga Produk', 'Qty', 'Subtotal'];
        headerRow.font = { bold: true };
        headerRow.eachCell(cell => {
            cell.fill = {
                type: 'pattern',
                pattern: 'solid',
                fgColor: { argb: 'FFCCE5FF' }
            };
            cell.alignment = { horizontal: 'center', vertical: 'middle' };
            cell.border = {
                top: { style: 'thin' },
                left: { style: 'thin' },
                bottom: { style: 'thin' },
                right: { style: 'thin' }
            };
        });

        const table = block.querySelector('table.dataTable');
        if (!table) return;

        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(tr => {
            const tds = tr.querySelectorAll('td');
            if (tds.length >= 0) {
                const nomorator = tds[0].innerText.trim();
                const tanggal = tds[1].innerText.trim();
                const pelanggan = tds[2].innerText.trim();
                const ukuran = tds[3].innerText.trim();
                const finishing = tds[4].innerText.trim();
                const hargaProduk = parseInt(tds[5].innerText.replace(/[^0-9]/g, '')) || 0;
                const qty = parseInt(tds[6].innerText.replace(/[^0-9]/g, '')) || 0;
                const subtotal = parseInt(tds[7].innerText.replace(/[^0-9]/g, '')) || 0;

                const newRow = sheet.addRow([
                    nomorator, tanggal, pelanggan, ukuran, finishing, hargaProduk, qty, subtotal
                ]);

                [6, 7, 8].forEach(i => {
                    newRow.getCell(i).numFmt = '#,##0';
                });

                newRow.eachCell(cell => {
                    cell.border = {
                        top: { style: 'thin' },
                        left: { style: 'thin' },
                        bottom: { style: 'thin' },
                        right: { style: 'thin' }
                    };
                    cell.alignment = { vertical: 'middle' };
                });
            }
        });

        sheet.addRow([]);
    });

    sheet.columns = [
        { key: 'nomorator', width: 15 },
        { key: 'tanggal', width: 15 },
        { key: 'pelanggan', width: 25 },
        { key: 'ukuran', width: 15 },
        { key: 'finishing', width: 20 },
        { key: 'harga_produk', width: 15 },
        { key: 'qty', width: 8 },
        { key: 'subtotal', width: 18 },
    ];

    const blob = await workbook.xlsx.writeBuffer();
    saveAs(new Blob([blob]), `Transaksi_Per_Item_${tanggal}.xlsx`);
});

document.getElementById('btnExportWord').addEventListener('click', function () {
    const { Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell, HeadingLevel, AlignmentType, WidthType, BorderStyle } = window.docx;

    const toko = "<?= addslashes($storeName) ?>";
    const alamat = "<?= addslashes($storeAddress) ?>";

    const startDate = document.getElementById('start_date')?.value ?? '';
    const endDate = document.getElementById('end_date')?.value ?? '';
    const tanggal = (startDate === endDate || !endDate) ? startDate : `${startDate} s.d. ${endDate}`;

    const children = [];

    children.push(
        new Paragraph({ text: toko, heading: HeadingLevel.TITLE, alignment: AlignmentType.CENTER, spacing: { after: 100 } }),
        new Paragraph({ text: alamat, alignment: AlignmentType.CENTER, spacing: { after: 150 } }),
        new Paragraph({ text: "Laporan Sortir Per Konsumen", heading: HeadingLevel.HEADING_1, alignment: AlignmentType.CENTER, spacing: { after: 150 } }),
        new Paragraph({ text: `Tanggal: ${tanggal}`, alignment: AlignmentType.CENTER, spacing: { after: 300 } })
    );

    const tables = document.querySelectorAll('.dataTable');

    tables.forEach(table => {
        const produkTitle = table.closest('.produk-block')?.querySelector('.produk-title')?.innerText ?? 'Produk';

        children.push(new Paragraph({
            text: produkTitle,
            heading: HeadingLevel.HEADING_2,
            spacing: { before: 200, after: 150 }
        }));

        const headerRow = table.querySelector('thead tr');
        const bodyRows = table.querySelectorAll('tbody tr');
        const headerCells = headerRow.querySelectorAll('th');
        const headers = Array.from(headerCells).map(th => th.innerText.trim());

        const tableRows = [];

        tableRows.push(new TableRow({
            children: headers.map(header => new TableCell({
                width: { size: 100 / headers.length, type: WidthType.PERCENTAGE },
                borders: fullBorder(),
                children: [new Paragraph({ text: header, bold: true, alignment: AlignmentType.CENTER })]
            }))
        }));

        bodyRows.forEach(tr => {
            const tds = tr.querySelectorAll('td');
            const rowCells = Array.from(tds).map(td => new TableCell({
                borders: fullBorder(),
                children: [new Paragraph(td.innerText.trim())]
            }));
            tableRows.push(new TableRow({ children: rowCells }));
        });

        children.push(new Table({
            rows: tableRows,
            width: { size: 100, type: WidthType.PERCENTAGE }
        }));

        children.push(new Paragraph({ text: "", spacing: { after: 150 } }));
    });

    const doc = new Document({ sections: [{ children }] });

    Packer.toBlob(doc).then(blob => {
        saveAs(blob, `Transaksi_Per_Item_${tanggal}.docx`);
    });

    function fullBorder() {
        return {
            top: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
            bottom: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
            left: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
            right: { style: BorderStyle.SINGLE, size: 1, color: "000000" }
        };
    }
});
</script>

</body>
</html>