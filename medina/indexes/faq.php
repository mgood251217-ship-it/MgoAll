
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

        .faq-container{
            width: 80%;
            margin: 40px auto 80px;
        }

        .faq-item{
            background: #fff;
            border-radius: 10px;
            margin-bottom: 12px;
            overflow: hidden;
            box-shadow: 0 6px 18px rgba(0,0,0,.08);
            transition: box-shadow .3s ease;
        }

        .faq-item.active{
            box-shadow: 0 12px 28px rgba(0,0,0,.12);
        }

        .faq-question{
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 22px;
            cursor: pointer;
        }

        .faq-question h3{
            margin: 0;
            font-size: 18px;
            color: var(--text-color);
        }

        .faq-icon{
            width: 18px;
            height: 18px;
            position: relative;
        }

        .faq-icon::before,
        .faq-icon::after{
            content: '';
            position: absolute;
            background: var(--logo-color);
            transition: transform .35s ease;
        }

        .faq-icon::before{
            width: 100%;
            height: 2px;
            top: 50%;
            transform: translateY(-50%);
        }

        .faq-icon::after{
            height: 100%;
            width: 2px;
            left: 50%;
            transform: translateX(-50%);
        }

        .faq-item.active .faq-icon::after{
            transform: translateX(-50%) scaleY(0);
        }

        .faq-answer{
            max-height: 0;
            overflow: hidden;
            transition: max-height .5s ease, padding .3s ease;
            padding: 0 22px;
        }

        .faq-item.active .faq-answer{
            max-height: 300px;
            padding: 0 22px 20px;
        }

        .faq-answer p{
            margin: 0;
            font-size: 16px;
            color: #555;
            line-height: 1.7;
        }


    </style>
</head>

<body>
<?php require_once BASE_PATH . '/elements/navbar.php' ?>

<div class="content">
    <img class="banner-akreditasi" src="<?= BASE_URL ?>/assets/img/akreditasi.webp">
    <div class="title-body">
        <h2 class="title">
            Frequently Asked Questions
        </h2>
    </div>
    <div class="faq-container">
        <div class="faq-collapsibel">
            <div class="faq-item">
                <div class="faq-question">
                    <h3>Apa itu Medina Insan Qur'ani?</h3>
                    <div class="faq-icon"></div>
                </div>
                <div class="faq-answer">
                    <p>Medina Insan Qur'ani adalah sebuah lembaga pendidikan pesantren yang </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/elements/footer.php' ?>

<script>
document.querySelectorAll('.faq-question').forEach(question => {
    question.addEventListener('click', () => {
        const item = question.parentElement;

        // tutup yang lain (accordion style)
        document.querySelectorAll('.faq-item').forEach(faq => {
            if (faq !== item) faq.classList.remove('active');
        });

        item.classList.toggle('active');
    });
});
</script>


</body> 
</html>
