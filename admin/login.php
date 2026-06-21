<?php
session_start();
require_once "connect.php";
require_once BASE_PATH . '/global_functions.php';
require_once BASE_PATH . '/functions/setInfo.php';
require_once BASE_PATH . '/functions/Otp.php';


if (isset($_SESSION['user']['user_id']) &&
    isset($_SESSION['user']['store_id']) &&
    isset($_SESSION['user']['role']) &&
    isset($_SESSION['user']['username']) &&
    isset($_SESSION['user']['initial']) &&
    isset($_SESSION['user']['name']) &&
    isset($_SESSION['user']['foto'])&&
    isset($_SESSION['user']['store_name']) &&
    isset($_SESSION['user']['store_address']) &&
    isset($_SESSION['user']['store_logo']) 
    // isset($_SESSION['user']['mode'])
    ) {
    header("Location: " . BASE_URL . "/customer");

    exit;
}elseif(isset($_COOKIE['user_user_id']) &&
    isset($_COOKIE['user_username']) &&
    isset($_COOKIE['user_name']) &&
    isset($_COOKIE['user_initial']) &&
    isset($_COOKIE['user_store_id']) &&
    isset($_COOKIE['user_role']) &&
    isset($_COOKIE['user_foto']) &&
    isset($_COOKIE['store_name']) &&
    isset($_COOKIE['store_address']) &&
    isset($_COOKIE['store_logo']) 
    // isset($_COOKIE['user_mode'])
    ){
        $_SESSION['user'] = [
            'user_id' => $_COOKIE['user_user_id'],
            'store_id'         => $_COOKIE['user_store_id'],
            'role'           => $_COOKIE['user_role'],
            'username'           => $_COOKIE['user_username'],
            'initial'           => $_COOKIE['user_initial'],
            'name'           => $_COOKIE['user_name'],
            'foto'              => $_COOKIE['user_foto']
        ];
    header("Location: " . BASE_URL . "/customer");
    exit;
}

$pesan_error = '';

date_default_timezone_set('Asia/Jakarta');
$date = date("Y-m-d H:i:s");

$site_key   = "6LegPm0sAAAAACMlVF_Q0hQmj2cRMXNl2Pj8pldB";
$secret_key = "6LegPm0sAAAAAD028ehVM8ZVd1yn_cXLN2rNEkDA";

// --- TAMBAHAN BARU: Cek apakah berjalan di localhost ---
$is_localhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', '::1']);
// --- AKHIR TAMBAHAN BARU ---

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username_input = strtolower(trim($_POST['usernames']));
    $password = $_POST['password'];
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    $address = getClientIP();

    // --- PENYESUAIAN: Hanya validasi reCAPTCHA jika BUKAN localhost ---
    if (!$is_localhost) {
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
            }
        }
    }

    // Eksekusi Login jika tidak ada error dari reCAPTCHA (atau di-bypass oleh localhost)
    if (empty($pesan_error)) {
        $sql = "SELECT COUNT(*) as total
                FROM users WHERE LOWER(username) = ?";
        $stmt = $koneksi->prepare($sql);
        $stmt->bind_param("s", $username_input);
        $stmt->execute();
        $resultCek = $stmt->get_result();
        $stmt->close(); 

        if ($resultCek->fetch_assoc()['total'] > 0) {
            
            $sql = "SELECT password, store_id
                    FROM users WHERE LOWER(username) = ?";
            $stmt = $koneksi->prepare($sql);
            $stmt->bind_param("s", $username_input);
            $stmt->execute();
            $resultCek = $stmt->get_result();
            $user = $resultCek->fetch_assoc();
            $stmt->close();

            $dataStore = dataStore($user['store_id']);
            $storeEmail = $dataStore['email'];

            if (password_verify($password, $user['password'])) {

                unset($user['password']);

                $sql = "SELECT user_id, username, name, store_id, initial, role, picture
                        FROM users WHERE LOWER(username) = ?";
                $stmt = $koneksi->prepare($sql);
                $stmt->bind_param("s", $username_input);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();

                // // 🔥 CEK TRUSTED DEVICE (BYPASS OTP)
                // $stmt = $koneksi->prepare("
                //     SELECT last_used, status 
                //     FROM otp_verifications 
                //     WHERE LOWER(username) = ? AND address = ?
                //     LIMIT 1
                // ");
                // $stmt->bind_param("ss", $username_input, $address);
                // $stmt->execute();
                // $resultOtp = $stmt->get_result();

                // if ($resultOtp->num_rows > 0) {
                //     $otpData = $resultOtp->fetch_assoc();

                //     if (
                //         $otpData['status'] == 1 &&
                //         time() <= (strtotime($otpData['last_used']) + (5 * 24 * 60 * 60))
                //     ) {
                //         // ✅ LOGIN LANGSUNG (BYPASS OTP)
                //         setInfo($user, $dataStore);
                //         insertActivity($user['user_id'], $address, $date);

                //         header("Location: customer");
                //         exit;
                //     }
                // }

                // if (isset($_GET['u']) && $_GET['u'] == 'medina') {
                //     setOtp($username_input, $address, 'umifaruq@gmail.com');
                //     exit;
                // } elseif (isset($_GET['u']) && $_GET['u'] == 'demo') {
                //     setOtp($username_input, $address, 'mgood251217@gmail.com');
                //     exit;
                // }else {
                //     setOtp($username_input, $address, $storeEmail);
                // }

                // ❌ kalau tidak lolos → kirim OTP
                
                setInfo($user, $dataStore);
                insertActivity($user['user_id'], $address, $date);

                header("Location: customer");
                exit;

            } else {
                $pesan_error = "Username atau password salah!";
            }
            
        } else {
            $pesan_error = "Username atau password salah!";
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
    <link rel="icon" href="/assets/img/title_icon.webp" type="image/png">

    <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet">
    <script src="<?= BASE_URL ?>/assets/js/sweetalert2@11.js"></script>

    <?php if (!$is_localhost): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?= $site_key ?>"></script>
    <?php endif; ?>

    <style>
        body {
            min-height: 100vh;
            background: url('https://mgood.my.id/admin/assets/img/background.webp') no-repeat center center fixed;
            background-size: cover;
            position: relative;
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: inherit;
            filter: blur(10px) brightness(0.7);
            z-index: 0;
        }
        .containerl, .card {
            position: relative;
            z-index: 1;
        }
        .global-loading {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.45);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        }

        .global-loading .loading-content {
        color: #fff;
        text-align: center;
        }
    </style>

<style media="screen">
        *,
  *:before,
  *:after{
      padding: 0;
      margin: 0;
      box-sizing: border-box;
  }
  body{
      background-color: #080710;
  }
  .background{
      width: 430px;
      height: 520px;
      position: absolute;
      transform: translate(-50%,-50%);
      left: 50%;
      top: 50%;
  }
  .background .shape{
      height: 350px;
      width: 350px;
      position: absolute;
      border-radius: 50%;
  }
  .shape:first-child{
      background: linear-gradient(
          #1845ad,
          #23a2f6
      );
      left: -130px;
      top: -150px;
  }
  .shape:last-child{
      background: linear-gradient(
          to right,
          #ff512f,
          #f09819
      );
      right: -30px;
      bottom: -80px;
  }
  form{
      height: 470px;
      width: 400px;
      background-color: rgba(255,255,255,0.13);
      position: absolute;
      transform: translate(-50%,-50%);
      top: 50%;
      left: 50%;
      border-radius: 10px;
      backdrop-filter: blur(10px);
      border: 2px solid rgba(255,255,255,0.1);
      box-shadow: 0 0 40px rgba(8,7,16,0.6);
      padding: 50px 35px;
  }
  form *{
      font-family: 'Poppins',sans-serif;
      color: #ffffff;
      letter-spacing: 0.5px;
      outline: none;
      border: none;
  }
  form h3{
      font-size: 32px;
      font-weight: 500;
      line-height: 42px;
      text-align: center;
  }

  label{
      display: block;
      margin-top: 30px;
      font-size: 16px;
      font-weight: 500;
  }
  input{
      display: block;
      height: 50px;
      width: 100%;
      background-color: rgba(255,255,255,0.07);
      border-radius: 3px;
      padding: 0 10px;
      margin-top: 8px;
      font-size: 14px;
      font-weight: 300;
  }
  ::placeholder{
      color: #e5e5e5;
  }
  button{
      margin-top: 50px;
      width: 100%;
      background-color: #ffffff;
      color: #080710;
      padding: 15px 0;
      font-size: 18px;
      font-weight: 600;
      border-radius: 5px;
      cursor: pointer;
  }
      .bg-bubbles {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 1;
      }
      .bg-bubbles li {
        position: absolute;
        list-style: none;
        display: block;
        width: 40px;
        height: 40px;
        background-color: rgba(255, 255, 255, 0.15);
        bottom: -160px;
        -webkit-animation: square 25s infinite;
        animation: square 25s infinite;
        transition-timing-function: linear;
      }
      .bg-bubbles li:nth-child(1) {
        left: 10%;
      }
      .bg-bubbles li:nth-child(2) {
        left: 20%;
        width: 80px;
        height: 80px;
        -webkit-animation-delay: 2s;
                animation-delay: 2s;
        -webkit-animation-duration: 17s;
                animation-duration: 17s;
      }
      .bg-bubbles li:nth-child(3) {
        left: 25%;
        -webkit-animation-delay: 4s;
                animation-delay: 4s;
      }
      .bg-bubbles li:nth-child(4) {
        left: 40%;
        width: 60px;
        height: 60px;
        -webkit-animation-duration: 22s;
                animation-duration: 22s;
        background-color: rgba(255, 255, 255, 0.25);
      }
      .bg-bubbles li:nth-child(5) {
        left: 70%;
      }
      .bg-bubbles li:nth-child(6) {
        left: 80%;
        width: 120px;
        height: 120px;
        -webkit-animation-delay: 3s;
                animation-delay: 3s;
        background-color: rgba(255, 255, 255, 0.2);
      }
      .bg-bubbles li:nth-child(7) {
        left: 32%;
        width: 160px;
        height: 160px;
        -webkit-animation-delay: 7s;
                animation-delay: 7s;
      }
      .bg-bubbles li:nth-child(8) {
        left: 55%;
        width: 20px;
        height: 20px;
        -webkit-animation-delay: 15s;
                animation-delay: 15s;
        -webkit-animation-duration: 40s;
                animation-duration: 40s;
      }
      .bg-bubbles li:nth-child(9) {
        left: 25%;
        width: 10px;
        height: 10px;
        -webkit-animation-delay: 2s;
                animation-delay: 2s;
        -webkit-animation-duration: 40s;
                animation-duration: 40s;
        background-color: rgba(255, 255, 255, 0.3);
      }
      .bg-bubbles li:nth-child(10) {
        left: 90%;
        width: 160px;
        height: 160px;
        -webkit-animation-delay: 11s;
                animation-delay: 11s;
      }
      @keyframes square {
        0% {
          transform: translateY(0) rotate(0deg);
          opacity: 1;
        }
        100% {
          transform: translateY(-1000px) rotate(600deg);
          opacity: 0;
        }
      }
    ol, ul {
        padding-left: 0;
    }
</style>
</head>

<body>

  <div class="background">
      <div class="shape"></div>
      <div class="shape"></div>
  </div>

<div class="containerl" style="overflow: hidden;">
    <div class="row justify-content-center align-items-center vh-100">
        <div class="">
            <div class="">

                <div >
                    <form method="POST" id="loginForm" style="z-index: 999;">
                      <h4>Login</h4>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="usernames"  required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password"  required autocomplete="off">
                        </div>

                        <?php if (!$is_localhost): ?>
                        <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
                        <?php endif; ?>

                        <button type="submit" >Login</button>
                    </form>
                </div>
            </div>
        </div>
      <div id="global-loading" class="global-loading d-none">
        <div class="loading-content">
          <div class="spinner-border text-light" role="status"></div>
          <div class="mt-2">Loading...</div>
        </div>
      </div>
    </div>
        <ul class="bg-bubbles">
          <li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li>
        </ul>
</div>
<script>
function showGlobalLoading() {
  document.getElementById('global-loading').classList.remove('d-none');
}
document.getElementById('loginForm').addEventListener('submit', function() {
  showGlobalLoading();
});
</script>

<?php if (!$is_localhost): ?>
<script>
grecaptcha.ready(function () {
    grecaptcha.execute('<?= $site_key ?>', {action: 'login'})
        .then(function (token) {
            document.getElementById('g-recaptcha-response').value = token;
            
        });
});
</script>
<?php endif; ?>

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
</body>
</html>