<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/controllers/UserController.php';
require_once BASE_PATH . '/models/Location.php';
require BASE_PATH . '/access_rights.php';

$userController = new UserController($koneksi);
$users = $userController->getByStore($store_id);

$locationModel = new Location($koneksi);
$locations = $locationModel->getAllLocation();
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Manajemen User</title>
  <?php include BASE_PATH . '/header.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div id="main-wrapper" <?= ($mode === 1) ? 'class="dark-mode"' : '' ?>>
  <?php include BASE_PATH . '/navbar.php'; ?>
  <div id="main-content" <?= (isset($mode) && $mode === 1) ? 'class="dark-mode"' : '' ?>>
    <?php include BASE_PATH . '/sidebar.php'; ?>
    <div id="page-content-wrapper">
      <?php include 'chart.php'; ?>

      <div id="map" style="height: 400px;"></div>
      <button id="setLocationBtn" class="btn btn-primary mt-2">Set Lokasi Saya</button>

      <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h1 class="mb-0">Manajemen User</h1>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
          + Tambah User
        </button>
      </div>

      <?php if (empty($users)): ?>
        <div class="alert alert-warning">Belum ada user terdaftar.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-bordered table-striped text-center">
            <thead class="table-primary">
              <tr>
                <th>No</th>
                <th>Nama</th>
                <th>Role</th>
                <th>Initial</th>
                <th>Foto</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $no => $u): ?>
              <tr>
                <td><?= $no + 1 ?></td>
                <td><?= htmlspecialchars($u['name']) ?></td>
                <td><span class="badge bg-primary"><?= htmlspecialchars($u['role']) ?></span></td>
                <td><?= htmlspecialchars($u['initial']) ?></td>
                <td>
                  <?php if (!empty($u['picture'])): ?>
                    <img src="<?= BASE_URL ?>/assets/img/user/<?= htmlspecialchars($u['picture']) ?>" width="40" height="40" class="img-thumbnail rounded">
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td>
                  <button class="btn btn-sm btn-warning btn-edit me-1" data-user='<?= json_encode($u) ?>'>Edit</button>
                  <form method="POST" action="store_action.php" class="d-inline delete-user-form">
                    <input type="hidden" name="store" value="delete_user">
                    <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                    <input type="hidden" name="picture" value="<?= $u['picture'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <?php include BASE_PATH . '/footer.php'; ?>
  </div>
  <br>

  <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <form id="addUserForm" method="POST" action="store_action.php" enctype="multipart/form-data" class="modal-content" autocomplete="off">
        <input type="hidden" name="store" value="add_user">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title" id="addUserLabel">Tambah User Baru</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3 row">
            <label class="col-md-3 col-form-label">Nama</label>
            <div class="col-md-9">
              <input type="text" name="name" class="form-control" required style="text-transform:uppercase">
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-md-3 col-form-label">Username</label>
            <div class="col-md-9">
              <input type="text" name="username" class="form-control" required>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-md-3 col-form-label">Password</label>
            <div class="col-md-9">
              <input type="text" name="password" class="form-control" required style="-webkit-text-security: disc;">
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-md-3 col-form-label">Role</label>
            <div class="col-md-9">
              <select name="role" class="form-select" required>
                <option value="">--Pilih--</option>
                <option value="MANAGER">MANAGER</option>
                <option value="ADMIN">ADMIN</option>
                <option value="SETTING">SETTING</option>
                <option value="ONLINE">ONLINE</option>
                <option value="PRODUKSI">PRODUKSI</option>
              </select>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-md-3 col-form-label">Initial</label>
            <div class="col-md-9">
              <input type="text" name="initial" class="form-control" maxlength="5" required style="text-transform:uppercase">
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-md-3 col-form-label">Foto</label>
            <div class="col-md-9">
              <input type="file" name="picture" accept="image/*" class="form-control">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Simpan User</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form id="editUserForm" method="POST" action="store_action.php" enctype="multipart/form-data" class="modal-content">
        <input type="hidden" name="store" value="update_user">
        <div class="modal-header">
          <h5 class="modal-title" id="editUserLabel">Edit User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="user_id" id="edit_user_id">
          <input type="hidden" name="old_picture" id="edit_old_picture">

          <div class="mb-3">
            <label>Nama</label>
            <input type="text" name="name" id="edit_name" class="form-control" required style="text-transform:uppercase">
          </div>
          <div class="mb-3">
            <label>Username</label>
            <input type="text" name="username" id="edit_username" class="form-control" required>
          </div>
          <div class="mb-3">
            <label>Password Baru <small class="text-muted">(Kosongkan jika tidak ingin ganti)</small></label>
            <input type="password" name="password" class="form-control">
          </div>
          <div class="mb-3">
            <label>Role</label>
            <select name="role" id="edit_role" class="form-select" required>
              <option value="">--Pilih--</option>
              <option value="MANAGER">MANAGER</option>
              <option value="ADMIN">ADMIN</option>
              <option value="SETTING">SETTING</option>
              <option value="ONLINE">ONLINE</option>
              <option value="PRODUKSI">PRODUKSI</option>
            </select>
          </div>
          <div class="mb-3">
            <label>Initial</label>
            <input type="text" name="initial" id="edit_initial" class="form-control" maxlength="5" required style="text-transform:uppercase">
          </div>
          <div class="mb-3">
            <label>Ganti Foto (Opsional)</label>
            <input type="file" name="picture" accept="image/*" class="form-control">
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  async function sendFormData(formElement, modalId = null) {
    const formData = new FormData(formElement);
    try {
      const response = await fetch('store_action.php', {
        method: 'POST',
        body: formData
      });
      const data = await response.json();
      
      if (data.success) {
        if (modalId) {
          bootstrap.Modal.getInstance(document.getElementById(modalId))?.hide();
        }
        Swal.fire({ icon: 'success', title: 'Berhasil', text: data.message }).then(() => {
          window.location.reload();
        });
      } else {
        Swal.fire({ icon: 'error', title: 'Gagal', html: data.errors.join('<br>') });
      }
    } catch (error) {
      Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan sistem.' });
    }
  }

  document.getElementById('addUserForm').addEventListener('submit', function (e) {
    e.preventDefault();
    sendFormData(this, 'addUserModal');
  });

  document.getElementById('editUserForm').addEventListener('submit', function (e) {
    e.preventDefault();
    sendFormData(this, 'editUserModal');
  });

  document.querySelectorAll('.delete-user-form').forEach(form => {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      Swal.fire({
        title: 'Yakin ingin menghapus?',
        text: "Data user akan dihapus permanen.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal'
      }).then((result) => {
        if (result.isConfirmed) {
          sendFormData(form);
        }
      });
    });
  });

  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', function () {
      const user = JSON.parse(this.dataset.user);
      document.getElementById('edit_user_id').value = user.user_id;
      document.getElementById('edit_name').value = user.name;
      document.getElementById('edit_username').value = user.username;
      document.getElementById('edit_role').value = user.role;
      document.getElementById('edit_initial').value = user.initial;
      document.getElementById('edit_old_picture').value = user.picture;
      new bootstrap.Modal(document.getElementById('editUserModal')).show();
    });
  });
</script>

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
let map;
let userMarker;
let tempMarker;

window.addEventListener('DOMContentLoaded', async () => {
  const locations = <?= json_encode($locations) ?>;

  const firstLoc = locations.length > 0
      ? [locations[0].latitude, locations[0].longitude]
      : [-6.9175, 107.6191];

  map = L.map('map').setView(firstLoc, 13);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: 'Mgo'
  }).addTo(map);

  locations.forEach(loc => {
    L.marker([loc.latitude, loc.longitude])
      .addTo(map)
      .bindPopup(loc.name);
  });

  document.getElementById('setLocationBtn').addEventListener('click', () => {
    Swal.fire({ icon: 'info', title: 'Pilih Lokasi', text: 'Klik di peta untuk memilih lokasi toko.' });

    map.off('click');

    map.on('click', function (e) {
      const { lat, lng } = e.latlng;

      if (tempMarker) tempMarker.remove();

      tempMarker = L.marker([lat, lng], { draggable: true }).addTo(map);
      tempMarker.bindPopup(`
        <b>Konfirmasi Lokasi Baru</b><br>
        Koordinat: ${lat.toFixed(5)}, ${lng.toFixed(5)}<br>
        <button id="saveLocationBtn" class="btn btn-sm btn-success mt-2">Simpan Lokasi</button>
      `).openPopup();

      setTimeout(() => {
        document.getElementById('saveLocationBtn')?.addEventListener('click', async () => {
          const pos = tempMarker.getLatLng();
          const formData = new FormData();
          formData.append('latitude', pos.lat);
          formData.append('longitude', pos.lng);
          formData.append('store', 'set_location');

          try {
            const res = await fetch('store_action.php', {
              method: 'POST',
              body: formData
            });
            const data = await res.json();

            if (data.success) {
              Swal.fire({ icon: 'success', title: 'Berhasil', text: data.message }).then(() => {
                window.location.reload();
              });
            } else {
              Swal.fire({ icon: 'error', title: 'Gagal', text: data.errors.join('<br>') });
            }
          } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan sistem saat menyimpan lokasi.' });
          }
        });
      }, 300);
    });
  });
});
</script>
</body>
</html>