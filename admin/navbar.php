<?php
require_once BASE_PATH . '/models/Setting.php';
require_once BASE_PATH . '/models/Store.php';

$settingModel = new Setting($koneksi);
$storeModel = new Store($koneksi);

$mode = (int)$settingModel->getOneValue($user_id, 'mode');

$iconClass = $mode === 1 ? "text-warning" : "text-primary";
$iconMode = '';
if ($mode === 1) {
  $iconMode = '
<svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" fill="currentColor" class="bi bi-cloud-sun-fill text-warning" viewBox="0 0 16 16">
  <path d="M11.473 11a4.5 4.5 0 0 0-8.72-.99A3 3 0 0 0 3 16h8.5a2.5 2.5 0 0 0 0-5z"/>
  <path d="M10.5 1.5a.5.5 0 0 0-1 0v1a.5.5 0 0 0 1 0zm3.743 1.964a.5.5 0 1 0-.707-.707l-.708.707a.5.5 0 0 0 .708.708zm-7.779-.707a.5.5 0 0 0-.707.707l.707.708a.5.5 0 1 0 .708-.708zm1.734 3.374a2 2 0 1 1 3.296 2.198q.3.423.516.898a3 3 0 1 0-4.84-3.225q.529.017 1.028.129m4.484 4.074c.6.215 1.125.59 1.522 1.072a.5.5 0 0 0 .039-.742l-.707-.707a.5.5 0 0 0-.854.377M14.5 6.5a.5.5 0 0 0 0 1h1a.5.5 0 0 0 0-1z"/>
</svg>
  ';
}else {
  $iconMode = '
<svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" fill="currentColor" class="bi bi-moon-stars-fill text-primary" viewBox="0 0 16 16">
  <path d="M6 .278a.77.77 0 0 1 .08.858 7.2 7.2 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277q.792-.001 1.533-.16a.79.79 0 0 1 .81.316.73.73 0 0 1-.031.893A8.35 8.35 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.75.75 0 0 1 6 .278"/>
  <path d="M10.794 3.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387a1.73 1.73 0 0 0-1.097 1.097l-.387 1.162a.217.217 0 0 1-.412 0l-.387-1.162A1.73 1.73 0 0 0 9.31 6.593l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387a1.73 1.73 0 0 0 1.097-1.097zM13.863.099a.145.145 0 0 1 .274 0l.258.774c.115.346.386.617.732.732l.774.258a.145.145 0 0 1 0 .274l-.774.258a1.16 1.16 0 0 0-.732.732l-.258.774a.145.145 0 0 1-.274 0l-.258-.774a1.16 1.16 0 0 0-.732-.732l-.774-.258a.145.145 0 0 1 0-.274l.774-.258c.346-.115.617-.386.732-.732z"/>
</svg>
  ';
}
$navbarBgClass = $mode === 1 ? 'bg-dark-night' : 'bg-primary';

$access = '';
if (isset($_SESSION['user']['access']) && startEnk('dek', $_SESSION['user']['access']) == 'all' ) {
  $access = startEnk('dek', $_SESSION['user']['access']);
}

$userAgent = $_SERVER['HTTP_USER_AGENT'];
$mobile = false;
if (strpos($userAgent, 'Mobile') !== false) {
  $mobile = true;
}

?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/navbar.css">
<nav class="navbar navbar-expand-lg navbar-dark <?= $navbarBgClass ?> shadow sticky-top py-2" style="<?= ($username == 'zannia' || $username == 'vikialvian') ? 'background-color:rgb(248, 141, 230) !important;' : '' ?>">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="#">
      <img src="<?= BASE_URL ?>/assets/img/store/<?= $storeLogo ?>" alt="Logo" style="height:32px; margin-right:10px;">
      <?= htmlspecialchars($storeName) ?>
    </a>

    <?php if (isset($_SESSION['admin_logged_in']['administrator_id'])){ ?>
    <div class="btn btn-primary">
      Admin Mode
    </div>
    <?php } ?>

    <div class="d-flex align-items-center">

      <button class="btn btn-light btn-sm me-2 d-flex align-items-center" id="toggleMode" title="Mode Terang/Gelap">
        <i class="bi <?= $iconClass ?>" id="iconMode"></i>
        <?= $iconMode ?>
      </button>

      <button class="btn btn-light btn-sm d-flex align-items-center" id="btnLogoout">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-right me-1 text-primary" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0z"/>
            <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708z"/>
          </svg>
        Logout
      </button>
    </div>
  </div>
</nav>

<script src="<?= BASE_URL ?>/assets/js/sweetalert2@11.js"></script>
<script>

document.getElementById("btnLogoout").addEventListener('click', function (){
  fetch('<?= BASE_URL ?>/action.php?action=logout', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' }
  })
  .then(response => {
    if (!response.ok) {
        throw new Error("Gagal");
    }
    return response.json();
  }).then(data => {
      if (data.success) {
        window.location.href = '<?= BASE_URL ?>/login';
      }
    });
})


document.getElementById('toggleMode').addEventListener('click', function () {
  let mode = '<?= $mode ?>';
  let newMode = (mode == '1') ? 0 : 1;

  fetch('<?= BASE_URL ?>/action.php?action=theme', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ mode: newMode })
  }).then(response => {
    if (!response.ok) {
        throw new Error("Gagal");
    }
    return response.json();
  })
  .then(data => {
      if (data.success) {
        window.location.reload(); 
      } else {
        Swal.fire({ icon: 'error', title: 'Gagal', text: data.message });
      }
    })
    .catch(err => {
        console.error('Error:', err);
    });
});
</script>
<script src="<?= BASE_URL ?>/assets/js/navbar.js"></script>