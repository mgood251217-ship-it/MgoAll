<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/controllers/UserController.php';
require_once BASE_PATH . '/controllers/LocationController.php';
require_once BASE_PATH . '/models/Store.php';
require_once  BASE_PATH . '/access_rights.php';
require_once BASE_PATH . '/components/Modal.php';
require_once BASE_PATH . '/components/Alert.php';
require_once BASE_PATH . '/components/Table.php';
require_once BASE_PATH . '/components/Icon.php';

$userController = new UserController($koneksi);
$users = $userController->index();

$storeModel = new Store($koneksi);

$locationController = new LocationController($koneksi);
$locations = $locationController->index();

$mesinList = $storeModel->getMachineByStore_id($store_id);

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
<div id="main-wrapper">
  <?php include BASE_PATH . '/navbar.php'; ?>
  <div id="main-content" <?= (isset($mode) && $mode === 1) ? 'class="dark-mode"' : '' ?>>
    <?php include BASE_PATH . '/sidebar.php'; ?>
    <div id="page-content-wrapper">
      <?php include 'chart.php'; ?>

      <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h1 class="mb-0">Manajemen User</h1>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
          <?= get_icon('create', ['class' => 'me-1', 'width' => '18', 'height' => '18']) ?> Tambah User
        </button>
      </div>

      <?php if (empty($users)): ?>
        <div class="alert alert-warning">Belum ada user terdaftar.</div>
      <?php else: ?>
      <?php
      $htmlTableUser = [
          'data' => $users ?? [],
          'empty_message' => 'Belum ada user terdaftar.',
          'table_class' => 'table table-bordered table-striped text-center',
          'thead_class' => 'table-primary',
          'columns' => [
              ['header' => 'No', 'type' => 'number'],
              ['header' => 'Nama', 'field' => 'name'],
              [
                  'header' => 'Role', 
                  'type' => 'badge', 
                  'field' => 'role', 
                  'badge_class' => 'bg-primary'
              ],
              ['header' => 'Initial', 'field' => 'initial'],
              [
                  'header' => 'Foto', 
                  'type' => 'image', 
                  'field' => 'picture', 
                  'base_path' => '/assets/img/user'
              ],
              [
                  'header' => 'Aksi', 
                  'type' => 'action_buttons',
                  'buttons' => [
                      [
                          'type' => 'button',
                          'icon' => get_icon('update', ['class' => 'me-1']),
                          'text' => 'Edit',
                          'color' => 'warning',
                          'class' => 'btn-edit me-1',
                          'data_json' => 'user' 
                      ],
                      [
                          'type' => 'form',
                          'icon' => get_icon('delete', ['class' => 'me-1']),
                          'text' => 'Hapus',
                          'color' => 'danger',
                          'action_url' => 'store_action.php?action=delete_user',
                          'form_class' => 'delete-user-form',
                          'hidden_inputs' => [
                              'user_id' => 'user_id',
                              'picture' => 'picture'
                          ]
                      ]
                  ]
              ]
          ]
      ];

      echo renderTable($htmlTableUser);
      ?>
      <?php endif; ?>
      <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
          <h1 class="mb-0">Daftar Mesin</h1>
          <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#tambahMesinModal">
              <?= get_icon('create', ['class' => 'me-1', 'width' => '18', 'height' => '18']) ?> Tambah Mesin
          </button>
      </div>

      <?php
      $htmlTableMesin = [
          'data' => $mesinList ?? [],
          'empty_message' => 'Belum ada data mesin. Silakan tambah mesin baru.',
          'table_class' => 'table table-bordered table-striped text-center',
          'thead_class' => 'table-primary',
          'columns' => [
              ['header' => 'No', 'type' => 'number'],
              [
                  'header' => 'Nama Mesin', 
                  'field' => 'name', 
                  'text_class' => 'fw-bold'
              ],
              [
                  'header' => 'Tipe Mesin', 
                  'type' => 'badge', 
                  'field' => 'type', 
                  'badge_class' => 'bg-secondary'
              ],
              [
                  'header' => 'Aksi', 
                  'type' => 'action_buttons',
                  'buttons' => [
                      [
                          'type' => 'button',
                          'icon' => get_icon('delete'), // Mengganti tag <i> statis dengan get_icon('delete')
                          'color' => 'danger',
                          'onclick' => [
                              'function' => 'hapusMesin',
                              'param_field' => 'machine_id'
                          ]
                      ]
                  ]
              ]
          ]
      ];

      echo renderTable($htmlTableMesin);
      ?>

      <div id="map" style="height: 400px;"></div>
      <button id="setLocationBtn" class="btn btn-primary mt-2">Set Lokasi Saya</button>
    </div>
    <?php include BASE_PATH . '/footer.php'; ?>
  </div>
  <br>

  <?php

  $roleOptions = [
      'MANAGER'  => 'MANAGER',
      'ADMIN'    => 'ADMIN',
      'SETTING'  => 'SETTING',
      'ONLINE'   => 'ONLINE',
      'PRODUKSI' => 'PRODUKSI'
  ];

  $htmlModalAddUser = renderModal([
      'id'           => 'addUserModal',
      'form_id'      => 'addUserForm',
      'title'        => 'Tambah User Baru',
      'header_class' => 'bg-success text-white',
      'size'         => 'modal-md',
      'btn_color'    => 'success',
      'btn_text'     => 'Simpan User',
      'layout'        => 'horizontal',
      'label_width'   => 'col-sm-4',
      'input_width'   => 'col-sm-8',
      'inputs'       => [
          [
              'type'        => 'text',
              'name'        => 'name',
              'label'       => 'Nama',
              'required'    => true,
              'custom_attr' => 'style="text-transform:uppercase"'
          ],
          [
              'type'     => 'text',
              'name'     => 'username',
              'label'    => 'Username',
              'required' => true
          ],
          [
              'type'        => 'text',
              'name'        => 'password',
              'label'       => 'Password',
              'required'    => true,
              'custom_attr' => 'style="-webkit-text-security: disc;"'
          ],
          [
              'type'     => 'select',
              'name'     => 'role',
              'label'    => 'Role',
              'options'  => $roleOptions,
              'required' => true
          ],
          [
              'type'        => 'text',
              'name'        => 'initial',
              'label'       => 'Initial',
              'required'    => true,
              'custom_attr' => 'maxlength="5" style="text-transform:uppercase"'
          ],
          [
              'type'        => 'file',
              'name'        => 'picture',
              'label'       => 'Foto',
              'custom_attr' => 'accept="image/*"'
          ]
      ]
  ]);

  $htmlModalEditUser = renderModal([
      'id'       => 'editUserModal',
      'form_id'  => 'editUserForm',
      'title'    => 'Edit User',
      'btn_text' => 'Simpan Perubahan',
      'layout'        => 'horizontal',
      'label_width'   => 'col-sm-4',
      'input_width'   => 'col-sm-8',
      'inputs'   => [
          [
              'type' => 'hidden',
              'name' => 'user_id',
              'id'   => 'edit_user_id'
          ],
          [
              'type' => 'hidden',
              'name' => 'old_picture',
              'id'   => 'edit_old_picture'
          ],
          [
              'type'        => 'text',
              'name'        => 'name',
              'id'          => 'edit_name',
              'label'       => 'Nama',
              'required'    => true,
              'custom_attr' => 'style="text-transform:uppercase"'
          ],
          [
              'type'     => 'text',
              'name'     => 'username',
              'id'       => 'edit_username',
              'label'    => 'Username',
              'required' => true
          ],
          [
              'type'  => 'password',
              'name'  => 'password',
              'label' => 'Password Baru <small class="text-muted">(Kosongkan jika tidak ingin ganti)</small>'
          ],
          [
              'type'     => 'select',
              'name'     => 'role',
              'id'       => 'edit_role',
              'label'    => 'Role',
              'options'  => $roleOptions,
              'required' => true
          ],
          [
              'type'        => 'text',
              'name'        => 'initial',
              'id'          => 'edit_initial',
              'label'       => 'Initial',
              'required'    => true,
              'custom_attr' => 'maxlength="5" style="text-transform:uppercase"'
          ],
          [
              'type'        => 'file',
              'name'        => 'picture',
              'id'          => 'edit_picture',
              'label'       => 'Ganti Foto (Opsional)',
              'custom_attr' => 'accept="image/*"'
          ]
      ]
  ]);

  $htmlModalTambahMesin = renderModal([
      'id'            => 'tambahMesinModal',
      'form_id'       => 'formTambahMesin',
      'title'         => 'Tambah Mesin Baru',
      'body_class'    => 'p-4',
      'btn_text'      => 'Simpan Mesin',
      'layout'        => 'horizontal',
      'label_width'   => 'col-sm-4',
      'input_width'   => 'col-sm-8',
      'custom_bottom' => (isset($mode) && $mode === 1) ? '<style>#tambahMesinModal .modal-content { background-color: #333 !important; color: #e0e0e0 !important; } #tambahMesinModal .btn-close { filter: invert(1); }</style>' : '',
      'inputs'        => [
          [
              'type'        => 'text',
              'name'        => 'name',
              'id'          => 'machine_name',
              'label'       => 'Nama Mesin',
              'required'    => true,
              'custom_attr' => 'placeholder="Contoh: Epson SC-B6070"'
          ],
          [
              'type'        => 'text',
              'name'        => 'type',
              'id'          => 'machine_type',
              'label'       => 'Tipe Mesin',
              'required'    => true,
              'custom_attr' => 'placeholder="Contoh: INDOOR, OUTDOOR, LASER A3"'
          ]
      ]
  ]);
  echo $htmlModalAddUser;
  echo $htmlModalEditUser;
  echo $htmlModalTambahMesin;

  ?>
</div>
 
<script>
    const formTambahMesin = document.getElementById('formTambahMesin');
    formTambahMesin.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('store_action.php?action=create_machine', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => { throw new Error(text) });
            }
            return response.json();
        })
        .then(data => {
          
            if (data.success) {
              await showAlert('success', data.message);
              setTimeout(() => {
                window.location.reload();
              },3000);
            } else {
                Swal.fire('Gagal', data.message, 'error');
            }
        })
        .catch(error => {
            Swal.fire('Error', 'Terjadi kesalahan sistem: ' + error.message, 'error');
        });
    });

  async function sendFormData(formElement, targetUrl, modalId = null) {
    const formData = new FormData(formElement);
    try {
      const response = await fetch(targetUrl, {
        method: 'POST',
        body: formData
      });
      const data = await response.json();
      
      if (data.success) {
        if (modalId) {
          bootstrap.Modal.getInstance(document.getElementById(modalId))?.hide();
        }
        await showAlert('success', data.message);
        setTimeout(() => {
          window.location.reload();
        },3000);
      } else {
        Swal.fire({ icon: 'error', title: 'Gagal', html: data.errors.join('<br>') });
      }
    } catch (error) {
      Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan sistem.' });
    }
  }

  document.getElementById('addUserForm').addEventListener('submit', function (e) {
    e.preventDefault();
    sendFormData(this, 'store_action.php?action=create_user', 'addUserModal');
  });

  document.getElementById('editUserForm').addEventListener('submit', function (e) {
    e.preventDefault();
    sendFormData(this, 'store_action.php?action=update_user', 'editUserModal');
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
          const actionUrl = form.getAttribute('action');
          sendFormData(form, actionUrl);
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
            const res = await fetch('store_action.php?action=set_location', {
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