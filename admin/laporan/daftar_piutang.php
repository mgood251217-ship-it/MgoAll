<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
?>
 
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Daftar Piutang</title>
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
        <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">Daftar Piutang</h1>
        <div>
            <button id="btnExportExcel" class="btn btn-success me-2">Export Excel</button>
            <button id="btnExportWord" class="btn btn-primary">Export Word</button>
        </div>
        </div>

      <div class="table-responsive">
        <table class="table table-bordered table-striped" id="tabelPiutang">
          <thead class="table-primary">
            <tr>
              <th>No</th>
              <th>Nama</th>
              <th>Nomorator</th>
              <th>Nomor</th>
              <th>Hutang</th>
              <th>OP</th>
              <th>Tanggal</th>
              <th>Cek</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $query = "
                SELECT 
                    o.order_id,
                    o.customer_name AS nama,
                    o.nomorator,
                    o.nomor,
                    o.total,
                    o.user_id,
                    o.date,
                    CASE 
                    WHEN ps.lunas = 1 THEN 0
                    ELSE o.total - IFNULL(ps.total_dp, 0)
                    END AS hutang
                FROM orders o
                LEFT JOIN (
                    SELECT 
                        order_id,
                        MAX(CASE WHEN status = 'LUNAS' THEN 1 ELSE 0 END) AS lunas,
                        SUM(CASE WHEN status = 'DP' THEN nominal ELSE 0 END) AS total_dp
                    FROM payment
                    GROUP BY order_id
                ) ps ON o.order_id = ps.order_id
                WHERE o.store_id = ?
                HAVING hutang > 0
                ORDER BY o.order_id DESC, o.nomor DESC
            ";

            $stmt = $koneksi->prepare($query);
            $stmt->bind_param("i", $store_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $no = 1;
            $total_hutang = 0;

            while ($row = $result->fetch_assoc()):
                $op_id = $row['user_id'];
                $op_stmt = $koneksi->prepare("SELECT initial FROM users WHERE user_id = ?");
                $op_stmt->bind_param("i", $op_id);
                $op_stmt->execute();
                $op_result = $op_stmt->get_result();
                $operator = $op_result->fetch_assoc();
            ?>
                <tr>
                    <td><?= $no ?></td>
                    <td><?= htmlspecialchars($row['nama']) ?></td>
                    <td><?= htmlspecialchars($row['nomorator']) ?></td>
                    <td><?= htmlspecialchars($row['nomor']) ?></td>
                    <td>Rp <?= number_format($row['hutang'], 0, ',', '.') ?></td>
                    <td><?= htmlspecialchars($operator['initial'] ?? '') ?></td>
                    <td><?= date('Y-m-d', strtotime($row['date'])) ?></td>
                    <td><a href="transaksi_detil?scrl_id=<?= htmlspecialchars($row['order_id']) ?>&start_date=<?= date('Y-m-d', strtotime($row['date'])) ?>&end_date=<?= date('Y-m-d', strtotime($row['date'])) ?>" target="_black" class="btn btn-danger">Cek Order</a></td>
                </tr>
            <?php
                $total_hutang += $row['hutang'];
                $no++;
                $op_stmt->close();
            endwhile;
            $stmt->close();
            ?>

          </tbody>
          <tfoot>
                <tr class="table-success">
                    <td colspan="4" class="text-end">Total Hutang : </td>
                    <td colspan="4">Rp <?= number_format($total_hutang, 0, ',', '.') ?></td>
                </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <?php include BASE_PATH . '/footer.php'; ?>
</div>
<script>
document.getElementById('btnExportExcel').addEventListener('click', async () => {
  const toko = "<?= addslashes($storeName) ?>";
  const alamat = "<?= addslashes($storeAddress) ?>";

  const workbook = new ExcelJS.Workbook();
  const sheet = workbook.addWorksheet("Piutang", {
    pageSetup: { paperSize: 9, orientation: 'portrait' } // A4
  });

  // Judul toko (merge dan center)
  sheet.mergeCells("A1:G1");
  sheet.getCell("A1").value = toko;
  sheet.getCell("A1").font = { bold: true, size: 16 };
  sheet.getCell("A1").alignment = { horizontal: "center", vertical: "middle" };
  sheet.getRow(1).height = 25;

  // Alamat toko (merge dan center)
  sheet.mergeCells("A2:G2");
  sheet.getCell("A2").value = alamat;
  sheet.getCell("A2").alignment = { horizontal: "center", vertical: "middle" };

  sheet.addRow([]);

  // Judul laporan tanpa tanggal
  sheet.mergeCells("A4:G4");
  sheet.getCell("A4").value = "Laporan Piutang";
  sheet.getCell("A4").font = { bold: true, size: 14 };
  sheet.getCell("A4").alignment = { horizontal: "center", vertical: "middle" };

  sheet.addRow([]);

  // Header kolom
  const header = ['No', 'Nama', 'Nomorator', 'Nomor', 'Hutang', 'OP', 'Tanggal'];
  const headerRow = sheet.addRow(header);

  headerRow.font = { bold: true };
  headerRow.alignment = { horizontal: 'center', vertical: 'middle' };
  headerRow.eachCell(cell => {
    cell.fill = {
      type: 'pattern',
      pattern: 'solid',
      fgColor: { argb: 'FFCCE5FF' } // biru muda
    };
    cell.border = {
      top: { style: 'thin' }, bottom: { style: 'thin' },
      left: { style: 'thin' }, right: { style: 'thin' }
    };
  });

  // Data baris
  const rows = document.querySelectorAll("#tabelPiutang tbody tr");
  rows.forEach(tr => {
    const tds = tr.querySelectorAll("td");
    if (tds.length >= 7) {
      const no = tds[0].innerText.trim();
      const nama = tds[1].innerText.trim();
      const nomorator = tds[2].innerText.trim();
      const nomor = tds[3].innerText.trim();
      const hutangText = tds[4].innerText.replace(/Rp\s?|-|\./g, '').trim();
      const hutang = parseInt(hutangText) || 0;
      const op = tds[5].innerText.trim();
      const tanggal = tds[6].innerText.trim();

      const row = sheet.addRow([no, nama, nomorator, nomor, hutang, op, tanggal]);

      // Format kolom hutang (E) sebagai angka dengan pemisah ribuan, rata kanan
      row.getCell(5).numFmt = '#,##0';
      row.getCell(5).alignment = { horizontal: 'right', vertical: 'middle' };

      // Rata tengah untuk kolom No dan Tanggal
      row.getCell(1).alignment = { horizontal: 'center', vertical: 'middle' };
      row.getCell(7).alignment = { horizontal: 'center', vertical: 'middle' };

      // Sisanya rata kiri
      [2, 3, 4, 6].forEach(i => {
        row.getCell(i).alignment = { horizontal: 'left', vertical: 'middle' };
      });

      // Border semua sel
      row.eachCell(cell => {
        cell.border = {
          top: { style: 'thin' }, bottom: { style: 'thin' },
          left: { style: 'thin' }, right: { style: 'thin' }
        };
      });
    }
  });

  // Set lebar kolom
  sheet.columns = [
    { width: 6 },  // No
    { width: 30 }, // Nama
    { width: 18 }, // Nomorator
    { width: 18 }, // Nomor
    { width: 15 }, // Hutang
    { width: 10 }, // OP
    { width: 15 }  // Tanggal
  ];

  // Save file dengan tanggal saat ini untuk nama file
  const today = new Date().toISOString().slice(0, 10);
  const buffer = await workbook.xlsx.writeBuffer();
  saveAs(new Blob([buffer]), `Laporan_Piutang_${today}.xlsx`);
});

document.getElementById('btnExportWord').addEventListener('click', async () => {
  const { Document, Packer, Paragraph, Table, TableCell, TableRow, TextRun, WidthType, AlignmentType, BorderStyle } = window.docx;

  const toko = "<?= addslashes($storeName) ?>";
  const alamat = "<?= addslashes($storeAddress) ?>";

  // Header toko
  const headerToko = new Paragraph({
    children: [new TextRun({ text: toko, bold: true, size: 32 })],
    alignment: AlignmentType.CENTER,
    spacing: { after: 200 },
  });

  // Alamat toko
  const headerAlamat = new Paragraph({
    children: [new TextRun({ text: alamat, size: 24 })],
    alignment: AlignmentType.CENTER,
    spacing: { after: 300 },
  });

  // Judul laporan tanpa tanggal
  const judul = new Paragraph({
    children: [new TextRun({ text: "Laporan Piutang", bold: true, size: 28 })],
    alignment: AlignmentType.CENTER,
    spacing: { after: 300 },
  });

  // Header tabel
  const tableRows = [
    new TableRow({
      children: [
        new TableCell({ children: [new Paragraph({ text: "No", bold: true })], width: { size: 6, type: WidthType.DXA }, verticalAlign: "center" }),
        new TableCell({ children: [new Paragraph({ text: "Nama", bold: true })], width: { size: 30, type: WidthType.DXA }, verticalAlign: "center" }),
        new TableCell({ children: [new Paragraph({ text: "Nomorator", bold: true })], width: { size: 18, type: WidthType.DXA }, verticalAlign: "center" }),
        new TableCell({ children: [new Paragraph({ text: "Nomor", bold: true })], width: { size: 18, type: WidthType.DXA }, verticalAlign: "center" }),
        new TableCell({ children: [new Paragraph({ text: "Hutang", bold: true })], width: { size: 15, type: WidthType.DXA }, verticalAlign: "center" }),
        new TableCell({ children: [new Paragraph({ text: "OP", bold: true })], width: { size: 10, type: WidthType.DXA }, verticalAlign: "center" }),
        new TableCell({ children: [new Paragraph({ text: "Tanggal", bold: true })], width: { size: 15, type: WidthType.DXA }, verticalAlign: "center" }),
      ]
    })
  ];

  // Ambil data dari tabel HTML
  const rows = document.querySelectorAll("#tabelPiutang tbody tr");
  rows.forEach(tr => {
    const tds = tr.querySelectorAll("td");
    if (tds.length >= 7) {
      const no = tds[0].innerText.trim();
      const nama = tds[1].innerText.trim();
      const nomorator = tds[2].innerText.trim();
      const nomor = tds[3].innerText.trim();
      const hutang = tds[4].innerText.trim();
      const op = tds[5].innerText.trim();
      const tanggal = tds[6].innerText.trim();

      // Buat row isi
      tableRows.push(new TableRow({
        children: [
          new TableCell({ children: [new Paragraph(no)], verticalAlign: "center" }),
          new TableCell({ children: [new Paragraph(nama)], verticalAlign: "center" }),
          new TableCell({ children: [new Paragraph(nomorator)], verticalAlign: "center" }),
          new TableCell({ children: [new Paragraph(nomor)], verticalAlign: "center" }),
          new TableCell({ children: [new Paragraph(hutang)], verticalAlign: "center" }),
          new TableCell({ children: [new Paragraph(op)], verticalAlign: "center" }),
          new TableCell({ children: [new Paragraph(tanggal)], verticalAlign: "center" }),
        ]
      }));
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
      children: [
        headerToko,
        headerAlamat,
        judul,
        table
      ],
    }],
  });

  const blob = await Packer.toBlob(doc);
  saveAs(blob, `Laporan_Piutang_${new Date().toISOString().slice(0,10)}.docx`);
});

</script>
</body>
</html>
