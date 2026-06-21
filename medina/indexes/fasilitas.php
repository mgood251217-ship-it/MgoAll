<?php
require_once '../config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Fasilitas | Medina Insan Qur'ani</title>
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
            background: #fff;
        }

        .content {
            min-height: 100vh;
            margin-top: 90px;
        }

        .banner-akreditasi {
            width: 100%;
            margin-bottom: 50px;
        }

        .title {
            text-align: center;
            color: var(--logo-color);
            margin-bottom: 30px;
        }

        /* ================= TAB ================= */
        .tabs {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 40px;
        }

        .tab-btn {
            padding: 10px 22px;
            border-radius: 25px;
            border: 2px solid var(--logo-color);
            background: transparent;
            color: var(--logo-color);
            cursor: pointer;
            transition: .3s;
        }

        .tab-btn.active,
        .tab-btn:hover {
            background: var(--logo-color);
            color: #fff;
        }

        /* ================= GALLERY ================= */
        .tab-content {
            display: none;
            animation: fadeUp .6s ease;
        }

        .tab-content.active {
            display: block;
        }

        .container{
            margin: 0 100px;
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 80px;
        }

        .gallery-grid img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 14px;
            cursor: pointer;
            opacity: 0;
            transform: translateY(30px);
            animation: imgFade .6s ease forwards;
        }

        .gallery-grid img:hover {
            transform: scale(1.05);
        }

        @keyframes imgFade {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ================= MODAL ================= */
        .modal-gallery {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,.92);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: .3s;
            z-index: 99999;
        }

        .modal-gallery.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-gallery img {
            width: auto;
            height: auto;
            max-width: 95vw;
            min-height: 92vh;
            object-fit: contain;
            border-radius: 16px;
            animation: zoomIn .3s ease;
        }

        /* animasi zoom saat buka */
        @keyframes zoomIn {
            from {
                transform: scale(.9);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .modal-close {
            position: fixed;
            top: 20px;
            right: 30px;
            font-size: 36px;
            color: #fff;
            cursor: pointer;
            z-index: 100000;
        }

        .modal-nav {
            position: fixed;
            top: 50%;
            transform: translateY(-50%);
            font-size: 48px;
            color: #fff;
            cursor: pointer;
            user-select: none;
            z-index: 100000;
            padding: 10px;
        }

        .modal-prev { left: 20px; }
        .modal-next { right: 20px; }

        @media (min-width: 1200px) {
            .modal-gallery img {
                max-width: 98vw;
                max-height: 95vh;
            }
        }

        /* ================= RESPONSIVE ================= */

        /* Laptop kecil / Tablet landscape */
        @media (max-width: 1024px) {
            .gallery-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        /* Tablet & Mobile */
        @media (max-width: 768px) {
            .gallery-grid {
                width: 92%;
                grid-template-columns: repeat(2, 1fr);
                gap: 14px;
            }

            .gallery-grid img {
                height: 180px;
            }
        }

        /* Small Mobile */
        @media (max-width: 480px) {
            .gallery-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .gallery-grid img {
                height: 160px;
            }

            .tab-btn {
                padding: 8px 16px;
                font-size: 14px;
            }

            .modal-close {
                top: 15px;
                right: 20px;
                font-size: 30px;
            }

            .modal-nav {
                font-size: 36px;
            }
        }

    </style>
</head>

<body>

<?php require_once BASE_PATH . '/elements/navbar.php' ?>

<div class="content">
    <img class="banner-akreditasi" src="<?= BASE_URL ?>/assets/img/akreditasi.webp">

    <h2 class="title">Fasilitas</h2>

    <!-- TAB -->
    <div class="tabs">
        <button class="tab-btn active" data-tab="tab1">Ruang Kelas</button>
        <button class="tab-btn" data-tab="tab2">Perpustakaan</button>
        <button class="tab-btn" data-tab="tab3">Masjid</button>
        <button class="tab-btn" data-tab="tab4">Area Outdoor</button>
    </div>


    <div class="container">
        <!-- TAB CONTENT -->
        <?php for($t=1;$t<=4;$t++): ?>
        <div class="tab-content <?= $t==1?'active':'' ?>" id="tab<?= $t ?>">
            <div class="gallery-grid">
                <?php for($i=1;$i<=12;$i++): ?>
                    <img src="<?= BASE_URL ?>/assets/img/landscape.jpeg" alt="Gambar Fasilitas">
                <?php endfor; ?>
            </div>
        </div>
        <?php endfor; ?>
    </div>
    
</div>

<?php require_once BASE_PATH . '/elements/footer.php' ?>

<!-- MODAL -->
<div class="modal-gallery" id="modalGallery">
    <span class="modal-close">&times;</span>
    <span class="modal-nav modal-prev">&#10094;</span>
    <img id="modalImage">
    <span class="modal-nav modal-next">&#10095;</span>
</div>

<script>
/* ===== TAB ===== */
const tabs = document.querySelectorAll('.tab-btn');
const contents = document.querySelectorAll('.tab-content');

tabs.forEach(btn => {
    btn.addEventListener('click', () => {
        tabs.forEach(b => b.classList.remove('active'));
        contents.forEach(c => c.classList.remove('active'));

        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.add('active');
    });
});

/* ===== MODAL ===== */
const modal = document.getElementById('modalGallery');
const modalImg = document.getElementById('modalImage');
const images = document.querySelectorAll('.gallery-grid img');
let currentIndex = 0;

images.forEach((img, index) => {
    img.addEventListener('click', () => {
        currentIndex = index;
        modalImg.src = img.src;
        modal.classList.add('active');
    });
});

document.querySelector('.modal-close').onclick = () => modal.classList.remove('active');

document.querySelector('.modal-next').onclick = () => {
    currentIndex = (currentIndex + 1) % images.length;
    modalImg.src = images[currentIndex].src;
};

document.querySelector('.modal-prev').onclick = () => {
    currentIndex = (currentIndex - 1 + images.length) % images.length;
    modalImg.src = images[currentIndex].src;
};

modal.addEventListener('click', e => {
    if (e.target === modal) modal.classList.remove('active');
});
</script>

</body>
</html>
