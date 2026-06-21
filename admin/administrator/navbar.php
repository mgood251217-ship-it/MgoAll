<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #1e3a8a; box-shadow: 0 2px 4px rgb(0 0 0 / 0.1); position: fixed; top: 0; width: 100%; z-index: 1050;">
  <div class="container-fluid">
    <a class="navbar-brand" href="#" style="color: #93c5fd;">Admin Panel</a>

    <!-- Tombol toggle menu saat mobile -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent"
      aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation" style="border-color: #93c5fd;">
      <span class="navbar-toggler-icon" style="filter: invert(1) brightness(2);"></span>
    </button>

    <!-- Konten navbar kanan -->
    <div class="collapse navbar-collapse justify-content-end" id="navbarContent">
      <ul class="navbar-nav mb-2 mb-lg-0">
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/administrator/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
