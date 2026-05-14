<?php
require_once '../connect.php';
require_once BASE_PATH . '/functions/user_validation.php';

$user_id = $_SESSION['shopee_users']['user_id'] ?? 0;

date_default_timezone_set('Asia/Jakarta');

$date = $_GET['d'] ?? date('Y-m-d');

$stmtProducts = $koneksi->prepare("
    SELECT product_id, name, unit_type, type
    FROM products
    WHERE user_id = ?
    ORDER BY type DESC");
$stmtProducts->bind_param("i", $user_id);
$stmtProducts->execute();
$resultProducts = $stmtProducts->get_result();

?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard</title>
  <?php include BASE_PATH . '/elements/header.php'; ?>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/content.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dark_mode.css">
    <script src="https://cdn.jsdelivr.net/npm/exceljs/dist/exceljs.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/file-saver@2.0.5/dist/FileSaver.min.js"></script>
    <style>
        .loading {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>

<body>
<div id="main-wrapper" <?= ($mode ?? 0) === 1 ? 'class="dark-mode"' : '' ?>>
  <?php include BASE_PATH . '/elements/navbar.php'; ?>
    <div id="page-content-wrapper" <?= ($mode ?? 0) === 1 ? 'class="dark-mode"' : '' ?>>
        <div class="judul-page">
        <h2>Meteran</h2>
            <div class="d-flex gap-2 ">
                <button class="btn btn-success" id="btnExportExcel">Export Excel</button>
                <form class="shopee-form" id="formTanggal" method="get">
                    <input type="date" name="d" id="d" value="<?= htmlspecialchars($date) ?>" onchange="this.form.submit()">
                </form>
            </div>
        </div>
        <table class="shopee-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Type Produk</th>
                    <th>Nama Produk</th>
                    <th>Total Meteran</th>
                    <th>List Meteran</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; ?>
                <?php while ($product = $resultProducts->fetch_assoc()) { ?>
                <tr>
                    <td><?= $no ?></td>
                    <td><?= htmlspecialchars($product['type']) ?></td>
                    <td><?= htmlspecialchars($product['name']) ?></td>
                    <td>
                    <?php 
                        $stmtMeter = $koneksi->prepare("
                            SELECT total, date, meter_id
                            FROM meters
                            WHERE product_id = ?
                            AND date = ?");
                            $stmtMeter->bind_param("is", $product['product_id'], $date);
                            $stmtMeter->execute();
                            $resultMeter = $stmtMeter->get_result()->fetch_assoc();  
                            echo htmlspecialchars($resultMeter['total'] ?? 0);  
                            $dateMeter = $resultMeter['date'] ?? $date;
                            $meterId = $resultMeter['meter_id'] ?? 0;       
                    ?>
                    </td>
                    <td>
                        <?php
                        if ($meterId != 0) {
                            $stmtListMeter = $koneksi->prepare("
                                SELECT list_meter_id, value
                                FROM list_meters
                                WHERE meter_id = ?");
                            $stmtListMeter->bind_param("i", $meterId);
                            $stmtListMeter->execute();
                            $resultListMeter = $stmtListMeter->get_result();
                            while ($listMeter = $resultListMeter->fetch_assoc()) {
                                ?>
                                <span class="deleteList"
                                data-id="<?= $listMeter['list_meter_id'] ?>
                                ">
                                    <?= htmlspecialchars($listMeter['value']) ?>
                                </span>

                                <?php
                            }
                            ?>
                        
                        <?php

                        }else {
                            echo "";
                        }
                        
                        ?>
                    </td>
                    <td><?= htmlspecialchars($dateMeter) ?></td>
                    <td style="width: 15vw;">
                        <div class="formUpdateMeteran" style="align-items: center; width: 100%; display: flex; gap: 5px;">
                            <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                            <input type="hidden" name="date" value="<?= $dateMeter; ?>">
                            <input type="number" step="any" min="0" name="value" class="value" placeholder="+ Meteran" style="padding: 3px 2px;" required >
                            <button type="button" class="btn-aksi btnTambahMeteran">Tambah</button>
                        </div>
                    </td>
                </tr>
                <?php $no++ ?>
                <?php } ?>
            </tbody>
        </table>
        <div>
            <button class="btn-shopee" id="meteranBulanan">Meteran Bulanan</button>
        </div>
    </div>

  <?php include BASE_PATH . '/elements/footer.php'; ?>
</div>
<script>

document.getElementById('meteranBulanan').addEventListener('click', function() {
    window.location.href = '<?= BASE_URL ?>/indexes/meteran_bulanan.php';
});

document.getElementById('btnExportExcel').addEventListener('click', function() {
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Data Meteran');

    // Tambahkan header
    worksheet.columns = [
        { header: 'No', key: 'no', width: 10 },
        { header: 'Type Produk', key: 'type_produk', width: 30 },
        { header: 'Nama Produk', key: 'nama_produk', width: 30 },
        { header: 'Total Meteran', key: 'total_meteran', width: 20 },
        { header: 'List Meteran', key: 'list_meteran', width: 40 },
        { header: 'Tanggal', key: 'tanggal', width: 20 }
    ];

    // Tambahkan data dari tabel HTML
    const rows = document.querySelectorAll('.shopee-table tbody tr');
    rows.forEach((row, index) => {
        const cells = row.querySelectorAll('td');
        worksheet.addRow({
            no: cells[0].innerText,
            type_produk: cells[1].innerText,
            nama_produk: cells[2].innerText,
            total_meteran: cells[3].innerText,
            list_meteran: cells[4].innerText,
            tanggal: cells[5].innerText
        });
    });

    // Simpan file Excel
    workbook.xlsx.writeBuffer().then(function(buffer) {
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        saveAs(blob, 'data_meteran.xlsx');
    });
});

document.querySelectorAll('.shopee-table tbody tr').forEach(row => {
    
    row.addEventListener('click', () => {
        document.querySelectorAll('.shopee-table tbody tr').forEach(r => r.classList.remove('selected'));
        row.classList.toggle('selected');
    });

    // ENTER di input value -> klik tombol tambah di baris itu
    const inputValue = row.querySelector('input.value');
    const btnTambah = row.querySelector('.btnTambahMeteran');
    if (inputValue && btnTambah) {
        inputValue.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                btnTambah.click();
            }
        });
    }
    
});

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('deleteList')) {

        const btn = e.target;
        const listMeterId = btn.getAttribute('data-id');

        if (!confirm('Yakin hapus data list meteran ini?')) return;

        // ✅ prevent double click
        if (btn.classList.contains('loading')) return;
        btn.classList.add('loading');

        const originalText = btn.innerText;
        btn.innerText = '...'; // loading sederhana
        btn.style.pointerEvents = 'none';

        fetch('<?= BASE_URL ?>/functions/delete_list_meter.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ list_meter_id: listMeterId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {

                const row = btn.closest('tr');

                // hapus span
                const span = row.querySelector(`span[data-id="${listMeterId}"]`);
                if (span) span.remove();

                // update total
                const totalCell = row.querySelector('td:nth-child(4)');
                if (totalCell) {
                    totalCell.innerText = data.updated_total;
                }

            } else {
                alert(data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Terjadi kesalahan');
        })
        .finally(() => {
            // ✅ kembalikan state kalau gagal
            btn.classList.remove('loading');
            btn.innerText = originalText;
            btn.style.pointerEvents = 'auto';
        });
    }
});

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('btnTambahMeteran')) {

        const btn = e.target;

        // ✅ prevent spam klik
        if (btn.classList.contains('loading')) return;
        btn.classList.add('loading');

        let form = btn.closest('.formUpdateMeteran');
        let productId = form.querySelector('input[name="product_id"]').value;
        let date = form.querySelector('input[name="date"]').value;
        let value = form.querySelector('input[name="value"]').value;

        if (value === '' || isNaN(value) || Number(value) < 0) {
            alert('Masukkan nilai meteran yang valid');
            btn.classList.remove('loading');
            return;
        }

        const originalText = btn.innerText;
        btn.innerText = 'Loading...';
        btn.disabled = true;

        fetch('<?= BASE_URL ?>/functions/update_meteran.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                product_id: productId, 
                date: date, 
                value: value 
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {

                let row = form.closest('tr');

                // update total
                row.querySelector('td:nth-child(4)').innerText = data.updated_total;

                // tambah span baru
                let listTd = row.querySelector('td:nth-child(5)');
                let newSpan = document.createElement('span');
                newSpan.className = 'deleteList';
                newSpan.setAttribute('data-id', data.new_list_id);
                newSpan.textContent = data.value;
                listTd.appendChild(newSpan);

                // reset input
                form.querySelector('input[name="value"]').value = '';

            } else {
                alert(data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Terjadi kesalahan');
        })
        .finally(() => {
            btn.classList.remove('loading');
            btn.innerText = originalText;
            btn.disabled = false;
        });
    }
});
</script>
</body>
</html>
