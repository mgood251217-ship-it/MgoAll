<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/components/Table.php';
require_once BASE_PATH . '/controllers/ReportController.php';
require_once BASE_PATH . '/functions/helpers.php';

$reportController = new ReportController($koneksi);

$start_date = ($_GET['start_date'] ?? date('Y-m-d')). ' 00:00:00';
$end_date = ($_GET['end_date'] ?? date('Y-m-d')). ' 23:59:59';

$rekap = $reportController->transactionsCapture();

$dataPelunasan = $rekap['pelunasan']['data'];
$tfPelunasan            = $rekap['pelunasan']['total_tf'];
$cashPelunasan          = $rekap['pelunasan']['total_cash'];
$totalPelunasan  = $rekap['pelunasan']['grand_total'];

$htmlTablePelunasan = renderTable([
    'id'             => 'tableTransaksi',
    'data'           => $dataPelunasan,
    'table_class'    => 'table table-bordered table-striped',
    'thead_class'    => 'table-primary',
    'row_attributes' => function($row) {
        return 'data-date="' . date('Y-m-d', strtotime($row['payment_date'])) . '"';
    },
    'tfoot'          => '
        <tr class="table-success">
            <th colspan="5" class="text-end">Data Pelunasan Dari ' . sanitize(format_tanggal_id($start_date)) . ' Sampai ' . sanitize(format_tanggal_id($end_date)) . ' : </th>
            <th colspan="2">' . format_rupiah($cashPelunasan + $tfPelunasan) . '</th>
            <th>TF : ' . format_rupiah($tfPelunasan) . '</th>
            <th>CASH : ' . format_rupiah($cashPelunasan) . '</th>
            <th></th>
        </tr>
    ',
    'columns'        => [
        [
            'header' => 'No',
            'type'   => 'number'
        ],
        [
            'header' => 'Nomorator',
            'field'  => 'nomorator'
        ],
        [
            'header' => 'Nama',
            'field'  => 'customer_name'
        ],
        [
            'header' => 'Nominal DP',
            'type'   => 'currency',
            'field'  => 'dp_nominal'
        ],
        [
            'header' => 'Metode DP',
            'field'  => 'dp_method'
        ],
        [
            'header' => 'Tanggal DP',
            'render' => function($row) {
                return sanitize($row['dp_date']);
            }
        ],
        [
            'header' => 'Nominal Pelunasan',
            'type'   => 'currency',
            'field'  => 'nominal'
        ],
        [
            'header' => 'Metode Pelunasan',
            'field'  => 'payment_method'
        ],
        [
            'header' => 'Tanggal Pelunasan',
            'render' => function($row) {
                return sanitize($row['payment_date']);
            }
        ],
        [
            'header' => 'Cek Order',
            'render' => function($row) {
                $dOrder = date('Y-m-d', strtotime($row['order_date']));
                return '<a href="transaksi_detil?scrl_id=' . $row['order_id'] . '&start_date=' . $dOrder . '&end_date=' . $dOrder . '" target="_blank" class="btn btn-danger btn-sm">Cek Order</a>';
            }
        ]
    ]
]);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Transaksi Harian</title>
    <?php include BASE_PATH . '/header.php'; ?>
    <?php include BASE_PATH . '/export_libraries.php'; ?>
</head>
<body>
<div id="main-wrapper">
    <?php include BASE_PATH . '/navbar.php'; ?>
    <div id="main-content" <?= ($mode ?? 0) === 1 ? 'class="dark-mode"' : '' ?>>
        <?php include BASE_PATH . '/sidebar.php'; ?>

        <div id="page-content-wrapper">
            <?php require 'summary_cards.php'; ?>

            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h1 class="mb-0">Data Pelunasan Harian</h1>
                <?php $showExport = true; include BASE_PATH . '/interval_date.php'; ?>
            </div>
            
            <div id="tabelPelunasanWrapper">
                <?= $htmlTablePelunasan ?>
            </div>

        </div>
    </div>
    <?php include BASE_PATH . '/footer.php'; ?>
</div>

<script>
document.getElementById('start_date').addEventListener('change', function() {
    document.getElementById('filterForm').submit();
});
document.getElementById('end_date').addEventListener('change', function() {
    document.getElementById('filterForm').submit();
});
</script>
<script>
document.getElementById('btnExportExcel').addEventListener('click', async function () {
    const toko = "<?= addslashes($storeName) ?>";
    const alamat = "<?= addslashes($storeAddress) ?>";

    const startDate = document.getElementById('start_date')?.value || "";
    const endDate = document.getElementById('end_date')?.value || "";
    const tanggal = (startDate && endDate) ? `Tanggal ${startDate} s.d. ${endDate}` : 'Tanggal -';

    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet("Daftar Pelunasan");

    sheet.mergeCells("A1:F1");
    sheet.getCell("A1").value = toko;
    sheet.getCell("A1").alignment = { vertical: 'middle', horizontal: 'center' };
    sheet.getCell("A1").font = { bold: true, size: 16 };

    sheet.mergeCells("A2:F2");
    sheet.getCell("A2").value = alamat;
    sheet.getCell("A2").alignment = { vertical: 'middle', horizontal: 'center' };

    sheet.addRow([]);
    sheet.mergeCells("A4:F4");
    sheet.getCell("A4").value = "Transaksi Harian";
    sheet.getCell("A4").alignment = { vertical: 'middle', horizontal: 'center' };
    sheet.getCell("A4").font = { bold: true, size: 14 };

    sheet.mergeCells("A5:F5");
    sheet.getCell("A5").value = tanggal;
    sheet.getCell("A5").alignment = { vertical: 'middle', horizontal: 'center' };

    sheet.addRow([]);

    const headerRow = sheet.addRow(['No', 'Nomorator', 'Nama', 'Nominal DP', 'Metode DP', 'Tanggal DP', 'Nominal Pelunasan', 'Metode Pelunasan', 'Tanggal Pelunasan']);
    headerRow.font = { bold: true };
    headerRow.eachCell(cell => {
        cell.fill = {
            type: 'pattern',
            pattern: 'solid',
            fgColor: { argb: 'FFCCE5FF' }
        };
        cell.alignment = { horizontal: 'center', vertical: 'middle' };
        cell.border = {
            top: { style: 'thin' }, left: { style: 'thin' },
            bottom: { style: 'thin' }, right: { style: 'thin' }
        };
    });

    const rows = document.querySelectorAll("#tableTransaksi tbody tr");
    rows.forEach(tr => {
        const tds = tr.querySelectorAll("td");
        if (tds.length >= 6) {
            const rowDate = tr.getAttribute("data-date") || "";
            if (!startDate || !endDate || (rowDate >= startDate && rowDate <= endDate)) {
                const nominalDP = parseInt(tds[3].innerText.replace(/\./g, '')) || 0;
                const nominalPelunasan = parseInt(tds[6].innerText.replace(/\./g, '')) || 0;
                const row = sheet.addRow([
                    tds[0].innerText.trim(),
                    tds[1].innerText.trim(),
                    tds[2].innerText.trim(),
                    nominalDP,
                    tds[4].innerText.trim(),
                    tds[5].innerText.trim(),
                    nominalPelunasan,
                    tds[7].innerText.trim(),
                    tds[8].innerText.trim()
                ]);
                row.getCell(4).numFmt = '#,##0';
                row.getCell(7).numFmt = '#,##0';
                row.eachCell(cell => {
                    cell.border = {
                        top: { style: 'thin' }, left: { style: 'thin' },
                        bottom: { style: 'thin' }, right: { style: 'thin' }
                    };
                    cell.alignment = { vertical: 'middle' };
                });
            }
        }
    });

    sheet.columns = [
        { key: 'no', width: 6 },
        { key: 'nomorator', width: 15 },
        { key: 'nama', width: 20 },
        { key: 'nominaldp', width: 12 },
        { key: 'metodedp', width: 15 },
        { key: 'tanggaldp', width: 14 },
        { key: 'nominalpelunasan', width: 18 },
        { key: 'metodepelunasan', width: 18 },
        { key: 'statuspelunasan', width: 17 }
    ];

    const buffer = await workbook.xlsx.writeBuffer();
    saveAs(new Blob([buffer]), `Daftar_Pelunasan_${startDate}_sd_${endDate}.xlsx`);
});


document.getElementById('btnExportWord').addEventListener('click', async function () {
    const { Document, Packer, Paragraph, Table, TableCell, TableRow, TextRun, WidthType, AlignmentType, BorderStyle } = window.docx;

    const toko = "<?= addslashes($storeName) ?>";
    const alamat = "<?= addslashes($storeAddress) ?>";

    const startDate = document.getElementById('start_date')?.value || "";
    const endDate = document.getElementById('end_date')?.value || "";
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
        children: [new TextRun({ text: "Transaksi Pelunasan", bold: true, size: 28 })],
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
                new TableCell({ children: [new Paragraph({ text: "No", bold: true })] }),
                new TableCell({ children: [new Paragraph({ text: "Nomorator", bold: true })] }),
                new TableCell({ children: [new Paragraph({ text: "Nama", bold: true })] }),
                new TableCell({ children: [new Paragraph({ text: "Nominal DP", bold: true })] }),
                new TableCell({ children: [new Paragraph({ text: "Metode DP", bold: true })] }),
                new TableCell({ children: [new Paragraph({ text: "Tanggal DP", bold: true })] }),
                new TableCell({ children: [new Paragraph({ text: "Nominal Pelunasan", bold: true })] }),
                new TableCell({ children: [new Paragraph({ text: "Metode Pelunasan", bold: true })] }),
                new TableCell({ children: [new Paragraph({ text: "Tanggal Pelunasan", bold: true })] }),
            ]
        })
    ];

    const rows = document.querySelectorAll("#tableTransaksi tbody tr");
    rows.forEach(tr => {
        const tds = tr.querySelectorAll("td");
        if (tds.length >= 6) {
            const rowDate = tr.getAttribute("data-date") || "";
            if (!startDate || !endDate || (rowDate >= startDate && rowDate <= endDate)) {
                const nominalTextDP = tds[3].innerText.trim().replace(/\./g, '') || "0";
                const nominalTextPelunasan = tds[6].innerText.trim().replace(/\./g, '') || "0";
                tableRows.push(new TableRow({
                    children: [
                        new TableCell({ children: [new Paragraph(tds[0].innerText.trim())] }),
                        new TableCell({ children: [new Paragraph(tds[1].innerText.trim())] }),
                        new TableCell({ children: [new Paragraph(tds[2].innerText.trim())] }),
                        new TableCell({ children: [new Paragraph(nominalTextDP)] }),
                        new TableCell({ children: [new Paragraph(tds[4].innerText.trim())] }),
                        new TableCell({ children: [new Paragraph(tds[5].innerText.trim())] }),
                        new TableCell({ children: [new Paragraph(nominalTextPelunasan)] }),
                        new TableCell({ children: [new Paragraph(tds[7].innerText.trim())] }),
                        new TableCell({ children: [new Paragraph(tds[8].innerText.trim())] }),
                    ]
                }));
            }
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
    saveAs(blob, `Data_Pelunasan_${startDate}_sd_${endDate}.docx`);
});

</script>


</body>
</html>
