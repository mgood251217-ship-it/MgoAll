<?php
require_once '../config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Literasi Sekolah | Medina Insan Qur'ani</title>
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
            margin-bottom: 60px;
        }

        /* ================= CONTAINER ================= */
        .container{
            margin: 0 100px;
            margin-bottom: 80px;
        }

        /* ================= HEADER ARTIKEL ================= */
        .article-header{
            text-align: center;
            margin-bottom: 50px;
        }

        .article-header h1{
            color: var(--logo-color);
            font-size: 36px;
            margin-bottom: 12px;
        }

        .article-header p{
            color: var(--muted-color);
            font-size: 16px;
            max-width: 700px;
            margin: auto;
            line-height: 1.6;
        }

        /* ================= ARTIKEL ================= */
        .article{
            background: var(--bg-soft);
            padding: 50px;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,.08);
        }

        .article h2{
            color: var(--logo-color);
            margin-top: 40px;
            margin-bottom: 14px;
            font-size: 26px;
        }

        .article p{
            font-size: 17px;
            line-height: 1.9;
            margin-bottom: 20px;
            color: #374151;
        }

        .article ul{
            margin-left: 20px;
            margin-bottom: 30px;
        }

        .article ul li{
            margin-bottom: 12px;
            line-height: 1.7;
        }

        /* ================= QUOTE ================= */
        .highlight{
            margin: 40px 0;
            padding: 30px;
            background: white;
            border-left: 6px solid var(--logo-color);
            border-radius: 12px;
            font-style: italic;
            box-shadow: 0 10px 25px rgba(0,0,0,.06);
        }

        /* ================= RESPONSIVE ================= */
        @media (max-width: 768px){
            .container{
                width: 92%;
            }

            .article{
                padding: 30px;
            }

            .article-header h1{
                font-size: 28px;
            }
        }
    </style>
</head>

<body>

<?php require_once BASE_PATH . '/elements/navbar.php' ?>

<div class="content">
    <img class="banner-akreditasi" src="<?= BASE_URL ?>/assets/img/akreditasi.webp">

    <div class="container">

        <div class="article-header">
            <h1>Literasi Sekolah</h1>
            <p>
                Program Literasi Sekolah di Medina Insan Qur’ani dirancang untuk menumbuhkan
                budaya membaca, menulis, dan berpikir kritis sejak dini dengan landasan nilai-nilai Islam.
            </p>
        </div>

        <div class="article animate__animated animate__fadeInUp">

            <p>
                Literasi sekolah merupakan salah satu fondasi penting dalam membentuk generasi
                yang berilmu, berakhlak, dan berdaya saing. Di Medina Insan Qur’ani, literasi tidak
                hanya dimaknai sebagai kemampuan membaca dan menulis, tetapi juga sebagai
                kemampuan memahami, menganalisis, serta mengamalkan ilmu dalam kehidupan sehari-hari.
            </p>

            <h2>Konsep Literasi di Medina Insan Qur’ani</h2>

            <p>
                Konsep literasi di sekolah kami terintegrasi dengan nilai-nilai Al-Qur’an dan Sunnah.
                Peserta didik dibimbing untuk mencintai ilmu sebagai bagian dari ibadah, sehingga
                aktivitas literasi menjadi kegiatan yang menyenangkan dan bermakna.
            </p>

            <ul>
                <li>Membaca Al-Qur’an dan buku bacaan berkualitas setiap hari</li>
                <li>Menulis refleksi, ringkasan, dan karya kreatif siswa</li>
                <li>Diskusi dan presentasi untuk melatih berpikir kritis</li>
                <li>Literasi digital yang bijak dan bertanggung jawab</li>
            </ul>

            <div class="highlight">
                “Membaca adalah jendela ilmu, dan ilmu adalah cahaya bagi kehidupan.”
            </div>

            <h2>Kegiatan Literasi Sekolah</h2>

            <p>
                Program literasi sekolah dilaksanakan melalui berbagai kegiatan terstruktur dan
                berkelanjutan, seperti pojok baca kelas, waktu membaca bersama, lomba menulis,
                serta kajian buku Islami yang disesuaikan dengan jenjang pendidikan siswa.
            </p>

            <p>
                Guru berperan sebagai fasilitator dan teladan dalam membangun budaya literasi.
                Dengan demikian, siswa tidak hanya terbiasa membaca, tetapi juga mampu
                mengekspresikan gagasan secara lisan maupun tulisan dengan baik.
            </p>

            <h2>Harapan dan Tujuan</h2>

            <p>
                Melalui literasi sekolah, Medina Insan Qur’ani berharap dapat melahirkan generasi
                yang cinta ilmu, memiliki karakter Qur’ani, serta siap menghadapi tantangan zaman
                dengan tetap berpegang pada nilai-nilai Islam.
            </p>

        </div>

    </div>
</div>

<?php require_once BASE_PATH . '/elements/footer.php' ?>

</body>
</html>
