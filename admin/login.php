<?php
session_start();
require_once "connect.php";
require_once BASE_PATH . '/functions/helpers.php';
require_once BASE_PATH . '/controllers/AuthController.php';
require_once BASE_PATH . '/components/Alert.php';

if (AuthController::checkSession()) {
    header("Location: " . BASE_URL . "/customer");
    exit;
}

$site_key   = "6LfKclYtAAAAAD9zWKtWXNNl-n3hahu0GmNXthVE";
$is_localhost = isLocalhostRequest();
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
    .dp-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(3px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    opacity: 0;
    transition: opacity 0.25s ease;
    }

    .dp-overlay.active {
    display: flex;
    opacity: 1;
    }

    .dp-modal {
    background: #ffffff;
    border-radius: 16px;
    padding: 32px 28px;
    max-width: 360px;
    width: 90%;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
    transform: translateY(20px) scale(0.96);
    transition: transform 0.25s ease;
    }

    .dp-overlay.active .dp-modal {
    transform: translateY(0) scale(1);
    }

    .dp-icon {
    width: 72px;
    height: 72px;
    margin: 0 auto 16px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    }

    .dp-modal h2 {
    font-size: 18px;
    font-weight: 700;
    color: #111827;
    margin: 0 0 8px;
    }

    .dp-modal p {
    font-size: 14px;
    color: #6b7280;
    line-height: 1.5;
    margin: 0 0 24px;
    }

    .dp-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    }

    .dp-btn {
    border: none;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.15s ease, transform 0.15s ease;
    }

    .dp-btn:active {
    transform: scale(0.97);
    }

    .dp-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #ffffff;
    }

    .dp-btn-primary:hover {
    opacity: 0.9;
    }

    .dp-btn-secondary {
    background: #f3f4f6;
    color: #374151;
    }

    .dp-btn-secondary:hover {
    background: #e5e7eb;
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
                    <form id="loginForm" style="z-index: 999;">
                      <h4>Login</h4>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" id="username" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" id="password" required autocomplete="off">
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
<div id="desktopPromoOverlay" class="dp-overlay">
  <div class="dp-modal">
    <div class="dp-icon">
      <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5">
        <rect x="3" y="4" width="18" height="13" rx="2"></rect>
        <path d="M8 21h8M12 17v4"></path>
      </svg>
    </div>
    <h2>Coba Aplikasi Desktop</h2>
    <p>Kelola pesanan lebih cepat dan nyaman langsung dari komputer Anda dengan aplikasi MgoDesktop.</p>
    <div class="dp-actions">
      <button id="dpDownloadBtn" class="dp-btn dp-btn-primary">Unduh Sekarang</button>
      <button id="dpLaterBtn" class="dp-btn dp-btn-secondary">Nanti Saja</button>
    </div>
  </div>
</div>
<script>
const isLocalhost = <?= $is_localhost ? 'true' : 'false' ?>;
const siteKey = '<?= $site_key ?>';

function showGlobalLoading() {
  document.getElementById('global-loading').classList.remove('d-none');
}

function hideGlobalLoading() {
  document.getElementById('global-loading').classList.add('d-none');
}

function generateRecaptchaToken() {
    if (!isLocalhost && typeof grecaptcha !== 'undefined') {
        grecaptcha.ready(function () {
            grecaptcha.execute(siteKey, {action: 'login'})
                .then(function (token) {
                    document.getElementById('g-recaptcha-response').value = token;
                });
        });
    }
}

generateRecaptchaToken();

function showDesktopPromoModal(downloadUrl, onDone) {
    const overlay = document.getElementById('desktopPromoOverlay');
    const downloadBtn = document.getElementById('dpDownloadBtn');
    const laterBtn = document.getElementById('dpLaterBtn');

    overlay.classList.add('active');

    function close(shouldContinue) {
        overlay.classList.remove('active');
        downloadBtn.removeEventListener('click', handleDownload);
        laterBtn.removeEventListener('click', handleLater);
        if (shouldContinue) onDone();
    }

    function handleDownload() {
        window.open(downloadUrl, '_blank');
        close(true);
    }

    function handleLater() {
        close(true);
    }

    downloadBtn.addEventListener('click', handleDownload);
    laterBtn.addEventListener('click', handleLater);
}

document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault();
    showGlobalLoading();

    const formData = new FormData(this);

    fetch('routes/?action=login', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideGlobalLoading();

        if (data.success) {
            if (data.data.show_desktop_promo && data.data.desktop_download_url) {
                showDesktopPromoModal(data.data.desktop_download_url, function() {
                    window.location.href = "customer";
                });
            } else {
                window.location.href = "customer";
            }
        } else {
            showAlert('error', data.message);
            generateRecaptchaToken();
        }
    })
    .catch(error => {
        hideGlobalLoading();
        console.error('Error:', error);
        showAlert('success', 'Terjadi Kesalahan');
        generateRecaptchaToken();
        generateRecaptchaToken();
    });
});
</script>

</body>
</html>