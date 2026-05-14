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
            margin-bottom: 30px;
            transition: .3s;
        }

        .title:hover{
            transform: scale(1.1);
        }

        .container{
            margin: 0 100px;
        }

        .section{
            margin: auto;
            padding: 0 20px;
        }

        /* ================= VALUE CARD ================= */
        .value-section{
            margin: 80px auto;
        }

        .value-grid{
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 24px;
        }

        .value-card{
            background: #fff;
            border-radius: 18px;
            padding: 28px 22px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,.08);
            transition: .35s ease;
        }

        .value-card:hover{
            transform: translateY(-10px) scale(1.03);
            box-shadow: 0 18px 40px rgba(0,0,0,.15);
        }

        .value-icon{
            font-size: 42px;
            margin-bottom: 14px;
        }

        .value-title{
            font-size: 18px;
            color: var(--logo-color);
            margin-bottom: 10px;
        }

        .value-desc{
            font-size: 14px;
            font-weight: lighter;
            color: #555;
        }

        /* ================= STATS ================= */
        .stats{
            background: linear-gradient(135deg, var(--logo-color), var(--logo-color-2));
            color: #fff;
            padding: 70px 20px;
            margin: 90px 0;
        }

        .stats-grid{
            max-width: 1000px;
            margin: auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px,1fr));
            gap: 30px;
            text-align: center;
        }

        .stat-number{
            font-size: 42px;
            font-weight: 800;
        }

        .stat-label{
            font-size: 15px;
            opacity: .9;
        }

        /* ================= QUOTE ================= */
        .quote-section{
            max-width: 900px;
            margin: 90px auto;
            padding: 40px;
            text-align: center;
            border-left: 6px solid var(--logo-color);
            background: #f9fefe;
            border-radius: 16px;
        }

        .quote-section p{
            font-size: 18px;
            font-weight: 500;
            color: #444;
        }

        .quote-author{
            margin-top: 16px;
            font-size: 14px;
            color: var(--logo-color);
        }

        /* ================= CTA ================= */
        .cta{
            text-align: center;
            margin: 80px 0 120px;
        }

        .cta a{
            background: var(--logo-color);
            color: #fff;
            padding: 14px 36px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 16px;
            transition: .3s;
            display: inline-block;
        }

        .cta a:hover{
            background: var(--logo-color-2);
            transform: scale(1.08);
        }
    </style>
</head>

<body>

<?php require_once BASE_PATH . '/elements/navbar.php' ?>

<div class="content">

    <img class="banner-akreditasi" src="<?= BASE_URL ?>/assets/img/akreditasi.webp">

    <div class="container">
        <div class="section">
            <h2 class="title animate__animated animate__fadeInDown">
                Standar Penjamin Mutu Internal Medina Insan Qur'ani
            </h2>

            <h1>Alasan Memilih Medina Insan Qur'ani</h1>
            <p>
                Standar Penjamin Mutu Internal (SPMI) di Medina Insan Qur'ani dirancang
                untuk memastikan bahwa setiap aspek pendidikan dan operasional memenuhi
                standar kualitas tertinggi.
            </p>
            <p>
                Standar Penjamin Mutu Internal (SPMI) Medina Insan Qur’ani merupakan sebuah sistem
                yang dirancang secara komprehensif untuk menjamin bahwa seluruh proses pendidikan,
                pengelolaan lembaga, serta pelayanan kepada peserta didik dan orang tua berjalan
                sesuai dengan standar mutu yang telah ditetapkan. SPMI ini menjadi landasan utama
                dalam setiap pengambilan keputusan strategis, baik dalam bidang akademik,
                pembinaan karakter, maupun pengembangan sumber daya manusia di lingkungan sekolah.
                Melalui penerapan SPMI, Medina Insan Qur’ani berkomitmen untuk menciptakan budaya
                mutu yang berkelanjutan, transparan, dan akuntabel dalam seluruh aktivitas pendidikan.
            </p>

            <p>
                Pelaksanaan SPMI di Medina Insan Qur’ani mencakup perencanaan, pelaksanaan,
                evaluasi, pengendalian, dan peningkatan mutu secara berkesinambungan. Setiap program
                pendidikan disusun berdasarkan analisis kebutuhan peserta didik, visi misi lembaga,
                serta perkembangan dunia pendidikan yang dinamis. Proses evaluasi dilakukan secara
                rutin dan sistematis melalui monitoring internal, supervisi akademik, serta masukan
                dari berbagai pemangku kepentingan, termasuk guru, peserta didik, dan orang tua.
                Hasil evaluasi tersebut kemudian digunakan sebagai dasar untuk melakukan perbaikan
                dan inovasi agar kualitas pendidikan terus meningkat dari waktu ke waktu.
            </p>

            <p>
                Selain berfokus pada pencapaian akademik, SPMI Medina Insan Qur’ani juga menaruh
                perhatian besar pada pembentukan karakter islami dan penguatan nilai-nilai akhlakul
                karimah. Setiap kegiatan pembelajaran diarahkan tidak hanya untuk mengembangkan
                kemampuan kognitif, tetapi juga untuk menumbuhkan sikap disiplin, tanggung jawab,
                kejujuran, serta kecintaan terhadap Al-Qur’an dan ajaran Islam. Dengan dukungan tenaga
                pendidik yang profesional, lingkungan belajar yang kondusif, serta keterlibatan aktif
                orang tua, Medina Insan Qur’ani berupaya melahirkan generasi yang unggul secara
                intelektual, kuat secara spiritual, dan siap menghadapi tantangan masa depan.
            </p>

        </div>

        <!-- VALUE -->
        <section class="value-section section">
            <div class="value-grid">
                <div class="value-card animate__animated animate__fadeInUp">
                    <div class="value-icon">📘</div>
                    <div class="value-title">Kurikulum Terpadu</div>
                    <div class="value-desc">
                        Akademik nasional berpadu dengan Al-Qur'an & karakter islami.
                    </div>
                </div>

                <div class="value-card animate__animated animate__fadeInUp" style="animation-delay:.1s">
                    <div class="value-icon">🧑‍🏫</div>
                    <div class="value-title">Guru Profesional</div>
                    <div class="value-desc">
                        Pengajar berpengalaman, berdedikasi, dan berakhlak.
                    </div>
                </div>

                <div class="value-card animate__animated animate__fadeInUp" style="animation-delay:.2s">
                    <div class="value-icon">🏫</div>
                    <div class="value-title">Lingkungan Nyaman</div>
                    <div class="value-desc">
                        Aman, bersih, dan mendukung tumbuh kembang siswa.
                    </div>
                </div>

                <div class="value-card animate__animated animate__fadeInUp" style="animation-delay:.3s">
                    <div class="value-icon">📊</div>
                    <div class="value-title">Evaluasi Berkala</div>
                    <div class="value-desc">
                        Monitoring mutu dilakukan secara konsisten & berkelanjutan.
                    </div>
                </div>
            </div>
        </section>

        <!-- STATS -->
        <section class="stats">
            <div class="stats-grid">
                <div>
                    <div class="stat-number">10+</div>
                    <div class="stat-label">Tahun Pengalaman</div>
                </div>
                <div>
                    <div class="stat-number">500+</div>
                    <div class="stat-label">Siswa Aktif</div>
                </div>
                <div>
                    <div class="stat-number">100%</div>
                    <div class="stat-label">Pendampingan Karakter</div>
                </div>
                <div>
                    <div class="stat-number">A</div>
                    <div class="stat-label">Akreditasi</div>
                </div>
            </div>
        </section>

        <!-- QUOTE -->
        <div class="quote-section animate__animated animate__fadeIn">
            <p>
                “Pendidikan terbaik adalah yang mampu membentuk kecerdasan akal,
                kelembutan hati, dan kekuatan iman.”
            </p>
            <div class="quote-author">— Medina Insan Qur'ani</div>
        </div>

        <!-- CTA -->
        <div class="cta">
            <a href="<?= BASE_URL ?>/pendaftaran">
                📌 Daftarkan Putra-Putri Anda Sekarang
            </a>
        </div>
    </div>

</div>

<?php require_once BASE_PATH . '/elements/footer.php' ?>

</body>
</html>
