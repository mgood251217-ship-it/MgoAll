<?php

require_once '../../connect.php';
require_once BASE_PATH . '/global_functions.php';

// Query menampilkan data users
$result = $koneksi->query("
  SELECT 
    users.user_id, 
    users.name, 
    users.role, 
    users.picture, 
    stores.name AS store_name 
  FROM users
  LEFT JOIN stores ON users.store_id = stores.store_id
");

?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>User & Karyawan</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/content.css">
  <style>
    .img-thumbnail-small {
      width: 40px;
      height: 40px;
      object-fit: cover;
      border-radius: 4px;
    }
  </style>
</head>
<body>

<?php include BASE_PATH . '/administrator/navbar.php'; ?>

<div id="mainWrapper">
  <?php include BASE_PATH . '/administrator/sidebar.php'; ?>

  <div id="contentWrapper">
    <main id="mainContent">
      <div class="container-fluid">
        <h1 class="mb-4">User & Karyawan</h1>
        <div class="d-flex justify-content-end">
          <button class="btn btn-primary btn-add-store" data-bs-toggle="modal" data-bs-target="#modalTambahUser">
            <i class="bi bi-plus-lg"></i> Tambah User
          </button>
        </div>

        <div class="table-responsive">
          <table class="table table-modern align-middle">
            <thead>
              <tr>
                <th>No</th>
                <th>Nama</th>
                <th>Peran</th>
                <th>Toko</th>
                <th>Picture</th>
              </tr>
            </thead>
            <tbody>
              <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
                <tr>
                  <td><?= $no++ ?></td>
                  <td><?= htmlspecialchars($row['name']) ?></td>
                  <td><?= htmlspecialchars($row['role']) ?></td>
                  <td class="d-flex justify-content-between align-items-center">
                    <span><?= htmlspecialchars($row['store_name']) ?></span>
                    <div class="dropdown">
                      <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-arrow-left-right"></i>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end">
                        <?php
                          $stores = $koneksi->query("SELECT store_id, name FROM stores ORDER BY name");
                          while ($store = $stores->fetch_assoc()):
                        ?>
                          <li>
                            <a class="dropdown-item" href="#" onclick="changeStore(<?= $row['user_id'] ?>, <?= $store['store_id'] ?>)">
                              <?= htmlspecialchars($store['name']) ?>
                            </a>
                          </li>
                        <?php endwhile; ?>
                      </ul>
                    </div>
                  </td>

                  <td>
                    <?php if (!empty($row['picture'])): ?>
                      <img src="<?= BASE_URL . '/assets/img/user/' . htmlspecialchars($row['picture']) ?>" alt="Foto <?= htmlspecialchars($row['name']) ?>" class="img-thumbnail-small" />
                    <?php else: ?>
                      <span class="text-muted">No Image</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>

    <?php include BASE_PATH . '/administrator/footer.php'; ?>
  </div>
</div>

<!-- Modal Tambah User -->
<div class="modal fade" id="modalTambahUser" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="add_user.php" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title">Tambah User Baru</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="form-group-row">
          <label for="userName" class="form-label">Nama</label>
          <input type="text" class="form-control" id="userName" name="name" required>
        </div>
        <div class="form-group-row">
          <label for="username" class="form-label">Username</label>
          <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="form-group-row">
          <label for="password" class="form-label">Password</label>
          <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <div class="form-group-row">
          <label for="initial" class="form-label">Initial</label>
          <input type="text" class="form-control" id="initial" name="initial" required>
        </div>
        <div class="form-group-row">
          <label for="role" class="form-label">Peran</label>
          <select id="role" name="role" class="form-select" required>
            <option value="">-- Pilih Peran --</option>
            <option value="ADMIN">ADMIN</option>
            <option value="SETTING">SETTING</option>
            <option value="ONLINE">ONLINE</option>
            <option value="PRODUKSI">PRODUKSI</option>
          </select>
        </div>
        <div class="form-group-row">
          <label for="store_id" class="form-label">Toko</label>
          <select id="store_id" name="store_id" class="form-select" required>
            <option value="">-- Pilih Toko --</option>
            <?php
              $stores = $koneksi->query("SELECT store_id, name FROM stores ORDER BY name");
              while ($s = $stores->fetch_assoc()):
            ?>
              <option value="<?= $s['store_id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group-row">
          <label for="picture" class="form-label">Foto</label>
          <input type="file" class="form-control" id="picture" name="picture" accept="image/*">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Konfirmasi -->
<div class="modal fade" id="confirmChangeModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Konfirmasi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Apakah Anda yakin ingin memindahkan user ke toko ini?</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button class="btn btn-primary" onclick="confirmChangeStore()">Ya, Pindahkan</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Sukses -->
<div class="modal fade" id="successModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content text-center p-3">
      <p class="mb-0">✅ Berhasil pindah toko.</p>
    </div>
  </div>
</div>
<br>
<br>
<script>
  let selectedUserId = null;
  let selectedStoreId = null;

  function changeStore(userId, storeId) {
    selectedUserId = userId;
    selectedStoreId = storeId;
    const confirmModal = new bootstrap.Modal(document.getElementById('confirmChangeModal'));
    confirmModal.show();
  }

  function confirmChangeStore() {
    if (!selectedUserId || !selectedStoreId) return;

    fetch('change_user_store.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ user_id: selectedUserId, store_id: selectedStoreId })
    })
    .then(res => res.text())
    .then(data => {
      const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmChangeModal'));
      confirmModal.hide();

      if (data.trim() === 'OK') {
        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
        successModal.show();
        setTimeout(() => location.reload(), 1000);
      } else {
        alert('Gagal: ' + data);
      }
    })
    .catch(err => alert('Error: ' + err));
  }
</script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
