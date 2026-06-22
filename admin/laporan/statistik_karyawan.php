<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/components/Table.php';

$start_input = $_GET['start_date'] ?? date('Y-m-01');
$end_input   = $_GET['end_date'] ?? date('Y-m-d');

$filter_start_date = $start_input . ' 00:00:00';
$filter_end_date   = $end_input . ' 23:59:59';

$stmtUsers = $koneksi->prepare("SELECT user_id, initial, name FROM users WHERE store_id = ?");
$stmtUsers->bind_param("i", $store_id);
$stmtUsers->execute();
$resultUsers = $stmtUsers->get_result();
$users = [];
$usernames = [];
while ($row = $resultUsers->fetch_assoc()) {
    $users[$row['user_id']]     = $row['initial'];
    $usernames[$row['user_id']] = $row['name'];
}
$stmtUsers->close();

$receiverCounts = array_fill_keys(array_keys($users), 0);
$stmtOrders = $koneksi->prepare("
    SELECT o.user_id 
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    WHERE DATE(o.date) BETWEEN ? AND ?
");
$stmtOrders->bind_param("ss", $filter_start_date, $filter_end_date);
$stmtOrders->execute();
$resultOrders = $stmtOrders->get_result();
while ($row = $resultOrders->fetch_assoc()) {
    $uid = $row['user_id'];
    if (isset($receiverCounts[$uid])) {
        $receiverCounts[$uid]++;
    }
}
$stmtOrders->close();

$pickupCounts = array_fill_keys(array_keys($users), 0);
$stmtHitung = $koneksi->prepare("
    SELECT p.user_id 
    FROM projects p
    JOIN users u ON p.user_id = u.user_id
    WHERE DATE(p.date) BETWEEN ? AND ? 
      AND p.process = 'DIAMBIL'
");
$stmtHitung->bind_param("ss", $filter_start_date, $filter_end_date);
$stmtHitung->execute();
$resultHitung = $stmtHitung->get_result();
while ($row = $resultHitung->fetch_assoc()) {
    $uid = $row['user_id'];
    if (isset($pickupCounts[$uid])) {
        $pickupCounts[$uid]++;
    }
}
$stmtHitung->close();

$settingCounts = array_fill_keys(array_keys($users), 0);
$stmt = $koneksi->prepare("
    SELECT o.order_id, o.user_id 
    FROM orders o
    WHERE DATE(o.date) BETWEEN ? AND ?
");
$stmt->bind_param("ss", $filter_start_date, $filter_end_date);
$stmt->execute();
$result = $stmt->get_result();

$stmtCheck = $koneksi->prepare("SELECT 1 FROM order_items WHERE order_id = ? AND UPPER(judul) = 'SETTING' LIMIT 1");
while ($row = $result->fetch_assoc()) {
    $order_id = $row['order_id'];
    $user_id  = $row['user_id'];

    $stmtCheck->bind_param("i", $order_id);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();

    if ($resultCheck->num_rows > 0 && isset($settingCounts[$user_id])) {
        $settingCounts[$user_id]++;
    }
}
$stmtCheck->close();
$stmt->close();

$omsetPerUser = array_fill_keys(array_keys($users), 0);
$sql = "SELECT nominal, order_id FROM payment WHERE date BETWEEN ? AND ?";
$stmt = $koneksi->prepare($sql);
$stmt->bind_param("ss", $filter_start_date, $filter_end_date);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $orderIds = explode(',', $row['order_id']);
    $nominalPerOrder = $row['nominal'] / count($orderIds); 

    foreach ($orderIds as $oid) {
        $oid = trim($oid);
        $orderRes = $koneksi->query("SELECT user_id, store_id FROM orders WHERE order_id = '$oid'");
        if ($orderRes && $ord = $orderRes->fetch_assoc()) {
            if ((int)$ord['store_id'] === (int)$store_id) {
                $uid = $ord['user_id'];
                if (isset($omsetPerUser[$uid])) {
                    $omsetPerUser[$uid] += $nominalPerOrder;
                }
            }
        }
    }
}
$stmt->close();

$dataStatistik = [];

$max1 = max($pickupCounts);
$maxId1 = array_search($max1, $pickupCounts);
$row1 = ['no' => 1, 'keterangan' => 'PENGAMBILAN BARANG TERBANYAK', 'is_currency' => false];
foreach ($users as $id => $initial) { $row1['op_'.$id] = $pickupCounts[$id]; }
$row1['hasil'] = ($max1 > 0 && isset($usernames[$maxId1])) ? $usernames[$maxId1] : '-';
$dataStatistik[] = $row1;

$max2 = max($receiverCounts);
$maxId2 = array_search($max2, $receiverCounts);
$row2 = ['no' => 2, 'keterangan' => 'PALING BANYAK NERIMA KONSUMEN', 'is_currency' => false];
foreach ($users as $id => $initial) { $row2['op_'.$id] = $receiverCounts[$id]; }
$row2['hasil'] = ($max2 > 0 && isset($usernames[$maxId2])) ? $usernames[$maxId2] : '-';
$dataStatistik[] = $row2;

$max3 = max($settingCounts);
$maxId3 = array_search($max3, $settingCounts);
$row3 = ['no' => 3, 'keterangan' => 'PALING BANYAK SETTING', 'is_currency' => false];
foreach ($users as $id => $initial) { $row3['op_'.$id] = $settingCounts[$id]; }
$row3['hasil'] = ($max3 > 0 && isset($usernames[$maxId3])) ? $usernames[$maxId3] : '-';
$dataStatistik[] = $row3;

$max4 = max($omsetPerUser);
$maxId4 = array_search($max4, $omsetPerUser);
$row4 = ['no' => 4, 'keterangan' => 'OMSET TERBANYAK', 'is_currency' => true];
foreach ($users as $id => $initial) { $row4['op_'.$id] = $omsetPerUser[$id]; }
$row4['hasil'] = ($max4 > 0 && isset($usernames[$maxId4])) ? $usernames[$maxId4] : '-';
$dataStatistik[] = $row4;

$columns = [
    ['header' => 'No', 'field' => 'no'],
    ['header' => 'Keterangan', 'field' => 'keterangan']
];
foreach ($users as $id => $initial) {
    $columns[] = [
        'header' => htmlspecialchars($initial),
        'render' => function($row) use ($id) {
            $val = $row['op_'.$id];
            return $row['is_currency'] ? number_format($val, 0, ',', '.') : $val;
        }
    ];
}
$columns[] = ['header' => 'Hasil', 'field' => 'hasil'];

$htmlTableStatistik = renderTable([
    'id'          => 'tableStatistik',
    'data'        => $dataStatistik,
    'table_class' => 'table table-bordered table-striped text-center align-middle',
    'thead_class' => 'table-primary',
    'columns'     => $columns
]);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Statistik Karyawan</title>
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
                <h1 class="mb-0">Statistik Karyawan</h1>
                <div class="row g-2 align-items-end justify-content-end flex-nowrap" style="margin-bottom:0;">
                    <?php $showExport = false; include BASE_PATH . '/interval_date.php'; ?>
                    <div class="col-auto align-self-end d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-success" id="btnExportExcel">Export Excel</button>
                        <button type="button" class="btn btn-primary" id="btnExportWord">Export Word</button>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <?= $htmlTableStatistik ?>
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

function getTableData() {
    const table = document.getElementById('tableStatistik');
    const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.innerText.trim());
    const dataRows = [];

    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach((td, idx) => {
            let val = td.innerText.trim();
            if (idx >= 2 && idx < headers.length - 1) {
                val = parseInt(val.replace(/\./g, '')) || 0;
            }
            row.push(val);
        });
        if(row.length > 0) dataRows.push(row);
    });
    
    return { headers, dataRows };
}

document.getElementById('btnExportExcel').addEventListener('click', async function () {
    const toko = "<?= addslashes($storeName) ?>";
    const alamat = "<?= addslashes($storeAddress) ?>";
    const startDate = document.getElementById('start_date')?.value || "";
    const endDate = document.getElementById('end_date')?.value || "";
    const tanggal = (startDate && endDate) ? `Tanggal ${startDate} s.d. ${endDate}` : 'Tanggal -';

    const { headers, dataRows } = getTableData();

    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet("Rekap Operator", {
        pageSetup: { paperSize: 9, orientation: 'landscape', fitToPage: true }
    });

    const totalCols = headers.length;

    sheet.mergeCells(1, 1, 1, totalCols);
    sheet.getCell("A1").value = toko;
    sheet.getCell("A1").alignment = { vertical: 'middle', horizontal: 'center' };
    sheet.getCell("A1").font = { bold: true, size: 16 };

    sheet.mergeCells(2, 1, 2, totalCols);
    sheet.getCell("A2").value = alamat;
    sheet.getCell("A2").alignment = { vertical: 'middle', horizontal: 'center' };

    sheet.addRow([]);
    sheet.mergeCells(4, 1, 4, totalCols);
    sheet.getCell("A4").value = "Statistik Karyawan";
    sheet.getCell("A4").alignment = { vertical: 'middle', horizontal: 'center' };
    sheet.getCell("A4").font = { bold: true, size: 14 };

    sheet.mergeCells(5, 1, 5, totalCols);
    sheet.getCell("A5").value = tanggal;
    sheet.getCell("A5").alignment = { vertical: 'middle', horizontal: 'center' };

    sheet.addRow([]);

    const headerRow = sheet.addRow(headers);
    headerRow.font = { bold: true };
    headerRow.eachCell(cell => {
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFCCE5FF' } };
        cell.border = { top: { style: 'thin' }, bottom: { style: 'thin' }, left: { style: 'thin' }, right: { style: 'thin' } };
        cell.alignment = { horizontal: 'center', vertical: 'middle' };
    });

    dataRows.forEach(data => {
        const row = sheet.addRow(data);
        row.eachCell((cell, colNumber) => {
            cell.border = { top: { style: 'thin' }, bottom: { style: 'thin' }, left: { style: 'thin' }, right: { style: 'thin' } };
            cell.alignment = { vertical: 'middle', horizontal: 'center' };
            if (colNumber > 2 && colNumber < headers.length) {
                cell.numFmt = '#,##0';
            }
        });
        row.getCell(2).alignment = { vertical: 'middle', horizontal: 'left' };
    });

    sheet.getColumn(1).width = 5;
    sheet.getColumn(2).width = 35;
    sheet.getColumn(headers.length).width = 20;
    for(let i = 3; i < headers.length; i++) {
        sheet.getColumn(i).width = 12;
    }

    const buffer = await workbook.xlsx.writeBuffer();
    saveAs(new Blob([buffer]), `Rekap_Operator_${startDate}_sd_${endDate}.xlsx`);
});

document.getElementById('btnExportWord').addEventListener('click', async function () {
    const { Document, Packer, Paragraph, Table, TableCell, TableRow, TextRun, WidthType, AlignmentType, BorderStyle, VerticalAlign } = window.docx;

    const toko = "<?= addslashes($storeName) ?>";
    const alamat = "<?= addslashes($storeAddress) ?>";
    const startDate = document.getElementById('start_date')?.value || "";
    const endDate = document.getElementById('end_date')?.value || "";
    const tanggal = (startDate && endDate) ? `Tanggal ${startDate} s.d. ${endDate}` : 'Tanggal -';

    const { headers, dataRows } = getTableData();

    const headerToko = new Paragraph({ children: [new TextRun({ text: toko, bold: true, size: 32 })], alignment: AlignmentType.CENTER, spacing: { after: 200 } });
    const headerAlamat = new Paragraph({ children: [new TextRun({ text: alamat, size: 24 })], alignment: AlignmentType.CENTER, spacing: { after: 300 } });
    const judul = new Paragraph({ children: [new TextRun({ text: "Statistik Karyawan", bold: true, size: 28 })], alignment: AlignmentType.CENTER, spacing: { after: 100 } });
    const tanggalPar = new Paragraph({ children: [new TextRun({ text: tanggal, size: 24 })], alignment: AlignmentType.CENTER, spacing: { after: 300 } });

    const tableHeaderCells = headers.map(h => new TableCell({
        children: [new Paragraph({ text: h, bold: true, alignment: AlignmentType.CENTER })],
        shading: { fill: "CCE5FF" },
        margins: { top: 100, bottom: 100, left: 100, right: 100 }
    }));

    const tableDataRows = dataRows.map(rowData => new TableRow({
        children: rowData.map((cellData, idx) => {
            let align = (idx === 1) ? AlignmentType.LEFT : AlignmentType.CENTER;
            let textData = (idx >= 2 && idx < headers.length - 1) ? cellData.toLocaleString('id-ID') : String(cellData);
            return new TableCell({
                children: [new Paragraph({ text: textData, alignment: align })],
                margins: { top: 100, bottom: 100, left: 100, right: 100 }
            });
        })
    }));

    const table = new Table({
        rows: [new TableRow({ children: tableHeaderCells }), ...tableDataRows],
        width: { size: 100, type: WidthType.PERCENTAGE },
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
            properties: { page: { size: { orientation: "landscape" } } },
            children: [headerToko, headerAlamat, judul, tanggalPar, table]
        }]
    });

    const blob = await Packer.toBlob(doc);
    saveAs(blob, `Rekap_Operator_${startDate}_sd_${endDate}.docx`);
});
</script>
</body>
</html>