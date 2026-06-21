<?php
require_once '../connect.php';
require_once BASE_PATH . '/functions/user_validation.php';

$jenis = ['outdoor', 'indoor', 'laser', 'jersey', 'sublim', 'merchandise'];
$satuan = ['meter', 'centimeter', 'pcs', 'lembar'];

$user_id = $_SESSION['shopee_users']['user_id'] ?? 0;

$stmtProducts = $koneksi->prepare("
    SELECT product_id, name, type, unit_type
    FROM products
    WHERE user_id = ?
    ORDER BY product_id DESC");
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
</head>

<style>


</style>

<body>
<div id="main-wrapper" <?= ($mode ?? 0) === 1 ? 'class="dark-mode"' : '' ?>>
  <?php include BASE_PATH . '/elements/navbar.php'; ?>

  <div id="page-content-wrapper" <?= ($mode ?? 0) === 1 ? 'class="dark-mode"' : '' ?>>
    <div class="judul-page">
      <h2>Produk</h2>
        <label for="modal-toggle" class="btn-shopee">
        Tambah Produk
        </label>
    </div>
    <input type="checkbox" id="modal-toggle" hidden>

    <table class="shopee-table">
        <thead>
            <tr>
                <th>No</th>
                <th>type</th>
                <th>nama</th>
                <th>satuan</th>
                <th>aksi</th>
            </tr>
        </thead>
        <?php $no = 1; ?>
        <tbody>
            <?php while ($product = $resultProducts->fetch_assoc()) { ?>
            <tr>
                <td><?= $no ?></td>
                <td><?= htmlspecialchars($product['name']) ?></td>
                <td><?= htmlspecialchars($product['type']) ?></td>
                <td><?= htmlspecialchars($product['unit_type']) ?></td>
                <td>
                    <button type="button" class="btn-aksi btn-delete"
                    data-id="<?= $product['product_id'] ?>">Delete</button>
                    <label for="modal-update" class="btn-aksi btn-edit" data-id="<?= $product['product_id'] ?>" style="text-indent: 0;">
                    Edit
                    </label>
                </td>
            </tr>
            <?php $no++ ?>
            <?php } ?>
        </tbody>


    </table>

    <!-- Modal -->
    <div class="css-modal">
        <div class="css-modal-dialog">
            <div class="css-modal-header">
                <h5>Modal Tambah Produk</h5>
                <label for="modal-toggle" class="close-btn">&times;</label>
            </div>

            <div class="css-modal-body">
                <form class="shopee-form grid" id="form-tambah-produk">
                    <input type="text" name="product_name" id="">
                    <select name="product_type" id="product_type">
                        <option value=""><= Pilih Jenis =></option>
                        <?php
                        $a = 0;
                        while ($a < count($jenis)) { ?>
                        <option value="<?= $jenis[$a] ?>"><?= ucwords($jenis[$a]) ?></option>
                        <?php $a++; } ?>
                    </select>
                    <select name="product_unit_type" id="product_unit_type">
                        <option value=""><= Pilih Satuan =></option>
                        <?php
                        $a = 0;
                        while ($a < count($satuan)) { ?>
                        <option value="<?= $satuan[$a] ?>"><?= ucwords($satuan[$a]) ?></option>
                        <?php $a++; } ?>
                    </select>
                </form>

                
            </div>

            <div class="css-modal-footer">
                <label id="form-tambah-submit" class="btn-shopee">Tambahkan +</label>
            </div>
        </div>
    </div>

    <!-- Modal Edit -->
     <input type="checkbox" id="modal-update" hidden>
    <div class="css-modal-update">
        <div class="css-modal-dialog">
            <div class="css-modal-header">
                <h5>Modal Edit Produk</h5>
                <label for="modal-update" class="close-btn">&times;</label>
            </div>

            <div class="css-modal-body">
                <form class="shopee-form grid" id="form-edit-produk">
                    <input type="text" name="product_name" id="product_name">
                    <select name="product_type" id="product_type">
                        <option value=""><= Pilih Jenis =></option>
                        <?php
                        $a = 0;
                        while ($a < count($jenis)) { ?>
                        <option value="<?= $jenis[$a] ?>"><?= ucwords($jenis[$a]) ?></option>
                        <?php $a++; } ?>
                    </select>
                    <select name="product_unit_type" id="product_unit_type">
                        <option value=""><= Pilih Satuan =></option>
                        <?php
                        $a = 0;
                        while ($a < count($satuan)) { ?>
                        <option value="<?= $satuan[$a] ?>"><?= ucwords($satuan[$a]) ?></option>
                        <?php $a++; } ?>
                    </select>
                </form>

                
            </div>

            <div class="css-modal-footer">
                <label id="form-edit" class="btn-shopee">Edit +</label>
            </div>
        </div>
    </div>

  <?php include BASE_PATH . '/elements/footer.php'; ?>
</div>
<script>

document.getElementById('form-tambah-submit').addEventListener('click', () => {

    const form = document.querySelector('#form-tambah-produk');
    const formData = new FormData(form);

    fetch('<?= BASE_URL ?>/functions/add_product.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        if (res.status) {
            console.log('✅ ' + res.message);
            location.reload();
        } else {
            alert('❌ ' + res.message);
            console.error(res);
        }
    })
    .catch(err => {
        console.error(err);
    });
});

</script>

<script>
document.addEventListener('click', function(e){
    if(e.target.classList.contains('btn-delete')){
        const productId = e.target.dataset.id;
        console.log(productId);
        

        if(!confirm('Yakin hapus produk?')) return;

        fetch('<?= BASE_URL ?>/functions/delete_product.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                product_id: productId
            })
        })
        .then(res => res.json())
        .then(res => {
            if(res.success){
                alert('Produk berhasil dihapus');
                location.reload();
            }else{
                alert(res.message || 'Gagal menghapus produk');
            }
        });
    }
});

document.querySelectorAll('.btn-edit').forEach(button => {
    button.addEventListener('click', () => {
        const productId = button.dataset.id;

        fetch(`<?= BASE_URL ?>/functions/get_product.php?product_id=${productId}`)
        .then(res => res.json())
        .then(product => {
            document.querySelector('#form-edit-produk #product_name').value = product.name;
            document.querySelector('#form-edit-produk #product_type').value = product.type;
            document.querySelector('#form-edit-produk #product_unit_type').value = product.unit_type;

            document.getElementById('form-edit').onclick = function() {
                const form = document.querySelector('#form-edit-produk');
                const formData = new FormData(form);
                formData.append('product_id', productId);

                fetch('<?= BASE_URL ?>/functions/edit_product.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(res => {
                    if (res.status) {
                        alert('✅ ' + res.message);
                        location.reload();
                    } else {
                        alert('❌ ' + res.message);
                        console.error(res);
                    }
                })
                .catch(err => {
                    console.error(err);
                });
            };
        });
    });
});
</script>


</body>
</html>
