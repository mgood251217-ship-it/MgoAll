<style>
/* ===== NAVBAR ===== */
.navigation {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 90px;
    background-color: var(--logo-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    z-index: 9999;
    box-shadow: 0 2px 6px rgba(0,0,0,.15);
}

.logo {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}

.logo img {
    height: 55px;
}

/* ===== MENU DESKTOP ===== */
.menu ul {
    list-style: none;
    display: flex;
    margin: 0;
    padding: 0;
}

.menu ul li {
    position: relative;
}

.menu ul li a {
    display: block;
    padding: 15px 20px;
    color: #fff;
    text-decoration: none;
}

.menu ul li a i{
    font-size: small;
}

.menu ul li ul {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    background: #fff;
    min-width: 230px;
    box-shadow: 0 2px 6px rgba(0,0,0,.15);
    z-index: 999;
}

.menu ul li:hover ul {
    display: block;
}

.menu ul li ul li a {
    color: var(--logo-color);
    padding: 10px 20px;
}

.menu ul li ul li a:hover{
    background-color: var(--logo-color-2);
    color: white;
}

.info-register .btn-register {
    background-color: var(--secondary-color);
    color: #fff;
    border: none;
    padding: 10px 18px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 16px;
    text-decoration: none;
    transition: 0.5s ease;
}

.info-register .btn-register:hover{
    background-color: var(--logo-color-2);

}

/* ===== HAMBURGER ===== */
.hamburger {
    display: none;
    font-size: 28px;
    color: #fff;
    cursor: pointer;
}

/* ===== MOBILE REGISTER BUTTON ===== */
.mobile-register {
    display: none;
    padding: 15px 20px;
}

.mobile-btn {
    display: block;
    text-align: center;
    background: var(--secondary-color);
    color: #fff !important;
    padding: 12px;
    border-radius: 8px;
    font-size: 16px;
}

/* ===== MOBILE ===== */
@media (max-width: 768px) {

    .logo {
        width: 50px;
        height: 50px;
    }

    .logo img {
        height: 37px;
    }

    .hamburger {
        display: block;
    }

    .menu {
        position: fixed;
        top: 90px;
        left: -100%;
        width: 100%;
        background: var(--logo-color);
        transition: .3s;
    }

    .menu.active {
        left: 0;
    }

    .menu ul {
        flex-direction: column;
    }

    .menu ul li ul {
        position: static;
        background: rgba(255,255,255,.1);
        box-shadow: none;
    }

    .menu ul li:hover ul {
        display: none;
    }

    .menu ul li.open ul {
        display: block;
    }

    .menu ul li ul li a {
        color: #fff;
        padding-left: 35px;
    }

    .info-register {
        display: none;
    }

    .mobile-register {
        display: block;
    }

    .mobile-btn:hover {
        background: var(--logo-color-2);
    }


}
</style>
<nav class="navigation">
    <div class="logo">
        <img src="<?= BASE_URL ?>/assets/img/medina.png">
    </div>

    <div class="hamburger" onclick="toggleMenu()">☰</div>

    <div class="menu" id="menu">
        <ul>
            <li><a href="<?= BASE_URL ?>">Beranda</a></li>

            <li onclick="toggleSub(this)">
                <a href="#">Tentang Kami <i>▼</i></a>
                <ul>
                    <li><a href="<?= BASE_URL ?>/indexes/profile_medina_insan_qurani">Profile Medina Insan Qur'ani</a></li>
                    <li><a href="<?= BASE_URL ?>/indexes/nilai_nilai_inti_medina_insan_qurani">Nilai Nilai Inti Medina Insan Qur'ani</a></li>
                    <li><a href="<?= BASE_URL ?>/indexes/standar-penjamin-mutu-internal">SPMI Medina Insan Qur'ani</a></li>
                    <li><a href="<?= BASE_URL ?>/indexes/program">Kurikulum & Program</a></li>
                    <li><a href="<?= BASE_URL ?>/indexes/our_team">Tim Kami</a></li>
                </ul>
            </li>

            <li onclick="toggleSub(this)">
                <a href="#">Kegiatan Harian <i>▼</i></a>
                <ul>
                    <li><a href="#">Project Siswa</a></li>
                    <li><a href="<?= BASE_URL ?>/indexes/daily_activity">Aktivitas Harian</a></li>
                    <li><a href="#">Ekstrakurikuler</a></li>
                    <li><a href="<?= BASE_URL ?>/indexes/achievements">Penghargaan</a></li>
                    <li><a href="<?= BASE_URL ?>/indexes/literasi_sekolah">Literasi Sekolah</a></li>
                </ul>
            </li>

            <li onclick="toggleSub(this)">
                <a href="#">Galeri <i>▼</i></a>
                <ul>
                    <li><a href="<?= BASE_URL ?>/indexes/fasilitas">Fasilitas</a></li>
                    <li><a href="<?= BASE_URL ?>/indexes/event_gallery">Galeri Acara</a></li>
                    <li><a href="<?= BASE_URL ?>/indexes/testimoni">Testimoni</a></li>
                </ul>
            </li>

            <li onclick="toggleSub(this)">
                <a href="#">PPDB <i>▼</i></a>
                <ul>
                    <li><a href="<?= BASE_URL ?>/indexes/informasi_pendaftaran">Informasi Pendaftaran</a></li>
                    <li><a href="<?= BASE_URL ?>/indexes/informasi_biaya">Informasi Biaya</a></li>
                    <li><a href="<?= BASE_URL ?>/indexes/faq">FAQ</a></li>
                </ul>
            </li>
            <li class="mobile-register">
                <a href="<?= BASE_URL ?>/register" class="btn-register mobile-btn">
                    Daftar Sekarang
                </a>
            </li>
        </ul>
    </div>
    <div class="info-register">
        <a href="<?= BASE_URL ?>/register" class="btn-register">
            Daftar Sekarang
        </a>
    </div>


</nav>
<script>
function toggleMenu() {
    document.getElementById('menu').classList.toggle('active');
}

function toggleSub(el) {
    if (window.innerWidth <= 768) {
        el.classList.toggle('open');
    }
}
</script>