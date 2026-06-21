<style>
    .top-navbar {
        height: 70px;
        background-color: var(--card-bg);
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        z-index: 10;
    }
    .nav-left { display: flex; align-items: center; gap: 15px; }
    .store-logo { 
        height: 40px; object-fit: cover; 
    }
    .store-info h1 { font-size: 16px; font-weight: 700; color: var(--text-dark); }
    .store-info p { font-size: 11px; color: var(--text-muted); }

    .nav-right { display: flex; align-items: center; gap: 15px; }
    .user-profile { text-align: right; }
    .user-name { font-size: 14px; font-weight: 600; color: var(--text-dark); display: block; }
    .user-role { font-size: 11px; color: var(--text-muted); display: block; }
    .user-avatar { 
        width: 42px; height: 42px; border-radius: 50%; object-fit: cover;
        border: 2px solid #fff; box-shadow: 0 0 0 2px var(--primary);
    }

    @media (max-width: 768px) {
        .user-profile { display: none; }
        .top-navbar { padding: 0 15px; }
    }
</style>

<div class="top-navbar">
    <div class="nav-left">
        <img src="/assets/stores/<?= $_SESSION['store_img'] ?? 'default-store.png'; ?>" class="store-logo" alt="Logo Toko">
        <div class="store-info">
            <h1><?= $_SESSION['store_name'] ?? 'Ganesa Optical'; ?></h1>
            <p>Kasir Optik</p>
        </div>
    </div>

    <div class="nav-right">
        <div class="user-profile">
            <span class="user-name"><?= $_SESSION['nama_user']; ?></span>
            <span class="user-role">Admin</span>
        </div>
        <img src="/assets/users/<?= $_SESSION['user_img'] ?? 'default-user.png'; ?>" class="user-avatar" alt="User Avatar">
    </div>
</div>