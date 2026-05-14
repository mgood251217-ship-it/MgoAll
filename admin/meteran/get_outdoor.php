<?php
require_once 'get_order_ids.php';

// OUTDOOR
if (empty($order_ids)) {
    $products = [];
    $product_data = [];
    $max_rows = 0;
} else {
    // Ambil semua produk dengan type OUTDOOR
    $stmt = $koneksi->prepare("SELECT product_id, name FROM products WHERE type = 'OUTDOOR' AND store_id = ?");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $outdoor_products = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Ambil juga produk type PAKET INDOOR OUTDOOR
    $stmt = $koneksi->prepare("SELECT product_id, name FROM products WHERE type = 'PAKET INDOOR OUTDOOR' AND store_id = ?");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $paket_products = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Gabungkan ke dalam satu array final, cocokkan nama paket ke nama outdoor
    $products = $outdoor_products;
    foreach ($paket_products as $paket) {
        foreach ($outdoor_products as $outdoor) {
            // Cek jika nama outdoor terdapat di nama paket
            if (stripos($paket['name'], $outdoor['name']) !== false) {
                $products[] = [
                    'product_id' => $paket['product_id'],
                    'name' => $outdoor['name'] // Gunakan nama OUTDOOR sebagai label
                ];
                break; // Setelah match, lanjut ke paket berikutnya
            }
        }
    }

    // Ambil data order item untuk semua produk yang dipilih
    $product_data = [];
    foreach ($products as $product) {
        $pid = $product['product_id'];
        $in = implode(',', array_fill(0, count($order_ids), '?'));
        $types = str_repeat('i', count($order_ids) + 1);
        $params = array_merge([$pid], $order_ids);

        $queryStr = "SELECT size, quantity FROM order_items WHERE product_id = ? AND order_id IN ($in)";
        $query = $koneksi->prepare($queryStr);
        if (!$query) {
            die("Query error: " . $koneksi->error);
        }
        $query->bind_param($types, ...$params);
        $query->execute();
        $res = $query->get_result();

        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $size = $row['size'];
            $qty = (int)$row['quantity'];
            if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $size, $match)) {
                $p = floatval($match[1]);
                $l = floatval($match[2]);
                $m2 = $p * $l * $qty;
                $rows[] = ['p' => $p, 'l' => $l, 'qty' => $qty, 'm2' => $m2];
            }
        }

        // Gabung berdasarkan nama OUTDOOR
        $found = false;
        foreach ($product_data as &$pd) {
            if ($pd['name'] === $product['name']) {
                $pd['rows'] = array_merge($pd['rows'], $rows);
                $found = true;
                break;
            }
        }
        unset($pd);

        if (!$found) {
            $product_data[] = [
                'name' => $product['name'],
                'rows' => $rows
            ];
        }
    }

    // Cari jumlah maksimum baris (untuk keperluan display)
    $max_rows = 0;
    foreach ($product_data as $product) {
        $count_rows = count($product['rows']);
        if ($count_rows > $max_rows) $max_rows = $count_rows;
    }
}

?>

<div class="excel-container" id="table-container" >
    <?php foreach ($product_data as $product): ?>
    <table class="excel-table" class="tableOutdoor">
    <thead >
        <tr><th colspan="4"><?= htmlspecialchars($product['name']) ?></th></tr>
        <tr><th>P</th><th>L</th><th>Qty</th><th>M2</th></tr>
    </thead>
    <tbody>
        <?php if (empty($product['rows'])): ?>
        <?php for ($i = 0; $i < $max_rows; $i++): ?>
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        <?php endfor; ?>
        <tr style="font-weight:bold;">
            <td colspan="3" style="text-align:right;">Total m2:</td>
            <td>0</td>
        </tr>
        <?php else: ?>
        <?php 
            $total_m2_product = 0;
            foreach ($product['rows'] as $row):
            $total_m2_product += $row['m2'];
            $total_all_m2_outdoor += $row['m2']; // Tambahkan ke total keseluruhan di sini
        ?>
            <tr>
            <td><?= $row['p'] ?></td>
            <td><?= $row['l'] ?></td>
            <td><?= $row['qty'] ?></td>
            <td><?= $row['m2'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php for ($i = count($product['rows']); $i < $max_rows; $i++): ?>
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        <?php endfor; ?>
        <tr style="font-weight:bold; ">
            <td colspan="3" style="text-align:right;">Total m2:</td>
            <td><?= round($total_m2_product, 2) ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
    </table>
    <?php endforeach; ?>
</div>
<!-- TOTAL M2 OUTDOOR -->
<div class="mt-3 d-flex align-items-center justify-content-between">
    <table class="table table-bordered" style="max-width:500px; font-weight:bold;">
    <tr style="background:#dff0d8;">
        <td class="text-end">Total Meteran Outdoor:</td>
        <td class="text-center"><?= round($total_all_m2_outdoor, 2) ?></td>
    </tr>
    </table>
    <!-- <button class="btn btn-success d-inline-block" id="btnExportExcel">Export Excel</button> -->
</div>
<script>
// document.getElementById('btnExportExcel').addEventListener('click', function() {
//     console.log('c');
    
//     const workbook = new ExcelJS.Workbook();
//     const worksheet = workbook.addWorksheet('Produksi Outdoor');

//     // Tambahkan header toko
//     worksheet.addRow([ 'Nama Toko:', '<?= $storeName ?>' ]);
//     worksheet.addRow([ 'Alamat Toko:', '<?= $storeAddress ?>' ]);
//     worksheet.addRow([]);
    
//     // Ambil semua tabel di dalam container
//     const tables = document.querySelectorAll('#table-container table.excel-table');
//     tables.forEach((table, index) => {
//         // Tambahkan judul produk
//         const title = table.querySelector('thead tr:first-child th').innerText;
//         worksheet.addRow([title]);
        
//         // Tambahkan header kolom
//         const headerRow = ['P', 'L', 'Qty', 'M2'];
//         worksheet.addRow(headerRow);
        
//         // Tambahkan data baris
//         const rows = table.querySelectorAll('tbody tr');
//         rows.forEach(row => {
//             const rowData = Array.from(row.cells).map(cell => cell.innerText);
//             worksheet.addRow(rowData);
//         });

//         // Tambahkan baris kosong antar tabel
//         if (index < tables.length - 1) {
//             worksheet.addRow([]);
//         }
//     });

//     // Simpan file Excel
//     workbook.xlsx.writeBuffer().then(function(buffer) {
//         const blob = new Blob([buffer], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
//         saveAs(blob, "Produksi_Outdoor_<?= $start_date_only ?>_sampai_<?= $end_date_only ?>.xlsx");
//     });
// });
</script>