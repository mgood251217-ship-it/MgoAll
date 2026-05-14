<?php

require_once '../../connect.php';
require_once BASE_PATH . '/global_functions.php';


$result = $koneksi->query("SELECT s.*, (SELECT COUNT(*) FROM users u WHERE u.store_id = s.store_id) as total_karyawan FROM stores s");

// Ambil user untuk opsi dropdown owner
$userResult = $koneksi->query("SELECT user_id, name FROM users ORDER BY name ASC");


?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cabang & Toko</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Select2 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <!-- Select2 JS -->
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <!-- Include SweetAlert2 CSS & JS (pakai CDN) -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body {
      margin: 0;
      padding-top: 56px;
    }
    #mainWrapper {
      display: flex;
      width: 100%;
    }
    #contentWrapper {
      flex-grow: 1;
      margin-left: 70px;
      transition: margin-left 0.3s ease;
      padding: 20px;
    }
    #sidebar:hover ~ #contentWrapper {
      margin-left: 240px;
    }
    footer {
      position: fixed;
      bottom: 0;
      left: 70px;
      right: 0;
      background: #f8f9fa;
      padding: 10px;
      border-top: 1px solid #ddd;
      transition: left 0.3s ease;
    }
    #sidebar:hover ~ #contentWrapper footer {
      left: 240px;
    }
    .table-modern {
      border-collapse: separate;
      border-spacing: 0 8px;
    }
    .table-modern th {
      background: linear-gradient(to right, #0d6efd, #0dcaf0);
      color: white;
      border: none;
    }
    .table-modern td {
      background: #ffffff;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
      border: none;
    }
    .btn-add-store {
      margin-bottom: 1rem;
    }
    .form-label {
      min-width: 120px;
      margin-bottom: 0;
    }
    .form-group-row {
      display: flex;
      align-items: center;
      margin-bottom: 1rem;
    }
    .form-group-row input,
    .form-group-row textarea {
      flex: 1;
    }
  </style>
</head>
<body>

<?php include BASE_PATH . '/navbar.php'; ?>

<div id="mainWrapper">
  <?php include BASE_PATH . '/sidebar.php'; ?>

  <div id="contentWrapper">
    <main id="mainContent">

    </main>

    <?php include BASE_PATH . '/footer.php'; ?>
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
  document.addEventListener("DOMContentLoaded", function () {
    const ownerSelect = document.getElementById("owner_id");
    if (ownerSelect) {
      $(ownerSelect).select2({
        theme: 'bootstrap-5',
        placeholder: "Cari Manager...",
        allowClear: true
      });
    }
  });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>