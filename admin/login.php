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
    
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

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
  *, *:before, *:after {
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
      background: linear-gradient(#1845ad, #23a2f6);
      left: -130px;
      top: -150px;
  }
  .shape:last-child{
      background: linear-gradient(to right, #ff512f, #f09819);
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
  button[type="submit"]{
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
  .bg-bubbles li:nth-child(1) { left: 10%; }
  .bg-bubbles li:nth-child(2) { left: 20%; width: 80px; height: 80px; animation-delay: 2s; animation-duration: 17s; }
  .bg-bubbles li:nth-child(3) { left: 25%; animation-delay: 4s; }
  .bg-bubbles li:nth-child(4) { left: 40%; width: 60px; height: 60px; animation-duration: 22s; background-color: rgba(255, 255, 255, 0.25); }
  .bg-bubbles li:nth-child(5) { left: 70%; }
  .bg-bubbles li:nth-child(6) { left: 80%; width: 120px; height: 120px; animation-delay: 3s; background-color: rgba(255, 255, 255, 0.2); }
  .bg-bubbles li:nth-child(7) { left: 32%; width: 160px; height: 160px; animation-delay: 7s; }
  .bg-bubbles li:nth-child(8) { left: 55%; width: 20px; height: 20px; animation-delay: 15s; animation-duration: 40s; }
  .bg-bubbles li:nth-child(9) { left: 25%; width: 10px; height: 10px; animation-delay: 2s; animation-duration: 40s; background-color: rgba(255, 255, 255, 0.3); }
  .bg-bubbles li:nth-child(10) { left: 90%; width: 160px; height: 160px; animation-delay: 11s; }
  @keyframes square {
      0% { transform: translateY(0) rotate(0deg); opacity: 1; }
      100% { transform: translateY(-1000px) rotate(600deg); opacity: 0; }
  }
  ol, ul { padding-left: 0; }

  .dp-overlay {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.85);
      backdrop-filter: blur(8px);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      opacity: 0;
      transition: opacity 0.3s ease;
  }
  .dp-overlay.active {
      display: flex;
      opacity: 1;
  }

  .dp-modal {
      background: #ffffff;
      border-radius: 24px;
      padding: 40px;
      max-width: 700px;
      width: 95%;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
      transform: translateY(20px) scale(0.96);
      transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      display: flex;
      flex-direction: column;
      gap: 20px;
  }
  .dp-overlay.active .dp-modal {
      transform: translateY(0) scale(1);
  }

  .dp-header {
      text-align: center;
  }
  .dp-icon-wrapper {
      display: flex;
      justify-content: center;
      gap: 20px;
      margin-bottom: 24px;
  }
  .dp-icon {
      width: 72px;
      height: 72px;
      border-radius: 20px;
      background: linear-gradient(135deg, #6366f1, #8b5cf6);
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4);
  }
  .dp-icon.android {
      background: linear-gradient(135deg, #10b981, #059669);
      box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.4);
  }

  .dp-header h2 {
      font-size: 28px;
      font-weight: 700;
      color: #111827;
      margin: 0 0 12px;
      font-family: 'Poppins', sans-serif;
  }
  .dp-header p {
      font-size: 15px;
      color: #4b5563;
      line-height: 1.6;
      margin: 0;
      font-family: 'Poppins', sans-serif;
  }
  
  .dp-features {
      background: #f8fafc;
      border-radius: 16px;
      padding: 20px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
      text-align: left;
  }
  .dp-feature-item {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 14px;
      color: #334155;
      font-family: 'Poppins', sans-serif;
      font-weight: 500;
  }
  .dp-feature-item svg {
      color: #10b981;
      flex-shrink: 0;
  }

  .dp-download-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-top: 10px;
  }
  .dp-download-btn {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 10px;
      padding: 20px 15px;
      border-radius: 16px;
      border: 2px solid transparent;
      cursor: pointer;
      transition: all 0.2s ease;
      font-weight: 600;
      font-size: 16px;
      text-decoration: none;
  }
  .dp-download-btn.desktop {
      background: #eff6ff;
      color: #1d4ed8;
      border-color: #bfdbfe;
  }
  .dp-download-btn.desktop:hover { background: #dbeafe; transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(29, 78, 216, 0.15); }
  
  .dp-download-btn.mobile {
      background: #ecfdf5;
      color: #047857;
      border-color: #a7f3d0;
  }
  .dp-download-btn.mobile:hover { background: #d1fae5; transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(4, 120, 87, 0.15); }

  .dp-actions-bottom {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 15px;
      margin-top: 10px;
  }
  .dp-btn {
      border: none;
      border-radius: 12px;
      padding: 14px 24px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s ease;
      font-family: 'Poppins', sans-serif;
      flex: 1;
  }
  .dp-btn:active { transform: scale(0.97); }
  .dp-btn-outline {
      background: transparent;
      color: #4b5563;
      border: 2px solid #e5e7eb;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
  }
  .dp-btn-outline:hover { background: #f9fafb; color: #111827; border-color: #d1d5db; }
  .dp-btn-secondary { background: #1e293b; color: #ffffff; }
  .dp-btn-secondary:hover { background: #0f172a; }

  .changelog-modal {
      max-width: 800px;
      max-height: 90vh;
      display: flex;
      flex-direction: column;
      padding: 32px;
  }
  .changelog-content {
      text-align: left;
      overflow-y: auto;
      flex: 1;
      padding-right: 15px;
      margin: 20px 0;
      color: #374151;
      font-size: 15px;
      line-height: 1.7;
  }
  .release-item {
      margin-bottom: 30px;
      background: #f8fafc;
      padding: 20px;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
  }
  .release-item h3 {
      color: #0f172a;
      margin-top: 0;
      margin-bottom: 15px;
      font-size: 20px;
      font-weight: 700;
      border-bottom: 2px solid #e2e8f0;
      padding-bottom: 10px;
      display: flex;
      justify-content: space-between;
      align-items: center;
  }
  .release-date {
      font-size: 13px;
      color: #64748b;
      font-weight: 500;
      background: #e2e8f0;
      padding: 4px 10px;
      border-radius: 20px;
  }
  .changelog-content ul { padding-left: 20px; margin-bottom: 15px; }
  .changelog-content li { margin-bottom: 8px; }
  .changelog-content p { margin-bottom: 15px; }
  .changelog-content code { background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
  
  .changelog-content::-webkit-scrollbar { width: 8px; }
  .changelog-content::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
  .changelog-content::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
  .changelog-content::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
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
    <div class="dp-header">
        <div class="dp-icon-wrapper">
            <div class="dp-icon">
              <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                <line x1="8" y1="21" x2="16" y2="21"></line>
                <line x1="12" y1="17" x2="12" y2="21"></line>
              </svg>
            </div>
            <div class="dp-icon android">
              <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
                <line x1="12" y1="18" x2="12.01" y2="18"></line>
              </svg>
            </div>
        </div>
        <h2>Aplikasi Resmi MGO Telah Hadir</h2>
        <p>Tingkatkan efisiensi dan pengalaman pengelolaan sistem Anda dengan menggunakan aplikasi mandiri kami, tersedia untuk Desktop dan perangkat Android.</p>
    </div>

    <div class="dp-features">
        <div class="dp-feature-item">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            Performa lebih cepat & stabil
        </div>
        <div class="dp-feature-item">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            Fitur lebih lengkap & optimal
        </div>
        <div class="dp-feature-item">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            Akses langsung tanpa browser
        </div>
        <div class="dp-feature-item">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            Tampilan UI yang disempurnakan
        </div>
    </div>
    
    <div class="dp-download-grid">
      <button id="dpDownloadDesktopBtn" class="dp-download-btn desktop" style="display:none;">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        Download Versi Desktop
      </button>
      <button id="dpDownloadMobileBtn" class="dp-download-btn mobile" style="display:none;">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        Download Versi Android
      </button>
    </div>

    <div class="dp-actions-bottom">
      <button id="dpChangelogBtn" class="dp-btn dp-btn-outline">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
        Catatan Rilis (Changelog)
      </button>
      <button id="dpLaterBtn" class="dp-btn dp-btn-secondary">Nanti, Buka Dashboard</button>
    </div>
  </div>
</div>

<div id="changelogOverlay" class="dp-overlay">
  <div class="dp-modal changelog-modal">
    <h2 style="margin-bottom:0; font-family: 'Poppins', sans-serif; font-size: 24px;">Riwayat Pembaruan Sistem</h2>
    <div id="changelogContent" class="changelog-content">
       <div class="spinner-border text-primary" role="status" style="margin: 30px auto; display: block;"></div>
       <div style="text-align: center; font-family: 'Poppins', sans-serif;">Memuat data rilis terbaru...</div>
    </div>
    <button id="closeChangelogBtn" class="dp-btn dp-btn-secondary" style="width: 100%;">Tutup Riwayat</button>
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

async function openChangelogModal() {
    const clOverlay = document.getElementById('changelogOverlay');
    const clContent = document.getElementById('changelogContent');
    clOverlay.classList.add('active');
    
    try {
        const response = await fetch('https://api.github.com/repos/mgood251217-ship-it/MgoApp/releases');
        if (!response.ok) throw new Error('API Error');
        
        const releases = await response.json();
        let htmlContent = '';
        
        if (releases.length > 0) {
            releases.forEach(release => {
                const dateObj = new Date(release.published_at);
                const formattedDate = dateObj.toLocaleDateString('id-ID', {
                    day: 'numeric', month: 'long', year: 'numeric'
                });
                
                const releaseName = release.name || release.tag_name || 'Update Terbaru';
                const bodyHtml = marked.parse(release.body || 'Tidak ada deskripsi pembaruan.');
                
                htmlContent += `<div class="release-item">
                                    <h3>${releaseName} <span class="release-date">${formattedDate}</span></h3>
                                    <div class="release-body">${bodyHtml}</div>
                                </div>`;
            });
        } else {
            htmlContent = `<div style="text-align:center; padding:30px;">Belum ada catatan rilis yang dipublikasikan.</div>`;
        }
        
        clContent.innerHTML = htmlContent;
    } catch (error) {
        clContent.innerHTML = `<div style="text-align:center; color:#ef4444; padding:30px;">Gagal terhubung ke server untuk memuat log pembaruan.</div>`;
    }
}

document.getElementById('closeChangelogBtn').addEventListener('click', function() {
    document.getElementById('changelogOverlay').classList.remove('active');
});

function showAppPromoModal(desktopUrl, mobileUrl, onDone) {
    const overlay = document.getElementById('desktopPromoOverlay');
    const desktopBtn = document.getElementById('dpDownloadDesktopBtn');
    const mobileBtn = document.getElementById('dpDownloadMobileBtn');
    const laterBtn = document.getElementById('dpLaterBtn');
    const changelogBtn = document.getElementById('dpChangelogBtn');

    if (desktopUrl) {
        desktopBtn.style.display = 'flex';
        desktopBtn.onclick = () => window.open(desktopUrl, '_blank');
    }
    if (mobileUrl) {
        mobileBtn.style.display = 'flex';
        mobileBtn.onclick = () => window.open(mobileUrl, '_blank');
    }
    
    overlay.classList.add('active');

    function close(shouldContinue) {
        overlay.classList.remove('active');
        laterBtn.removeEventListener('click', handleLater);
        changelogBtn.removeEventListener('click', openChangelogModal);
        if (shouldContinue) onDone();
    }

    function handleLater() {
        close(true);
    }

    laterBtn.addEventListener('click', handleLater);
    changelogBtn.addEventListener('click', openChangelogModal);
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
            const hasDesktop = data.data.download_url_desktop;
            const hasMobile = data.data.download_url_mobile;

            if (data.data.show_desktop_promo && (hasDesktop || hasMobile)) {
                showAppPromoModal(hasDesktop, hasMobile, function() {
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
        showAlert('success', 'Terjadi Kesalahan');
        generateRecaptchaToken();
        generateRecaptchaToken();
    });
});
</script>

</body>
</html>