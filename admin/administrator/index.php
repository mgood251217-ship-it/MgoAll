<?php
require_once '../../connect.php';
require_once BASE_PATH . '/global_functions.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding-top: 20px; /* untuk beri ruang navbar fixed-top */
    }

    #layout {
      display: flex;
    }

    #mainContent {
      flex-grow: 1;
      padding: 20px;
      transition: margin-left 0.3s ease;
      margin-left: 70px;
    }

    #sidebar:hover ~ #mainContent {
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

    #sidebar:hover ~ footer {
      left: 240px;
    }
  </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div id="layout">
  <?php include 'sidebar.php'; ?>

  <main id="mainContent">
    <h1 class="mt-4">Selamat Datang di Dashboard Admin</h1>
    <p>Konten dashboard ditampilkan di sini.</p>
  </main>

  <?php include 'footer.php'; ?>
</div>
<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
