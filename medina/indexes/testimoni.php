
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

        .review-container{
            margin: 0 100px;
            position: relative;
        }

        .review-track{
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }


        .review-item{
            padding: 20px;
            /* box-shadow: 0 6px 14px rgba(0,0,0,.2); */
            outline: 1px solid var(--logo-color);
            border-radius: 12px;
            background-color: white;
        }

        .review-item p{
            font-weight: lighter;
        }

        /* REVIEW CONTENT */
        .review-item-header{
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .header-profile{
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-profile img{
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }

        .name-rating p{
            margin: 0;
            font-weight: bold;
            color: var(--logo-color);
        }

        .name-rating .rating{
            font-size: small;
            color: gold;
        }

        .social-media img{
            width: 25px;
            height: 25px;
            filter: invert(0.5);
        }

        /* ================= RESPONSIVE REVIEW ================= */

        /* Tablet */
        @media (max-width: 1024px) {
            .review-container {
                margin: 0 40px;
            }

            .review-track {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Mobile */
        @media (max-width: 768px) {
            .review-container {
                margin: 0 20px;
            }

            .review-track {
                grid-template-columns: 1fr;
            }

            .review-item {
                padding: 18px;
            }

            .header-profile img {
                width: 42px;
                height: 42px;
            }
        }

        /* Small Mobile */
        @media (max-width: 480px) {
            .review-container {
                margin: 0 16px;
            }

            .review-item p {
                font-size: 14px;
                line-height: 1.5;
            }

            .name-rating p {
                font-size: 14px;
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
            Testimoni
        </h2>
    </div>


    <div class="review-container">
        <div class="review-track">

            <div class="review-item">
                <div class="review-item-header">
                    <div class="header-profile">
                        <img src="<?= BASE_URL ?>/assets/img/orang.png">
                        <div class="name-rating">
                            <p>Viki Review 1</p>
                            <span class="rating">⭐⭐⭐⭐⭐</span>
                        </div>
                    </div>
                    <div class="social-media">
                        <img src="<?= BASE_URL ?>/assets/svg/instagram.svg">
                    </div>
                </div>
                <p>
                    Lorem ipsum dolor sit amet consectetur adipiscing elit. Quisque faucibus ex sapien vitae pellentesque sem placerat. In id cursus mi pretium tellus duis convallis. Tempus leo eu aenean sed diam urna tempor. Pulvinar vivamus fringilla lacus nec metus bibendum egestas. Iaculis massa nisl malesuada lacinia integer nunc posuere. Ut hendrerit semper vel class aptent taciti sociosqu. Ad litora torquent per conubia nostra inceptos himenaeos.
                </p>
            </div>

            <div class="review-item">
                <div class="review-item-header">
                    <div class="header-profile">
                        <img src="<?= BASE_URL ?>/assets/img/orang.png">
                        <div class="name-rating">
                            <p>Viki Review 2</p>
                            <span class="rating">⭐⭐⭐⭐⭐</span>
                        </div>
                    </div>
                    <div class="social-media">
                        <img src="<?= BASE_URL ?>/assets/svg/instagram.svg">
                    </div>
                </div>
                <p>
                    Lorem ipsum dolor sit amet consectetur adipiscing elit. Quisque faucibus ex sapien vitae pellentesque sem placerat. In id cursus mi pretium tellus duis convallis. Tempus leo eu aenean sed diam urna tempor. Pulvinar vivamus fringilla lacus nec metus bibendum egestas. Iaculis massa nisl malesuada lacinia integer nunc posuere. Ut hendrerit semper vel class aptent taciti sociosqu. Ad litora torquent per conubia nostra inceptos himenaeos.
                </p>
            </div>

            <div class="review-item">
                <div class="review-item-header">
                    <div class="header-profile">
                        <img src="<?= BASE_URL ?>/assets/img/orang.png">
                        <div class="name-rating">
                            <p>Viki Review 3</p>
                            <span class="rating">⭐⭐⭐⭐⭐</span>
                        </div>
                    </div>
                    <div class="social-media">
                        <img src="<?= BASE_URL ?>/assets/svg/instagram.svg">
                    </div>
                </div>
                <p>
                    Lorem ipsum dolor sit amet consectetur adipiscing elit. Quisque faucibus ex sapien vitae pellentesque sem placerat. In id cursus mi pretium tellus duis convallis. Tempus leo eu aenean sed diam urna tempor. Pulvinar vivamus fringilla lacus nec metus bibendum egestas. Iaculis massa nisl malesuada lacinia integer nunc posuere. Ut hendrerit semper vel class aptent taciti sociosqu. Ad litora torquent per conubia nostra inceptos himenaeos.
                </p>
            </div>

            <div class="review-item">
                <div class="review-item-header">
                    <div class="header-profile">
                        <img src="<?= BASE_URL ?>/assets/img/orang.png">
                        <div class="name-rating">
                            <p>Viki Review 4</p>
                            <span class="rating">⭐⭐⭐⭐⭐</span>
                        </div>
                    </div>
                    <div class="social-media">
                        <img src="<?= BASE_URL ?>/assets/svg/instagram.svg">
                    </div>
                </div>
                <p>
                    Lorem ipsum dolor sit amet consectetur adipiscing elit. Quisque faucibus ex sapien vitae pellentesque sem placerat. In id cursus mi pretium tellus duis convallis. Tempus leo eu aenean sed diam urna tempor. Pulvinar vivamus fringilla lacus nec metus bibendum egestas. Iaculis massa nisl malesuada lacinia integer nunc posuere. Ut hendrerit semper vel class aptent taciti sociosqu. Ad litora torquent per conubia nostra inceptos himenaeos.
                </p>
            </div>

            <div class="review-item">
                <div class="review-item-header">
                    <div class="header-profile">
                        <img src="<?= BASE_URL ?>/assets/img/orang.png">
                        <div class="name-rating">
                            <p>Viki Review 2</p>
                            <span class="rating">⭐⭐⭐⭐⭐</span>
                        </div>
                    </div>
                    <div class="social-media">
                        <img src="<?= BASE_URL ?>/assets/svg/instagram.svg">
                    </div>
                </div>
                <p>
                    Lorem ipsum dolor sit amet consectetur adipiscing elit. Quisque faucibus ex sapien vitae pellentesque sem placerat. In id cursus mi pretium tellus duis convallis. Tempus leo eu aenean sed diam urna tempor. Pulvinar vivamus fringilla lacus nec metus bibendum egestas. Iaculis massa nisl malesuada lacinia integer nunc posuere. Ut hendrerit semper vel class aptent taciti sociosqu. Ad litora torquent per conubia nostra inceptos himenaeos.
                </p>
            </div>

        </div>
    </div>

</div>

<?php require_once BASE_PATH . '/elements/footer.php' ?>

</body> 
</html>
