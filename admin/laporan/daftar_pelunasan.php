<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

$start_input = $_GET['start_date'] ?? date('Y-m-d');
$end_input = $_GET['end_date'] ?? date('Y-m-d');

// Versi lengkap (Y-m-d H:i:s) untuk query
$filter_start_date = $start_input . ' 00:00:00';
$filter_end_date = $end_input . ' 23:59:59';



// --- Query data transaksi ---
$queryTransaksi = "
    SELECT 
        p.order_id,
        o.nomorator, 
        o.customer_name, 
        p.nominal, 
        p.payment_method, 
        p.status,
        p.date
    FROM payment p
    JOIN orders o ON p.order_id = o.order_id
    WHERE o.store_id = ? AND p.date BETWEEN ? AND ?
    ORDER BY p.date ASC
";
$stmtTransaksi = $koneksi->prepare($queryTransaksi);
if (!$stmtTransaksi) {
    die("Query error: " . $koneksi->error);
}
$stmtTransaksi->bind_param("iss", $storeIdTransaksi, $filter_start_date, $filter_end_date);
$stmtTransaksi->execute();
$result = $stmtTransaksi->get_result();

// --- Cari order yang pernah DP ---
$orderDenganDP = [];
$dataTransaksi = [];

while ($row = $result->fetch_assoc()) {
    if (strtoupper($row['status']) === 'DP') {
        $orderDenganDP[$row['order_id']] = true;
    }
    $dataTransaksi[] = $row;
}


// Ambil nama dan alamat toko dari tabel stores
$stmtStore = $koneksi->prepare("SELECT name, address FROM stores WHERE store_id = ?");
$stmtStore->bind_param("i", $storeIdTransaksi);
$stmtStore->execute();
$resultStore = $stmtStore->get_result();
$store = $resultStore->fetch_assoc();
$storeName = $store['name'] ?? 'Nama Toko';
$storeAddress = $store['address'] ?? 'Alamat belum tersedia';

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Transaksi Harian</title>
    <?php include BASE_PATH . '/header.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Library untuk Export Excel -->
    <script src="https://cdn.jsdelivr.net/npm/exceljs/dist/exceljs.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/file-saver@2.0.5/dist/FileSaver.min.js"></script>

    <!-- Library untuk Export Word -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pizzip/3.1.1/pizzip.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/docx@7.2.0/build/index.min.js"></script>

    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/content.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dark_mode.css">
</head>
<body>
<div id="main-wrapper" <?= ($mode ?? 0) === 1 ? 'class="dark-mode"' : '' ?>>
    <?php include BASE_PATH . '/navbar.php'; ?>
    <div id="main-content" <?= ($mode ?? 0) === 1 ? 'class="dark-mode"' : '' ?>>
        <?php include BASE_PATH . '/sidebar.php'; ?>

        <div id="page-content-wrapper">
            <?php require 'summary_cards.php'; ?>

            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h1 class="mb-0">Data Pelunasan Harian</h1>
                <form method="get" class="row g-2 align-items-end justify-content-end" id="filterForm" style="margin-bottom:0;">
                    <div class="col-auto">
                        <label for="start_date" class="form-label mb-0">Dari</label>
                        <input type="date" class="form-control" name="start_date" id="start_date" value="<?= htmlspecialchars($start_input) ?>">
                    </div>
                    <div class="col-auto">
                        <label for="end_date" class="form-label mb-0">Sampai</label>
                        <input type="date" class="form-control" name="end_date" id="end_date" value="<?= htmlspecialchars($end_input) ?>">
                    </div>
                    <div class="col-auto align-self-end d-flex gap-2 flex-wrap">
                        <!-- tombol Tampilkan dihilangkan -->
                        <button type="button" class="btn btn-success" id="btnExportExcel">Export Excel</button>
                        <button type="button" class="btn btn-primary" id="btnExportWord">Export Word</button>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="tableTransaksi">
                    <thead class="table-primary">
                        <tr>
                            <th>No</th>
                            <th>Nomorator</th>
                            <th>Nama</th>
                            <th>Nominal DP</th>
                            <th>Metode DP</th>
                            <th>Tanggal DP</th>
                            <th>Nominal Pelunasan</th>
                            <th>Metode Pelunasan</th>
                            <th>Tanggal Pelunasan</th>
                            <th>Cek Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($dataTransaksi) > 0): 
                            $no = 1;
                            $tf = 0;
                            $cash = 0;
                            $total_harian = 0;
                            foreach ($dataTransaksi as $row): 
                                $date = $row['date'];
                                $status = strtoupper($row['status']);

                                // Cek logika status
                                if ($status === 'LUNAS' && isset($orderDenganDP[$row['order_id']])) {
                                    $statusLabel = 'PELUNASAN';
                                } elseif ($status === 'DP') {
                                    $statusLabel = 'BAYAR DP';
                                } else {
                                    $statusLabel = 'LUNAS';
                                }
                            if ($row['payment_method'] == "TF") {
                                $tf += $row['nominal'];
                            }else{
                                $cash += $row['nominal'];
                            }
                            
                        ?>
                        <?php if ($statusLabel == 'PELUNASAN') {
                            
                         ?>
                        <tr data-date="<?= date('Y-m-d', strtotime($date)) ?>">
                            <?php  
                                $iid = (INT)$row['order_id'];
                                $stmtDate = $koneksi->prepare("SELECT date FROM orders WHERE order_id = ?");
                                $stmtDate->bind_param("i", $iid);
                                $stmtDate->execute();
                                $resultDate = $stmtDate->get_result()->fetch_assoc();
                            ?>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['nomorator']) ?></td>
                            <td><?= htmlspecialchars($row['customer_name']) ?></td>
                            <?php
                            
                            $stmtDP = $koneksi->prepare("SELECT payment_id, nominal, payment_method, date FROM payment WHERE order_id = ? ORDER BY date LIMIT 1");
                            $stmtDP->bind_param("i", $iid);
                            $stmtDP->execute();
                            $resultDP = $stmtDP->get_result()->fetch_assoc();
                            ?>

                            <td><?= number_format($resultDP['nominal'], 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars($resultDP['payment_method']) ?></td>
                            <td><?= htmlspecialchars($resultDP['date']) ?></td>
                            <td><?= number_format($row['nominal'], 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars($row['payment_method']) ?></td>
                            <td><?= htmlspecialchars($row['date']) ?></td>
                            <td><a href="transaksi_detil.php?scrl_id=<?= htmlspecialchars($row['order_id']) ?>&start_date=<?= date('Y-m-d', strtotime($resultDate['date'])) ?>&end_date=<?= date('Y-m-d', strtotime($resultDate['date'])) ?>" target="_black" class="btn btn-danger">Cek Order</a></td>
                        </tr>
                        <?php } ?>
                        <?php 
                            $total_harian += $row['nominal']; 
                            endforeach;
                        else: ?>
                        <tr><td colspan="6" class="text-center">Tidak ada data transaksi.</td></tr>
                        <?php endif; ?>
                    </tbody>

                    <tfoot>
                        <tr class="table-success">
                            <th colspan="3" class="text-end">Total Harian Dari <?= $start_input ?> Sampai <?= $end_input ?> : </th>
                            <th><?= number_format($total_harian, 0, ',', '.') ?></th>
                            <th>TF : <?= number_format($tf, 0, ',', '.') ?></th>
                            <th>CASH : <?= number_format($cash, 0, ',', '.') ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
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
// Export Excel
document.getElementById('btnExportExcel').addEventListener('click', async function () {
    const toko = "<?= addslashes($storeName) ?>";
    const alamat = "<?= addslashes($storeAddress) ?>";

    const startDate = document.getElementById('start_date')?.value || "";
    const endDate = document.getElementById('end_date')?.value || "";
    const tanggal = (startDate && endDate) ? `Tanggal ${startDate} s.d. ${endDate}` : 'Tanggal -';

    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet("Daftar Pelunasan");

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

    // Data rows dari tabel HTML
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

    // Kolom lebar
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


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
