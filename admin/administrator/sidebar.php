<!-- sidebar.php -->
<div id="sidebar" class="text-white shadow-sm">
  <div class="d-flex flex-column align-items-center align-items-md-start pt-3 px-2 h-100">
    <ul class="nav nav-pills flex-column mb-auto w-100 text-center text-md-start">
      <li class="nav-item mb-1">
        
        <a href="<?= BASE_URL . '/administrator/dashboard/'?>dashboard.php" class="nav-link text-white d-flex align-items-center gap-2" data-bs-toggle="tooltip" title="Dashboard">
          <i class="bi bi-house-door fs-6"></i>
          <span class="sidebar-label">Dashboard</span>
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="<?= BASE_URL . '/administrator/pesanan/'?>pesanan.php" class="nav-link text-white d-flex align-items-center gap-2" data-bs-toggle="tooltip" title="Manajemen Pesanan">
          <i class="bi bi-clipboard-data fs-6"></i>
          <span class="sidebar-label">Manajemen Pesanan</span>
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="<?= BASE_URL . '/administrator/cabang/'?>cabang.php" class="nav-link text-white d-flex align-items-center gap-2" data-bs-toggle="tooltip" title="Cabang & Toko">
          <i class="bi bi-shop fs-6"></i>
          <span class="sidebar-label">Cabang & Toko</span>
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="<?= BASE_URL . '/administrator/karyawan/'?>karyawan.php" class="nav-link text-white d-flex align-items-center gap-2" data-bs-toggle="tooltip" title="User & Karyawan">
          <i class="bi bi-people fs-6"></i>
          <span class="sidebar-label">User & Karyawan</span>
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="<?= BASE_URL . '/administrator/produksi/'?>produksi.php" class="nav-link text-white d-flex align-items-center gap-2" data-bs-toggle="tooltip" title="Produksi & Mesin">
          <i class="bi bi-cpu fs-6"></i>
          <span class="sidebar-label">Produksi & Mesin</span>
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="<?= BASE_URL . '/administrator/komunikasi/'?>komunikasi.php" class="nav-link text-white d-flex align-items-center gap-2" data-bs-toggle="tooltip" title="Komunikasi Internal">
          <i class="bi bi-chat-dots fs-6"></i>
          <span class="sidebar-label">Komunikasi Internal</span>
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="<?= BASE_URL . '/administrator/laporan/'?>laporan.php" class="nav-link text-white d-flex align-items-center gap-2" data-bs-toggle="tooltip" title="Laporan & Analisis">
          <i class="bi bi-graph-up fs-6"></i>
          <span class="sidebar-label">Laporan & Analisis</span>
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="<?= BASE_URL . '/administrator/finance/'?>finance.php" class="nav-link text-white d-flex align-items-center gap-2" data-bs-toggle="tooltip" title="Finance">
          <i class="bi bi-cash-coin fs-6"></i>
          <span class="sidebar-label">Finance</span>
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="<?= BASE_URL . '/administrator/pengaturan/'?>pengaturan.php" class="nav-link text-white d-flex align-items-center gap-2" data-bs-toggle="tooltip" title="Pengaturan">
          <i class="bi bi-gear fs-6"></i>
          <span class="sidebar-label">Pengaturan</span>
        </a>
      </li>
    </ul>
  </div>
</div>

<style>
  #sidebar {
    position: fixed;
    top: 56px;
    left: 0;
    bottom: 0;
    width: 60px;
    background: linear-gradient(to bottom right, #2563eb, #93c5fd);

    transition: width 0.3s ease;
    overflow-x: hidden;
    z-index: 1040;
    border-right: 1px solid #ccc;
  }

  #sidebar:hover {
    width: 200px;
  }

  #sidebar .nav-link {
    padding: 8px 12px;
    border-radius: 6px;
    transition: all 0.2s;
    font-size: 0.85rem;
    color: #ffffff !important;
  }

  #sidebar .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.15);
  }

  .sidebar-label {
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.2s ease, visibility 0.2s ease;
  }

  #sidebar:hover .sidebar-label {
    opacity: 1;
    visibility: visible;
  }

  #sidebar:hover ~ footer {
    left: 200px;
  }
</style>


