<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

$start_input = $_GET['start_date'] ?? date('Y-m-d');
$end_input = $_GET['end_date'] ?? date('Y-m-d');

// Validasi format tanggal (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_input)) {
    $start_input = date('Y-m-d');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_input)) {
    $end_input = date('Y-m-d');
}

// Versi lengkap (Y-m-d H:i:s) untuk query
$filter_start_date = $start_input . ' 00:00:00';
$filter_end_date = $end_input . ' 23:59:59';


// Ambil data produk utama berdasarkan interval
$sql = "SELECT 
          i.judul, i.size, i.amount, i.quantity, i.product_id, i.finishing,
          o.nomorator, o.customer_name, o.date, o.order_id,
          p.price, p.name AS product_name
        FROM order_items i
        INNER JOIN orders o ON i.order_id = o.order_id
        LEFT JOIN products p ON i.product_id = p.product_id
        WHERE o.store_id = ? AND o.date BETWEEN ? AND ?
        ORDER BY i.judul, o.date DESC";

$stmt = $koneksi->prepare($sql);
$stmt->bind_param("iss", $store_id, $filter_start_date, $filter_end_date);
$stmt->execute();
$result = $stmt->get_result();

$produkData = [];

while ($row = $result->fetch_assoc()) {
    // Ambil nama finishing
    $finishingNames = [];
    if (!empty($row['finishing'])) {
        $finishingIDs = explode(',', $row['finishing']);
        $placeholders = implode(',', array_fill(0, count($finishingIDs), '?'));

        $stmt2 = $koneksi->prepare("SELECT name FROM products WHERE product_id IN ($placeholders)");
        $types = str_repeat('i', count($finishingIDs));
        $stmt2->bind_param($types, ...array_map('intval', $finishingIDs));
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($r2 = $res2->fetch_assoc()) {
            $finishingNames[] = $r2['name'];
        }
    }

    $row['finishing_names'] = implode(', ', $finishingNames);
    $produkData[$row['judul']][] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Transaksi per Item</title>
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
        <h1 class="mb-0">Transaksi per Item</h1>
        <?php $showExport = true; include BASE_PATH . '/interval_date.php'; ?>
      </div>

      <?php if (empty($produkData)): ?>
        <div class="alert alert-warning">Tidak ada transaksi pada tanggal ini.</div>
      <?php else: ?>
        <?php foreach ($produkData as $judul => $items): ?>
        <div class="produk-block">
            <div class="produk-title fw-bold mb-2"><?= htmlspecialchars($judul) ?></div>
            <div class="table-responsive">
            <table class="table table-bordered table-sm dataTable">
                <thead class="table-primary">
                <tr>
                    <th>No</th>
                    <th>Nomorator</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Ukuran</th>
                    <th>Finishing</th>
                    <th>Harga Produk</th>
                    <th>Qty</th>
                    <th>Subtotal</th>
                    <th>Opsi</th>
                    <th>Cek Order</th>
                </tr>
                </thead>
                <tbody>
                <?php $i = 1; ?>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= $i ?></td>
                    <td><?= htmlspecialchars($item['nomorator']) ?></td>
                    <td><?= htmlspecialchars($item['date']) ?></td>
                    <td><?= htmlspecialchars($item['customer_name']) ?></td>
                    <td><?= htmlspecialchars($item['size']) ?></td>
                    <td><?= htmlspecialchars($item['finishing_names']) ?></td>
                    <td>Rp<?= number_format($item['price'], 0, ',', '.') ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td>Rp<?= number_format($item['amount'], 0, ',', '.') ?></td>
                    <td>
                      <?php
                      if ($item['product_id'] == 0) {
                        echo "Manual⚠️";
                      }else {
                        echo "Otomatis✅";
                      }
                      
                      ?>
                    </td>
                    <td><a href="transaksi_detil?scrl_id=<?= htmlspecialchars($item['order_id']) ?>&start_date=<?= date('Y-m-d', strtotime($item['date'])) ?>&end_date=<?= date('Y-m-d', strtotime($item['date'])) ?>" target="_black" class="btn btn-primary" style="padding: 2px;">Cek Order</a></td>
                </tr>
                <?php $i += 1; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
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
    const sheet = workbook.addWorksheet("Transaksi Per Item");

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
        new Paragraph({ text: "Laporan Transaksi Per Item", heading: HeadingLevel.HEADING_1, alignment: AlignmentType.CENTER, spacing: { after: 150 } }),
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