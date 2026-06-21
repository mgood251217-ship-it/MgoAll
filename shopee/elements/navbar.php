<?php

require_once '../connect.php';
require_once BASE_PATH . '/functions/user_validation.php';

$user_id = $_SESSION['shopee_users']['user_id'] ?? 0;
$stmtUser = $koneksi->prepare("SELECT name, foto FROM users WHERE user_id = ?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$resultUser = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

$currentFile = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
?>

<style>

.navbar-floating {
    position: sticky;
    top: 10px; 
    z-index: 1030;
    width: 95%; 
    max-width: 1200px;
    margin: 0 auto; 
    border-radius: 50px; 
    padding: 0.5rem 1.5rem; 
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    transition: all 0.3s ease;
}

.navbar-floating .navbar-toggler {
    border: none;
    outline: none;
}

.nav-item:hover{
  scale: 1.1;
  transform: translateY(-3px);
}
.tridi{
  box-shadow: 10px 10px 12px -7px rgba(0,0,0,0.34) inset;
  border-radius: 50px;
  border: 3px outset #c75b45ff;
}
.text-tridi{
  text-shadow: 2px 2px 2px rgba(0,0,0,0.62);
}
</style>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/navbar.css">

<nav class="navbar navbar-expand-lg navbar-dark bg-shopee shadow navbar-floating p-0">
  <div class="container-fluid px-1 tridi text-tridi">

    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>/indexes/dashboard">
      <img src="<?= BASE_URL ?>/assets/img/users/<?= $resultUser['foto'] ?>" height="30" width="30" style="border-radius: 50%; margin-left: 2px;"> <?= $resultUser['name'] ?>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarMenu">
      <ul class="navbar-nav me-auto ms-lg-4">

        <li class="nav-item">
          <a class="nav-link d-flex align-items-center gap-1 <?= $currentFile === 'produk' ? 'active' : '' ?>"
             href="<?= BASE_URL ?>/indexes/produk">

            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-seam" viewBox="0 0 16 16">
              <path d="M8.186 1.113a.5.5 0 0 0-.372 0L1.846 3.5l2.404.961L10.404 2zm3.564 1.426L5.596 5 8 5.961 14.154 3.5zm3.25 1.7-6.5 2.6v7.922l6.5-2.6V4.24zM7.5 14.762V6.838L1 4.239v7.923zM7.443.184a1.5 1.5 0 0 1 1.114 0l7.129 2.852A.5.5 0 0 1 16 3.5v8.662a1 1 0 0 1-.629.928l-7.185 2.874a.5.5 0 0 1-.372 0L.63 13.09a1 1 0 0 1-.63-.928V3.5a.5.5 0 0 1 .314-.464z"/>
            </svg>

            Produk
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link d-flex align-items-center gap-1 <?= $currentFile === 'meteran' ? 'active' : '' ?>"
             href="<?= BASE_URL ?>/indexes/meteran">

            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-bounding-box" viewBox="0 0 16 16">
              <path d="M5 2V0H0v5h2v6H0v5h5v-2h6v2h5v-5h-2V5h2V0h-5v2zm6 1v2h2v6h-2v2H5v-2H3V5h2V3zm1-2h3v3h-3zm3 11v3h-3v-3zM4 15H1v-3h3zM1 4V1h3v3z"/>
            </svg>

            Meteran
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link d-flex align-items-center gap-1 <?= $currentFile === 'keuangan' ? 'active' : '' ?>"
             href="<?= BASE_URL ?>/indexes/keuangan">

            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cash" viewBox="0 0 16 16">
            <path d="M8 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4"/>
            <path d="M0 4a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H1a1 1 0 0 1-1-1zm3 0a2 2 0 0 1-2 2v4a2 2 0 0 1 2 2h10a2 2 0 0 1 2-2V6a2 2 0 0 1-2-2z"/>
            </svg>

            Keuangan
          </a>
        </li>

      </ul>

      <a href="<?= BASE_URL ?>/logout.php" class="btn btn-light btn-sm px-3 rounded-pill fw-bold tridi" style="text-shadow: none;">
        Logout
      </a>
    </div>
  </div>
</nav>