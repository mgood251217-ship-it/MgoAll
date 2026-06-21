<?php
require_once 'connect.php';
require_once BASE_PATH . '/session.php';

$foto_file = $foto ? $foto : 'default_user.png';
$foto_view = BASE_URL . '/assets/img/user/' . $foto_file;

$currentFile = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$is_restricted_all = in_array($role, ['ONLINE', 'PRODUKSI', 'SETTING']);
$mode_class = ($mode ?? 0) === 1 ? 'dark-mode' : 'light-mode';

$user_name_short = explode(' ', trim($name))[0] ?? $name;
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sidebar.css">
<style>

<?php if (isset($username) && ($username == 'zannia' || $username == 'vikialvian')) { ?>
  #sidebar-wrapper.hovered.dark-mode .sidebar-header {
    background-color: rgb(255, 76, 225) !important;
    color: #cbd5e1 !important;
  }
  #sidebar-wrapper.dark-mode {
    background-color: rgb(248, 141, 230) !important;
    color: #cbd5e1 !important;
  }
  /* Colors for dark mode */
  #sidebar-wrapper.dark-mode .list-group-item {
    color:rgb(255, 255, 255) !important;
  }
  #sidebar-wrapper.dark-mode .list-group-item:hover {
    background-color: rgb(255, 0, 170) !important;
    color: #f8fafc !important;
  }
  #sidebar-wrapper.dark-mode .list-group-item.active {
    background-color:rgb(255, 115, 232) !important;
    color: white !important;
  }
<?php } ?>
</style>

<div id="sidebar-wrapper" class="<?= $mode_class ?> border-end">
  <div class="sidebar-header">
    <img src="<?= htmlspecialchars($foto_view) ?>" alt="Foto User">
    <div class="user-shortname"><?= htmlspecialchars($user_name_short) ?></div>
    <div class="user-info">
      <h5><?= htmlspecialchars($name) ?></h5>
      <h6><?= htmlspecialchars($role) ?></h6>
    </div>
  </div>

  <div class="list-group list-group-flush mt-3">
    <!-- Toko -->
    <a href="<?= BASE_URL ?>/toko"
      class="list-group-item list-group-item-action <?= $currentFile === 'toko' ? 'active' : '' ?> <?= $is_restricted_all ? 'disabled' : '' ?>"
      <?= $is_restricted_all ? 'tabindex="-1" aria-disabled="true" style="pointer-events:none;opacity:0.5;"' : '' ?>>
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-shop" viewBox="0 0 16 16">
          <path d="M2.97 1.35A1 1 0 0 1 3.73 1h8.54a1 1 0 0 1 .76.35l2.609 3.044A1.5 1.5 0 0 1 16 5.37v.255a2.375 2.375 0 0 1-4.25 1.458A2.37 2.37 0 0 1 9.875 8 2.37 2.37 0 0 1 8 7.083 2.37 2.37 0 0 1 6.125 8a2.37 2.37 0 0 1-1.875-.917A2.375 2.375 0 0 1 0 5.625V5.37a1.5 1.5 0 0 1 .361-.976zm1.78 4.275a1.375 1.375 0 0 0 2.75 0 .5.5 0 0 1 1 0 1.375 1.375 0 0 0 2.75 0 .5.5 0 0 1 1 0 1.375 1.375 0 1 0 2.75 0V5.37a.5.5 0 0 0-.12-.325L12.27 2H3.73L1.12 5.045A.5.5 0 0 0 1 5.37v.255a1.375 1.375 0 0 0 2.75 0 .5.5 0 0 1 1 0M1.5 8.5A.5.5 0 0 1 2 9v6h1v-5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v5h6V9a.5.5 0 0 1 1 0v6h.5a.5.5 0 0 1 0 1H.5a.5.5 0 0 1 0-1H1V9a.5.5 0 0 1 .5-.5M4 15h3v-5H4zm5-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1zm3 0h-2v3h2z"/>
        </svg>
        <span class="menu-text">Toko</span>
    </a>

    <!-- Customer -->
    <a href="<?= BASE_URL ?>/customer"
      class="list-group-item list-group-item-action <?= $currentFile === 'customer' ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-people" viewBox="0 0 16 16">
          <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1zm-7.978-1L7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002-.014.002zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4m3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0M6.936 9.28a6 6 0 0 0-1.23-.247A7 7 0 0 0 5 9c-4 0-5 3-5 4q0 1 1 1h4.216A2.24 2.24 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816M4.92 10A5.5 5.5 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0m3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4"/>
        </svg>
        <span class="menu-text">Customer</span>
    </a>

    <!-- Barang -->
    <a href="<?= BASE_URL ?>/barang"
      class="list-group-item list-group-item-action <?= $currentFile === 'barang' ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-seam" viewBox="0 0 16 16">
          <path d="M8.186 1.113a.5.5 0 0 0-.372 0L1.846 3.5l2.404.961L10.404 2zm3.564 1.426L5.596 5 8 5.961 14.154 3.5zm3.25 1.7-6.5 2.6v7.922l6.5-2.6V4.24zM7.5 14.762V6.838L1 4.239v7.923zM7.443.184a1.5 1.5 0 0 1 1.114 0l7.129 2.852A.5.5 0 0 1 16 3.5v8.662a1 1 0 0 1-.629.928l-7.185 2.874a.5.5 0 0 1-.372 0L.63 13.09a1 1 0 0 1-.63-.928V3.5a.5.5 0 0 1 .314-.464z"/>
        </svg>
        <span class="menu-text">Barang</span>
    </a>

    <a href="<?= BASE_URL ?>/stock"
      class="list-group-item list-group-item-action <?= $currentFile === 'stock' ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-stack" viewBox="0 0 16 16">
          <path d="m14.12 10.163 1.715.858c.22.11.22.424 0 .534L8.267 15.34a.6.6 0 0 1-.534 0L.165 11.555a.299.299 0 0 1 0-.534l1.716-.858 5.317 2.659c.505.252 1.1.252 1.604 0l5.317-2.66zM7.733.063a.6.6 0 0 1 .534 0l7.568 3.784a.3.3 0 0 1 0 .535L8.267 8.165a.6.6 0 0 1-.534 0L.165 4.382a.299.299 0 0 1 0-.535z"/>
          <path d="m14.12 6.576 1.715.858c.22.11.22.424 0 .534l-7.568 3.784a.6.6 0 0 1-.534 0L.165 7.968a.299.299 0 0 1 0-.534l1.716-.858 5.317 2.659c.505.252 1.1.252 1.604 0z"/>
        </svg>
        <span class="menu-text">Stock</span>
    </a>

    <a href="<?= BASE_URL ?>/global_stock"
      class="list-group-item list-group-item-action <?= $currentFile === 'global_stock' ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clipboard-check" viewBox="0 0 16 16">
          <path fill-rule="evenodd" d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0"/>
          <path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1z"/>
          <path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0z"/>
        </svg>
        <span class="menu-text">Stock Global</span>
    </a>

    <a href="<?= BASE_URL ?>/meteran"
      class="list-group-item list-group-item-action <?= $currentFile === 'meteran' ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-bounding-box" viewBox="0 0 16 16">
          <path d="M5 2V0H0v5h2v6H0v5h5v-2h6v2h5v-5h-2V5h2V0h-5v2zm6 1v2h2v6h-2v2H5v-2H3V5h2V3zm1-2h3v3h-3zm3 11v3h-3v-3zM4 15H1v-3h3zM1 4V1h3v3z"/>
        </svg>
        <span class="menu-text">Meteran</span>
    </a>

    <a href="<?= BASE_URL ?>/kegagalan"
      class="list-group-item list-group-item-action <?= $currentFile === 'kegagalan' ? 'active' : '' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clipboard2-x" viewBox="0 0 16 16">
          <path d="M9.5 0a.5.5 0 0 1 .5.5.5.5 0 0 0 .5.5.5.5 0 0 1 .5.5V2a.5.5 0 0 1-.5.5h-5A.5.5 0 0 1 5 2v-.5a.5.5 0 0 1 .5-.5.5.5 0 0 0 .5-.5.5.5 0 0 1 .5-.5z"/>
          <path d="M3 2.5a.5.5 0 0 1 .5-.5H4a.5.5 0 0 0 0-1h-.5A1.5 1.5 0 0 0 2 2.5v12A1.5 1.5 0 0 0 3.5 16h9a1.5 1.5 0 0 0 1.5-1.5v-12A1.5 1.5 0 0 0 12.5 1H12a.5.5 0 0 0 0 1h.5a.5.5 0 0 1 .5.5v12a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5z"/>
          <path d="M8 8.293 6.854 7.146a.5.5 0 1 0-.708.708L7.293 9l-1.147 1.146a.5.5 0 0 0 .708.708L8 9.707l1.146 1.147a.5.5 0 0 0 .708-.708L8.707 9l1.147-1.146a.5.5 0 0 0-.708-.708z"/>
        </svg>
        <span class="menu-text">Kegagalan</span>
    </a>

    <a href="<?= BASE_URL ?>/maklun"
      class="list-group-item list-group-item-action <?= $currentFile === 'maklun' ? 'active' : '' ?> <?= $is_restricted_all ? 'disabled' : '' ?>"
      <?= $is_restricted_all ? 'tabindex="-1" aria-disabled="true" style="pointer-events:none;opacity:0.5;"' : '' ?>>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 -960 960 960"  fill="currentColor">
        <path d="m480-312 216-144-216-144v288ZM120-48q-29.7 0-50.85-21.15Q48-90.3 48-120v-456h72v456h648v72H120Zm144-144q-29.7 0-50.85-21.15Q192-234.3 192-264v-456h192v-72q0-29.7 21.15-50.85Q426.3-864 456-864h192q29.7 0 50.85 21.15Q720-821.7 720-792v72h192v456q0 29.7-21.15 50.85Q869.7-192 840-192H264Zm0-72h576v-384H264v384Zm192-456h192v-72H456v72ZM264-264v-384 384Z"/>
      </svg>
      <span class="menu-text">Maklun</span>
       
    </a>

    <a href="<?= BASE_URL ?>/laporan"
      class="list-group-item list-group-item-action <?= $currentFile === 'laporan' ? 'active' : '' ?> <?= $is_restricted_all ? 'disabled' : '' ?>"
      <?= $is_restricted_all ? 'tabindex="-1" aria-disabled="true" style="pointer-events:none;opacity:0.5;"' : '' ?>>
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-text" viewBox="0 0 16 16">
          <path d="M5.5 7a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1zM5 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5"/>
          <path d="M9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.5zm0 1v2A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1z"/>
        </svg>
      <span class="menu-text">Laporan</span>
       
    </a>
  </div> 
</div>
<script src="<?= BASE_URL ?>/assets/js/sidebar.js"></script>