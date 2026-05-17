<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

// Tangkap input tanggal dari GET (default hari ini)
$start_input = $_GET['start_date'] ?? date('Y-m-d');
$end_input   = $_GET['end_date'] ?? date('Y-m-d');

// Validasi tanggal format Y-m-d
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

if (!validateDate($start_input)) {
    $start_input = date('Y-m-d');
}
if (!validateDate($end_input)) {
    $end_input = date('Y-m-d');
}

// Tambahkan waktu agar sesuai dengan format Y-m-d H:i:s
$start_date = $start_input . ' 00:00:00';
$end_date   = $end_input . ' 23:59:59';

?>



<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Pemakaian Bahan Harian</title>
  <?php include BASE_PATH . '/header.php'; ?>
  <?php include BASE_PATH . '/export_libraries.php'; ?>
</head>
<body>
<div id="main-wrapper" <?= ($mode === 1) ? 'class="dark-mode"' : '' ?>>
  <?php include BASE_PATH . '/navbar.php'; ?>
  <div id="main-content" <?= (isset($mode) && $mode === 1) ? 'class="dark-mode"' : '' ?>>
    <?php include BASE_PATH . '/sidebar.php'; ?>

    <div id="page-content-wrapper">
        <?php require 'summary_cards.php'; ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Pemakaian Bahan Harian</h1>
        <form method="get" class="row g-2 align-items-end justify-content-end flex-nowrap" id="filterForm" style="margin-bottom:0;">
          <div class="col-auto">
            <label for="start_date" class="form-label">Dari</label>
            <input
              type="date"
              name="start_date"
              id="start_date"
              class="form-control"
              value="<?= htmlspecialchars($start_input) ?>"
              onchange="this.form.submit()"
            />
          </div>
          <div class="col-auto">
            <label for="end_date" class="form-label">Sampai</label>
            <input
              type="date"
              name="end_date"
              id="end_date"
              class="form-control"
              value="<?= htmlspecialchars($end_input) ?>"
              onchange="this.form.submit()"
            />
          </div>
          <div class="col-auto align-self-end d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-success" id="btnExportExcel">Export Excel</button>
            <button type="button" class="btn btn-primary" id="btnExportWord">Export Word</button>
          </div>
        </form>
        </div>


      <div class="table-responsive">
        <table class="table table-bordered table-striped" id="tabelPemakaian">
          <thead class="table-primary">
            <tr>
              <th>No</th>
              <th>Nama Barang</th>
              <th>Satuan</th>
              <th>Jumlah Pemakaian</th>
            </tr>
          </thead>
          <tbody>
            <?php
            // Contoh ambil filter dari GET, default 7 hari terakhir
            $start_date = $_GET['start_date'] ?? date('Y-m-d');
            $end_date   = $_GET['end_date'] ?? date('Y-m-d');

            $sql = "
                SELECT 
                  p.product_id,
                  p.name AS nama_barang,
                  p.unit_type AS satuan,
                  COALESCE(
                    SUM(
                      CASE
                        WHEN p.unit_type = 'M2' AND oi.size LIKE '%x%' THEN 
                          oi.quantity * 
                          CAST(SUBSTRING_INDEX(oi.size, 'x', 1) AS DECIMAL(10,4)) * 
                          CAST(SUBSTRING_INDEX(oi.size, 'x', -1) AS DECIMAL(10,4))
                        WHEN p.unit_type = 'M2' THEN 
                          oi.quantity
                        ELSE 
                          oi.quantity
                      END
                    ), 0
                  ) AS total_pemakaian
                FROM products p
                LEFT JOIN order_items oi ON oi.product_id = p.product_id AND oi.store_id = ?
                LEFT JOIN orders o ON o.order_id = oi.order_id AND o.store_id = ?
                WHERE p.store_id = ?
                  AND NOT p.unit_type = '~'
                  AND (o.order_id IS NULL OR (DATE(o.date) BETWEEN ? AND ?))
                GROUP BY p.product_id
                ORDER BY type DESC
            ";

            $stmt = $koneksi->prepare($sql);
            $stmt->bind_param("issss", $store_id, $store_id, $store_id, $start_input, $end_input);

            $stmt->execute();
            $result = $stmt->get_result();

            $no = 1;
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $no++ . "</td>";
                echo "<td>" . htmlspecialchars($row['nama_barang']) . "</td>";
                echo "<td>" . htmlspecialchars($row['satuan']) . "</td>";
                if ($row['satuan'] === 'M2') {
                    echo "<td>" . number_format($row['total_pemakaian'], 2) . "</td>";
                } else {
                    echo "<td>" . number_format($row['total_pemakaian']) . "</td>";
                }
                echo "</tr>";
            }

            $stmt->close();
            ?>
          </tbody>
        </table>


      </div>
    </div>
  </div>
  <?php include BASE_PATH . '/footer.php'; ?>
</div>
<script>
document.getElementById('btnExportExcel').addEventListener('click', async function () {
    const toko = "<?= addslashes($storeName) ?>";
    const alamat = "<?= addslashes($storeAddress) ?>";

    // Pastikan variabel PHP sudah ada, kalau belum beri default
    const startDate = "<?= isset($start_date) ? $start_date : date('Y-m-d') ?>";
    const endDate = "<?= isset($end_date) ? $end_date : date('Y-m-d') ?>";

    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet("Pemakaian Barang");

    // Judul dan header
    sheet.pageSetup.paperSize = 9; // A4
    sheet.pageSetup.orientation = "portrait";

    sheet.mergeCells("A1:D1");
    sheet.getCell("A1").value = toko;
    sheet.getCell("A1").alignment = { horizontal: 'center' };
    sheet.getCell("A1").font = { bold: true, size: 16 };

    sheet.mergeCells("A2:D2");
    sheet.getCell("A2").value = alamat;
    sheet.getCell("A2").alignment = { horizontal: 'center' };

    sheet.addRow([]);
    sheet.mergeCells("A4:D4");
    sheet.getCell("A4").value = "Laporan Pemakaian Barang";
    sheet.getCell("A4").alignment = { horizontal: 'center' };
    sheet.getCell("A4").font = { bold: true, size: 14 };

    sheet.mergeCells("A5:D5");
    sheet.getCell("A5").value = `Tanggal: Dari ${startDate} sampai ${endDate}`;
    sheet.getCell("A5").alignment = { horizontal: 'center' };

    sheet.addRow([]);

    // Header tabel
    const headerRow = sheet.addRow(['No', 'Nama Barang', 'Satuan', 'Jumlah Pemakaian']);
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

    // Data tabel
    const rows = document.querySelectorAll("table tbody tr");
    rows.forEach(tr => {
        const tds = tr.querySelectorAll("td");
        if (tds.length >= 4) {
            // Ambil nilai mentah dari HTML (string persis)
            const jumlah = tds[3].innerText.trim();

            // Tambahkan baris ke sheet (semua kolom string)
            const row = sheet.addRow([
                tds[0].innerText.trim(),
                tds[1].innerText.trim(),
                tds[2].innerText.trim(),
                jumlah // string, bukan number
            ]);

            // Styling
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
        { width: 6 }, { width: 30 }, { width: 10 }, { width: 20 }
    ];

    const fileName = `Pemakaian_Barang_${startDate}_sampai_${endDate}.xlsx`;
    const blob = await workbook.xlsx.writeBuffer();
    saveAs(new Blob([blob]), fileName);
});


document.getElementById("btnExportWord").addEventListener("click", async function () {
    const { Document, Packer, Paragraph, Table, TableRow, TableCell, TextRun, AlignmentType, WidthType, BorderStyle, PageOrientation } = window.docx;

    const toko = "<?= addslashes($storeName) ?>";
    const alamat = "<?= addslashes($storeAddress) ?>";
    const startDate = "<?= isset($start_date) ? $start_date : date('Y-m-d') ?>";
    const endDate = "<?= isset($end_date) ? $end_date : date('Y-m-d') ?>";

    const headerToko = new Paragraph({
        children: [new TextRun({ text: toko, bold: true, size: 32 })],
        alignment: AlignmentType.CENTER
    });
    const headerAlamat = new Paragraph({
        children: [new TextRun({ text: alamat, size: 24 })],
        alignment: AlignmentType.CENTER
    });
    const judul = new Paragraph({
        children: [new TextRun({ text: "Laporan Pemakaian Barang", bold: true, size: 28 })],
        alignment: AlignmentType.CENTER
    });
    const tanggalPar = new Paragraph({
        children: [new TextRun({ text: `Tanggal: Dari ${startDate} sampai ${endDate}`, size: 24 })],
        alignment: AlignmentType.CENTER
    });

    const tableRows = [];

    // Header tabel
    const headers = ['No', 'Nama Barang', 'Satuan', 'Jumlah Pemakaian'];
    tableRows.push(new TableRow({
        children: headers.map(h =>
            new TableCell({
                children: [new Paragraph({ text: h, bold: true })],
                borders: { top: { style: BorderStyle.SINGLE } }
            })
        )
    }));

    // Ambil data dari tabel HTML
    const rows = document.querySelectorAll("#tabelPemakaian tbody tr");
    if (rows.length === 0) {
        alert("Data tidak ditemukan atau ID tabel salah.");
        return;
    }

    rows.forEach(tr => {
        const tds = tr.querySelectorAll("td");
        if (tds.length >= 4) {
            const cells = Array.from(tds).map(td => {
                return new TableCell({
                    children: [new Paragraph(td.textContent.trim())],
                });
            });
            tableRows.push(new TableRow({ children: cells }));
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
            properties: {
                page: {
                    size: { orientation: PageOrientation.PORTRAIT }
                }
            },
            children: [headerToko, headerAlamat, judul, tanggalPar, new Paragraph(""), table]
        }]
    });

    const blob = await Packer.toBlob(doc);
    const fileName = `Pemakaian_Barang_${startDate}_sampai_${endDate}.docx`;
    saveAs(blob, fileName);
});

</script>
<script>
document.getElementById('start_date').addEventListener('change', function() {
    document.getElementById('filterForm').submit();
});
document.getElementById('end_date').addEventListener('change', function() {
    document.getElementById('filterForm').submit();
});
</script>
<script>
  document.querySelectorAll('#day, #month, #year').forEach(function(el) {
    el.addEventListener('change', function() {
      document.getElementById('filterForm').submit();
    });
  });

</script>
</body>
</html>
