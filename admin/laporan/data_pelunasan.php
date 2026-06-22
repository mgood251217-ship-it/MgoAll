<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/components/Table.php';

$start_input = $_GET['start_date'] ?? date('Y-m-d');
$end_input   = $_GET['end_date'] ?? date('Y-m-d');

$filter_start_date = $start_input . ' 00:00:00';
$filter_end_date   = $end_input . ' 23:59:59';

$queryTransaksi = "
    SELECT 
        p.order_id,
        o.nomorator, 
        o.customer_name, 
        p.nominal, 
        p.payment_method, 
        p.status,
        p.date AS payment_date,
        o.date AS order_date
    FROM payment p
    JOIN orders o ON p.order_id = o.order_id
    WHERE o.store_id = ? AND p.date BETWEEN ? AND ?
    ORDER BY p.date ASC
";

$stmtTransaksi = $koneksi->prepare($queryTransaksi);
if (!$stmtTransaksi) {
    die("Query error: " . $koneksi->error);
}
$stmtTransaksi->bind_param("iss", $store_id, $filter_start_date, $filter_end_date);
$stmtTransaksi->execute();
$result = $stmtTransaksi->get_result();

$rawPayments = [];
$orderIds    = [];

while ($row = $result->fetch_assoc()) {
    $rawPayments[] = $row;
    $orderIds[$row['order_id']] = $row['order_id'];
}
$stmtTransaksi->close();

$paymentCounts = [];
$dpData = [];

if (!empty($orderIds)) {
    $inClause = implode(',', $orderIds);
    
    $resCount = $koneksi->query("SELECT order_id, COUNT(*) as cnt FROM payment WHERE order_id IN ($inClause) GROUP BY order_id");
    while ($c = $resCount->fetch_assoc()) {
        $paymentCounts[$c['order_id']] = (int)$c['cnt'];
    }

    $dpQuery = "
        SELECT p1.order_id, p1.nominal, p1.payment_method, p1.date
        FROM payment p1
        JOIN (
            SELECT order_id, MIN(date) as min_date
            FROM payment
            WHERE order_id IN ($inClause)
            GROUP BY order_id
        ) p2 ON p1.order_id = p2.order_id AND p1.date = p2.min_date
    ";
    $resDp = $koneksi->query($dpQuery);
    while ($d = $resDp->fetch_assoc()) {
        if (!isset($dpData[$d['order_id']])) {
            $dpData[$d['order_id']] = $d;
        }
    }
}

$dataPelunasan  = [];
$Pelunasan_Cash = 0;
$Pelunasan_TF   = 0;

foreach ($rawPayments as $row) {
    $oid    = $row['order_id'];
    $status = strtoupper($row['status']);
    $pCount = $paymentCounts[$oid] ?? 0;

    $tanggal_bayar = date('Y-m-d', strtotime($row['payment_date']));
    $tanggal_order = date('Y-m-d', strtotime($row['order_date']));

    $statusLabel = '';
    if ($status === 'LUNAS' && $pCount > 1) {
        $statusLabel = 'PELUNASAN';
    } elseif ($status === 'DP') {
        $statusLabel = 'BAYAR DP';
    } else {
        $statusLabel = 'LUNAS';
    }

    if ($tanggal_bayar > $tanggal_order) {
        $statusLabel = 'PELUNASAN';
    }

    if ($statusLabel === 'PELUNASAN') {
        $dp = $dpData[$oid] ?? null;

        $row['dp_nominal'] = $dp ? $dp['nominal'] : 0;
        $row['dp_method']  = $dp ? $dp['payment_method'] : '-';
        $row['dp_date']    = $dp ? $dp['date'] : '-';

        if ($row['payment_method'] == 'TF') {
            $Pelunasan_TF += $row['nominal'];
        } else {
            $Pelunasan_Cash += $row['nominal'];
        }

        $dataPelunasan[] = $row;
    }
}

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
            <th colspan="5" class="text-end">Data Pelunasan Dari ' . htmlspecialchars($start_input) . ' Sampai ' . htmlspecialchars($end_input) . ' : </th>
            <th colspan="2">' . number_format($Pelunasan_Cash + $Pelunasan_TF, 0, ',', '.') . '</th>
            <th>TF : ' . number_format($Pelunasan_TF, 0, ',', '.') . '</th>
            <th>CASH : ' . number_format($Pelunasan_Cash, 0, ',', '.') . '</th>
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
                return htmlspecialchars($row['dp_date']);
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
                return htmlspecialchars($row['payment_date']);
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
