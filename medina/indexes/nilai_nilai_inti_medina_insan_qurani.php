
<?php
require_once '../config.php';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Medina Insan Qur'ani</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/animate.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global-color.css">
    <style>

        * {
            font-family: system-ui, sans-serif;
            font-weight: 600;
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background-color: #fff;
            
        }

        .content{
            min-height: 100vh;
            margin-top: 90px;
        }

        .banner-akreditasi{
            width: 100%;
        }
        .banner-akreditasi{
            width: 100%;
            margin-bottom: 50px;
        }
        .title-body{
            display: flex;
            flex-direction: column;
            gap: 8px;
            text-align: center;
            margin-bottom: 30px;
            margin-top: 70px;
        }

        .button-title{
            background-color: var(--logo-color);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 25px;
            font-size: 14px;
            cursor: default;
            margin: auto;
            transition-duration: 0.3s;
            text-decoration: none;
        }

        .button-title:hover{
            transform: scale(1.2);
        }

        .title{
            text-align: center;
            margin: auto;
            color: var(--logo-color);
            transition-duration: 0.3s;
        }

        .title:hover{
            transform: scale(1.2);
        }

        .subtitle{
            display: flex;
            text-align: center;
            margin: auto;
            color: var(--logo-color);
            transition-duration: 0.3s;
        }

        .subtitle:hover{
            transform: scale(1.2);
        }
        .core-values-container{
            gap: 50px;
            padding: 0 100px;
            margin-bottom: 50px;
            margin-top: 50px;
        }
        .core-value{
            display: flex;
            box-shadow: 0 6px 14px rgba(0,0,0,.2);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            align-items: center;
            justify-content: left;
            margin-bottom: 40px;
        }
        .core-value .subtitle:hover{
            transform: none;
        }
        .large-logo{
            position: relative;
            width: 120px;
            height: 120px;
            margin-bottom: 20px;
        }
        .full-logo{
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.1;
        }
        .center-logo{
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60px;
            height: 60px;
        }
        .text-content{
            text-align: left;
        }
        .text-content ul{
            padding-left: 20px;
        }
        .text-content ul li{
            margin-bottom: 10px;
        }

/* =========================
   TABLET (≤1024px)
   ========================= */
@media (max-width: 1024px) {

    .core-values-container {
        padding: 0 40px;
        gap: 30px;
    }

    .core-value {
        gap: 20px;
    }

    .large-logo {
        width: 100px;
        height: 100px;
    }

    .center-logo {
        width: 50px;
        height: 50px;
    }
}


/* =========================
   MOBILE (≤768px)
   ========================= */
@media (max-width: 768px) {

    .content {
        margin-top: 70px;
    }

    .title-body {
        margin-top: 40px;
    }

    .core-values-container {
        padding: 0 20px;
    }

    .core-value {
        flex-direction: column;
        text-align: center;
        align-items: center;
        padding: 20px 16px;
    }

    .large-logo {
        margin-bottom: 10px;
    }

    .text-content {
        text-align: left;
        width: 100%;
    }

    .subtitle {
        font-size: 18px;
    }

    .title {
        font-size: 22px;
    }
}


/* =========================
   SMALL MOBILE (≤480px)
   ========================= */
@media (max-width: 480px) {

    .large-logo {
        width: 90px;
        height: 90px;
    }

    .center-logo {
        width: 45px;
        height: 45px;
    }

    .text-content ul li {
        font-size: 14px;
    }

    .button-title {
        font-size: 13px;
        padding: 6px 14px;
    }
}


    </style>
</head>

<body>
<?php require_once BASE_PATH . '/elements/navbar.php' ?>

<div class="content">
    <img class="banner-akreditasi" src="<?= BASE_URL ?>/assets/img/akreditasi.webp">
        <div class="title-body">
        <h2 class="title">
            Nilai-Nilai Inti Medina Insan Qur'ani
        </h2>
    </div>
    <div class="core-values-container">
        <div class="core-value">
            <div class="large-logo">
                <img class="full-logo" src="<?= BASE_URL ?>/assets/svg/background-icon2.svg" alt="Keislaman" width="100" height="100">
                <img class="center-logo" src="<?= BASE_URL ?>/assets/svg/gmail.svg" alt="Keislaman" width="100" height="100">
            </div>
            <div class="text-content">
                <h3 class="subtitle">1. Keislaman</h3>
                <ul>
                    <li>Menjadikan Al-Qur'an dan Sunnah sebagai pedoman hidup.</li>
                    <li>Mengamalkan ajaran Islam dalam kehidupan sehari-hari.</li>
                    <li>Menghormati dan menghargai perbedaan dalam Islam.</li>
                </ul>
            </div>
        </div>
        <div class="core-value">
            <div class="large-logo">
                <img class="full-logo" src="<?= BASE_URL ?>/assets/svg/background-icon2.svg" alt="Keislaman" width="100" height="100">
                <img class="center-logo" src="<?= BASE_URL ?>/assets/svg/gmail.svg" alt="Keislaman" width="100" height="100">
            </div>
            <div class="text-content">
                <h3 class="subtitle">2. Integritas</h3>
                <ul>
                    <li>Bersikap jujur dan bertanggung jawab dalam setiap tindakan.</li>
                    <li>Menjaga konsistensi antara perkataan dan perbuatan.</li>
                    <li>Menepati janji dan komitmen yang telah dibuat.</li>
                </ul>
            </div>
        </div>
        <div class="core-value">
            <div class="large-logo">
                <img class="full-logo" src="<?= BASE_URL ?>/assets/svg/background-icon2.svg" alt="Keislaman" width="100" height="100">
                <img class="center-logo" src="<?= BASE_URL ?>/assets/svg/gmail.svg" alt="Keislaman" width="100" height="100">
            </div>
            <div class="text-content">
                <h3 class="subtitle">3. Profesional</h3>
                <ul>
                    <li>Menjunjung tinggi standar kualitas dalam setiap pekerjaan.</li>
                    <li>Terus belajar dan mengembangkan diri untuk meningkatkan kompetensi.</li>
                    <li>Bekerja dengan penuh dedikasi dan etika profesional.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/elements/footer.php' ?>

</body> 
</html>
