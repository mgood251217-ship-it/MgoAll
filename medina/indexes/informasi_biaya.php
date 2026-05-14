<?php
require_once '../config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Informasi Biaya | Medina Insan Qur'ani</title>
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

        .title-body{
            text-align: center;
            margin-bottom: 40px;
        }

        .title{
            color: var(--logo-color);
            font-size: 32px;
        }

        /* ================= CONTAINER ================= */
        .cost_information-container{
            width: 85%;
            margin: auto;
            margin-bottom: 80px;
        }

        /* ================= INFO BOX ================= */
        .info-box{
            background: var(--bg-soft);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,.08);
            line-height: 1.8;
        }

        /* ================= COST CARDS ================= */
        .cost-grid{
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }

        .cost-card{
            background: white;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 15px 35px rgba(0,0,0,.1);
            transition: transform .3s ease;
        }

        .cost-card:hover{
            transform: translateY(-8px);
        }

        .cost-card h3{
            color: var(--logo-color);
            margin-bottom: 10px;
        }

        .cost-card .price{
            font-size: 26px;
            color: var(--logo-color-2);
            margin-bottom: 12px;
        }

        .cost-card ul{
            padding-left: 18px;
        }

        .cost-card ul li{
            margin-bottom: 10px;
        }

        /* ================= TABLE ================= */
        .cost-table{
            width: 100%;
            border-collapse: collapse;
            margin-top: 50px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0,0,0,.1);
        }

        .cost-table th,
        .cost-table td{
            padding: 16px 20px;
            text-align: left;
        }

        .cost-table th{
            background: var(--logo-color);
            color: white;
        }

        .cost-table tr:nth-child(even){
            background: #f9fafb;
        }

        /* ================= NOTE ================= */
        .note{
            margin-top: 30px;
            padding: 20px;
            background: #fff8e1;
            border-left: 6px solid var(--logo-color-2);
            border-radius: 12px;
            line-height: 1.7;
        }

        /* ================= RESPONSIVE ================= */
        @media (max-width: 992px){
            .cost-grid{
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px){
            .cost-grid{
                grid-template-columns: 1fr;
            }

            .cost_information-container{
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

    <div class="title-body">
        <h2 class="title">Informasi Biaya</h2>
    </div>

    <div class="cost_information-container">

        <!-- INFO -->
        <div class="info-box animate__animated animate__fadeInUp">
            <p>
                Informasi biaya pendidikan di Medina Insan Qur’ani disusun secara transparan
                untuk memudahkan orang tua dalam memahami komponen pembiayaan.
                Biaya yang tercantum sudah mencakup fasilitas pembelajaran dan program
                unggulan sekolah.
            </p>
        </div>

        <!-- COST CARDS -->
        <div class="cost-grid">
            <div class="cost-card animate__animated animate__fadeInUp">
                <h3>Biaya Pendaftaran</h3>
                <div class="price">Rp 500.000</div>
                <ul>
                    <li>Formulir pendaftaran</li>
                    <li>Seleksi administrasi</li>
                    <li>Tes masuk</li>
                </ul>
            </div>

            <div class="cost-card animate__animated animate__fadeInUp">
                <h3>Biaya Masuk</h3>
                <div class="price">Rp 3.500.000</div>
                <ul>
                    <li>Seragam sekolah</li>
                    <li>Buku pelajaran</li>
                    <li>Perlengkapan belajar</li>
                </ul>
            </div>

            <div class="cost-card animate__animated animate__fadeInUp">
                <h3>SPP Bulanan</h3>
                <div class="price">Rp 750.000</div>
                <ul>
                    <li>Kegiatan belajar mengajar</li>
                    <li>Program tahfidz</li>
                    <li>Kegiatan ekstrakurikuler</li>
                </ul>
            </div>
        </div>

        <!-- TABLE -->
        <table class="cost-table animate__animated animate__fadeInUp">
            <thead>
                <tr>
                    <th>Komponen</th>
                    <th>Biaya</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Pendaftaran</td>
                    <td>Rp 500.000</td>
                    <td>Satu kali</td>
                </tr>
                <tr>
                    <td>Biaya Masuk</td>
                    <td>Rp 3.500.000</td>
                    <td>Satu kali</td>
                </tr>
                <tr>
                    <td>SPP</td>
                    <td>Rp 750.000</td>
                    <td>Per bulan</td>
                </tr>
            </tbody>
        </table>

        <!-- NOTE -->
        <div class="note">
            <strong>Catatan:</strong><br>
            Biaya dapat berubah sewaktu-waktu sesuai kebijakan sekolah.
            Untuk informasi lebih lengkap dan terbaru, silakan menghubungi
            bagian administrasi Medina Insan Qur’ani.
        </div>

    </div>
</div>

<?php require_once BASE_PATH . '/elements/footer.php' ?>

</body>
</html>
