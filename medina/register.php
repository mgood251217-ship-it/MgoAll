<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Pendaftaran | Medina Insan Qur'ani</title>
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
            background: #fff;
            color: var(--text-color);
        }

        .content{
            min-height: 100vh;
            margin-top: 90px;
        }

        /* ================= LAYOUT ================= */
        .register-container{
            width: 85%;
            margin: 100px 100px;
            display: flex;
            gap: 60px;
            align-items: center;
        }

        /* ================= LEFT IMAGE ================= */
        .register-image{
            flex: 1;
            border-radius: 24px;
            overflow: hidden;
            
        }

        .register-image img{
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* ================= RIGHT FORM ================= */
        .register-form{
            flex: 1;
            background: white;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,.12);
        }

        .register-form h2{
            color: var(--logo-color);
            margin-bottom: 10px;
        }

        .register-form p{
            font-size: 14px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        /* ================= FORM ================= */
        .form-group{
            margin-bottom: 18px;
        }

        .form-group label{
            display: block;
            font-size: 14px;
            margin-bottom: 6px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select{
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid #ddd;
            outline: none;
            font-size: 14px;
            transition: border .3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus{
            border-color: var(--logo-color);
        }

        textarea{
            resize: vertical;
            min-height: 80px;
        }

        /* ================= BUTTON ================= */
        .submit-btn{
            margin-top: 10px;
            background: var(--logo-color);
            color: white;
            padding: 14px;
            border: none;
            width: 100%;
            border-radius: 30px;
            font-size: 15px;
            cursor: pointer;
            transition: transform .3s, background .3s;
        }

        .submit-btn:hover{
            transform: scale(1.03);
            background: var(--logo-color-2);
        }

        /* ================= RESPONSIVE REGISTER ================= */

        /* Laptop kecil / Tablet */
        @media (max-width: 1200px) {
            .register-container {
                width: 92%;
                margin: 80px auto;
                gap: 40px;
            }
        }

        /* Tablet */
        @media (max-width: 992px) {
            .register-container {
                flex-direction: column;
                margin: 60px auto;
            }

            .register-image {
                width: 100%;
                max-height: 320px;
            }

            .register-image img {
                height: 100%;
            }
        }

        /* Mobile */
        @media (max-width: 768px) {
            .register-container {
                margin: 40px auto;
                gap: 30px;
            }

            .register-form {
                padding: 28px 22px;
                border-radius: 18px;
            }

            .register-form h2 {
                font-size: 22px;
            }

            .register-form p {
                font-size: 13px;
            }

            .submit-btn {
                padding: 13px;
                font-size: 14px;
            }
        }

        /* Small Mobile */
        @media (max-width: 480px) {
            .content {
                margin-top: 80px;
            }

            .register-container {
                width: 94%;
                margin: 30px auto;
            }

            .register-form {
                padding: 22px 18px;
            }

            .form-group input,
            .form-group textarea,
            .form-group select {
                padding: 11px 12px;
                font-size: 13px;
            }
        }

    </style>
</head>

<body>

<?php require_once BASE_PATH . '/elements/navbar.php' ?>

<div class="content">

    <div class="register-container">

        <!-- LEFT IMAGE -->
        <div class="register-image">
            <img src="<?= BASE_URL ?>/assets/img/orang.png" alt="Pendaftaran Santri">
        </div>

        <!-- RIGHT FORM -->
        <div class="register-form">
            <h2>Pendaftaran Santri Baru</h2>
            <p>
                Silakan lengkapi formulir pendaftaran di bawah ini dengan data
                yang benar. Informasi yang Anda berikan akan digunakan sebagai
                dasar proses seleksi dan administrasi calon santri.
            </p>

            <form action="#" method="post">

                <div class="form-group">
                    <label>Jenis Pembayaran</label>
                    <select name="jenis_pembayaran" required>
                        <option value="">-- Pilih Jenis Pendaftaran --</option>
                        <option value="baru">Siswa Baru</option>
                        <option value="pindahan">Pindahan</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Nama Lengkap Calon Siswa</label>
                    <input type="text" name="nama_siswa" placeholder="Masukkan nama lengkap" required>
                </div>

                <div class="form-group">
                    <label>Nama Lengkap Orang Tua</label>
                    <input type="text" name="nama_ortu" placeholder="Masukkan nama orang tua" required>
                </div>

                <div class="form-group">
                    <label>Alamat Domisili</label>
                    <textarea name="alamat" placeholder="Alamat lengkap domisili" required></textarea>
                </div>

                <div class="form-group">
                    <label>Asal Sekolah</label>
                    <input type="text" name="asal_sekolah" placeholder="Nama sekolah sebelumnya" required>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="contoh@gmail.com" required>
                </div>

                <div class="form-group">
                    <label>No. HP / WhatsApp</label>
                    <input type="tel" name="no_hp" placeholder="08xxxxxxxxxx" required>
                </div>

                <button type="submit" class="submit-btn">
                    Kirim Pendaftaran
                </button>

            </form>
        </div>

    </div>

</div>

<?php require_once BASE_PATH . '/elements/footer.php' ?>

</body>
</html>
