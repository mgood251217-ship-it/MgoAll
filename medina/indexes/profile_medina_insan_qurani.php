
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
        .profile-container{
            display: flex;
            gap: 50px;
            padding: 0 100px;
            margin-top: 50px;
            margin-bottom: 50px;
        }
        .profile-container .left{
            width: 60%;
        }
        .profile-container .right{
            width: 60%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .profile-container .right img{
            width: 80%;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 6px 14px rgba(0,0,0,.2);
        }
        .paragraph-content{
            text-align: justify;
            line-height: 1.6;
            color: var(--text-color);
        }
        .paragraph-content p{
            font-weight: lighter;
        }
        .visi-misi-container{
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 50px;
            padding: 0 100px;
        }
        .visi-misi{
            padding: 0 100px;
            box-shadow: 0 6px 14px rgba(0,0,0,.2);
            border-radius: 12px;
            text-align: center;
        }
        .visi-misi .subtitle{
            margin: auto;
            margin-top: 30px;
            display: block;
        }
        .visi-misi .visi, .visi-misi .misi{
            margin-top: 30px;
        }
        .visi-misi .visi .subtitle, .visi-misi .misi .subtitle{
            background-color: var(--primary-color);
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 14px;
        }
        .visi-misi .misi .paragraph-content{
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .visi-misi .misi ul{
            width: 60%;
            list-style: disc;
            padding-left: 20px;
        }
        .visi-misi .misi img{
            width: 35%;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 6px 14px rgba(0,0,0,.2);
            background-size: cover;
            aspect-ratio: 2/1;
        }
        .curriculum-programs-container{
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 50px;
            padding: 0 100px;
        }
        .curriculum-programs{
            text-align: center;
        }
        .curriculum-programs .subtitle{
            margin: auto;
            margin-top: 30px;
            display: block;
        }
        .curriculum-programs .curriculum, .curriculum-programs .programs{
            margin-top: 30px;
            text-align: left;
        }
        .curriculum-programs .curriculum .paragraph-content, .curriculum-programs .programs .paragraph-content{
            line-height: 1.6;
            color: var(--text-color);
        }
        .curriculum-programs .curriculum .paragraph-content p, .curriculum-programs .programs .paragraph-content p{
            font-weight: lighter;
        }
        .legalitas-container{
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 50px;
            padding: 0 100px;
        }
        .legalitas{
            text-align: center;
        }
        .legalitas .subtitle{
            margin: auto;
            margin-top: 30px;
            display: block;
        }
        .legalitas .paragraph-content{
            line-height: 1.6;
            color: var(--text-color);
        }
        .legalitas .paragraph-content p{
            font-weight: lighter;
        }
        .maps-container{
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 50px;
            padding: 0 100px;
        }
        .maps-container iframe{
            width: 100%;
            height: 450px;
            border: none;
            border-radius: 12px;
            box-shadow: 0 6px 14px rgba(0,0,0,.2);
        }

        /* =========================
        RESPONSIVE TABLET
        ========================= */
        @media (max-width: 1024px) {

            .profile-container,
            .visi-misi-container,
            .curriculum-programs-container,
            .legalitas-container,
            .maps-container {
                padding: 0 40px;
            }

            .profile-container {
                gap: 30px;
            }

            .profile-container .left,
            .profile-container .right {
                width: 100%;
            }

            .visi-misi {
                padding: 30px;
            }
        }

        /* =========================
        RESPONSIVE MOBILE
        ========================= */
        @media (max-width: 768px) {

            .content {
                margin-top: 70px;
            }

            .title-body {
                margin-top: 40px;
            }

            .profile-container {
                flex-direction: column;
                padding: 0 20px;
                gap: 20px;
            }

            .profile-container .right img {
                width: 100%;
            }

            .visi-misi-container,
            .curriculum-programs-container,
            .legalitas-container,
            .maps-container {
                padding: 0 20px;
            }

            .visi-misi {
                padding: 20px;
            }

            .visi-misi .misi .paragraph-content {
                flex-direction: column;
            }

            .visi-misi .misi ul,
            .visi-misi .misi img {
                width: 100%;
            }

            .visi-misi .misi img {
                margin-top: 20px;
            }

            .maps-container iframe {
                height: 300px;
            }

            h2.title {
                font-size: 22px;
            }

            h3.subtitle {
                font-size: 18px;
            }
        }

        /* =========================
        RESPONSIVE SMALL MOBILE
        ========================= */
        @media (max-width: 480px) {

            .banner-akreditasi {
                margin-bottom: 30px;
            }

            .paragraph-content {
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
            Profile Medina Insan Qur'ani
        </h2>
    </div>
    <div class="profile-container">
        <div class="left">
            <h3 class="subtitle">
                Sejarah Singkat
            </h3>
            <div class="paragraph-content">
                <p>
                    Lorem ipsum dolor sit amet consectetur adipiscing elit. Quisque faucibus ex sapien vitae pellentesque sem placerat. In id cursus mi pretium tellus duis convallis. Tempus leo eu aenean sed diam urna tempor. Pulvinar vivamus fringilla lacus nec metus bibendum egestas. Iaculis massa nisl malesuada lacinia integer nunc posuere. Ut hendrerit semper vel class aptent taciti sociosqu. Ad litora torquent per conubia nostra inceptos himenaeos.
                </p>
                <p>
                    Lorem ipsum dolor sit amet consectetur adipiscing elit. Quisque faucibus ex sapien vitae pellentesque sem placerat. In id cursus mi pretium tellus duis convallis. Tempus leo eu aenean sed diam urna tempor. Pulvinar vivamus fringilla lacus nec metus bibendum egestas. Iaculis massa nisl malesuada lacinia integer nunc posuere. Ut hendrerit semper vel class aptent taciti sociosqu. Ad litora torquent per conubia nostra inceptos himenaeos.
                </p>
                <p>
                    Lorem ipsum dolor sit amet consectetur adipiscing elit. Quisque faucibus ex sapien vitae pellentesque sem placerat. In id cursus mi pretium tellus duis convallis. Tempus leo eu aenean sed diam urna tempor. Pulvinar vivamus fringilla lacus nec metus bibendum egestas. Iaculis massa nisl malesuada lacinia integer nunc posuere. Ut hendrerit semper vel class aptent taciti sociosqu. Ad litora torquent per conubia nostra inceptos himenaeos.
                </p>
            </div>
        </div>
        <div class="right">
            <img src="<?= BASE_URL ?>/assets/img/orang.png" alt="" srcset="">
        </div>
    </div>
    <div class="visi-misi-container">
        <div class="visi-misi">
            <h3 class="subtitle">
                Visi dan Misi Medina Insan Qur'ani
            </h3>
            <div class="visi">
                <h3 class="subtitle">Visi</h3>
                <div class="paragraph-content">
                    <p>
                        Lorem ipsum dolor sit amet consectetur adipiscing elit. Quisque faucibus ex sapien vitae pellentesque sem placerat. In id cursus mi pretium tellus duis convallis. Tempus leo eu aenean sed diam urna tempor. Pulvinar vivamus fringilla lacus nec metus bibendum egestas. Iaculis massa nisl malesuada lacinia integer nunc posuere. Ut hendrerit semper vel class aptent taciti sociosqu. Ad litora torquent per conubia nostra inceptos himenaeos.
                    </p>
                </div>
            </div>
            <div class="misi">
                <h3 class="subtitle">Misi</h3>
                <div class="paragraph-content">
                    <ul>
                        <li>Lorem ipsum dolor sit amet consectetur adipiscing elit.</li>
                        <li>Quisque faucibus ex sapien vitae pellentesque sem placerat.</li>
                        <li>In id cursus mi pretium tellus duis convallis.</li>
                        <li>Tempus leo eu aenean sed diam urna tempor.</li>
                        <li>Pulvinar vivamus fringilla lacus nec metus bibendum egestas.</li>
                        <li>Iaculis massa nisl malesuada lacinia integer nunc posuere.</li>
                        <li>Ut hendrerit semper vel class aptent taciti sociosqu.</li>
                        <li>Ad litora torquent per conubia nostra inceptos himenaeos.</li>
                    </ul>
                    <img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="" srcset="">
                </div>
            </div>
        </div>
    </div>

    <div class="curriculum-programs-container">
        <div class="curriculum-programs">
            <h3 class="subtitle">
                Kurikulum dan Program Medina Insan Qur'ani
            </h3>
            <div class="curriculum">
                <h4>Kurikulum</h4>
                <div class="paragraph-content">
                    <p>
                        Lorem ipsum dolor sit amet consectetur adipiscing elit. Quisque faucibus ex sapien vitae pellentesque sem placerat. In id cursus mi pretium tellus duis convallis. Tempus leo eu aenean sed diam urna tempor. Pulvinar vivamus fringilla lacus nec metus bibendum egestas. Iaculis massa nisl malesuada lacinia integer nunc posuere. Ut hendrerit semper vel class aptent taciti sociosqu. Ad litora torquent per conubia nostra inceptos himenaeos.
                    </p>
                </div>
            </div>
            <div class="programs">
                <h4>Program Program</h4>
                <div class="paragraph-content">
                    <p>
                        Lorem ipsum dolor sit amet consectetur adipiscing elit. Quisque faucibus ex sapien vitae pellentesque sem placerat. In id cursus mi pretium tellus duis convallis. Tempus leo eu aenean sed diam urna tempor. Pulvinar vivamus fringilla lacus nec metus bibendum egestas. Iaculis massa nisl malesuada lacinia integer nunc posuere. Ut hendrerit semper vel class aptent taciti sociosqu. Ad litora torquent per conubia nostra inceptos himenaeos.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="legalitas-container">
        <div class="legalitas">
            <h3 class="subtitle">
                Legalitas Medina Insan Qur'ani
            </h3>
            <div class="paragraph-content">
                <p>
                    Lorem ipsum dolor sit amet consectetur adipiscing elit. Quisque faucibus ex sapien vitae pellentesque sem placerat. In id cursus mi pretium tellus duis convallis. Tempus leo eu aenean sed diam urna tempor. Pulvinar vivamus fringilla lacus nec metus bibendum egestas. Iaculis massa nisl malesuada lacinia integer nunc posuere. Ut hendrerit semper vel class aptent taciti sociosqu. Ad litora torquent per conubia nostra inceptos himenaeos.
                </p>
            </div>
        </div>
    </div>

    <div class="maps-container">
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15842.442336515476!2d107.68755345669945!3d-6.937057838290292!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e68c32c7ac17833%3A0x2198862019c7409e!2sAsrama%20Tahfidz%20Qur&#39;an%20SAHAL(Sahabat%20Al%20Quran)!5e0!3m2!1sen!2sid!4v1769137775743!5m2!1sen!2sid" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
</div>

<?php require_once BASE_PATH . '/elements/footer.php' ?>

</body> 
</html>
