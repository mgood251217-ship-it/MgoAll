<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

$start_input = $_GET['start_date'] ?? date('Y-m-d');
$end_input = $_GET['end_date'] ?? date('Y-m-d');

// Versi lengkap (Y-m-d H:i:s) untuk query
$filter_start_date = $start_input . ' 00:00:00';
$filter_end_date = $end_input . ' 23:59:59';



// --- Query data transaksi harian ---
$queryTransaksi = "
    SELECT 
        o.order_id,
        o.nomorator, 
        o.customer_name,
        p.nominal, 
        p.payment_method, 
        p.status,
        p.date
    FROM payment p
    JOIN orders o ON p.order_id = o.order_id
    WHERE o.store_id = ? AND p.date BETWEEN ? AND ?
    ORDER BY o.nomorator ASC
";
$stmtTransaksi = $koneksi->prepare($queryTransaksi);
if (!$stmtTransaksi) {
    die("Query error: " . $koneksi->error);
}
$stmtTransaksi->bind_param("iss", $store_id, $filter_start_date, $filter_end_date);
$stmtTransaksi->execute();
$result = $stmtTransaksi->get_result();

// --- Cari order yang pernah DP ---
$orderDenganDP = [];
$dataTransaksi = [];

while ($row = $result->fetch_assoc()) {
    // if ($row['status'] == 'DP') {
    //     $orderDenganDP[$row['order_id']] = true;
    // }
    $stmtPanjangPayment = $koneksi->prepare("SELECT order_id FROM payment WHERE order_id = ?");
    $stmtPanjangPayment->bind_param("i", $row['order_id']);
    $stmtPanjangPayment->execute();
    $stmtPanjangPayment->store_result();
    $num_rows = $stmtPanjangPayment->num_rows;
    if ($num_rows > 1) {
         $orderDenganDP[$row['order_id']] = true;
    }
    $dataTransaksi[] = $row;
}

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
<div id="main-wrapper" <?= ($mode ?? 0) === 1 ? 'class="dark-mode"' : '' ?>>
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
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="tableTransaksi">
                    <thead class="table-primary">
                        <tr>
                            <th>No</th>
                            <th>Nomorator</th>
                            <th>Nama</th>
                            <th>Nominal</th>
                            <th>Metode</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
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

                                $iid = (INT)$row['order_id'];
                                $stmtDate = $koneksi->prepare("SELECT date FROM orders WHERE order_id = ?");
                                $stmtDate->bind_param("i", $iid);
                                $stmtDate->execute();
                                $resultDate = $stmtDate->get_result()->fetch_assoc();

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
                            
                                $tanggal_bayar = date('Y-m-d', strtotime($row['date']));
                                $tanggal_order = date('Y-m-d', strtotime($resultDate['date']));
                                if ($tanggal_bayar > $tanggal_order) {
                                    $statusLabel = 'PELUNASAN';
                                }

                        ?>
                        <tr data-date="<?= date('Y-m-d', strtotime($date)) ?>">
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['nomorator']) ?></td>
                            <td><?= htmlspecialchars($row['customer_name']) ?></td>
                            <td><?= number_format($row['nominal'], 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars($row['payment_method']) ?></td>
                            <td><?= $statusLabel ?></td>
                            <td><?= htmlspecialchars($row['date']) ?></td>
                            <?php  

                            ?>
                            <td><a href="transaksi_detil?scrl_id=<?= htmlspecialchars($row['order_id']) ?>&start_date=<?= date('Y-m-d', strtotime($resultDate['date'])) ?>&end_date=<?= date('Y-m-d', strtotime($resultDate['date'])) ?>" target="_black" class="btn btn-danger">Cek Order</a></td>
                        </tr>
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
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
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
