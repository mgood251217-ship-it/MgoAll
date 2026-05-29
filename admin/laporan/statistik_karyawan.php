<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

$start_input = $_GET['start_date'] ?? date('Y-m-01');
$end_input = $_GET['end_date'] ?? date('Y-m-d');

// Versi lengkap (Y-m-d H:i:s) untuk query
$filter_start_date = $start_input . ' 00:00:00';
$filter_end_date = $end_input . ' 23:59:59';

// Ambil data users
$stmtUsers = $koneksi->prepare("SELECT user_id, initial, name FROM users WHERE store_id = ?");
$stmtUsers->bind_param("i", $store_id);
$stmtUsers->execute();
$resultUsers = $stmtUsers->get_result();
$users = [];
$usernames = [];
while ($row = $resultUsers->fetch_assoc()) {
    $users[$row['user_id']] = $row['initial'];
    $usernames[$row['user_id']] = $row['name'];
}

// Hitung penerima order (konsumen)
$receiverCounts = array_fill_keys(array_keys($users), 0);
$stmtOrders = $koneksi->prepare("
    SELECT o.user_id 
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    WHERE DATE(o.date) BETWEEN ? AND ?
      AND o.store_id = ?
");
$stmtOrders->bind_param("ssi", $filter_start_date, $filter_end_date, $store_id);
$stmtOrders->execute();
$resultOrders = $stmtOrders->get_result();
while ($row = $resultOrders->fetch_assoc()) {
    $uid = $row['user_id'];
    if (isset($receiverCounts[$uid])) {
        $receiverCounts[$uid]++;
    }
}

// Hitung pengambilan barang terbanyak (projects.process = 'DIAMBIL')
$pickupCounts = array_fill_keys(array_keys($users), 0);
$stmtHitung = $koneksi->prepare("
    SELECT p.user_id 
    FROM projects p
    JOIN users u ON p.user_id = u.user_id
    WHERE DATE(p.date) BETWEEN ? AND ? 
      AND u.store_id = ? 
      AND p.process = 'DIAMBIL'
");
$stmtHitung->bind_param("ssi", $filter_start_date, $filter_end_date, $store_id);
$stmtHitung->execute();
$resultHitung = $stmtHitung->get_result();
while ($row = $resultHitung->fetch_assoc()) {
    $uid = $row['user_id'];
    if (isset($pickupCounts[$uid])) {
        $pickupCounts[$uid]++;
    }
}

// Hitung operator dengan SETTING terbanyak
$settingCounts = array_fill_keys(array_keys($users), 0);
$stmt = $koneksi->prepare("
    SELECT o.order_id, o.user_id 
    FROM orders o
    WHERE DATE(o.date) BETWEEN ? AND ?
      AND o.store_id = ?
");
$stmt->bind_param("ssi", $filter_start_date, $filter_end_date, $store_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $order_id = $row['order_id'];
    $user_id = $row['user_id'];

    // Cek apakah order ini memiliki item dengan judul SETTING
    $stmtCheck = $koneksi->prepare("
        SELECT 1 FROM order_items 
        WHERE order_id = ? AND UPPER(judul) = 'SETTING' LIMIT 1
    ");
    $stmtCheck->bind_param("i", $order_id);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();

    if ($resultCheck->num_rows > 0 && isset($settingCounts[$user_id])) {
        $settingCounts[$user_id]++;
    }

    $stmtCheck->close();
}
$stmt->close();

// Hitung total omset per user dari tabel payment
$omsetPerUser = array_fill_keys(array_keys($users), 0);

$sql = "SELECT nominal, order_id FROM payment WHERE date BETWEEN ? AND ?";
$stmt = $koneksi->prepare($sql);
$stmt->bind_param("ss", $filter_start_date, $filter_end_date);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $orderIds = explode(',', $row['order_id']);
    $nominalPerOrder = $row['nominal'] / count($orderIds); // Bagi rata jika banyak order_id

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
<div id="main-wrapper" <?= ($mode ?? 0) === 1 ? 'class="dark-mode"' : '' ?>>
    <?php include BASE_PATH . '/navbar.php'; ?>
    <div id="main-content" <?= ($mode ?? 0) === 1 ? 'class="dark-mode"' : '' ?>>
        <?php include BASE_PATH . '/sidebar.php'; ?>

        <div id="page-content-wrapper">
            <?php require 'summary_cards.php'; ?>

            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h1 class="mb-0">Statistik Karyawan</h1>
                <?php $showExport = true; include BASE_PATH . '/interval_date.php'; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-primary">
                        <tr>
                            <th rowspan="2">No</th>
                            <th rowspan="2">Keterangan</th>
                            <th colspan="<?= count($users) ?>" class="text-center">Operator</th>
                            <th rowspan="2" class="text-center">Hasil</th>
                        </tr>
                        <tr>
                            <?php foreach ($users as $initial): ?>
                                <th><?= htmlspecialchars($initial) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td>PALING BANYAK PENGAMBILAN BARANG</td>
                            <?php 
                                $max1 = max($pickupCounts);
                                $maxId1 = array_search($max1, $pickupCounts);
                                foreach ($users as $id => $initial): ?>
                                    <td><?= $pickupCounts[$id] ?></td>
                            <?php endforeach; ?>
                            <td><?= $max1 > 0 && isset($usernames[$maxId1]) ? htmlspecialchars($usernames[$maxId1]) : '-' ?></td>
                        </tr>

                        <tr>
                            <td>2</td>
                            <td>PALING BANYAK NERIMA KONSUMEN</td>
                            <?php 
                                $max2 = max($receiverCounts);
                                $maxId2 = array_search($max2, $receiverCounts);
                                foreach ($users as $id => $initial): ?>
                                    <td><?= $receiverCounts[$id] ?></td>
                            <?php endforeach; ?>
                            <td><?= $max2 > 0 && isset($usernames[$maxId2]) ? htmlspecialchars($usernames[$maxId2]) : '-' ?></td>
                        </tr>

                        <tr>
                            <td>3</td>
                            <td>PALING BANYAK SETTING</td>
                            <?php 
                                $max3 = max($settingCounts);
                                $maxId3 = array_search($max3, $settingCounts);
                                foreach ($users as $id => $initial): ?>
                                    <td><?= $settingCounts[$id] ?></td>
                            <?php endforeach; ?>
                            <td><?= $max3 > 0 && isset($usernames[$maxId3]) ? htmlspecialchars($usernames[$maxId3]) : '-' ?></td>
                        </tr>

                        <tr>
                            <td>4</td>
                            <td>OMSET TERBANYAK</td>
                            <?php 
                                $maxOmset = max($omsetPerUser);
                                $maxOmsetId = array_search($maxOmset, $omsetPerUser);
                                foreach ($users as $id => $initial): ?>
                                    <td><?= number_format($omsetPerUser[$id], 0, ',', '.') ?></td>
                            <?php endforeach; ?>
                            <td><?= $maxOmset > 0 && isset($usernames[$maxOmsetId]) ? htmlspecialchars($usernames[$maxOmsetId]) : '-' ?></td>
                        </tr>

                    </tbody>
                </table>


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
// === Export Excel ===
document.getElementById('btnExportExcel').addEventListener('click', async function () {
    const toko = "<?= addslashes($storeName) ?>";
    const alamat = "<?= addslashes($storeAddress) ?>";
    const startDate = document.getElementById('start_date')?.value || "";
    const endDate = document.getElementById('end_date')?.value || "";
    const tanggal = (startDate && endDate) ? `Tanggal ${startDate} s.d. ${endDate}` : 'Tanggal -';

    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet("Rekap Operator", {
        pageSetup: {
            paperSize: 9, // A4
            orientation: 'portrait',
            fitToPage: true,
        }
    });

    const users = <?= json_encode($users) ?>;
    const usernames = <?= json_encode($usernames) ?>;
    const operatorCounts = <?= json_encode($operatorCounts) ?>;
    const receiverCounts = <?= json_encode($receiverCounts) ?>;

    const operatorInitials = Object.values(users);
    const operatorIds = Object.keys(users);
    const totalCols = 2 + operatorInitials.length + 1; // No + Keterangan + initials + Hasil

    // === HEADER ===
    // 1. Nama toko
    sheet.mergeCells(1, 1, 1, totalCols);
    sheet.getCell("A1").value = toko;
    sheet.getCell("A1").alignment = { vertical: 'middle', horizontal: 'center' };
    sheet.getCell("A1").font = { bold: true, size: 16 };

    // 2. Alamat toko
    sheet.mergeCells(2, 1, 2, totalCols);
    sheet.getCell("A2").value = alamat;
    sheet.getCell("A2").alignment = { vertical: 'middle', horizontal: 'center' };

    // 3. Kosong
    sheet.addRow([]);

    // 4. Judul Statistik
    sheet.mergeCells(4, 1, 4, totalCols);
    sheet.getCell("A4").value = "Statistik Karyawan";
    sheet.getCell("A4").alignment = { vertical: 'middle', horizontal: 'center' };
    sheet.getCell("A4").font = { bold: true, size: 14 };

    // 5. Tanggal
    sheet.mergeCells(5, 1, 5, totalCols);
    sheet.getCell("A5").value = tanggal;
    sheet.getCell("A5").alignment = { vertical: 'middle', horizontal: 'center' };

    // 6. Kosong
    sheet.addRow([]);

    // === HEADER TABEL ===
    const header1 = ["No", "Keterangan"];
    const header2 = ["", ""];

    for (let i = 0; i < operatorInitials.length; i++) {
        header1.push("Operator");
        header2.push(operatorInitials[i]);
    }

    header1.push("Hasil");
    header2.push("");

    const rowHeader1 = sheet.addRow(header1);
    const rowHeader2 = sheet.addRow(header2);

    // Merge header cells:
    // Merge "Operator" kolom (baris 7, kolom 3 sampai kolom sebelum "Hasil")
    sheet.mergeCells(7, 3, 7, 2 + operatorInitials.length);

    // Merge "No", "Keterangan", "Hasil" header vertikal (baris 7 sampai 8)
    sheet.mergeCells(7, 1, 8, 1); // No
    sheet.mergeCells(7, 2, 8, 2); // Keterangan
    sheet.mergeCells(7, 2 + operatorInitials.length + 1, 8, 2 + operatorInitials.length + 1); // Hasil

    // Styling header
    [rowHeader1, rowHeader2].forEach(row => {
        row.font = { bold: true };
        row.alignment = { horizontal: 'center', vertical: 'middle' };
        row.height = 20;
        row.eachCell(cell => {
            cell.fill = {
                type: 'pattern',
                pattern: 'solid',
                fgColor: { argb: 'FFCCE5FF' }
            };
            cell.border = {
                top: { style: 'thin' },
                bottom: { style: 'thin' },
                left: { style: 'thin' },
                right: { style: 'thin' }
            };
        });
    });

    // === DATA BARIS 1 ===
    const maxOp = Math.max(...Object.values(operatorCounts));
    const maxOpId = Object.entries(operatorCounts).find(([id, val]) => val === maxOp)?.[0];
    const row1 = [
        1,
        "PENGAMBILAN BARANG TERBANYAK",
        ...operatorIds.map(id => operatorCounts[id] || 0),
        (maxOp > 0 && usernames[maxOpId]) ? usernames[maxOpId] : "-"
    ];
    sheet.addRow(row1);

    // === DATA BARIS 2 ===
    const maxRc = Math.max(...Object.values(receiverCounts));
    const maxRcId = Object.entries(receiverCounts).find(([id, val]) => val === maxRc)?.[0];
    const row2 = [
        2,
        "PALING BANYAK NERIMA KONSUMEN",
        ...operatorIds.map(id => receiverCounts[id] || 0),
        (maxRc > 0 && usernames[maxRcId]) ? usernames[maxRcId] : "-"
    ];
    sheet.addRow(row2);

    // Styling data rows
    [sheet.getRow(9), sheet.getRow(10)].forEach(row => {
        row.alignment = { horizontal: 'center', vertical: 'middle' };
        row.height = 18;
        row.eachCell(cell => {
            cell.border = {
                top: { style: 'thin' },
                bottom: { style: 'thin' },
                left: { style: 'thin' },
                right: { style: 'thin' }
            };
        });
    });

    // Set kolom lebar
    sheet.columns = [
        { width: 6 },   // No
        { width: 30 },  // Keterangan
        ...operatorIds.map(() => ({ width: 10 })), // Operator initials
        { width: 25 }   // Hasil
    ];

    // Export file
    const buffer = await workbook.xlsx.writeBuffer();
    saveAs(new Blob([buffer]), `Rekap_Operator_${startDate}_sd_${endDate}.xlsx`);
});






// Export Word
document.getElementById('btnExportWord').addEventListener('click', async function () {
    const {
        Document,
        Packer,
        Paragraph,
        Table,
        TableCell,
        TableRow,
        TextRun,
        WidthType,
        AlignmentType,
        BorderStyle,
        VerticalAlign
    } = window.docx;

    const toko = "<?= addslashes($storeName) ?>";
    const alamat = "<?= addslashes($storeAddress) ?>";
    const startDate = document.getElementById('start_date')?.value || "";
    const endDate = document.getElementById('end_date')?.value || "";
    const tanggal = (startDate && endDate) ? `Tanggal ${startDate} s.d. ${endDate}` : 'Tanggal -';

    const users = <?= json_encode($users) ?>;
    const usernames = <?= json_encode($usernames) ?>;
    const operatorCounts = <?= json_encode($operatorCounts) ?>;
    const receiverCounts = <?= json_encode($receiverCounts) ?>;

    const operatorInitials = Object.values(users);
    const operatorIds = Object.keys(users);

    // Header paragraf
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
        children: [new TextRun({ text: "Statistik Karyawan", bold: true, size: 28 })],
        alignment: AlignmentType.CENTER,
        spacing: { after: 100 }
    });

    const tanggalPar = new Paragraph({
        children: [new TextRun({ text: tanggal, size: 24 })],
        alignment: AlignmentType.CENTER,
        spacing: { after: 300 }
    });

    // Header tabel baris pertama (2 baris header dengan subkolom Operator)
    const headerRow1Cells = [
        new TableCell({
            rowSpan: 2,
            children: [new Paragraph({ text: "No", bold: true, alignment: AlignmentType.CENTER })],
            verticalAlign: VerticalAlign.CENTER,
            shading: { fill: "CCE5FF" }
        }),
        new TableCell({
            rowSpan: 2,
            children: [new Paragraph({ text: "Keterangan", bold: true, alignment: AlignmentType.CENTER })],
            verticalAlign: VerticalAlign.CENTER,
            shading: { fill: "CCE5FF" }
        }),
        new TableCell({
            columnSpan: operatorInitials.length,
            children: [new Paragraph({ text: "Operator", bold: true, alignment: AlignmentType.CENTER })],
            shading: { fill: "CCE5FF" }
        }),
        new TableCell({
            rowSpan: 2,
            children: [new Paragraph({ text: "Hasil", bold: true, alignment: AlignmentType.CENTER })],
            verticalAlign: VerticalAlign.CENTER,
            shading: { fill: "CCE5FF" }
        }),
    ];

    const headerRow2Cells = operatorInitials.map(initial => 
        new TableCell({
            children: [new Paragraph({ text: initial, bold: true, alignment: AlignmentType.CENTER })],
            shading: { fill: "CCE5FF" }
        })
    );

    // Baris data 1 (Pengambilan Barang Terbanyak)
    const maxOp = Math.max(...Object.values(operatorCounts));
    const maxOpId = Object.entries(operatorCounts).find(([id, val]) => val === maxOp)?.[0];
    const row1Cells = [
        new TableCell({ children: [new Paragraph("1")], alignment: AlignmentType.CENTER }),
        new TableCell({ children: [new Paragraph("PENGAMBILAN BARANG TERBANYAK")] }),
        ...operatorIds.map(id => new TableCell({ children: [new Paragraph(String(operatorCounts[id] || 0))], alignment: AlignmentType.CENTER })),
        new TableCell({ children: [new Paragraph((maxOp > 0 && usernames[maxOpId]) ? usernames[maxOpId] : "-")] }),
    ];

    // Baris data 2 (Paling Banyak Nerima Konsumen)
    const maxRc = Math.max(...Object.values(receiverCounts));
    const maxRcId = Object.entries(receiverCounts).find(([id, val]) => val === maxRc)?.[0];
    const row2Cells = [
        new TableCell({ children: [new Paragraph("2")], alignment: AlignmentType.CENTER }),
        new TableCell({ children: [new Paragraph("PALING BANYAK NERIMA KONSUMEN")] }),
        ...operatorIds.map(id => new TableCell({ children: [new Paragraph(String(receiverCounts[id] || 0))], alignment: AlignmentType.CENTER })),
        new TableCell({ children: [new Paragraph((maxRc > 0 && usernames[maxRcId]) ? usernames[maxRcId] : "-")] }),
    ];

    const table = new Table({
        rows: [
            new TableRow({ children: headerRow1Cells }),
            new TableRow({ children: headerRow2Cells }),
            new TableRow({ children: row1Cells }),
            new TableRow({ children: row2Cells }),
        ],
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
                new Paragraph({ text: "", spacing: { after: 200 } }), // kosong row
                judul,
                tanggalPar,
                new Paragraph({ text: "", spacing: { after: 200 } }), // kosong row
                table,
            ]
        }]
    });

    const blob = await Packer.toBlob(doc);
    saveAs(blob, `Rekap_Operator_${startDate}_sd_${endDate}.docx`);
});


</script>



</body>
</html>
