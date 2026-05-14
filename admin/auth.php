<?php

ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
require_once "connect.php";
require_once "key.php";
require_once BASE_PATH . '/functions/enkripsi.php';
require_once BASE_PATH . '/functions/setInfo.php';
require_once BASE_PATH . '/functions/Otp.php';

date_default_timezone_set('Asia/Jakarta');

$username_otp = isset($_GET['us']) ? startEnk('dek', $_GET['us']) : null;

if (!$username_otp) {
    echo 'error';
    exit;
}

// ambil expire untuk countdown
$stmt = $koneksi->prepare("
    SELECT expire 
    FROM otp_verifications 
    WHERE LOWER(username) = ?
    LIMIT 1
");
$stmt->bind_param("s", strtolower($username_otp));
$stmt->execute();
$result = $stmt->get_result();
$expireData = $result->fetch_assoc();
$expire = $expireData['expire'] ?? date("Y-m-d H:i:s");
$stmt->close();

// ambil email toko untuk ditampilkan
$stmt = $koneksi->prepare("
    SELECT store_id 
    FROM users 
    WHERE LOWER(username) = ?
    LIMIT 1
");
$stmt->bind_param("s", strtolower($username_otp));
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$storeEmail = '';
if ($userData) {
    $dataStore = dataStore($userData['store_id']);
    $storeEmail = $dataStore['email'] ?? '';
}
$stmt->close();

$pesan_error = null;
$pesan_success = null;

$site_key   = "6LegPm0sAAAAACMlVF_Q0hQmj2cRMXNl2Pj8pldB";
$secret_key = "6LegPm0sAAAAAD028ehVM8ZVd1yn_cXLN2rNEkDA";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $action = $_POST['action'] ?? 'verify';
    $otp_code = $_POST['otp_code'] ?? '';
    $g_recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    if ($action === 'resend') {

        $address = getClientIP();
        $updated = getOtp($koneksi, $username_otp, $address);

        if ($updated) {
            $expire = $updated['expire'];
            sendOtpEmail($storeEmail, $updated['otp']);
            $pesan_success = "Kode OTP baru telah dikirim.";
        } else {
            $pesan_error = "Gagal membuat OTP.";
        }

    } else {

        if (empty($otp_code)) {
            $pesan_error = "Kode OTP tidak boleh kosong.";
        } elseif (strlen($otp_code) != 6 || !ctype_digit($otp_code)) {
            $pesan_error = "Kode OTP harus 6 digit angka.";
        } elseif (empty($g_recaptcha_response)) {
            $pesan_error = "reCAPTCHA wajib diisi.";
        } else {

            $response = file_get_contents(
                'https://www.google.com/recaptcha/api/siteverify?secret='
                . urlencode($secret_key) .
                '&response=' . urlencode($g_recaptcha_response)
            );

            $response_keys = json_decode($response, true);

            if (!$response_keys["success"]) {
                $pesan_error = "Verifikasi reCAPTCHA gagal.";
            } else {

                $resultOtp = otpVerification($koneksi, $username_otp, $otp_code);

                if (!$resultOtp['status']) {
                    $pesan_error = $resultOtp['message'];
                } else {
                    insertActivity($resultOtp['user']['user_id'], getClientIP(), date("Y-m-d H:i:s"));
                    $pesan_success = $resultOtp['message'];
                    header("Refresh:2; url=" . BASE_URL);
                }
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
    <link rel="icon" href="/assets/img/title_icon.webp" type="image/png">

    <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet">
    <script src="<?= BASE_URL ?>/assets/js/sweetalert2@11.js"></script>

    <script src="https://www.google.com/recaptcha/api.js?render=<?= $site_key ?>"></script>

    <style>
        body {
            min-height: 100vh;
            background: url('https://mgood.my.id/assets/img/background.webp') no-repeat center center fixed;
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
      height: auto;
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
      padding: 40px 35px;
      overflow: hidden;
      box-sizing: border-box;
  }
  form *{
      font-family: 'Poppins',sans-serif;
      color: #ffffff;
      letter-spacing: 0.5px;
      outline: none;
      border: none;
  }
  form h3{
      font-size: 28px;
      font-weight: 500;
      line-height: 36px;
      text-align: center;
      margin-bottom: 10px;
  }

  label{
      display: block;
      margin-top: 20px;
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
  .otp-section{
      margin-top: 15px;
  }
  .otp-section .otp-inputs{
      display: grid;
      grid-template-columns: repeat(6, minmax(45px, 1fr));
      gap: 10px;
      margin-top: 12px;
  }
  .otp-box{
      height: 55px;
      border-radius: 12px;
      text-align: center;
      font-size: 22px;
      letter-spacing: 0.35em;
      background-color: rgba(255,255,255,0.12);
      transition: transform 0.15s ease, box-shadow 0.15s ease;
      border: 2px solid rgba(255,255,255,0.15);
      color: #ffffff;
  }
  .otp-box:focus{
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(255,255,255,0.18);
      border-color: #23a2f6;
      background-color: rgba(255,255,255,0.18);
  }
  .otp-note{
      display: block;
      margin-top: 10px;
      color: rgba(255,255,255,0.75);
      font-size: 13px;
  }
  ::placeholder{
      color: #e5e5e5;
  }
  button{
      margin-top: 30px;
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

<div class="containerl" style="overflow: hidden; position: relative;">
    <div class="row justify-content-center align-items-center vh-100">
        <div class="">
            <div class="">

                <div >
                    <form method="POST" id="loginForm" style="z-index: 999;">
                      <h4>Masukan Kode OTP</h4>

                        <div class="mb-3 otp-section">
                            <?php if (!empty($storeEmail)): ?>
                            <small class="otp-email" style="display: block; margin-top: 5px; color: rgba(255,255,255,0.8); font-size: 14px;">
                                OTP dikirim ke email toko: <strong><?= htmlspecialchars($storeEmail) ?></strong>
                            </small>
                            <?php endif; ?>
                            <div class="otp-inputs" id="otpInputs">
                                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-box" autocomplete="one-time-code">
                                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-box" autocomplete="one-time-code">
                                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-box" autocomplete="one-time-code">
                                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-box" autocomplete="one-time-code">
                                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-box" autocomplete="one-time-code">
                                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-box" autocomplete="one-time-code">
                            </div>
                            <small class="otp-note">Masukkan 6 digit kode OTP yang dikirim ke email Anda ketika diminta.</small>
                            <input type="hidden" name="otp_code" id="otp_code">
                        </div>

                        <input type="hidden" name="action" id="formAction" value="verify">
                        <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">

                        <button type="submit" >Login</button>

                        <div class="mt-2 text-center">
                            <span id="countdown" style="color: rgba(255,255,255,0.75); font-size: 14px;"></span>
                            <button type="button" id="resendButton" style="display:none; margin-top:5px; color:#ffffff; background:none; border:none; text-decoration:underline; cursor:pointer;">Minta Kode Baru</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
        <ul class="bg-bubbles">
          <li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li>
        </ul>
</div>

<script>
grecaptcha.ready(function () {
    grecaptcha.execute('<?= $site_key ?>', {action: 'login'})
        .then(function (token) {
            document.getElementById('g-recaptcha-response').value = token;
            
        });
});

// Countdown timer
var countdownElement = document.getElementById('countdown');
var resendButton = document.getElementById('resendButton');
var expireTime = new Date("<?= str_replace(' ', 'T', $expire) ?>").getTime();
var countdownInterval = setInterval(function() {
    var now = new Date().getTime();
    var distance = expireTime - now;

    if (distance > 0) {
        var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        var seconds = Math.floor((distance % (1000 * 60)) / 1000);
        countdownElement.innerHTML = minutes + "m " + seconds + "s";
        resendButton.style.display = 'none';
    } else {
        clearInterval(countdownInterval);
        countdownElement.innerHTML = "Kode OTP sudah kedaluwarsa. Silakan minta kode baru.";
        resendButton.style.display = 'inline-block';
    }
}, 1000);

if (resendButton) {
    resendButton.addEventListener('click', function() {
        document.getElementById('formAction').value = 'resend';
        document.getElementById('loginForm').submit();
    });
}

    const otpBoxes = Array.from(document.querySelectorAll('.otp-box'));
    const otpHidden = document.getElementById('otp_code');

    function updateOtpValue() {
        otpHidden.value = otpBoxes.map(box => box.value.trim()).join('');
    }

    otpBoxes.forEach((box, index) => {
        box.addEventListener('input', event => {
            const value = event.target.value.replace(/[^0-9]/g, '');
            event.target.value = value;
            updateOtpValue();
            if (value && otpBoxes[index + 1]) {
                otpBoxes[index + 1].focus();
            }
        });

        box.addEventListener('keydown', event => {
            if (event.key === 'Backspace' && !box.value && otpBoxes[index - 1]) {
                otpBoxes[index - 1].focus();
            }
        });
    });

</script>

<?php if ($pesan_error){ ?>
<script>
Swal.fire({
    icon: 'error',
    title: 'Login Gagal',
    text: <?= json_encode($pesan_error) ?>,
    confirmButtonColor: '#d33'
});
</script>
<?php } ?>
<?php if ($pesan_success) { ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Login Berhasil',
    text: <?= json_encode($pesan_success) ?>,
    confirmButtonColor: '#d33'
});
</script>
<?php } ?>
</body>
</html>