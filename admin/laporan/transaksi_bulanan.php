<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/components/Table.php';

$startMonth = $_GET['start_month'] ?? date('m');
$startYear  = $_GET['start_year'] ?? date('Y');
$endMonth   = $_GET['end_month'] ?? date('m');
$endYear    = $_GET['end_year'] ?? date('Y');

$startDate = date('Y-m-d', strtotime("$startYear-$startMonth-01"));
$endDate = date('Y-m-t', strtotime("$endYear-$endMonth-01"));

$startDatef = $startDate . " 00:00:00";
$endDatef = $endDate . " 23:59:59";

$queryTransaksi = "
    SELECT 
        DATE(p.date) AS tanggal,
        SUM(p.nominal) AS total_nominal,
        COUNT(DISTINCT p.order_id) AS jumlah_order,
        COUNT(p.payment_id) AS jumlah_transaksi
    FROM payment p
    JOIN orders o ON p.order_id = o.order_id
    WHERE o.store_id = ?
      AND p.date BETWEEN ? AND ?
    GROUP BY DATE(p.date)
    ORDER BY DATE(p.date) ASC
";

$stmt = $koneksi->prepare($queryTransaksi);
if (!$stmt) {
    die("Query error: " . $koneksi->error);
}
$stmt->bind_param("iss", $store_id, $startDatef, $endDatef);
$stmt->execute();
$result = $stmt->get_result();

$total_bulan = 0;
$data_per_tanggal = [];
$total_transaksi_all = 0;

while ($row = $result->fetch_assoc()) {
    $tanggal = $row['tanggal'];
    $data_per_tanggal[$tanggal] = [
        'tanggal' => $tanggal,
        'total_nominal' => (float)$row['total_nominal'],
        'jumlah_order' => (int)$row['jumlah_order'],
        'jumlah_transaksi' => (int)$row['jumlah_transaksi'],
        'CASH' => 0,
        'TF' => 0
    ];
    $total_bulan += $row['total_nominal'];
    $total_transaksi_all += (int)$row['jumlah_transaksi'];
}

$queryMetode = "
    SELECT 
        DATE(p.date) AS tanggal,
        p.payment_method,
        SUM(p.nominal) AS total_nominal
    FROM payment p
    JOIN orders o ON p.order_id = o.order_id
    WHERE o.store_id = ?
      AND p.date BETWEEN ? AND ?
    GROUP BY DATE(p.date), p.payment_method
";

$stmt2 = $koneksi->prepare($queryMetode);
if (!$stmt2) {
    die("Query error (metode): " . $koneksi->error);
}
$stmt2->bind_param("iss", $store_id, $startDatef, $endDatef);
$stmt2->execute();
$result2 = $stmt2->get_result();

$total_bulan_tf = 0;
$total_bulan_cash = 0;

while ($row = $result2->fetch_assoc()) {
    $tanggal = $row['tanggal'];
    $metode = strtoupper(trim($row['payment_method']));
    $nominal = (float)$row['total_nominal'];

    if (in_array($metode, ['TF', 'TRANSFER'])) {
        $metode = 'TF';
    } elseif (in_array($metode, ['CASH', 'TUNAI'])) {
        $metode = 'CASH';
    } else {
        continue;
    }

    if (isset($data_per_tanggal[$tanggal])) {
        $data_per_tanggal[$tanggal][$metode] = $nominal;
    }

    if ($metode === 'TF') $total_bulan_tf += $nominal;
    if ($metode === 'CASH') $total_bulan_cash += $nominal;
}

$data_per_tanggal = array_values($data_per_tanggal);

$htmlTableTransaksi = renderTable([
    'id'          => 'tableTransaksi',
    'data'        => $data_per_tanggal,
    'table_class' => 'table table-bordered table-striped',
    'thead_class' => 'table-primary',
    'tfoot'       => '
    <tr class="table-success">
        <th colspan="2" class="text-end">Total Bulanan Dari ' . htmlspecialchars($startDate) . ' Sampai ' . htmlspecialchars($endDate) . ' : </th>
        <th>' . number_format($total_transaksi_all, 0, ',', '.') . '</th>
        <th>' . number_format($total_bulan, 0, ',', '.') . '</th>
        <th>' . number_format($total_bulan_cash, 0, ',', '.') . '</th>
        <th>' . number_format($total_bulan_tf, 0, ',', '.') . '</th>
    </tr>
',
    'columns'     => [
        [
            'header' => 'No',
            'type'   => 'number'
        ],
        [
            'header' => 'Tanggal',
            'field'  => 'tanggal'
        ],
        [
            'header' => 'Jumlah Transaksi',
            'field'  => 'jumlah_transaksi'
        ],
        [
            'header' => 'Total Nominal',
            'type'   => 'currency',
            'field'  => 'total_nominal'
        ],
        [
            'header' => 'CASH',
            'type'   => 'currency',
            'field'  => 'CASH'
        ],
        [
            'header' => 'TF',
            'type'   => 'currency',
            'field'  => 'TF'
        ]
    ]
]);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title>Transaksi Bulanan</title>
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
                <h1 class="mb-0">Transaksi Bulanan</h1>
                <?php
                $currentYear = date('Y');
                $currentMonth = date('m');

                // Default nilai jika GET tidak tersedia
                $start_month = $_GET['start_month'] ?? $currentMonth;
                $start_year  = $_GET['start_year'] ?? $currentYear;
                $end_month   = $_GET['end_month'] ?? $currentMonth;
                $end_year    = $_GET['end_year'] ?? $currentYear;
                ?>

                <form method="get" class="row g-2 align-items-end justify-content-end" id="filterForm" style="margin-bottom:0;">
                    <div class="col-auto">
                        <label for="start_month" class="form-label mb-0">Dari Bulan</label>
                        <select name="start_month" id="start_month" class="form-select">
                            <?php 
                            for ($i = 1; $i <= 12; $i++):
                                $val = str_pad($i, 2, '0', STR_PAD_LEFT);
                                $selected = ($start_month == $val) ? 'selected' : '';
                            ?>
                                <option value="<?= $val ?>" <?= $selected ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label for="start_year" class="form-label mb-0">Tahun</label>
                        <select name="start_year" id="start_year" class="form-select">
                            <?php 
                            for ($i = $currentYear; $i >= 2023; $i--):
                                $selected = ($start_year == $i) ? 'selected' : '';
                            ?>
                                <option value="<?= $i ?>" <?= $selected ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label for="end_month" class="form-label mb-0">Sampai Bulan</label>
                        <select name="end_month" id="end_month" class="form-select">
                            <?php 
                            for ($i = 1; $i <= 12; $i++):
                                $val = str_pad($i, 2, '0', STR_PAD_LEFT);
                                $selected = ($end_month == $val) ? 'selected' : '';
                            ?>
                                <option value="<?= $val ?>" <?= $selected ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label for="end_year" class="form-label mb-0">Tahun</label>
                        <select name="end_year" id="end_year" class="form-select">
                            <?php 
                            for ($i = $currentYear; $i >= 2023; $i--):
                                $selected = ($end_year == $i) ? 'selected' : '';
                            ?>
                                <option value="<?= $i ?>" <?= $selected ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-auto align-self-end d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-success" id="btnExportExcel">Export Excel</button>
                        <button type="button" class="btn btn-primary" id="btnExportWord">Export Word</button>
                    </div>
                </form>
            </div>
            
            <div class="table-responsive">
                <?= $htmlTableTransaksi ?>
            </div>

        </div>
    </div>
    <?php include BASE_PATH . '/footer.php'; ?>
</div>

<script>
// Perbaikan Export Excel: Mendukung 6 Kolom
document.getElementById('btnExportExcel').addEventListener('click', async function () {
    const toko = "<?= addslashes($storeName) ?>";
    const alamat = "<?= addslashes($storeAddress) ?>";
    const bulanTahun = "<?= $start_month . '/' . $start_year . ' sd ' . $end_month . '/' . $end_year ?>";

    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet("Transaksi Bulanan");

    sheet.mergeCells("A1:F1");
    sheet.getCell("A1").value = toko;
    sheet.getCell("A1").alignment = { horizontal: 'center' };
    sheet.getCell("A1").font = { bold: true, size: 16 };

    sheet.mergeCells("A2:F2");
    sheet.getCell("A2").value = alamat;
    sheet.getCell("A2").alignment = { horizontal: 'center' };

    sheet.addRow([]);
    sheet.mergeCells("A4:F4");
    sheet.getCell("A4").value = "Transaksi Bulanan";
    sheet.getCell("A4").alignment = { horizontal: 'center' };
    sheet.getCell("A4").font = { bold: true, size: 14 };

    sheet.mergeCells("A5:F5");
    sheet.getCell("A5").value = `Periode: ${bulanTahun}`;
    sheet.getCell("A5").alignment = { horizontal: 'center' };

    sheet.addRow([]);

    const headerRow = sheet.addRow(['No', 'Tanggal', 'Jumlah Transaksi', 'Total Nominal', 'CASH', 'TF']);
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

    const rows = document.querySelectorAll("#tableTransaksi tbody tr");
    rows.forEach(tr => {
        const tds = tr.querySelectorAll("td");
        if (tds.length >= 6) { // Harus 6 Kolom
            const nominal = parseInt(tds[3].innerText.replace(/\./g, '')) || 0;
            const cash = parseInt(tds[4].innerText.replace(/\./g, '')) || 0;
            const tf = parseInt(tds[5].innerText.replace(/\./g, '')) || 0;

            const row = sheet.addRow([
                tds[0].innerText.trim(),
                tds[1].innerText.trim(),
                tds[2].innerText.trim(),
                nominal,
                cash,
                tf
            ]);
            
            row.getCell(4).numFmt = '#,##0'; // Total Nominal
            row.getCell(5).numFmt = '#,##0'; // CASH
            row.getCell(6).numFmt = '#,##0'; // TF
            
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
        { width: 6 }, { width: 15 }, { width: 20 }, { width: 18 }, { width: 18 }, { width: 18 }
    ];

    const blob = await workbook.xlsx.writeBuffer();
    saveAs(new Blob([blob]), `Transaksi_Bulanan_${bulanTahun.replace(/\//g, '-')}.xlsx`);
});

// Perbaikan Export Word: Mendukung 6 Kolom
document.getElementById('btnExportWord').addEventListener('click', async function () {
    const { Document, Packer, Paragraph, Table, TableRow, TableCell, TextRun, AlignmentType, WidthType, BorderStyle } = window.docx;

    const toko = "<?= addslashes($storeName) ?>";
    const alamat = "<?= addslashes($storeAddress) ?>";
    const bulanTahun = "<?= $start_month . '/' . $start_year . ' sd ' . $end_month . '/' . $end_year ?>";

    const headerToko = new Paragraph({
        children: [new TextRun({ text: toko, bold: true, size: 32 })],
        alignment: AlignmentType.CENTER
    });
    const headerAlamat = new Paragraph({
        children: [new TextRun({ text: alamat, size: 24 })],
        alignment: AlignmentType.CENTER
    });
    const judul = new Paragraph({
        children: [new TextRun({ text: "Transaksi Bulanan", bold: true, size: 28 })],
        alignment: AlignmentType.CENTER
    });
    const bulanPar = new Paragraph({
        children: [new TextRun({ text: `Periode: ${bulanTahun}`, size: 24 })],
        alignment: AlignmentType.CENTER,
        spacing: { after: 300 }
    });

    const tableRows = [];

    // Header table (6 Kolom)
    tableRows.push(new TableRow({
        children: ['No', 'Tanggal', 'Jumlah Transaksi', 'Total Nominal', 'CASH', 'TF'].map(h =>
            new TableCell({
                children: [new Paragraph({ text: h, bold: true })],
                borders: { top: { style: BorderStyle.SINGLE } },
                margins: { top: 100, bottom: 100, left: 100, right: 100 }
            })
        )
    }));

    const rows = document.querySelectorAll("#tableTransaksi tbody tr");
    rows.forEach(tr => {
        const tds = tr.querySelectorAll("td");
        if (tds.length >= 6) {
            const row = new TableRow({
                children: Array.from(tds).map(td =>
                    new TableCell({
                        children: [new Paragraph(td.innerText.trim())],
                        margins: { top: 100, bottom: 100, left: 100, right: 100 }
                    })
                )
            });
            tableRows.push(row);
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
            children: [headerToko, headerAlamat, judul, bulanPar, table]
        }]
    });

    const blob = await Packer.toBlob(doc);
    saveAs(blob, `Transaksi_Bulanan_${bulanTahun.replace(/\//g, '-')}.docx`);
});
</script>
<script>
const form = document.getElementById('filterForm');
['start_month', 'start_year', 'end_month', 'end_year'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => {
        form.submit();
    });
});
</script>
</body>
</html>