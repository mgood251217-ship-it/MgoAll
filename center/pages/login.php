<?php
session_start();
require_once __DIR__ . "/../config/connect.php";
require_once __DIR__ . "/../functions/helpers.php";

date_default_timezone_set('Asia/Jakarta');
$date = date("Y-m-d H:i:s");

if (isset($_SESSION['admin_logged_in'])) {
    header("Location: /dashboard");
    exit;
} elseif (
    isset($_COOKIE['admin_administrator_id']) &&
    isset($_COOKIE['admin_username']) &&
    isset($_COOKIE['admin_access'])
) {
    $administrator_id = startEnk('dek', $_COOKIE['admin_administrator_id']);
    $username         = startEnk('dek', $_COOKIE['admin_username']);
    $access           = startEnk('dek', $_COOKIE['admin_access']);

    if ($administrator_id && $username && $access) {
        $_SESSION['admin_logged_in'] = [
            'administrator_id' => $_COOKIE['admin_administrator_id'],
            'username'         => $_COOKIE['admin_username'],
            'access'           => $_COOKIE['admin_access']
        ];
    }
    header("Location: /dashboard");
    exit;
}

$is_localhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', '::1']);
$site_key   = "6LegPm0sAAAAACMlVF_Q0hQmj2cRMXNl2Pj8pldB";

$pesan_error = '';
if (isset($_SESSION['login_error'])) {
    $pesan_error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Login - App Center</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    :root {
      --login-primary: #2563eb;
      --login-text: #0f172a;
      --login-muted: #64748b;
      --login-border: #dbe3ef;
    }

    body {
      min-height: 100vh;
      margin: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      color: var(--login-text);
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 52%, #e0f2fe 100%);
    }

    .login-container {
      width: min(100%, 420px);
    }

    .login-container .card {
      overflow: hidden;
      border: 1px solid rgba(219, 227, 239, 0.9);
      border-radius: 16px;
      box-shadow: 0 20px 50px rgba(15, 23, 42, 0.12);
    }

    .login-container .card-header {
      padding: 28px 32px 20px;
      color: #fff;
      background: var(--login-primary);
      border: 0;
      text-align: center;
    }

    .login-container .card-header h4 {
      margin: 0;
      font-weight: 700;
    }

    .login-container .card-body {
      padding: 32px;
    }

    .login-container .form-label {
      color: var(--login-text);
      font-weight: 600;
    }

    .login-container .form-control {
      min-height: 46px;
      border-color: var(--login-border);
      border-radius: 8px;
    }

    .login-container .form-control:focus {
      border-color: var(--login-primary);
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
    }

    .login-container .btn-primary {
      min-height: 46px;
      border: 0;
      border-radius: 8px;
      background: var(--login-primary);
      font-weight: 600;
    }

    .login-container .btn-primary:hover {
      background: #1d4ed8;
    }

    @media (max-width: 480px) {
      body {
        padding: 16px;
      }

      .login-container .card-body {
        padding: 24px;
      }
    }
  </style>
  <?php if (!$is_localhost): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?= $site_key ?>"></script>
  <?php endif; ?>
  
</head>
<body>
  <div class="login-container">
    <div class="card">
      <div class="card-header">
        <h4>App Center</h4>
      </div>
      <div class="card-body">
        <form action="/action?action=login" method="POST">
          <div class="mb-4">
            <label class="form-label">Username</label>
            <input autocomplete="off" type="text" name="usernames" class="form-control" required placeholder="Enter your username">
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input autocomplete="off" type="password" name="password" class="form-control migrated-style-26" required placeholder="Enter your password">
          </div>
          <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
          <button type="submit" class="btn btn-primary w-100">Sign In</button>
        </form>
      </div>
    </div>
  </div>

<script>
<?php if (!$is_localhost): ?>
grecaptcha.ready(function () {
    grecaptcha.execute('<?= $site_key ?>', {action: 'login'})
        .then(function (token) {
            document.getElementById('g-recaptcha-response').value = token;
        });
});
<?php endif; ?>
</script>

<?php if ($pesan_error): ?>
<script>
  Swal.fire({
    icon: 'error',
    title: 'Login Gagal',
    text: <?= json_encode($pesan_error) ?>,
    confirmButtonColor: '#ef4444',
    customClass: {
        popup: 'rounded-4'
    }
  });
</script>
<?php endif; ?>

<script>
if ("geolocation" in navigator) {
  navigator.geolocation.getCurrentPosition(successCallback, errorCallback);
} else {
  console.log("Geolocation is not supported by this browser.");
}

function successCallback(position) {
  const latitude = position.coords.latitude;
  const longitude = position.coords.longitude;
  console.log("Latitude:", latitude);
  console.log("Longitude:", longitude);
}

function errorCallback(error) {
  switch (error.code) {
    case error.PERMISSION_DENIED:
      console.error("User denied the request for geolocation.");
      break;
    case error.POSITION_UNAVAILABLE:
      console.error("Location information is unavailable.");
      break;
    case error.TIMEOUT:
      console.error("The request to get user location timed out.");
      break;
    case error.UNKNOWN_ERROR:
      console.error("An unknown error occurred.");
      break;
  }
}
</script>
<script>
  console.log(<?= json_encode($_SESSION ?? []) ?>);
</script>
</body>
</html>