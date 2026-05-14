<?php
// File: cabang.php
require_once '../../connect.php';
require_once BASE_PATH . '/global_functions.php';
require_once BASE_PATH . '/administrator/session.php';

if ($access == 'ALL') {
  $result = $koneksi->query("SELECT s.*, (SELECT COUNT(*) FROM users u WHERE u.store_id = s.store_id) as total_karyawan FROM stores s");
}else {
  $stmtStore = $koneksi->prepare("SELECT s.*, (SELECT COUNT(*) FROM users u WHERE u.store_id = s.store_id) as total_karyawan FROM stores s WHERE administrator = ?");
  $stmtStore->bind_param("s", $access);
  $stmtStore->execute();
  $result = $stmtStore->get_result();
}


// Ambil user untuk opsi dropdown owner
$userResult = $koneksi->query("SELECT user_id, name, username FROM users ORDER BY name ASC");


?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cabang & Toko</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Include SweetAlert2 CSS & JS (pakai CDN) -->
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

        <?php $modalData = []; ?>

        <div class="table-responsive">
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
                <td>
                  <?php
                    $ownerName = '-';
                    if ($row['owner_id']) {
                      $stmt = $koneksi->prepare("SELECT name FROM users WHERE user_id = ?");
                      $stmt->bind_param("i", $row['owner_id']);
                      $stmt->execute();
                      $stmt->bind_result($ownerName);
                      $stmt->fetch();
                      $stmt->close();
                    }
                    echo htmlspecialchars($ownerName);
                  ?>
                </td>
                <td><?= (int)$row['total_karyawan'] ?> Orang</td>
                <td>
                  <div class="d-flex">
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['store_id'] ?>">
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <!-- <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $row['store_id'] ?>">
                      <i class="bi bi-trash"></i>
                    </button> -->
                  </div>
                </td>
                <td>
                  <form class="kelolaForm">
                    <input type="hidden" name="user_id" value="<?= $row['owner_id'] ?>">
                    <button type="submit" class="btn btn-danger">Kelola</button>
                  </form>
                </td>
              </tr>

              <!-- ✅ Modal Edit -->
              <div class="modal fade" id="editModal<?= $row['store_id'] ?>" tabindex="-1" aria-labelledby="editModalLabel<?= $row['store_id'] ?>" aria-hidden="true">
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
                            <?php
                            $managers = $koneksi->query("SELECT user_id, name, username FROM users");
                            while ($mgr = $managers->fetch_assoc()):
                            ?>
                              <option value="<?= $mgr['user_id'] ?>" <?= $mgr['user_id'] == $row['owner_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($mgr['name']) ?> (<?= htmlspecialchars($mgr['username']) ?>)
                              </option>
                            <?php endwhile; ?>
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

              <!-- ✅ Modal Delete -->
              <div class="modal fade" id="deleteModal<?= $row['store_id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $row['store_id'] ?>" aria-hidden="true">
                <div class="modal-dialog modal-sm">
                  <div class="modal-content">
                    <form action="delete_store.php" method="POST">
                      <input type="hidden" name="store_id" value="<?= $row['store_id'] ?>">
                      <div class="modal-header">
                        <h5 class="modal-title">Konfirmasi Hapus</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        Yakin ingin menghapus cabang <strong><?= htmlspecialchars($row['name']) ?></strong>?
                      </div>
                      <div class="modal-footer">
                        <button type="submit" class="btn btn-danger">Hapus</button>
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

<!-- Modal Tambah Toko -->
<div class="modal fade" id="modalTambahToko" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="add_store.php" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title">Tambah Toko Baru</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="form-group-row">
          <label for="storeName" class="form-label">Nama Toko</label>
          <input type="text" class="form-control" id="storeName" name="name" required style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase();">
        </div>
        <div class="form-group-row">
          <label for="branch" class="form-label">Cabang</label>
          <input type="text" class="form-control" id="branch" name="branch" required style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase();">
        </div>
        <div class="form-group-row">
          <label for="address" class="form-label">Alamat</label>
          <textarea class="form-control" id="address" name="address"></textarea>
        </div>
        <div class="form-group-row">
          <label for="nomor" class="form-label">Nomor</label>
          <input type="number" class="form-control" id="nomor" name="nomor">
        </div>
        <div class="form-group-row">
          <label for="email" class="form-label">Email</label>
          <input type="email" class="form-control" id="email" name="email">
        </div>

        <!-- ✅ Logo Upload -->
        <div class="form-group-row">
          <label for="logo" class="form-label">Logo</label>
          <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
        </div>

        <div class="form-group-row">
          <label for="owner_id" class="form-label">Manager</label>
          <select class="form-control" id="owner_id" name="owner_id" style="width:100%">
            <option value="">Pilih Manager</option>
            <?php while ($user = $userResult->fetch_assoc()): ?>
              <option value="<?= $user['user_id'] ?>"><?= htmlspecialchars($user['name']) ?>(<?= htmlspecialchars($user['username']) ?>)</option>
            <?php endwhile; ?>
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


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).on('submit', '.kelolaForm', function(e) {
  e.preventDefault();

  const form = $(this);

  $.ajax({
    url: 'set_session.php',
    type: 'POST',
    data: form.serialize(),
    success: function(response) {
      console.log('Response:', response);
      window.open('<?= BASE_URL ?>/customer');
    },
    error: function(xhr, status, error) {
      console.error('AJAX Error:', error);
      alert('Gagal set session!');
    }
  });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>