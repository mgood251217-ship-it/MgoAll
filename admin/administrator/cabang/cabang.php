<?php
require_once '../../connect.php';
require_once BASE_PATH . '/global_functions.php';
require_once BASE_PATH . '/administrator/session.php';

if ($access == 'ALL') {
    $query = "
        SELECT s.*, 
               u.name AS owner_name, 
               (SELECT COUNT(*) FROM users emp WHERE emp.store_id = s.store_id) AS total_karyawan 
        FROM stores s 
        LEFT JOIN users u ON s.owner_id = u.user_id
    ";
    $result = $koneksi->query($query);
} else {
    $query = "
        SELECT s.*, 
               u.name AS owner_name, 
               (SELECT COUNT(*) FROM users emp WHERE emp.store_id = s.store_id) AS total_karyawan 
        FROM stores s 
        LEFT JOIN users u ON s.owner_id = u.user_id 
        WHERE s.administrator = ?
    ";
    $stmtStore = $koneksi->prepare($query);
    $stmtStore->bind_param("s", $access);
    $stmtStore->execute();
    $result = $stmtStore->get_result();
}

$userResult = $koneksi->query("SELECT user_id, name, username FROM users ORDER BY name ASC");
$all_users = $userResult->fetch_all(MYSQLI_ASSOC);
$userResult->close();

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cabang & Toko</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="<?= BASE_URL ?>/administrator/assets/css/content.css">
</head>
<body>

<?php include BASE_PATH . '/administrator/navbar.php'; ?>

<div id="mainWrapper">
  <?php include BASE_PATH . '/administrator/sidebar.php'; ?>

  <div id="contentWrapper">
    <main id="mainContent">
      <div class="container-fluid">
        <h1 class="mb-4">Cabang & Toko</h1>
        <div class="d-flex justify-content-end">
          <button class="btn btn-primary btn-add-store" data-bs-toggle="modal" data-bs-target="#modalTambahToko">
            <i class="bi bi-plus-lg"></i> Tambah Toko
          </button>
        </div>

        <div class="table-responsive mt-3">
          <table class="table table-modern">
            <thead>
              <tr>
                <th>No.</th>
                <th>Nama</th>
                <th>Cabang</th>
                <th>Alamat</th>
                <th>Nomor</th>
                <th>Email</th>
                <th>Manager</th>
                <th>Karyawan</th>
                <th>Aksi</th>
                <th>Kelola</th>
              </tr>
            </thead>
            <tbody>
              
              <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= $no++ ?></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['branch']) ?></td>
                <td><?= htmlspecialchars($row['address']) ?></td>
                <td><?= htmlspecialchars($row['nomor']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['owner_name'] ?? '-') ?></td>
                <td><?= (int)$row['total_karyawan'] ?> Orang</td>
                <td>
                  <div class="d-flex">
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['store_id'] ?>">
                      <i class="bi bi-pencil-square"></i>
                    </button>
                  </div>
                </td>
                <td>
                  <form class="kelolaForm">
                    <input type="hidden" name="user_id" value="<?= $row['owner_id'] ?>">
                    <button type="submit" class="btn btn-danger">Kelola</button>
                  </form>
                </td>
              </tr>

              <div class="modal fade" id="editModal<?= $row['store_id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-md">
                  <div class="modal-content">
                    <form action="edit_store.php" method="POST">
                      <input type="hidden" name="store_id" value="<?= $row['store_id'] ?>">
                      <div class="modal-header">
                        <h5 class="modal-title">Edit Cabang</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <div class="mb-2">
                          <label class="form-label">Nama Toko</label>
                          <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($row['name']) ?>" required>
                        </div>
                        <div class="mb-2">
                          <label class="form-label">Cabang</label>
                          <input type="text" name="branch" class="form-control" value="<?= htmlspecialchars($row['branch']) ?>" required>
                        </div>
                        <div class="mb-2">
                          <label class="form-label">Alamat</label>
                          <textarea name="address" class="form-control" rows="2" required><?= htmlspecialchars($row['address']) ?></textarea>
                        </div>
                        <div class="mb-2">
                          <label class="form-label">Nomor</label>
                          <input type="text" name="nomor" class="form-control" value="<?= htmlspecialchars($row['nomor']) ?>" required>
                        </div>
                        <div class="mb-2">
                          <label class="form-label">Email</label>
                          <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($row['email']) ?>">
                        </div>
                        <div class="mb-2">
                          <label class="form-label">Manager</label>
                          <select name="owner_id" class="form-select">
                            <option value="">-- Pilih Manager --</option>
                            <?php foreach ($all_users as $mgr): ?>
                              <option value="<?= $mgr['user_id'] ?>" <?= $mgr['user_id'] == $row['owner_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($mgr['name']) ?> (<?= htmlspecialchars($mgr['username']) ?>)
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Simpan</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
              <?php endwhile; ?>

            </tbody>
          </table>
        </div>

      </div>
    </main>

    <?php include BASE_PATH . '/administrator/footer.php'; ?>
  </div>
</div>

<div class="modal fade" id="modalTambahToko" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="add_store.php" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title">Tambah Toko Baru</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Nama Toko</label>
          <input type="text" class="form-control" name="name" required style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase();">
        </div>
        <div class="mb-3">
          <label class="form-label">Cabang</label>
          <input type="text" class="form-control" name="branch" required style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase();">
        </div>
        <div class="mb-3">
          <label class="form-label">Alamat</label>
          <textarea class="form-control" name="address"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Nomor</label>
          <input type="number" class="form-control" name="nomor">
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" name="email">
        </div>
        <div class="mb-3">
          <label class="form-label">Logo</label>
          <input type="file" class="form-control" name="logo" accept="image/*">
        </div>
        <div class="mb-3">
          <label class="form-label">Manager</label>
          <select class="form-control" name="owner_id" style="width:100%">
            <option value="">Pilih Manager</option>
            <?php foreach ($all_users as $user): ?>
              <option value="<?= $user['user_id'] ?>"><?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['username']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php if (isset($_SESSION['swal_success'])): ?>
  <script>
    Swal.fire({
      icon: 'success',
      title: 'Berhasil',
      text: <?= json_encode($_SESSION['swal_success']) ?>,
      timer: 3500,
      timerProgressBar: true,
      showConfirmButton: false
    });
  </script>
  <?php unset($_SESSION['swal_success']); ?>
<?php elseif (isset($_SESSION['swal_error'])): ?>
  <script>
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: <?= json_encode($_SESSION['swal_error']) ?>,
      timer: 3500,
      timerProgressBar: true,
      showConfirmButton: false
    });
  </script>
  <?php unset($_SESSION['swal_error']); ?>
<?php endif; ?>

<script>
document.addEventListener('submit', function(e) {
    if (e.target && e.target.classList.contains('kelolaForm')) {
        e.preventDefault();
        
        const form = e.target;
        const formData = new URLSearchParams(new FormData(form)).toString();

        fetch('set_session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            console.log('Response OK');
            window.open('<?= BASE_URL ?>/customer');
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            alert('Gagal set session!');
        });
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>