<?php
require_once '../config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Penghargaan | Medina Insan Qur'ani</title>
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
            margin-bottom: 40px;
        }

        /* ================= CONTAINER ================= */
        .container {
            width: 85%;
            margin: auto;
            margin-bottom: 80px;
        }

        .award-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .award-item {
            position: relative;
            overflow: hidden;
            border-radius: 16px;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(0,0,0,.1);
            opacity: 0;
            transform: translateY(30px);
            animation: fadeUp .6s ease forwards;
        }

        .award-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            aspect-ratio: 2 / 3;
            transition: transform .4s ease;
        }

        .award-item:hover img {
            transform: scale(1.08);
        }

        @keyframes fadeUp {
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
            max-width: 95vw;
            max-height: 92vh;
            object-fit: contain;
            border-radius: 16px;
            animation: zoomIn .3s ease;
        }

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

        /* ================= RESPONSIVE ================= */
        @media (max-width: 600px) {
            .container {
                width: 92%;
            }
        }

        /* ================= RESPONSIVE ================= */

        /* Tablet */
        @media (max-width: 1024px) {
            .award-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        /* Mobile */
        @media (max-width: 768px) {
            .award-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }

            .container {
                width: 92%;
            }
        }

        /* Small Mobile */
        @media (max-width: 480px) {
            .award-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
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

    <h2 class="title">Penghargaan</h2>

    <div class="container">
        <div class="award-grid">
            <?php for($i=1;$i<=15;$i++): ?>
                <div class="award-item">
                    <img src="<?= BASE_URL ?>/assets/img/orang.png" alt="Penghargaan">
                </div>
            <?php endfor; ?>
        </div>
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
const modal = document.getElementById('modalGallery');
const modalImg = document.getElementById('modalImage');
const images = document.querySelectorAll('.award-item img');
let currentIndex = 0;

images.forEach((img, index) => {
    img.addEventListener('click', () => {
        currentIndex = index;
        modalImg.src = img.src;
        modal.classList.add('active');
    });
});

document.querySelector('.modal-close').onclick = () => {
    modal.classList.remove('active');
};

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
