<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {

    $inputJSON = file_get_contents('php://input');
    $data = json_decode($inputJSON, true);

    if (!isset($data['mode'])) {
        http_response_code(400);
        exit;
    }

    $mode = ($data['mode'] == 1) ? 1 : 0;

    $stmt = $koneksi->prepare("SELECT 1 FROM user_setting WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $stmt = $koneksi->prepare("UPDATE user_setting SET mode = ? WHERE user_id = ?");
        $stmt->bind_param("ii", $mode, $user_id);
        $stmt->execute();
    } else {
        $stmt = $koneksi->prepare("INSERT INTO user_setting (user_id, mode) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $mode);
        $stmt->execute();
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

$stmt = $koneksi->prepare("SELECT mode FROM user_setting WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $row = $result->fetch_assoc()) {
    $mode = (int)$row['mode'];
}
$stmt->close();

// Notifikasi
$queryNotifCount = "SELECT COUNT(*) as total FROM notifications WHERE is_read = 0 AND store_id = ?";
$stmtCount = $koneksi->prepare($queryNotifCount);
$stmtCount->bind_param("i", $store_id);
$stmtCount->execute();
$resultCount = $stmtCount->get_result();
$rowCount = $resultCount->fetch_assoc();
$totalNotif = $rowCount['total'];
$stmtCount->close();

$queryNotif = "SELECT * FROM notifications WHERE store_id = ? ORDER BY created_at DESC LIMIT 5";
$stmtNotif = $koneksi->prepare($queryNotif);
$stmtNotif->bind_param("i", $store_id);
$stmtNotif->execute();
$resultNotif = $stmtNotif->get_result();



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

$stmt = $koneksi->prepare("SELECT o.customer_name, o.nomorator, r.value AS rating_value, r.review, r.date FROM ratings r JOIN orders o ON r.order_id = o.order_id WHERE r.store_id = ? ORDER BY r.date DESC");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$resultRatingOrders = $stmt->get_result();
$ratingList = [];
while ($r = $resultRatingOrders->fetch_assoc()) {
    $ratingList[] = $r;
}
$stmt->close();

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

    <div class="d-flex align-items-center">

      <button class="btn btn-light btn-sm me-2 d-flex align-items-center" id="toggleMode" title="Mode Terang/Gelap">
        <i class="bi <?= $iconClass ?>" id="iconMode"></i>
        <?= $iconMode ?>
      </button>

      <div class="dropdown me-2">
        <a class="btn btn-light btn-sm position-relative dropdown-toggle" href="#" data-bs-toggle="dropdown">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-bell-fill text-primary" viewBox="0 0 16 16">
            <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2m.995-14.901a1 1 0 1 0-1.99 0A5 5 0 0 0 3 6c0 1.098-.5 6-2 7h14c-1.5-1-2-5.902-2-7 0-2.42-1.72-4.44-4.005-4.901"/>
          </svg>
          <?php if ($totalNotif > 0) { ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $totalNotif ?></span>
          <?php } ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-end" style="width: 300px; max-height: 300px; overflow-y: auto;">
          <li><h6 class="dropdown-header">Notifikasi</h6></li>
          <?php if ($resultNotif->num_rows > 0) {
            while ($row = $resultNotif->fetch_assoc()) { ?>
              <li>
                <a href="#" class="dropdown-item notif-item <?= $row['is_read'] == 1 ? 'read-notif' : '' ?>"
                   data-id="<?= $row['notif_id'] ?>">
                  <strong><?= htmlspecialchars($row['message']) ?></strong><br>
                  <small class="notif-content">
                    <?= htmlspecialchars(mb_strimwidth($row['message_content'], 0, 60, '...')) ?>
                  </small>
                  <div class="text-muted small"><?= date("d-m-Y H:i", strtotime($row['created_at'])) ?></div>
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
          <?php } } else { ?>
            <li><a class="dropdown-item text-muted" href="#">Tidak ada notifikasi</a></li>
          <?php } ?>
        </ul>
      </div>

      <a href="<?= BASE_URL ?>/logout.php" class="btn btn-light btn-sm d-flex align-items-center">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-right me-1 text-primary" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0z"/>
            <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708z"/>
          </svg>
        Logout
      </a>
    </div>
  </div>
</nav>

<script src="<?= BASE_URL ?>/assets/js/sweetalert2@11.js"></script>
<script>

document.getElementById('toggleMode').addEventListener('click', function () {
  const icon = document.getElementById('iconMode');
  let newMode;
  let mode = '<?= $mode ?>';

  if (mode == 0) {
    newMode = 1;
  } else {
    newMode = 0;
  }

  fetch(window.location.pathname, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ mode: newMode })
  }).finally(() => window.location.reload());
});

document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.notif-item').forEach(item => {
    item.addEventListener('click', function (e) {
      e.preventDefault();
      const notifId = {notif_id: this.dataset.id};

      fetch('<?= BASE_URL ?>/get_notif.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(notifId)
      })
      .then(response => {
        if (!response) {
            throw new Error("Error gengs");
        }
        return response.json();
      }).then(data => {
          Swal.fire({
            icon: 'info',
            title: data.message,
            html: data.message_content,
            confirmButtonText: 'Tutup'  
          })
        });
    });
  });
});
</script>
<script src="<?= BASE_URL ?>/assets/js/navbar.js"></script>
