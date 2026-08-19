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
  <?php if (!$is_localhost): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?= $site_key ?>"></script>
  <?php endif; ?>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      min-height: 100vh;
      background: url('https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1920&q=80') no-repeat center center fixed;
      background-size: cover;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0;
    }
    body::before {
      content: "";
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(15, 23, 42, 0.75);
      backdrop-filter: blur(12px);
      z-index: 0;
    }
    .login-container {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 400px;
      padding: 20px;
    }
    .card {
      background: rgba(255, 255, 255, 0.98);
      border: none;
      border-radius: 16px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }
    .card-header {
      background: transparent;
      border-bottom: none;
      padding: 40px 32px 20px;
      text-align: center;
    }
    .card-header h4 {
      font-weight: 700;
      color: #0f172a;
      margin: 0;
      font-size: 1.5rem;
    }
    .card-header p {
      color: #64748b;
      margin-top: 8px;
      margin-bottom: 0;
      font-size: 0.95rem;
    }
    .card-body {
      padding: 0 32px 40px;
    }
    .form-label {
      font-weight: 600;
      color: #334155;
      font-size: 0.875rem;
      margin-bottom: 8px;
    }
    .form-control {
      padding: 12px 16px;
      border-radius: 10px;
      border: 1px solid #e2e8f0;
      background-color: #f8fafc;
      font-size: 0.95rem;
      transition: all 0.2s ease;
    }
    .form-control:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
      background-color: #ffffff;
    }
    .btn-primary {
      background-color: #3b82f6;
      border: none;
      padding: 12px;
      border-radius: 10px;
      font-weight: 600;
      font-size: 1rem;
      margin-top: 24px;
      transition: all 0.2s ease;
    }
    .btn-primary:hover {
      background-color: #2563eb;
      transform: translateY(-1px);
    }
    .btn-primary:active {
      transform: translateY(0);
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="card">
      <div class="card-header">
        <h4>App Center</h4>
        <p>Welcome back! Please login to your account.</p>
      </div>
      <div class="card-body">
        <form action="/action?action=login" method="POST">
          <div class="mb-4">
            <label class="form-label">Username</label>
            <input autocomplete="off" type="text" name="usernames" class="form-control" required placeholder="Enter your username">
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input autocomplete="off" type="text" name="password" class="form-control" required style="-webkit-text-security: disc;" placeholder="••••••••">
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