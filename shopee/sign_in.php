<?php
require_once 'connect.php';

?>
<!DOCTYPE html>
<html lang="id">
    <head>
        <title>Daftar Shopee Manage</title>
        <link rel="icon" type="image/x-icon" href="https://hpanel.hostinger.com/favicons/hostinger.png">
        <meta charset="utf-8">
        <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
        <meta content="Halaman default" name="description">
        <meta content="width=device-width, initial-scale=1" name="viewport">
    <script src="https://www.google.com/recaptcha/api.js?render=<?= $site_key ?>"></script>
    <style>
      @font-face {
        font-family: 'Source Sans Pro';
        font-style: normal;
        font-weight: 200;
        src: url(https://fonts.gstatic.com/s/sourcesanspro/v23/6xKydSBYKcSV-LCoeQqfX1RYOo3i94_wlxdr.ttf) format('truetype');
      }
      @font-face {
        font-family: 'Source Sans Pro';
        font-style: normal;
        font-weight: 300;
        src: url(https://fonts.gstatic.com/s/sourcesanspro/v23/6xKydSBYKcSV-LCoeQqfX1RYOo3ik4zwlxdr.ttf) format('truetype');
      }
      * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        font-family: 'Source Sans Pro', sans-serif;
        font-weight: 300;
      }

      body {
        height: 100vh;
        color: white;
      }

      /* WRAPPER */
      .wrapper {
        position: fixed;
        inset: 0;
        display: flex;
        align-items: center;     
        justify-content: center; 
        background: linear-gradient(to bottom right, #9f8862ff, #cf8f36ff);
        overflow: hidden;
      }

      /* CONTAINER */
      .container {
        text-align: center;
        z-index: 2;
      }

      /* TITLE */
      .container h1 {
        font-size: 40px;
        margin-bottom: 20px;
        font-weight: 200;
        transition: transform 1s ease-in-out;
      }

      /* FORM */
      form {
        padding: 20px 0;
        display: flex;
        flex-direction: column;
        align-items: center;
      }

      form input {
        appearance: none;
        width: 250px;
        padding: 10px 15px;
        margin-bottom: 10px;
        border-radius: 3px;
        border: 1px solid rgba(255,255,255,0.4);
        background: rgba(255,255,255,0.2);
        color: white;
        font-size: 18px;
        text-align: center;
        transition: 0.25s;
        width: 250px;
        transition: background 0.25s, transform 0.25s;
      }

      form input:focus {
        background: white;
        width: 300px;
        color: #53e3a6;
        outline: none;
        transform: scale(1.08);
      }

      form button {
        width: 250px;
        padding: 10px 15px;
        border-radius: 3px;
        border: none;
        background: white;
        color: #d29429ff;
        font-size: 18px;
        cursor: pointer;
        transition: 0.25s;
      }

      form button:hover {
        background: #f5f7f9;
      }

      form button:hover {
        background-color: #f5f7f9;
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
      .file-upload {
          width: 250px;
          margin-bottom: 15px;
          text-align: left;
      }

      .file-label {
          display: flex;
          justify-content: space-between;
          align-items: center;
          border: 1px dashed rgba(255,255,255,0.6);
          border-radius: 4px;
          padding: 10px 12px;
          cursor: pointer;
          background: rgba(255,255,255,0.15);
          transition: 0.25s;
      }

      .file-label:hover {
          background: rgba(255,255,255,0.25);
      }

      .file-text {
          color: white;
          font-size: 14px;
          overflow: hidden;
          white-space: nowrap;
          text-overflow: ellipsis;
      }

      .file-btn {
          background: white;
          color: #d29429ff;
          font-size: 13px;
          padding: 4px 10px;
          border-radius: 3px;
          font-weight: 600;
      }

      .file-note {
          display: block;
          margin-top: 5px;
          font-size: 11px;
          color: rgba(255,255,255,0.8);
      }

    </style>
    </head>
<body>
    <div class="wrapper">
        <div class="container">
            <h1>Daftar Shopee Manage</h1>
            
            <form class="form" id="form-register" enctype="multipart/form-data">
                <input type="text" name="name" placeholder="Nama Lengkap" required>
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <div class="file-upload">
                    <label for="foto" class="file-label">
                        <span class="file-text">Pilih Foto Profil</span>
                        <span class="file-btn">Upload</span>
                    </label>
                    <input type="file" id="foto" name="foto" accept="image/*" hidden>
                    <small class="file-note">JPG / PNG / WEBP (Max 2MB)</small>
                </div>
                <button type="button" id="btn-register">Daftar</button>
            </form>

        </div>
        
        <ul class="bg-bubbles">
          <li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li>
        </ul>
    </div>
<script>
document.getElementById('btn-register').addEventListener('click', function () {
    const form = document.getElementById('form-register');
    const formData = new FormData(form);

    fetch('<?= BASE_URL ?>/functions/sign_in.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            alert('Registrasi berhasil');
            window.location.href = '<?= BASE_URL ?>';
        } else {
            alert(res.message);
        }
    })
    .catch(err => {
        alert('Terjadi kesalahan');
        console.error(err);
    });
});

document.getElementById('foto').addEventListener('change', function(){
    const fileName = this.files[0]?.name || 'Pilih Foto Profil';
    document.querySelector('.file-text').innerText = fileName;
});
</script>

</body>
</html>