<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>M G O</title>
  <meta name="robots" content="noindex, nofollow">
  <meta name="googlebot" content="noindex">
  <link rel="icon" href="/assets/img/title_icon.webp" type="image/png">
  
  <!-- Bootstrap -->
  <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
  <style>

    body {
        min-height: 100vh;
        background: url('https://mgood.my.id/assets/img/background.webp') no-repeat center center fixed;
        background-size: cover;
      display: flex;
      justify-content: center;
      align-items: center;
      color: #fff;
      font-family: 'Poppins', sans-serif;
      overflow: hidden;
    }
    body::before {
        content: "";
        position: fixed;
        inset: 0;
        background: inherit;
        filter: blur(10px) brightness(0.7);
        z-index: 0;
    }
    .container {
      text-align: center;
      animation: fadeIn 1.5s ease;
      z-index: 999;
    }
    .logo {
      width: 300px;
      height: auto;
      margin-bottom: 15px;
      animation: zoomIn 1s ease;
    }
    h1 {
      font-size: 3rem;
      font-weight: 700;
      margin-bottom: 1rem;
      animation: slideDown 1s ease;
    }
    p {
      font-size: 1.25rem;
      margin-bottom: 2rem;
      animation: slideUp 1.2s ease;
    }
    .btn-custom {
      padding: 0.8rem 2.5rem;
      font-size: 1.1rem;
      border-radius: 50px;
      border: none;
      background: #fff;
      color: #2a5298;
      position: relative;
      overflow: hidden;
      transition: all 0.3s ease;
    }
    .btn-custom:hover {
      transform: scale(1.05);
      color: #fff;
      background: #0d6efd;
    }
    .btn-custom::after {
      content: "";
      position: absolute;
      left: 50%;
      top: 50%;
      width: 0;
      height: 0;
      background: rgba(255,255,255,0.3);
      border-radius: 100%;
      transform: translate(-50%, -50%);
      transition: width 0.6s ease, height 0.6s ease;
    }
    .btn-custom:active::after {
      width: 300px;
      height: 300px;
      transition: 0s;
    }

    @keyframes fadeIn {
      from {opacity: 0;}
      to {opacity: 1;}
    }
    @keyframes zoomIn {
      from {transform: scale(0);}
      to {transform: scale(1);}
    }
    @keyframes slideDown {
      from {transform: translateY(-50px); opacity: 0;}
      to {transform: translateY(0); opacity: 1;}
    }
    @keyframes slideUp {
      from {transform: translateY(50px); opacity: 0;}
      to {transform: translateY(0); opacity: 1;}
    }
  </style>
</head>
<body>

<div class="container">
  <!-- Logo Gambar -->
  <img src="/assets/img/icon_web.webp" class="logo">

  <h1>M G O</h1>
  <p>High Data Management Solutions</p>
  <a href="login" class="btn btn-custom">Masuk Admin Panel</a>
</div>

</body>
</html>
