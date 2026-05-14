<style>
    footer {
        width: 100%;
        position: relative;
        bottom: 0;
        left: 0;
        margin-bottom: 0;
        margin-top: 50px;
        z-index: 1000;
    }

    .footer-up {
        width: 100%;
        background-color: var(--text-color);
        padding: 10px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        bottom: 0;
        color: white;
    }

    .footer-up .left {
        width: 40%;
    }

    .footer-up .left .logo-icon{
        height: 40px;
        display: block;
    }
    
    .footer-up .left .icon{
        height: 25px;
        filter: brightness(0) saturate(100%) invert(49%) sepia(78%) saturate(3671%) hue-rotate(360deg) brightness(101%) contrast(107%);
    }

    .footer-up .left .address{
        margin-top: 10px;
        display: flex;
        align-items: center;
    }

    .footer-up .left .text{
        margin-left: 10px;
        font-weight: normal;
        font-size: small;
    }

    .footer-up .left .address .address-text{
        margin-left: 10px;
    }

    .footer-up .left .address .address-text .address-title{
        margin-left: 10px;
        font-weight: bold;
        font-size: medium;
    }

    .footer-up .left .social-media{
        display: flex;
        margin-top: 10px;
        justify-content: start;
        gap: 20px;
    }

    .footer-up .left .social-media .whatsapp,
    .footer-up .left .social-media .gmail{
        display: flex;
        align-items: center;
    }

    .footer-up .right{
        display: flex;
        align-items: center;
        justify-content: space-evenly;
    }

    .footer-up .right a{
        color: white;
        text-decoration: none;
        margin-left: 20px;
        align-items: center;
        font-weight: normal;
        font-size: small;
    }

    .footer-up .right a .icon-parent{
        width: 70px;
        height: 70px;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        margin: auto;
        margin-bottom: 10px;
    }

    .footer-up .right a .icon-parent .parent{
        position: absolute;
        width: 100%;
        height: 100%;
    }

    .footer-up .right a .icon-parent .icon{
        width: 22px;
        height: 22px;
        z-index: 2;
        filter: invert(1);
    }

    footer .footer-down {
        display: flex;
        background-color: var(--logo-color);
        color: white;
        padding: 10px 30px;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.1);
        z-index: 1000;
    }

    footer .footer-down .left {
        font-size: 14px;
    }

    footer .footer-down .right {
        font-size: 14px;
        display: flex;
        align-items: center;
    }

    footer .footer-down .right a {
        color: white;
        text-decoration: none;
        margin-left: 10px;
    }

    footer .footer-down .right a img {
        filter: invert(1);
        height: 24px;
        vertical-align: middle;
    }

    @media (max-width: 992px) {
        .footer-up {
            flex-direction: column;
            align-items: flex-start;
            gap: 30px;
        }

        .footer-up .left,
        .footer-up .right {
            width: 100%;
        }

        .footer-up .right {
            justify-content: space-between;
        }
    }

    @media (max-width: 576px) {
        footer {
            margin-top: 30px;
        }

        .footer-up {
            padding: 20px;
        }

        .footer-up .left .address,
        .footer-up .left .whatsapp,
        .footer-up .left .gmail {
            align-items: flex-start;
        }

        .footer-up .left .text {
            font-size: 13px;
            line-height: 1.4;
        }

        .footer-up .left .address-text .address-title {
            margin-left: 0;
            font-size: 14px;
        }

        .footer-up .left .address-text {
            margin-left: 5px;
        }

        /* ICON MENU BAWAH */
        .footer-up .right {
            
            gap: 20px;
            justify-content: center;
            text-align: center;
        }

        .footer-up .right a {
            margin-left: 0;
            width: 30%;
            font-size: 12px;
        }

        .footer-up .right a .icon-parent {
            width: 55px;
            height: 55px;
        }

        .footer-up .right a .icon-parent .icon {
            width: 18px;
            height: 18px;
        }

        /* FOOTER DOWN */
        footer .footer-down {
            flex-direction: column;
            gap: 10px;
            text-align: center;
        }

        footer .footer-down .right {
            flex-wrap: wrap;
            justify-content: center;
        }
    }


</style>
<footer>
    <div class="footer-up">
        <div class="left">
            <img class="logo-icon" src="<?=  BASE_URL; ?>/assets/img/medina.png" alt="Medina Insan Qur'ani">
            <div class="address">
                <img class="address-icon icon" src="<?=  BASE_URL; ?>/assets/svg/address.svg">
                <div class="address-text">
                    <div class="address-title">Address</div>
                    <div class="text">
                        Jl. Babakan Sari No.11, Pasir Biru, Kec. Cibiru, Kota Bandung, Jawa Barat 40615
                    </div>
                </div>
            </div>
            <div class="social-media">
                <div class="whatsapp">
                    <img class="whatsapp-icon icon" src="<?=  BASE_URL; ?>/assets/svg/whatsapp.svg">
                    <div class="text">+62 8179234808</div>
                </div>
                <div class="gmail">
                    <img class="gmail-icon icon" src="<?=  BASE_URL; ?>/assets/svg/gmail.svg">
                    <div class="text">
                        medinainsanquarni@gmail.com
                    </div>
                </div>
            </div>
        </div>
        <div class="right">
            <a class="curriculum" href="#">
                <div class="icon-parent">
                    <img class="parent" src="<?=  BASE_URL; ?>/assets/svg/circle-footer.svg">
                    <img class="icon" src="<?=  BASE_URL; ?>/assets/svg/file.svg">
        
                </div>
                Struktur Kurikulum
            </a>
            <a class="profile" href="#">
                <div class="icon-parent">
                    <img class="parent" src="<?=  BASE_URL; ?>/assets/svg/circle-footer.svg">
                    <img class="icon" src="<?=  BASE_URL; ?>/assets/svg/cloud.svg">
                </div>
                Download Profil
            </a>
            <a class="brosur" href="#">
                <div class="icon-parent">
                    <img class="parent" src="<?=  BASE_URL; ?>/assets/svg/circle-footer.svg">
                    <img class="icon" src="<?=  BASE_URL; ?>/assets/svg/download.svg">
        
                </div>
                Download Brosur
            </a>
        </div>
    </div>
    <div class="footer-down">
        <div class="left">
            &copy; <?php echo date('Y'); ?> Medina Insan Qur'ani. All rights reserved.
        </div>
        <div class="right">
            Follow us 
            <a href="https://www.facebook.com/p/Medina-Insan-Qurani-100071792521263/" target="blank">
                <img src="<?=  BASE_URL; ?>/assets/svg/facebook.svg" alt="Facebook">
            </a>
            <a href="https://www.instagram.com/medinainsanqurani" target="_blank">
                <img src="<?=  BASE_URL; ?>/assets/svg/instagram.svg" alt="Instagram">
            </a>
            <a href="https://www.tiktok.com/@medinainsanqurani_" target="_blank">
                <img src="<?=  BASE_URL; ?>/assets/svg/tiktok.svg" alt="Tiktok">
            </a>
            <a href="#">
                <img src="<?=  BASE_URL; ?>/assets/svg/x.svg" alt="X">
            </a>
            <a href="https://www.youtube.com/@medinatv9006" target="_blank">
                <img src="<?=  BASE_URL; ?>/assets/svg/youtube.svg" alt="Youtube">
            </a>
            <a href="https://wa.me/+628179234808" target="_blank">
                <img src="<?=  BASE_URL; ?>/assets/svg/whatsapp.svg" alt="Whatsapp">
            </a>
        </div>
    </div>
</footer>