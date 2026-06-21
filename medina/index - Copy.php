<?php
require_once 'config.php';

$bannerImage = BASE_URL . '/assets/img/banner_web.webp';
$bannerImageMobile = BASE_URL . '/assets/img/banner_web_potrait.webp';

// Penulisan array yang bersih tanpa error spasi siluman
$galleryImage = [
    BASE_URL . "/assets/img/landscape.jpeg",
    BASE_URL . "/assets/img/landscape.jpeg",
    BASE_URL . "/assets/img/landscape.jpeg",
    BASE_URL . "/assets/img/landscape.jpeg",
    BASE_URL . "/assets/img/landscape.jpeg",
    BASE_URL . "/assets/img/landscape.jpeg",
    BASE_URL . "/assets/img/landscape.jpeg",
    BASE_URL . "/assets/img/landscape.jpeg",
    BASE_URL . "/assets/img/landscape.jpeg"
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Medina Insan Qur'ani</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/animate.min.css">
    
    <style>
        :root {
            /* Tema Warna ala PPTQAM */
            --logo-color: #009387;       /* Hijau Tosca PPTQAM */
            --logo-color-2: #FF7A00;     /* Oranye Aksen */
            --primary-color: #009387;
            --background-color: #F8FAFC; /* Abu-abu sangat terang untuk background */
            --surface-color: #FFFFFF;
            --text-color: #334155;       /* Slate gelap */
            --text-muted: #64748B;
            --border-radius: 16px;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s ease;
        }

        * {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--background-color);
            color: var(--text-color);
            overflow-x: hidden;
        }

        a { text-decoration: none; }
        img { max-width: 100%; height: auto; display: block; }

        .content {
            min-height: 100vh;
            margin-top: 80px; /* Offset Navbar */
        }

        /* BANNER */
        .banner-container picture img {
            width: 100%;
            height: auto;
            object-fit: cover;
        }

        /* GLOBAL SECTION & TITLE */
        .section-container {
            max-width: 1200px;
            margin: 80px auto;
            padding: 0 20px;
        }

        .title-body {
            text-align: center;
            margin-bottom: 40px;
        }

        .button-title {
            display: inline-block;
            background-color: rgba(0, 147, 135, 0.1);
            color: var(--logo-color);
            padding: 6px 20px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .title {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-color);
        }

        .subtitle {
            font-size: 16px;
            color: var(--text-muted);
            margin-top: 8px;
            font-weight: 500;
        }

        /* SWIPER KUSTOMISASI */
        .swiper-button-next, .swiper-button-prev {
            color: var(--logo-color) !important;
            background: var(--surface-color);
            width: 40px !important;
            height: 40px !important;
            border-radius: 50%;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .swiper-button-next:after, .swiper-button-prev:after {
            font-size: 18px !important;
            font-weight: bold;
        }
        .swiper-pagination-bullet-active {
            background: var(--logo-color) !important;
        }

        /* FASILITAS SWIPER */
        .fasilitas-slide {
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            height: 300px;
        }
        .fasilitas-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* PROGRAM UNGGULAN (GRUP CARD) */
        .grup-card {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
        }

        .card-vertical {
            background: var(--surface-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 30px 20px;
            text-align: center;
            transition: var(--transition);
            border-bottom: 4px solid transparent;
        }

        .card-vertical:hover {
            transform: translateY(-10px);
            border-bottom: 4px solid var(--logo-color);
        }

        .card-vertical .box-img {
            position: relative;
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: rgba(0, 147, 135, 0.05);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-vertical .box-img .center-img {
            width: 40px;
            height: 40px;
        }

        .card-vertical h3 {
            color: var(--text-color);
            font-size: 18px;
            margin-bottom: 10px;
        }

        .card-vertical p {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        /* ABOUT US (CARD HORIZONTAL) */
        .card-horizontal {
            display: flex;
            align-items: center;
            gap: 50px;
            margin-bottom: 60px;
            background: var(--surface-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-horizontal.reverse {
            flex-direction: row-reverse;
        }

        .card-horizontal .img-box {
            flex: 1;
            height: 450px;
        }

        .card-horizontal .img-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-horizontal .card-content {
            flex: 1;
            padding: 40px;
        }

        .card-horizontal .card-content h3 {
            font-size: 28px;
            color: var(--logo-color);
            margin: 15px 0;
        }

        .card-horizontal .card-content p {
            color: var(--text-muted);
            line-height: 1.8;
            font-size: 15px;
            margin-bottom: 15px;
        }

        .list-correct {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
        }

        .list-correct .list {
            color: var(--text-color);
            font-weight: 500;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* TESTIMONI SWIPER */
        .review-item {
            background: var(--surface-color);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid rgba(0,0,0,0.03);
            height: 100%;
        }

        .review-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .header-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-profile img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }

        .name-rating p {
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 4px;
        }

        .name-rating .rating {
            color: #FFB800;
            font-size: 14px;
        }

        .review-item > p {
            color: var(--text-muted);
            line-height: 1.7;
            font-style: italic;
        }

        /* SIMPLE CAROUSEL (APRESIASI) */
        .apresiasi-slide img {
            width: 100%;
            height: 350px;
            object-fit: cover;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        /* NEWS / ARTIKEL TERBARU */
        .article-container {
            display: flex;
            gap: 40px;
        }

        .article-container .left-article {
            flex: 1;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            position: relative;
        }
        .article-container .left-article img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            min-height: 400px;
        }

        .article-container .right-article {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .article-card {
            display: flex;
            background: var(--surface-color);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            transition: var(--transition);
        }

        .article-card:hover {
            box-shadow: var(--shadow);
            transform: translateX(-5px);
        }

        .article-card img {
            width: 150px;
            height: 130px;
            object-fit: cover;
        }

        .article-text {
            padding: 15px 20px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .article-text .title {
            font-size: 16px;
            color: var(--text-color);
            margin-bottom: 8px;
            text-align: left;
        }

        .article-text p {
            color: var(--text-muted);
            font-size: 13px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .see-more {
            color: var(--logo-color-2);
            font-weight: 700;
            font-size: 13px;
        }

        /* GALLERY KEGIATAN */
        .gallery-container {
            display: flex;
            gap: 20px;
        }

        .gallery-container .left-gallery {
            flex: 1;
        }
        .gallery-container .left-gallery img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .gallery-container .right-gallery {
            flex: 1;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .gallery-container .right-gallery .box-img {
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            position: relative;
        }
        .gallery-container .right-gallery .box-img::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(0,147,135, 0.4);
            opacity: 0;
            transition: var(--transition);
        }

        .gallery-container .right-gallery .box-img:hover::after {
            opacity: 1;
        }

        .gallery-container .right-gallery img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            transition: var(--transition);
        }

        .gallery-container .right-gallery .box-img:hover img {
            transform: scale(1.1);
        }

        /* MODAL GALLERY */
        .modal-gallery {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            z-index: 99999;
        }

        .modal-gallery.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-gallery img {
            max-width: 90vw;
            max-height: 85vh;
            border-radius: 12px;
            object-fit: contain;
            animation: zoomIn 0.3s ease;
        }

        @keyframes zoomIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .modal-gallery .close-button {
            position: absolute;
            top: 25px;
            right: 35px;
            font-size: 40px;
            color: white;
            cursor: pointer;
            z-index: 100000;
        }

        .modal-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-size: 50px;
            color: white;
            cursor: pointer;
            padding: 20px;
            user-select: none;
            opacity: 0.7;
        }
        .modal-nav:hover { opacity: 1; }
        .modal-prev { left: 20px; }
        .modal-next { right: 20px; }

        /* RESPONSIVE TABLET & MOBILE */
        @media (max-width: 1024px) {
            .grup-card { grid-template-columns: repeat(2, 1fr); }
            .card-horizontal, .card-horizontal.reverse { flex-direction: column; }
            .card-horizontal .img-box { height: 350px; width: 100%; }
            .article-container { flex-direction: column; }
            .article-container .left-article img { min-height: 300px; }
            .gallery-container { flex-direction: column; }
            .gallery-container .right-gallery { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 768px) {
            .section-container { margin: 50px auto; }
            .grup-card { grid-template-columns: 1fr; }
            .list-correct { grid-template-columns: 1fr; }
            .article-card { flex-direction: column; }
            .article-card img { width: 100%; height: 200px; }
            .gallery-container .right-gallery { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>

<body>
<?php require_once BASE_PATH . '/elements/navbar.php' ?>

<div class="content">
    
    <div class="banner-container">
        <picture>
            <source media="(max-width: 768px)" srcset="<?= $bannerImageMobile ?>">
            <img src="<?= $bannerImage ?>" alt="Banner Medina Insan Qur'ani">
        </picture>
    </div>

    <div class="section-container">
        <div class="title-body">
            <span class="button-title">Fasilitas</span>
            <h2 class="title">Nyaman & Tentram</h2>
        </div>
        
        <div class="swiper fasilitasSwiper">
            <div class="swiper-wrapper">
                <div class="swiper-slide fasilitas-slide"><img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="Fasilitas"></div>
                <div class="swiper-slide fasilitas-slide"><img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="Fasilitas"></div>
                <div class="swiper-slide fasilitas-slide"><img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="Fasilitas"></div>
                <div class="swiper-slide fasilitas-slide"><img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="Fasilitas"></div>
                <div class="swiper-slide fasilitas-slide"><img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="Fasilitas"></div>
            </div>
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
            <div class="swiper-pagination" style="bottom: -5px; position:relative; margin-top:20px;"></div>
        </div>
    </div>

    <div class="section-container">
        <div class="title-body">
            <span class="button-title">Welcome</span>
            <h2 class="title">Program Unggulan</h2>
            <p class="subtitle">Ponpes Medina Insan Qurani</p>
        </div>

        <div class="grup-card">
            <?php for($i=0; $i<4; $i++): ?>
            <div class="card-vertical">
                <div class="box-img">
                    <img class="center-img" src="<?= BASE_URL ?>/assets/svg/address.svg" alt="Icon">
                </div>
                <h3>Ruang Kelas Modern</h3>
                <p>Lorem ipsum dolor sit amet consectetur adipiscing elit. Quisque faucibus ex sapien vitae pellentesque.</p>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <div class="section-container">
        <div class="card-horizontal">
            <div class="img-box">
                <img src="<?= BASE_URL ?>/assets/img/orang.png" alt="Gambar About Us">
            </div>
            <div class="card-content">
                <span class="button-title">About Us</span>
                <h3>Ruang Kelas Modern</h3>
                <p>Lorem ipsum dolor sit amet consectetur adipiscing elit. Quisque faucibus ex sapien vitae pellentesque sem placerat. In id cursus mi pretium tellus duis convallis. Tempus leo eu aenean sed diam urna tempor. Pulvinar vivamus fringilla lacus nec metus bibendum egestas.</p>
                <p>Iaculis massa nisl malesuada lacinia integer nunc posuere. Ut hendrerit semper vel class aptent taciti sociosqu.</p>
            </div>
        </div>
    </div>

    <div class="section-container">
        <div class="card-horizontal">
            <div class="img-box">
                <img src="<?= BASE_URL ?>/assets/img/orang.png" alt="Gambar Fasilitas">
            </div>
            <div class="card-content">
                <span class="button-title">Keunggulan</span>
                <h3>Fasilitas Terbaik</h3>
                <p>Lorem ipsum dolor sit amet consectetur adipiscing elit. Quisque faucibus ex sapien vitae pellentesque sem placerat. In id cursus mi pretium tellus duis convallis.</p>
                
                <div class="list-correct">
                    <div class="list">✅ Jago Baca Quran</div>
                    <div class="list">✅ Fasilitas Asri</div>
                    <div class="list">✅ Lingkungan Islami</div>
                    <div class="list">✅ Tenaga Pengajar Ahli</div>
                    <div class="list">✅ Ekstrakurikuler Aktif</div>
                    <div class="list">✅ Kajian Kitab Kuning</div>
                </div>
            </div>
        </div>
    </div>

    <div class="section-container">
        <div class="card-horizontal reverse">
            <div class="img-box">
                <img src="<?= BASE_URL ?>/assets/img/orang.png" alt="Gambar About Us">
            </div>
            <div class="card-content">
                <span class="button-title">Visi Misi</span>
                <h3>Pendidikan Berkarakter</h3>
                <p>Lorem ipsum dolor sit amet consectetur adipiscing elit. Quisque faucibus ex sapien vitae pellentesque sem placerat. In id cursus mi pretium tellus duis convallis. Tempus leo eu aenean sed diam urna tempor.</p>
                <p>Iaculis massa nisl malesuada lacinia integer nunc posuere. Ut hendrerit semper vel class aptent taciti sociosqu. Ad litora torquent per conubia nostra inceptos himenaeos.</p>
            </div>
        </div>
    </div>

    <div class="section-container">
        <div class="title-body">
            <span class="button-title">Testimoni</span>
            <h2 class="title">Apa Kata Mereka</h2>
        </div>
        
        <div class="swiper testimoniSwiper" style="padding: 10px;">
            <div class="swiper-wrapper">
                <?php for($i=1; $i<=5; $i++): ?>
                <div class="swiper-slide">
                    <div class="review-item">
                        <div class="review-item-header">
                            <div class="header-profile">
                                <img src="<?= BASE_URL ?>/assets/img/orang.png" alt="Profile">
                                <div class="name-rating">
                                    <p>Wali Santri <?= $i ?></p>
                                    <span class="rating">⭐⭐⭐⭐⭐</span>
                                </div>
                            </div>
                            <img src="<?= BASE_URL ?>/assets/svg/instagram.svg" alt="IG" style="width:24px; filter:grayscale(1); opacity:0.5;">
                        </div>
                        <p>"Lorem ipsum dolor sit amet consectetur adipiscing elit. Quisque faucibus ex sapien vitae pellentesque sem placerat. In id cursus mi pretium tellus duis convallis."</p>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
            <div class="swiper-pagination" style="bottom: -30px;"></div>
        </div>
    </div>

    <div class="section-container" style="margin-top: 100px;">
        <div class="swiper apresiasiSwiper">
            <div class="swiper-wrapper">
                <?php for($i=0; $i<4; $i++): ?>
                <div class="swiper-slide apresiasi-slide">
                    <img src="<?= BASE_URL ?>/assets/img/apresiasi.webp" alt="Apresiasi">
                </div>
                <?php endfor; ?>
            </div>
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
        </div>
    </div>

    <div class="section-container">
        <div class="title-body">
            <span class="button-title">News</span>
            <h2 class="title">Artikel Terbaru</h2>
        </div>
        
        <div class="article-container">
            <div class="left-article">
                <img src="<?= BASE_URL ?>/assets/img/orang.png" alt="Berita Utama">
            </div>
            <div class="right-article">
                <?php for($i=0; $i<3; $i++): ?>
                <div class="article-card">
                    <img src="<?= BASE_URL ?>/assets/img/landscape.jpeg" alt="Artikel Thumbnail">
                    <div class="article-text">
                        <h3 class="title">Kegiatan Santri Berprestasi</h3>
                        <p>Lorem ipsum dolor sit amet consectetur adipiscing elit. Quisque faucibus ex sapien vitae pellentesque sem placerat.</p>
                        <a href="#" class="see-more">Baca Selengkapnya &rarr;</a>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <div class="section-container">
        <div class="title-body">
            <span class="button-title">Kegiatan Harian</span>
            <h2 class="title">Ponpes Medina Insan Qurani</h2>
        </div>
        
        <div class="gallery-container">
            <div class="left-gallery">
                <img src="<?= BASE_URL ?>/assets/img/landscape.jpeg" alt="Gambar Utama">
            </div>
            <div class="right-gallery">
                <?php foreach (array_slice($galleryImage, 0, 9) as $img): ?>
                    <div class="box-img gallery-trigger">
                        <img src="<?= $img ?>" alt="Gallery Image">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="modal-gallery" id="modalGallery">
        <div style="width: 100%; height: 100%; position: relative; display: flex; align-items: center; justify-content: center;">
            <span class="close-button" id="closeModal">&times;</span>
            <span class="modal-nav modal-prev" id="prevModal">&#10094;</span>
            <img id="imgSrcModal" src="" alt="Modal Image">
            <span class="modal-nav modal-next" id="nextModal">&#10095;</span>
        </div>
    </div>

</div>

<?php require_once BASE_PATH . '/elements/footer.php' ?>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<script>
    /* =========================================
       1. INISIALISASI SWIPER (CAROUSEL)
       ========================================= */
       
    // Swiper Fasilitas (Tengah Aktif, Pinggir Ngintip)
    var fasilitasSwiper = new Swiper(".fasilitasSwiper", {
        slidesPerView: 1.2,
        spaceBetween: 20,
        centeredSlides: true,
        loop: true,
        autoplay: {
            delay: 3000,
            disableOnInteraction: false,
        },
        pagination: {
            el: ".fasilitasSwiper .swiper-pagination",
            clickable: true,
        },
        navigation: {
            nextEl: ".fasilitasSwiper .swiper-button-next",
            prevEl: ".fasilitasSwiper .swiper-button-prev",
        },
        breakpoints: {
            640: { slidesPerView: 1.5, spaceBetween: 30 },
            1024: { slidesPerView: 2.5, spaceBetween: 40 }
        }
    });

    // Swiper Testimoni (3 Kolom)
    var testimoniSwiper = new Swiper(".testimoniSwiper", {
        slidesPerView: 1,
        spaceBetween: 20,
        loop: true,
        autoplay: {
            delay: 4000,
            disableOnInteraction: false,
        },
        pagination: {
            el: ".testimoniSwiper .swiper-pagination",
            clickable: true,
        },
        breakpoints: {
            768: { slidesPerView: 2, spaceBetween: 30 },
            1024: { slidesPerView: 3, spaceBetween: 30 }
        }
    });

    // Swiper Apresiasi (1 Slide Full)
    var apresiasiSwiper = new Swiper(".apresiasiSwiper", {
        slidesPerView: 1,
        loop: true,
        autoplay: {
            delay: 5000,
            disableOnInteraction: false,
        },
        navigation: {
            nextEl: ".apresiasiSwiper .swiper-button-next",
            prevEl: ".apresiasiSwiper .swiper-button-prev",
        }
    });

    /* =========================================
       2. MODAL GALLERY (VANILLA JS)
       ========================================= */
    const modal = document.getElementById('modalGallery');
    const modalImg = document.getElementById('imgSrcModal');
    const triggers = document.querySelectorAll('.gallery-trigger img');
    const closeBtn = document.getElementById('closeModal');
    const prevBtn = document.getElementById('prevModal');
    const nextBtn = document.getElementById('nextModal');
    
    let currentIndex = 0;
    const imagesArray = Array.from(triggers).map(img => img.src);

    triggers.forEach((img, index) => {
        img.addEventListener('click', () => {
            currentIndex = index;
            modalImg.src = imagesArray[currentIndex];
            modal.classList.add('active');
            document.body.style.overflow = 'hidden'; // Stop scroll belakang
        });
    });

    function closeModal() {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }

    closeBtn.addEventListener('click', closeModal);
    
    // Tutup jika klik area kosong (background)
    modal.addEventListener('click', (e) => {
        if (e.target === modal || e.target.firstElementChild === e.target) closeModal();
    });

    nextBtn.addEventListener('click', () => {
        currentIndex = (currentIndex + 1) % imagesArray.length;
        modalImg.src = imagesArray[currentIndex];
    });

    prevBtn.addEventListener('click', () => {
        currentIndex = (currentIndex - 1 + imagesArray.length) % imagesArray.length;
        modalImg.src = imagesArray[currentIndex];
    });
</script>

</body> 
</html>