<?php

require_once 'config.php';

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Medina Insan Qur'ani</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/swiper.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/animate.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/index.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global-color.css">
</head>

<body class="bg-body overflow-hidden h-screen">
    <div class="overflow-y-scroll h-screen">

        <main class="pt-20 overflow-hidden bg-body">
    <?php require_once BASE_PATH . '/elements/navbar.php' ?>
    <section class="-mt-1 pt-8 bg-hiro w-full bg-no-repeat bg-cover bg-body" style="background-position: center 100%; background-image: url('https://medina.mgood.my.id/assets/img/hiro1.svg');">
        <h1 class="font-bold sm:mt-5 px-[5%] text-center text-2xl sm:text-4xl text-body">
            PONDOK PESANTREN MEDINA INSAN QURANI
        </h1>
        <p class="text-base tracking-widest px-[5%] text-center sm:text-xl lg:text-2xl mt-1 text-body">
            Ayo Mencetak Generasi Hafidz Hafidzoh, Qori Qoriah, Da'i Da'iah
        </p>
        <h2 class="font-bold sm:mt-5 px-[5%] text-center text-3xl sm:text-4xl text-body">
            Program Unggulan
        </h2>
        <div class="swiper swiper-card">
            <div class="swiper-wrapper">
                <div class="swiper-slide">

                    <div class="card bg1 relative overflow-hidden">
                        <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="al ashr al madani" class="bg-cover absolute top-[-30px] right-[-30px] w-[60%] " />
                        <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="" class="bg-cover absolute bottom-[-30px] left-[-30px] w-[60%]" />
                        <div class="card__image w-[80%] h-[80%] p-1 overflow-hidden relative z-10">
                            <img src="https://medina.mgood.my.id/assets/img/people1.png" alt="takhosus sma smp al ashr al madani" loading="lazy" class="w-full object-cover" />
                        </div>

                        <button class="card__content flex items-center flex-col -mt-12 bg4 w-[90%] p-2 rounded-xl relative z-10">
                            <a href="https://medina.mgood.my.id/takhassus">
                            <h3 class="card__title text-2xl font-bold text-body">BEASISWA</h3>
                            <p class="card__text text-body">Program Takhassus</p>
                            </a>
                        </button>
                    </div>

                </div>
                <div class="swiper-slide">

                    <div class="card bg2 overflow-hidden relative">
                        <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="" class="bg-cover absolute top-[-30px] right-[-30px]  w-[60%] rotate-90 " />
                        <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="" class="bg-cover absolute bottom-[-30px] left-[-30px] w-[60%] rotate-90" />
                        <div class="card__image w-[80%] h-[80%] p-1 overflow-hidden relative z-10">
                            <img src="https://medina.mgood.my.id/assets/img/people2.png" alt="SMP Al Ashr Al Madani" loading="lazy" class="w-full object-cover" />
                        </div>

                        <button class="card__content flex items-center flex-col -mt-12 bg5 w-[90%] p-2 rounded-xl relative z-10">
                            <a href="https://medina.mgood.my.id/smp">
                            <h3 class="card__title text-2xl font-bold text-body">SMP Plus</h3>
                            <p class="card__text text-center text-[1.1rem] font-normal text-body">Program 3 Tahun</p>
                            </a>
                        </button>
                    </div>

                </div>

                <div class="swiper-slide">

                    <div class="card bg3 overflow-hidden relative">
                        <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="" class="bg-cover absolute top-[-30px] right-[-30px] w-[60%] " />
                        <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="" class="bg-cover absolute bottom-[-30px] left-[-30px] w-[60%] " />

                        <div class="card__image w-[80%] h-[80%] p-1 overflow-hidden relative z-10">
                            <img src="https://medina.mgood.my.id/assets/img/people3.png" alt="SMA Al Ashr Al Madani" loading="lazy" class="w-full object-cover" />
                        </div>

                        <button class="card__content flex items-center flex-col -mt-12 bg6 w-[90%] p-2 rounded-xl relative z-10">
                            <a href="https://medina.mgood.my.id/takhassus">
                            <h3 class="card__title text-2xl font-bold text-body"><p class="text-xl inline">SMA </p>Takhassus</h3>
                            <p class="card__text text-body">Program SMA</p>
                            </a>
                        </button>
                    </div>

                </div>
            </div>
            <div class="lg:ml-80 md:ml-2 ml-1 prev swiper-button-prev w-8 h-8 sm:w-12 sm:h-12 rounded-full bg-second-blue p-1 top-[10rem] lg:top-32 max-md:hidden">
                <img src="https://medina.mgood.my.id/assets/img/arrow-left.svg" alt="left" class="invert hue-rotate-[160deg] contrast-200">
            </div>
            <div class="lg:mr-80 md:mr-2 mr-1 next swiper-button-next w-8 h-8 sm:w-12 sm:h-12 rounded-full bg-second-blue p-1 top-[10rem] lg:top-32 max-md:hidden">
                <img src="https://medina.mgood.my.id/assets/img/arrow-right.svg" alt="right" class="invert hue-rotate-[160deg] contrast-200">
            </div>
        </div>
    </section>    <section class="container-wrapper w-full pb-12 bg-body">
    <h2 class="font-bold text-center text-2xl py-6 sm:text-4xl text-dark-font">
        Seputar Pesantren
    </h2>
    <div class="wrapper-content w-[90%] mx-auto grid grid-cols-3 md:flex flex-rows justify-center items-center gap-[2em] lg:gap-12 sm:gap-[2em] md:gap-5">
        <div class="flex flex-col justify-bottom items-center gap-2 md:gap-1  ">
        <a href="https://medina.mgood.my.id/indexes/literasi_sekolah" class="flex flex-col justify-bottom items-center gap-2 md:gap-1">
        <div class="card-seputar-pesantren items-center justify-center w-[80px] h-[70px] sm:w-[100px] sm:h-[90px] md:w-[80px] md:h-[70px] lg:w-[100px] lg:h-[90px] bg-white flex flex-col p-4 rounded-xl main-shadow">
            <img src="https://medina.mgood.my.id/assets/img/ppdb.svg" alt="PPDB">
        </div>
        <p class=" text-sm sm:text-base md:text-sm sm:font-medium text-center font-medium text-dark-font">PPDB</p>
        </a>
        </div>
        <div>
        <a href="https://medina.mgood.my.id/indexes/event_gallery" class="flex flex-col justify-bottom items-center gap-2 md:gap-1">
            <div class="card-seputar-pesantren items-center justify-center w-[80px] h-[70px] sm:w-[100px] sm:h-[90px] md:w-[80px] md:h-[70px] lg:w-[100px] lg:h-[90px] bg-white flex flex-col p-4 rounded-xl main-shadow">
            <img src="https://medina.mgood.my.id/assets/img/gallery.svg" alt="Image Gallery">
            </div>
            <p class=" text-sm sm:text-base md:text-sm sm:font-medium text-center font-medium text-dark-font">Image Gallery</p>
        </a>
        </div>
        <div class="flex flex-col justify-bottom items-center gap-2 md:gap-1">
        <a href="https://medina.mgood.my.id/indexes/daily_activity" class="flex flex-col justify-bottom items-center gap-2 md:gap-1">
            <div class="card-seputar-pesantren items-center justify-center w-[80px] h-[70px] sm:w-[100px] sm:h-[90px] md:w-[80px] md:h-[70px] lg:w-[100px] lg:h-[90px] bg-white flex flex-col p-4 rounded-xl main-shadow">
            <img src="https://medina.mgood.my.id/assets/img/list.svg" alt="Kegiatan">
            </div>
            <p class=" text-sm sm:text-base md:text-sm sm:font-medium  text-center font-medium text-dark-font">Kegiatan</p>
        </a>
        </div>
        <div>
        <a href="https://medina.mgood.my.id/indexes/informasi_biaya" class="flex flex-col justify-bottom items-center gap-2 md:gap-1">
            <div class="card-seputar-pesantren items-center justify-center w-[80px] h-[70px] sm:w-[100px] sm:h-[90px] md:w-[80px] md:h-[70px] lg:w-[100px] lg:h-[90px] bg-white flex flex-col p-4 rounded-xl main-shadow">
            <img src="https://medina.mgood.my.id/assets/img/money.svg" alt="Biaya">
            </div>
            <p class=" text-sm sm:text-base md:text-sm sm:font-medium  text-center font-medium text-dark-font">Infaq Pendidikan</p>
        </a>
        </div>
        <div>
        <a href="https://medina.mgood.my.id/indexes/informasi_pendaftaran" class="flex flex-col justify-bottom items-center gap-2 md:gap-1">
            <div class="card-seputar-pesantren items-center justify-center w-[80px] h-[70px] sm:w-[100px] sm:h-[90px] md:w-[80px] md:h-[70px] lg:w-[100px] lg:h-[90px] bg-white flex flex-col p-4 rounded-xl main-shadow">
            <img src="https://medina.mgood.my.id/assets/img/question.svg" alt="Daftar">
            </div>
            <p class=" text-sm sm:text-base md:text-sm sm:font-medium  text-center font-medium text-dark-font">Daftar</p>
        </a>
        </div>
    </div>
    </section>    <section class="container-extracurricular bg-white" style="background-image: url('https://medina.mgood.my.id/assets/img/grayvector.svg');">
        <div class="w-full h-full py-20 items-center flex justify-center">
            <div class="w-[90%] lg:w-[70%] shadow-2xl overflow-hidden rounded-lg">
                <div class="w-full bg5 py-10 px-5">
                    <h2 class="text-white text-center font-bold text-xl lg:text-4xl">Sekilas Tentang PP Medina Insan Qurani</h2>
                </div>
                <div class="bg-white p-10 text-base lg:text-xl">
                    <p>
Pesantren Tahfidz Qur’an dari Medina Insan Qurani merupakan lembaga pendidikan Islam yang berfokus pada pembinaan generasi penghafal Al-Qur’an (hafidz dan hafidzah) dengan kualitas hafalan yang kuat, pemahaman yang baik, serta akhlak yang mulia.
<br><br>
Pesantren ini mengintegrasikan program tahfidz dengan pendidikan diniyah dan pembentukan karakter Islami. Para santri dibimbing untuk menghafal Al-Qur’an secara bertahap dengan metode yang terstruktur, mulai dari tahsin (perbaikan bacaan), ziyadah (penambahan hafalan), hingga muraja’ah (pengulangan hafalan) agar hafalan tetap terjaga dengan baik.
                    </p>
                </div>
            </div>
        </div>
    </section>    <section class="text-old-blue  w-full  py-12" id="fasilitas" style="background-image: url('https://medina.mgood.my.id/assets/img/backvector-cardberita.svg');">
        <h2 class="font-bold text-4xl px-[5%] text-center">Fasilitas</h2>
        <p class="text-base px-[5%] tracking-widest text-center lg:text-2xl mt-1 sm:text-xl">
        PP Medina Insan Qurani
        </p>
        <div class="media_grid grid-cols-2 lg:grid-cols-3 gap-2 p-10 hidden md:grid">
            <img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="masjid pondok pesantren Medina Insan Qurani" class="rounded-lg">
            <img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="masjid pondok pesantren Medina Insan Qurani" class="rounded-lg">
            <img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="masjid pondok pesantren Medina Insan Qurani" class="rounded-lg">
            <img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="masjid pondok pesantren Medina Insan Qurani" class="rounded-lg">
            <img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="masjid pondok pesantren Medina Insan Qurani" class="rounded-lg">
            <img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="masjid pondok pesantren Medina Insan Qurani" class="rounded-lg">
        </div>

        <swiper-container class="mySwiper w-[80%] h-full md:hidden mt-5" effect="cards" grab-cursor="true" autoplay-delay="2000" autoplay-disable-on-interaction="false">
            <swiper-slide> <img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="masjid pondok pesantren Medina Insan Qurani" class="rounded-lg w-full object-cover"></swiper-slide>
            <swiper-slide> <img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="masjid pondok pesantren Medina Insan Qurani" class="rounded-lg w-full object-cover"></swiper-slide>
            <swiper-slide> <img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="masjid pondok pesantren Medina Insan Qurani" class="rounded-lg w-full object-cover"></swiper-slide>
            <swiper-slide> <img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="masjid pondok pesantren Medina Insan Qurani" class="rounded-lg w-full object-cover"></swiper-slide>
            <swiper-slide> <img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="masjid pondok pesantren Medina Insan Qurani" class="rounded-lg w-full object-cover"></swiper-slide>
            <swiper-slide> <img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="masjid pondok pesantren Medina Insan Qurani" class="rounded-lg w-full object-cover"></swiper-slide>
        </swiper-container>
    <button class="px-10 py-2 bg-old-blue text-body rounded-full mx-auto block mt-5">
            <a href="https://medina.mgood.my.id/indexes/fasilitas" class="text-xl">
            Fasilitas Lainnya
            </a>
        </button>
    </section>    <section id="wrapper-menu-popup" class="transition duration-[0.1s] ease-in translate-y-0 hidden bg-black/50 w-full h-full fixed top-0 left-0 z-[9999]">
    <div class="menu-popup bg-body w-full h-[70%] translate-y-[300px] delay-[0.3s] transition duration-700 ease-in absolute bottom-0 rounded-t-[50px] ">
        <button class="close absolute block w-16 h-16 rounded-lg border-none -top-11 right-8  lg:right-40"><img src="https://medina.mgood.my.id/assets/img/icons/close.svg" class=" w-full justify-end block text-main" alt="close"></button>
        <div class="wrapper-content w-[90%] lg:w-[700px] relative mx-auto grid grid-cols-3 md:flex flex-wrap flex-row justify-start items-center gap-2 sm:gap-6 md:gap-12 gap-y-8 top-12">
        <div class="flex flex-col justify-bottom items-center gap-2 md:gap-1">
            <a href="https://ppdb.pptqam.ponpes.id/">
            <div class="card-seputar-pesantren items-center justify-center w-[80px] h-[70px] sm:w-[100px] sm:h-[90px] md:w-[80px] md:h-[70px] lg:w-[100px] lg:h-[90px] bg-white flex flex-col p-4 rounded-xl main-shadow">
                <img src="https://medina.mgood.my.id/assets/img/icons/ppdb.svg" alt="ppdb">
            </div>
            <p class="text-sm sm:text-base md:text-sm sm:font-medium  text-center font-medium text-dark-font">PPDB</p>
            </a>
        </div>
        <div>
            <a href="https://medina.mgood.my.id/gallery-pesantren/" class="flex flex-col justify-bottom items-center gap-2 md:gap-1">
            <div class="card-seputar-pesantren items-center justify-center w-[80px] h-[70px] sm:w-[100px] sm:h-[90px] md:w-[80px] md:h-[70px] lg:w-[100px] lg:h-[90px] bg-white flex flex-col p-4 rounded-xl main-shadow">
                <img src="https://medina.mgood.my.id/assets/img/icons/image.svg" alt="image">
            </div>
            <p class="text-sm sm:text-base md:text-sm sm:font-medium  text-center font-medium text-dark-font">Image Gallery</p>
            </a>
        </div>
        <div>
            <a href="https://medina.mgood.my.id/kegiatan/" class="flex flex-col justify-bottom items-center gap-2 md:gap-1">
            <div class="card-seputar-pesantren items-center justify-center w-[80px] h-[70px] sm:w-[100px] sm:h-[90px] md:w-[80px] md:h-[70px] lg:w-[100px] lg:h-[90px] bg-white flex flex-col p-4 rounded-xl main-shadow">
            <img src="https://medina.mgood.my.id/assets/img/icons/kegiatan.svg" alt="kegiatan">
            </div>
            <p class=" text-sm sm:text-base md:text-sm sm:font-medium  text-center font-medium text-dark-font">Kegiatan</p>
        </a>
        </div>
        <div class="flex flex-col justify-bottom items-center gap-2 md:gap-1">
            <a href="https://medina.mgood.my.id/indexes/biaya" class="flex flex-col justify-bottom items-center gap-2 md:gap-1">
            <div class="card-seputar-pesantren items-center justify-center w-[80px] h-[70px] sm:w-[100px] sm:h-[90px] md:w-[80px] md:h-[70px] lg:w-[100px] lg:h-[90px] bg-white flex flex-col p-4 rounded-xl main-shadow">
                <img src="https://medina.mgood.my.id/assets/img/icons/biaya.svg" alt="biaya">
            </div>
            <p class="text-sm sm:text-base md:text-sm sm:font-medium  text-center font-medium text-dark-font">Infaq Pendidikan</p>
            </a>
        </div>
        <div class="flex flex-col justify-bottom items-center gap-2 md:gap-1 sm:gap-4">
            <a href="https://medina.mgood.my.id/indexes/daftar">
            <div class="card-seputar-pesantren items-center justify-center w-[80px] h-[70px] sm:w-[100px] sm:h-[90px] md:w-[80px] md:h-[70px] lg:w-[100px] lg:h-[90px] bg-white flex flex-col p-4 rounded-xl main-shadow">
                <img src="https://medina.mgood.my.id/assets/img/icons/daftar.svg" alt="daftar">
            </div>
            <p class="text-sm sm:text-base md:text-sm sm:font-medium  text-center font-medium text-dark-font">Daftar</p>
            </a>
        </div>
        <div class="flex flex-col justify-bottom items-center gap-2 md:gap-1 sm:gap-4">
            <a href="https://medina.mgood.my.id/brosur-pptqam/">
            <div class="card-seputar-pesantren items-center justify-center w-[80px] h-[70px] sm:w-[100px] sm:h-[90px] md:w-[80px] md:h-[70px] lg:w-[100px] lg:h-[90px] bg-white flex flex-col p-4 rounded-xl main-shadow">
                <img src="https://medina.mgood.my.id/assets/img/icons/download.svg" alt="download brosur">
            </div>
            <p class="text-sm sm:text-base md:text-sm sm:font-medium  text-center font-medium text-dark-font">Brosur</p>
            </a>
        </div>
        <div class="flex flex-col justify-bottom items-center gap-2 md:gap-1 sm:gap-4">
            <a href="https://medina.mgood.my.id/fasilitas/">
            <div class="card-seputar-pesantren items-center justify-center w-[80px] h-[70px] sm:w-[100px] sm:h-[90px] md:w-[80px] md:h-[70px] lg:w-[100px] lg:h-[90px] bg-white flex flex-col p-4 rounded-xl main-shadow">
                <img src="https://medina.mgood.my.id/assets/img/icons/fasilitas-icon.svg" alt="fasilitas PP Medina Insan Qurani">
            </div>
            <p class="text-sm sm:text-base md:text-sm sm:font-medium  text-center font-medium text-dark-font">Fasilitas</p>
            </a>
        </div>
        </div>
    </div>
    </section>
        
    <section class="container-extracurricular p-5 bg-white" style="background-image: url('https://medina.mgood.my.id/assets/img/grayvector.svg');">
    <div class="title text-center">
        <h2 class="font-bold text-4xl px-[5%] text-center">Ekstrakurikuler</h2>
        <p class="text-base tracking-widest px-[5%] text-center sm:text-xl lg:text-2xl mt-1 text-main-green">Tingkatkan Skill Anda Dengan Kami</p>
    </div>

    <swiper-container class="mySwiper display-extracurricular w-[85%] h-[200px] sm:w-[70%] sm:h-[280px] md:hidden" effect="cards" grab-cursor="true" autoplay-delay="2000" autoplay-disable-on-interaction="false">
            <swiper-slide style="background-color: #77B341;" class=" overflow-hidden">
            <div>
            <img class="relative z-10 bg-cover h-[200px] w-full object-cover" src="https://medina.mgood.my.id/assets/img/pencaksilat.png" alt="Badewa" loading="lazy">
            <div class="title-extracurricular flex flex-col justify-center  z-10 absolute bottom-0 left-0 h-[40px] w-full text-center items-center sm:h-[60px]" style="background-color: #F48120;">
                <h3 class="m-auto text-white">Badewa</h3>
            </div>
            <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="al ashr al madani" class="bg-cover absolute top-[-25px] right-[-30px] w-[40%] " />
            <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="" class="bg-cover absolute bottom-[10px] left-[-10px] w-[40%]" />
            </div>
        </swiper-slide>
            <swiper-slide style="background-color: #B1D136;" class=" overflow-hidden">
            <div>
            <img class="relative z-10 bg-cover h-[200px] w-full object-cover" src="https://medina.mgood.my.id/assets/img/memanah.png" alt="Panahan" loading="lazy">
            <div class="title-extracurricular flex flex-col justify-center  z-10 absolute bottom-0 left-0 h-[40px] w-full text-center items-center sm:h-[60px]" style="background-color: #51218E;">
                <h3 class="m-auto text-white">Panahan</h3>
            </div>
            <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="al ashr al madani" class="bg-cover absolute top-[-25px] right-[-30px] w-[40%] " />
            <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="" class="bg-cover absolute bottom-[10px] left-[-10px] w-[40%]" />
            </div>
        </swiper-slide>
            <swiper-slide style="background-color: #51218E;" class=" overflow-hidden">
            <div>
            <img class="relative z-10 bg-cover h-[200px] w-full object-cover" src="https://medina.mgood.my.id/assets/img/marawis.png" alt="Marawis" loading="lazy">
            <div class="title-extracurricular flex flex-col justify-center  z-10 absolute bottom-0 left-0 h-[40px] w-full text-center items-center sm:h-[60px]" style="background-color: #B1D136;">
                <h3 class="m-auto text-white">Marawis</h3>
            </div>
            <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="al ashr al madani" class="bg-cover absolute top-[-25px] right-[-30px] w-[40%] " />
            <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="" class="bg-cover absolute bottom-[10px] left-[-10px] w-[40%]" />
            </div>
        </swiper-slide>
            <swiper-slide style="background-color: #F48120;" class=" overflow-hidden">
            <div>
            <img class="relative z-10 bg-cover h-[200px] w-full object-cover" src="https://medina.mgood.my.id/assets/img/futsal.png" alt="Futsal" loading="lazy">
            <div class="title-extracurricular flex flex-col justify-center  z-10 absolute bottom-0 left-0 h-[40px] w-full text-center items-center sm:h-[60px]" style="background-color: #77B341;">
                <h3 class="m-auto text-white">Futsal</h3>
            </div>
            <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="al ashr al madani" class="bg-cover absolute top-[-25px] right-[-30px] w-[40%] " />
            <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="" class="bg-cover absolute bottom-[10px] left-[-10px] w-[40%]" />
            </div>
        </swiper-slide>
            <swiper-slide style="background-color: #FFBC00;" class=" overflow-hidden">
            <div>
            <img class="relative z-10 bg-cover h-[200px] w-full object-cover" src="https://medina.mgood.my.id/assets/img/pramukanew.png" alt="Pramuka (Paskibra)" loading="lazy">
            <div class="title-extracurricular flex flex-col justify-center  z-10 absolute bottom-0 left-0 h-[40px] w-full text-center items-center sm:h-[60px]" style="background-color: #0397C9;">
                <h3 class="m-auto text-white">Pramuka (Paskibra)</h3>
            </div>
            <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="al ashr al madani" class="bg-cover absolute top-[-25px] right-[-30px] w-[40%] " />
            <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="" class="bg-cover absolute bottom-[10px] left-[-10px] w-[40%]" />
            </div>
        </swiper-slide>
            <swiper-slide style="background-color: #0397C9;" class=" overflow-hidden">
            <div>
            <img class="relative z-10 bg-cover h-[200px] w-full object-cover" src="https://medina.mgood.my.id/assets/img/hadrohnew.png" alt="Hadroh" loading="lazy">
            <div class="title-extracurricular flex flex-col justify-center  z-10 absolute bottom-0 left-0 h-[40px] w-full text-center items-center sm:h-[60px]" style="background-color: #FFBC00;">
                <h3 class="m-auto text-white">Hadroh</h3>
            </div>
            <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="al ashr al madani" class="bg-cover absolute top-[-25px] right-[-30px] w-[40%] " />
            <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="" class="bg-cover absolute bottom-[10px] left-[-10px] w-[40%]" />
            </div>
        </swiper-slide>
        </swiper-container>
    <div id="extracurricular-container">
        <div class="container-card-extracurricular m-auto">
                <div class="card-extracurricular" effect="cards">
            <swiper-slide class="overflow-hidden bg1">
                <div>
                <img class="relative z-10 h-[210px] bg-bottom w-full object-cover" src="https://medina.mgood.my.id/assets/img/silat.png" alt="Bela Diri" loading="lazy">
                <div class="title-extracurricular flex flex-col justify-center items-center z-10 absolute bottom-0 left-0 h-[50px] w-full text-center bg2">
                    <h3 class="m-auto text-white">Bela Diri</h3>
                </div>
                <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="al ashr al madani" class="bg-cover absolute top-[-70px] right-[-70px] w-[50%] " />
                <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="" class="bg-cover absolute bottom-[-40px] left-[-30px] w-[50%]" />
                </div>
            </swiper-slide>
            </div>
                <div class="card-extracurricular" effect="cards">
            <swiper-slide class="overflow-hidden bg2">
                <div>
                <img class="relative z-10 h-[210px] bg-bottom w-full object-cover" src="https://medina.mgood.my.id/assets/img/memanah1.png" alt="Panahan" loading="lazy">
                <div class="title-extracurricular flex flex-col justify-center items-center z-10 absolute bottom-0 left-0 h-[50px] w-full text-center bg3">
                    <h3 class="m-auto text-white">Panahan</h3>
                </div>
                <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="al ashr al madani" class="bg-cover absolute top-[-70px] right-[-70px] w-[50%] " />
                <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="" class="bg-cover absolute bottom-[-40px] left-[-30px] w-[50%]" />
                </div>
            </swiper-slide>
            </div>
                <div class="card-extracurricular" effect="cards">
            <swiper-slide class="overflow-hidden bg3">
                <div>
                <img class="relative z-10 h-[210px] bg-bottom w-full object-cover" src="https://medina.mgood.my.id/assets/img/marawis.png" alt="Marawis" loading="lazy">
                <div class="title-extracurricular flex flex-col justify-center items-center z-10 absolute bottom-0 left-0 h-[50px] w-full text-center bg4">
                    <h3 class="m-auto text-white">Marawis</h3>
                </div>
                <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="al ashr al madani" class="bg-cover absolute top-[-70px] right-[-70px] w-[50%] " />
                <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="" class="bg-cover absolute bottom-[-40px] left-[-30px] w-[50%]" />
                </div>
            </swiper-slide>
            </div>
                <div class="card-extracurricular" effect="cards">
            <swiper-slide class="overflow-hidden bg4">
                <div>
                <img class="relative z-10 h-[210px] bg-bottom w-full object-cover" src="https://medina.mgood.my.id/assets/img/futsal.png" alt="Futsal" loading="lazy">
                <div class="title-extracurricular flex flex-col justify-center items-center z-10 absolute bottom-0 left-0 h-[50px] w-full text-center bg5">
                    <h3 class="m-auto text-white">Futsal</h3>
                </div>
                <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="al ashr al madani" class="bg-cover absolute top-[-70px] right-[-70px] w-[50%] " />
                <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="" class="bg-cover absolute bottom-[-40px] left-[-30px] w-[50%]" />
                </div>
            </swiper-slide>
            </div>
                <div class="card-extracurricular" effect="cards">
            <swiper-slide class="overflow-hidden bg5">
                <div>
                <img class="relative z-10 h-[210px] bg-bottom w-full object-cover" src="https://medina.mgood.my.id/assets/img/pramukanew.png" alt="Pramuka (Paskibra)" loading="lazy">
                <div class="title-extracurricular flex flex-col justify-center items-center z-10 absolute bottom-0 left-0 h-[50px] w-full text-center bg6">
                    <h3 class="m-auto text-white">Pramuka (Paskibra)</h3>
                </div>
                <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="al ashr al madani" class="bg-cover absolute top-[-70px] right-[-70px] w-[50%] " />
                <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="" class="bg-cover absolute bottom-[-40px] left-[-30px] w-[50%]" />
                </div>
            </swiper-slide>
            </div>
                <div class="card-extracurricular" effect="cards">
            <swiper-slide class="overflow-hidden bg6">
                <div>
                <img class="relative z-10 h-[210px] bg-bottom w-full object-cover" src="https://medina.mgood.my.id/assets/img/hadrohnew.png" alt="Hadroh" loading="lazy">
                <div class="title-extracurricular flex flex-col justify-center items-center z-10 absolute bottom-0 left-0 h-[50px] w-full text-center bg1">
                    <h3 class="m-auto text-white">Hadroh</h3>
                </div>
                <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="al ashr al madani" class="bg-cover absolute top-[-70px] right-[-70px] w-[50%] " />
                <img src="https://medina.mgood.my.id/assets/img/bintang.png" alt="" class="bg-cover absolute bottom-[-40px] left-[-30px] w-[50%]" />
                </div>
            </swiper-slide>
            </div>
            </div>
        <div class="text-center mt-8 mb-10">
        <a href="javascript:void(0);" onclick="loadExtracurricular(2);" class="px-5 py-3 bg-main-green text-white rounded-full">
            Lainnya...
        </a>
        </div>
    </div>

    </section>    <section class="text-main-purple w-full py-12" id="berita" style="background-image: url('https://medina.mgood.my.id/assets/img/backvector-cardberita.svg');">
        <h2 class="font-bold text-4xl px-[5%] text-center">Berita Terbaru</h2>
        <p class="text-base px-[5%] tracking-widest text-center lg:text-2xl mt-1 sm:text-xl">
            Berita Seputar Kegiatan Santri/Siswa dan Pesantren
        </p>

        <div class="mt-10 sm:flex sm:flex-row sm:flex-wrap sm:justify-center sm:gap-10">
            <div class="swiper swiper-berita sm:hidden">
                <div class="swiper-wrapper">
                    <div class="swiper-slide mb-10">
                        <div class="w-[85%] mx-auto rounded-lg overflow-hidden shadow-lg h-80 bg-white">
                            <img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="Kajian Rutinan" class="w-full h-48 object-cover" />
                            <div class="p-5">
                                <h3 class="font-bold text-2xl truncate">Kajian Rutinan</h3>
                                <p class="text-base line-clamp-2">
                                    Pengajian rutinan Ibu-ibu membaca Alquran, mendalami Hadis, ilmu Tauhid, Akhlak dan Fiqh.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="swiper-slide mb-10">
                        <div class="w-[85%] mx-auto rounded-lg overflow-hidden shadow-lg h-80 bg-white">
                            <img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="Pemeriksaan Barang" class="w-full h-48 object-cover" />
                            <div class="p-5">
                                <h3 class="font-bold text-2xl truncate">Pemeriksaan Barang</h3>
                                <p class="text-base line-clamp-2">
                                    Pemeriksaan barang bawaan santri sebelum masuk pondok pesantren.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="swiper-pagination top-10"></div>

                <div class="swiper-button-prev">
                    <img src="https://medina.mgood.my.id/assets/img/arrow-left.svg" width="30" height="30" alt="left" />
                </div>
                <div class="swiper-button-next">
                    <img src="https://medina.mgood.my.id/assets/img/arrow-right.svg" width="30" height="30" alt="right" />
                </div>
            </div>
        </div>

        <div id="container-berita">
            <div class="mt-10 sm:flex sm:flex-row sm:flex-wrap sm:justify-center sm:gap-10">
                <div class="rounded-lg overflow-hidden sm:shadow-lg hidden sm:w-80 sm:block lg:w-[350px] h-80 bg-white">
                    <img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="Kajian Rutinan" class="w-full h-48 object-cover" />
                    <div class="p-5">
                        <h3 class="font-bold text-2xl truncate">Kajian Rutinan</h3>
                        <p class="text-base line-clamp-2">
                            Pengajian rutinan Ibu-ibu membaca Alquran, mendalami Hadis, ilmu Tauhid, Akhlak dan Fiqh.
                        </p>
                    </div>
                </div>
                <div class="rounded-lg overflow-hidden sm:shadow-lg hidden sm:w-80 sm:block lg:w-[350px] h-80 bg-white">
                    <img src="<?= BASE_URL ?>/assets/img/masjid.jpg" alt="Pemeriksaan Barang" class="w-full h-48 object-cover" />
                    <div class="p-5">
                        <h3 class="font-bold text-2xl truncate">Pemeriksaan Barang</h3>
                        <p class="text-base line-clamp-2">
                            Pemeriksaan barang bawaan santri sebelum masuk pondok pesantren.
                        </p>
                    </div>
                </div>
            </div>
            <div class="text-center mt-8 mb-10">
                <a href="javascript:void(0);" onclick="loadBerita(2);" class="px-5 py-3 bg-main-purple text-white rounded-full">
                    Cari berita lain...
                </a>
            </div>
        </div>
    </section>
<?php require_once BASE_PATH . '/elements/footer.php' ?>
</main>
</div>

    <script src="https://medina.mgood.my.id/assets/js/vendors.min.js"></script>
    <script src="https://medina.mgood.my.id/assets/js/card.js"></script>

<script>
        document.addEventListener('DOMContentLoaded', function() {
            if(document.querySelector('.swiper-fasilitas')) {
                new Swiper('.swiper-fasilitas', {
                    effect: 'cards',
                    grabCursor: true,
                    autoplay: { delay: 2500, disableOnInteraction: false }
                });
            }

            if(document.querySelector('.swiper-ekstra')) {
                new Swiper('.swiper-ekstra', {
                    effect: 'cards',
                    grabCursor: true,
                    autoplay: { delay: 2500, disableOnInteraction: false }
                });
            }

            if(document.querySelector('.swiper-berita')) {
                new Swiper('.swiper-berita', {
                    slidesPerView: 1,
                    spaceBetween: 10,
                    loop: true,
                    pagination: {
                        el: '.swiper-berita .swiper-pagination',
                        clickable: true,
                    },
                    navigation: {
                        nextEl: '.swiper-berita .swiper-button-next',
                        prevEl: '.swiper-berita .swiper-button-prev',
                    },
                    autoplay: { delay: 3500, disableOnInteraction: false }
                });
            }
        });
    </script>

</body>

</html>