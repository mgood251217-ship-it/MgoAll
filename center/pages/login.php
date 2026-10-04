<?php
require_once __DIR__ . '/../functions/helpers.php';
configureCenterSession();
session_start();
require_once __DIR__ . "/../config/connect.php";

date_default_timezone_set('Asia/Jakarta');
$date = date("Y-m-d H:i:s");

if (isset($_SESSION['admin_logged_in']['administrator_id'])) {
    header("Location: /dashboard");
    exit;
}

$is_localhost = isCenterLocalRequest();
$site_key = $_ENV['RECAPTCHA_SITE_KEY'] ?? '';
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['login_csrf_token'] = $csrf_token;

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
    <script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($site_key, ENT_QUOTES, 'UTF-8') ?>"></script>
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
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
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
  grecaptcha.execute(<?= json_encode($site_key) ?>, {action: 'login'})
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
    text: <?= json_encode($pesan_error, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    confirmButtonColor: '#ef4444',
    customClass: { popup: 'rounded-4' }
  });
</script>
<?php endif; ?>
</body>
</html>