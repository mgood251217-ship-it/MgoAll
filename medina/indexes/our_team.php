<?php
require_once '../config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Our Team | Medina Insan Qur'ani</title>
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
            color: var(--text-color);
        }

        .content{
            min-height: 100vh;
            margin-top: 90px;
        }

        .banner-akreditasi{
            width: 100%;
            margin-bottom: 60px;
        }

        .title{
            text-align: center;
            color: var(--logo-color);
            margin-bottom: 10px;
        }

        .subtitle{
            text-align: center;
            font-size: 15px;
            max-width: 750px;
            margin: auto;
            color: #555;
        }

        /* ================= SECTION ================= */

        .container{
            margin: 0 100px;
        }

        .team-section{
            margin: 80px auto 120px;
            padding: 0 20px;
        }

        .team-group{
            margin-bottom: 80px;
        }

        .group-title{
            text-align: center;
            font-size: 22px;
            color: var(--logo-color);
            margin-bottom: 35px;
        }

        /* ================= GRID ================= */
        .team-grid{
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 30px;
        }

        /* ================= CARD ================= */
        .team-card{
            background: #fff;
            border-radius: 24px;
            padding: 30px 20px;
            text-align: center;
            box-shadow: 0 10px 28px rgba(0,0,0,.08);
            transition: .35s ease;
        }

        .team-card:hover{
            transform: translateY(-12px);
            box-shadow: 0 20px 45px rgba(0,0,0,.15);
        }

        .team-avatar{
            width: 120px;
            height: 120px;
            margin: auto;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--logo-color), var(--logo-color-2));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 52px;
            color: #fff;
            margin-bottom: 18px;
        }

        .team-name{
            font-size: 18px;
            color: var(--logo-color);
            margin-bottom: 8px;
        }

        .team-role{
            display: inline-block;
            background: var(--soft-bg);
            color: var(--logo-color);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        /* ================= QUOTE ================= */
        .team-quote{
            max-width: 900px;
            margin: 100px auto 0;
            padding: 40px;
            text-align: center;
            background: var(--soft-bg);
            border-radius: 18px;
            border-left: 6px solid var(--logo-color);
        }

        .team-quote p{
            font-size: 18px;
            font-weight: 500;
            color: #444;
        }

        .team-quote span{
            display: block;
            margin-top: 14px;
            font-size: 14px;
            color: var(--logo-color);
        }
    </style>
</head>

<body>

<?php require_once BASE_PATH . '/elements/navbar.php' ?>

<div class="content">

    <img class="banner-akreditasi" src="<?= BASE_URL ?>/assets/img/akreditasi.webp">

    <h2 class="title animate__animated animate__fadeInDown">Our Team</h2>
    <p class="subtitle animate__animated animate__fadeIn">
        Tim Medina Insan Qur'ani terdiri dari tenaga pendidik dan pengelola
        yang profesional, berdedikasi, dan berkomitmen membangun generasi
        Qur’ani yang unggul.
    </p>

    <div class="container">
        <section class="team-section">

            <!-- MANAJEMEN -->
            <div class="team-group">
                <div class="group-title">Manajemen Sekolah</div>
                <div class="team-grid">

                    <div class="team-card animate__animated animate__fadeInUp">
                        <div class="team-avatar">👤</div>
                        <div class="team-name">Nama Lengkap</div>
                        <div class="team-role">Kepala Sekolah</div>
                    </div>

                    <div class="team-card animate__animated animate__fadeInUp" style="animation-delay:.1s">
                        <div class="team-avatar">👤</div>
                        <div class="team-name">Nama Lengkap</div>
                        <div class="team-role">Wakil Kepala Sekolah</div>
                    </div>

                    <div class="team-card animate__animated animate__fadeInUp" style="animation-delay:.2s">
                        <div class="team-avatar">👤</div>
                        <div class="team-name">Nama Lengkap</div>
                        <div class="team-role">Kepala Kurikulum</div>
                    </div>

                </div>
            </div>

            <!-- TAHFIDZ -->
            <div class="team-group">
                <div class="group-title">Tim Tahfidz & Keislaman</div>
                <div class="team-grid">

                    <div class="team-card animate__animated animate__fadeInUp">
                        <div class="team-avatar">👤</div>
                        <div class="team-name">Nama Lengkap</div>
                        <div class="team-role">Koordinator Tahfidz</div>
                    </div>

                    <div class="team-card animate__animated animate__fadeInUp" style="animation-delay:.1s">
                        <div class="team-avatar">👤</div>
                        <div class="team-name">Nama Lengkap</div>
                        <div class="team-role">Ustadz Tahfidz</div>
                    </div>

                    <div class="team-card animate__animated animate__fadeInUp" style="animation-delay:.2s">
                        <div class="team-avatar">👤</div>
                        <div class="team-name">Nama Lengkap</div>
                        <div class="team-role">Pembina Akhlak</div>
                    </div>

                </div>
            </div>

            <!-- QUOTE -->
            <div class="team-quote animate__animated animate__fadeIn">
                <p>
                    “Setiap guru adalah teladan, setiap ilmu adalah amanah,
                    dan setiap anak adalah investasi akhirat.”
                </p>
                <span>— Tim Medina Insan Qur'ani</span>
            </div>

        </section>
    </div>

</div>

<?php require_once BASE_PATH . '/elements/footer.php' ?>

</body>
</html>
