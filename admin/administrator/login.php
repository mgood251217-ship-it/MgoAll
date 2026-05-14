<?php
session_start();
require_once "../connect.php";
require_once BASE_PATH . '/functions/enkripsi.php';

$pesan_error = '';

date_default_timezone_set('Asia/Jakarta');
$date = date("Y-m-d H:i:s");

if (isset($_SESSION['admin_logged_in'])) {
    header("Location: dashboard/dashboard.php");
} elseif (
    isset($_COOKIE['admin_administrator_id']) &&
    isset($_COOKIE['admin_username']) &&
    isset($_COOKIE['admin_access'])
) {
    $administrator_id = startEnk('dek', $_COOKIE['admin_administrator_id']);
    $username         = startEnk('dek', $_COOKIE['admin_username']);
    $access           = startEnk('dek', $_COOKIE['admin_access']);

    // Validasi hasil dekripsi
    if ($administrator_id && $username && $access) {
        $_SESSION['admin_logged_in'] = [
            'administrator_id' => $_COOKIE['admin_administrator_id'],
            'username'         => $_COOKIE['admin_username'],
            'access'           => $_COOKIE['admin_access']
        ];
    }
    header("Location: dashboard/dashboard.php");
    exit;
}




$site_key   = "6LegPm0sAAAAACMlVF_Q0hQmj2cRMXNl2Pj8pldB";
$secret_key = "6LegPm0sAAAAAD028ehVM8ZVd1yn_cXLN2rNEkDA";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_input = strtolower(trim($_POST['usernames']));
    $password = $_POST['password'];
    $recaptcha_response = $_POST['g-recaptcha-response'];

    if (empty($recaptcha_response)) {     
        $pesan_error = "reCAPTCHA tidak valid!";
    } else {
        
        $verify_url = "https://www.google.com/recaptcha/api/siteverify";
        $response = file_get_contents(
            $verify_url . "?secret=" . $secret_key . "&response=" . $recaptcha_response
        );
        $response_keys = json_decode($response, true);

        if (
            !$response_keys['success'] ||
            $response_keys['score'] < 0.5 ||
            $response_keys['action'] !== 'login'
        ) {
            $pesan_error = "Aktivitas mencurigakan terdeteksi!";
        } else {
            $sql = "SELECT administrator_id, username, name, password, access FROM administrator WHERE LOWER(username) = ?";
            $stmt = $koneksi->prepare($sql);
            $stmt->bind_param("s", $username_input);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if (password_verify($password, $user['password'])) {
                    unset($user['password']);
                    
                    // $_SESSION['admin_logged_in'] = [
                    //     'administrator_id' => startEnk('enk', $user['administrator_id']),
                    //     'username' => startEnk('enk', $user['username']),
                    //     'access' => startEnk('enk', $user['access'])
                    // ];

                    $expire = time() + (1 * 24 * 60 * 60);
                    $path   = '/';

                    setcookie('admin_administrator_id', startEnk('enk', $user['administrator_id']), $expire, $path, "", true, true);
                    setcookie('admin_username',         startEnk('enk', $user['username']),         $expire, $path, "", true, true);
                    setcookie('admin_access',           startEnk('enk', $user['access']),           $expire, $path, "", true, true);

                    // setcookie("administrator_id", $user['administrator_id'], time() + 86400, "/");
                    header("Location: dashboard/dashboard.php");
                    exit;
                } else {
                    $pesan_error = "Password salah!";
                }
            } else {
                $pesan_error = "Username tidak ditemukan!";
            }
        }

    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://www.google.com/recaptcha/api.js?render=<?= $site_key ?>"></script>
  <style>
    body {
      min-height: 100vh;
      background: url('https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1200&q=80') no-repeat center center fixed;
      background-size: cover;
      position: relative;
    }
    body::before {
      content: "";
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: inherit;
      filter: blur(8px) brightness(0.7);
      z-index: 0;
    }
    .container, .card {
      position: relative;
      z-index: 1;
    }
  </style>
</head>
<body class="bg-light">
  <div class="container">
    <div class="row justify-content-center align-items-center vh-100">
      <div class="col-md-4">
        <div class="card shadow">
          <div class="card-header text-center">
            <h4>Login Administrator</h4>
          </div>
          <div class="card-body">
            <form method="POST">
              <div class="mb-3">
                <label class="form-label">Username</label>
                <input autocomplete="off" type="text" name="usernames" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Password</label>
                <input autocomplete="off" type="text" name="password" class="form-control" required style="-webkit-text-security: disc;">
              </div>
              <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
              <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
<script>
grecaptcha.ready(function () {
    grecaptcha.execute('<?= $site_key ?>', {action: 'login'})
        .then(function (token) {
            document.getElementById('g-recaptcha-response').value = token;
        });
});
</script>
<?php if ($pesan_error): ?>
<script>
  Swal.fire({
    icon: 'error',
    title: 'Login Gagal',
    text: <?= json_encode($pesan_error) ?>,
    confirmButtonColor: '#d33'
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
  console.log(<?= json_encode($_SESSION) ?>);
  
</script>
</body>
</html>
