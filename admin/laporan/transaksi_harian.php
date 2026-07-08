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

$dataTransaksi = $rekap['harian']['data'];
$tf            = $rekap['harian']['total_tf'];
$cash          = $rekap['harian']['total_cash'];
$total_harian  = $rekap['harian']['grand_total'];

$tfootHtml = '
    <tr class="table-success">
        <th colspan="3" class="text-end">Total Harian Dari ' . sanitize(format_tanggal_id($start_date)) . ' Sampai ' . sanitize(format_tanggal_id($end_date)) . ' : </th>
        <th>' . format_rupiah($total_harian) . '</th>
        <th>CASH : ' . format_rupiah($cash) . '</th>
        <th colspan="3">TF : ' . format_rupiah($tf) . '</th>
    </tr>
';

$htmlTableTransaksi = renderTable([
    'id'             => 'tableTransaksi',
    'data'           => $dataTransaksi,
    'table_class'    => 'table table-bordered table-striped',
    'thead_class'    => 'table-primary',

    'row_attributes' => function($row) {
        return 'data-date="' . date('Y-m-d', strtotime($row['payment_date'])) . '"';
    },
    'tfoot'          => (count($dataTransaksi) > 0) ? $tfootHtml : '',
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
            'header' => 'Nominal',
            'type'   => 'currency',
            'field'  => 'nominal'
        ],
        [
            'header' => 'Metode',
            'field'  => 'payment_method'
        ],
        [
            'header' => 'Status',
            'field'  => 'status_label'
        ],
        [
            'header' => 'Tanggal',
            'field'  => 'payment_date'
        ],
        [
            'header' => 'Aksi',
            'render' => function($row) {
                $date = date('Y-m-d', strtotime($row['order_date']));
                return '<a href="transaksi_detil?scrl_id=' . sanitize($row['order_id']) . '&start_date=' . $date . '&end_date=' . $date . '" target="_blank" class="btn btn-danger btn-sm">Cek Order</a>';
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
                <h1 class="mb-0">Transaksi Harian</h1>
                <div class="row g-2 align-items-end justify-content-end flex-nowrap" style="margin-bottom:0;">
                <?php $showSearch = false; include BASE_PATH . '/interval_date.php'; ?>
                <div class="col-auto align-self-end d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-success" id="btnExportExcel">Export Excel</button>
                    <button type="button" class="btn btn-primary" id="btnExportWord">Export Word</button>
                </div>
                </div>
            </div>
            <?= $htmlTableTransaksi; ?>
        </div>
    </div>
    <?php include BASE_PATH . '/footer.php'; ?>
</div>
<script>
// Export Excel
document.getElementById('btnExportExcel').addEventListener('click', async function () {
    const toko = "<?= addslashes($storeName) ?>";
    const alamat = "<?= addslashes($storeAddress) ?>";

    const startDate = document.getElementById('start_date')?.value || "";
    const endDate = document.getElementById('end_date')?.value || "";
    const tanggal = (startDate && endDate) ? `Tanggal ${startDate} s.d. ${endDate}` : 'Tanggal -';

    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet("Transaksi Harian");

    // Header
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

    // Header tabel
    const headerRow = sheet.addRow(['No', 'Nomorator', 'Nama', 'Nominal', 'Metode', 'Status']);
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

    // Data rows dari tabel HTML
    const rows = document.querySelectorAll("#tableTransaksi tbody tr");
    rows.forEach(tr => {
        const tds = tr.querySelectorAll("td");
        if (tds.length >= 6) {
            const rowDate = tr.getAttribute("data-date") || "";
            if (!startDate || !endDate || (rowDate >= startDate && rowDate <= endDate)) {
                const nominal = parseInt(tds[3].innerText.replace(/\./g, '')) || 0;
                const row = sheet.addRow([
                    tds[0].innerText.trim(),
                    tds[1].innerText.trim(),
                    tds[2].innerText.trim(),
                    nominal,
                    tds[4].innerText.trim(),
                    tds[5].innerText.trim()
                ]);
                row.getCell(4).numFmt = '#,##0';
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

    // Kolom lebar
    sheet.columns = [
        { key: 'no', width: 6 },
        { key: 'nomorator', width: 15 },
        { key: 'nama', width: 20 },
        { key: 'nominal', width: 12 },
        { key: 'metode', width: 15 },
        { key: 'status', width: 14 }
    ];

    const buffer = await workbook.xlsx.writeBuffer();
    saveAs(new Blob([buffer]), `Transaksi_Harian_${startDate}_sd_${endDate}.xlsx`);
});


// Export Word
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
        children: [new TextRun({ text: "Transaksi Harian", bold: true, size: 28 })],
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
                new TableCell({ children: [new Paragraph({ text: "Nominal", bold: true })] }),
                new TableCell({ children: [new Paragraph({ text: "Metode", bold: true })] }),
                new TableCell({ children: [new Paragraph({ text: "Status", bold: true })] }),
            ]
        })
    ];

    const rows = document.querySelectorAll("#tableTransaksi tbody tr");
    rows.forEach(tr => {
        const tds = tr.querySelectorAll("td");
        if (tds.length >= 6) {
            const rowDate = tr.getAttribute("data-date") || "";
            if (!startDate || !endDate || (rowDate >= startDate && rowDate <= endDate)) {
                const nominalText = tds[3].innerText.trim().replace(/\./g, '') || "0";
                tableRows.push(new TableRow({
                    children: [
                        new TableCell({ children: [new Paragraph(tds[0].innerText.trim())] }),
                        new TableCell({ children: [new Paragraph(tds[1].innerText.trim())] }),
                        new TableCell({ children: [new Paragraph(tds[2].innerText.trim())] }),
                        new TableCell({ children: [new Paragraph(nominalText)] }),
                        new TableCell({ children: [new Paragraph(tds[4].innerText.trim())] }),
                        new TableCell({ children: [new Paragraph(tds[5].innerText.trim())] }),
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
    saveAs(blob, `Transaksi_Harian_${startDate}_sd_${endDate}.docx`);
});

</script>



</body>
</html>
