<?php
require_once '../config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Galeri Acara | Medina Insan Qur'ani</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/animate.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global-color.css">

    <style>

        * {
            font-family: system-ui, sans-serif;
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background-color: #fff;
            color: var(--text-color);
        }

        .content{
            min-height: 100vh;
            margin-top: 90px;
        }

        .banner-akreditasi{
            width: 100%;
            margin-bottom: 50px;
        }

        /* ================= TITLE ================= */
        .title-body{
            text-align: center;
            margin-bottom: 40px;
        }

        .title{
            color: var(--logo-color);
            font-size: 32px;
            margin-bottom: 10px;
        }

        .subtitle{
            max-width: 800px;
            margin: auto;
            line-height: 1.8;
            font-weight: 500;
            color: #555;
        }

        /* ================= CONTAINER ================= */
        .gallery-container{
            width: 85%;
            margin: auto;
            margin-bottom: 80px;
        }

        /* ================= GRID ================= */
        .gallery-grid{
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-top: 40px;
        }

        .gallery-card{
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0,0,0,.1);
            transition: transform .3s ease;
        }

        .gallery-card:hover{
            transform: translateY(-8px);
        }

        .gallery-card img{
            width: 100%;
            height: 220px;
            object-fit: cover;
        }

        .gallery-card .caption{
            padding: 18px;
        }

        .gallery-card .caption h4{
            color: var(--logo-color);
            margin-bottom: 6px;
        }

        .gallery-card .caption p{
            font-size: 14px;
            font-weight: 500;
            line-height: 1.6;
            color: #555;
        }

        /* ================= RESPONSIVE ================= */
        @media (max-width: 992px){
            .gallery-grid{
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px){
            .gallery-grid{
                grid-template-columns: 1fr;
            }

            .gallery-container{
                width: 92%;
            }

            .title{
                font-size: 26px;
            }
        }
    </style>
</head>

<body>

<?php require_once BASE_PATH . '/elements/navbar.php' ?>

<div class="content">

    <img class="banner-akreditasi" src="<?= BASE_URL ?>/assets/img/akreditasi.webp">

    <div class="title-body animate__animated animate__fadeInUp">
        <h2 class="title">Galeri Acara</h2>
        <p class="subtitle">
            Galeri ini menampilkan berbagai kegiatan dan momen berharga
            yang berlangsung di Pondok Pesantren Medina Insan Qur’ani.
            Setiap acara merupakan bagian dari proses pembentukan karakter,
            penguatan iman, serta pengembangan potensi santri dalam suasana
            yang penuh keberkahan.
        </p>
    </div>

    <div class="gallery-container">

        <div class="gallery-grid">

            <div class="gallery-card animate__animated animate__fadeInUp">
                <img src="<?= BASE_URL ?>/assets/img/landscape.jpeg" alt="">
                <div class="caption">
                    <h4>Kajian & Tausiyah</h4>
                    <p>
                        Kegiatan rutin untuk memperdalam pemahaman agama,
                        membentuk akhlak mulia, serta menanamkan nilai keislaman
                        dalam kehidupan sehari-hari santri.
                    </p>
                </div>
            </div>

            <div class="gallery-card animate__animated animate__fadeInUp">
                <img src="<?= BASE_URL ?>/assets/img/landscape.jpeg" alt="">
                <div class="caption">
                    <h4>Haflah & Wisuda</h4>
                    <p>
                        Momen penuh kebanggaan dan haru sebagai bentuk apresiasi
                        atas perjuangan santri dalam menempuh pendidikan dan
                        menghafal Al-Qur’an.
                    </p>
                </div>
            </div>

            <div class="gallery-card animate__animated animate__fadeInUp">
                <img src="<?= BASE_URL ?>/assets/img/landscape.jpeg" alt="">
                <div class="caption">
                    <h4>Kegiatan Santri</h4>
                    <p>
                        Berbagai aktivitas pembelajaran, kreativitas,
                        dan kebersamaan santri yang membangun jiwa sosial,
                        kemandirian, serta kedisiplinan.
                    </p>
                </div>
            </div>

            <div class="gallery-card animate__animated animate__fadeInUp">
                <img src="<?= BASE_URL ?>/assets/img/landscape.jpeg" alt="">
                <div class="caption">
                    <h4>Lomba & Prestasi</h4>
                    <p>
                        Dokumentasi keikutsertaan santri dalam berbagai perlombaan
                        sebagai wujud pengembangan bakat dan potensi unggulan.
                    </p>
                </div>
            </div>

            <div class="gallery-card animate__animated animate__fadeInUp">
                <img src="<?= BASE_URL ?>/assets/img/landscape.jpeg" alt="">
                <div class="caption">
                    <h4>Kegiatan Sosial</h4>
                    <p>
                        Program kepedulian sosial yang menanamkan nilai empati,
                        ukhuwah, dan tanggung jawab terhadap sesama.
                    </p>
                </div>
            </div>

            <div class="gallery-card animate__animated animate__fadeInUp">
                <img src="<?= BASE_URL ?>/assets/img/landscape.jpeg" alt="">
                <div class="caption">
                    <h4>Acara Besar Pesantren</h4>
                    <p>
                        Momen istimewa dalam peringatan hari besar Islam
                        dan agenda tahunan pesantren yang sarat makna
                        serta kebersamaan.
                    </p>
                </div>
            </div>

        </div>

    </div>
</div>

<?php require_once BASE_PATH . '/elements/footer.php' ?>

</body>
</html>
