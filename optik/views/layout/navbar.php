

<div class="top-navbar">
    <div class="nav-left">
        <img src="/assets/stores/<?= htmlspecialchars($store_img, ENT_QUOTES, 'UTF-8'); ?>" class="store-logo" alt="Logo Toko">
        <div class="store-info">
            <h1><?= htmlspecialchars($store_name, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p>Kasir Optik</p>
        </div>
    </div>

    <div class="nav-right">
        <div class="user-profile">
            <span class="user-name"><?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="user-role">Admin</span>
        </div>
        <img src="/assets/users/<?= htmlspecialchars($user_img, ENT_QUOTES, 'UTF-8'); ?>" class="user-avatar" alt="User Avatar">
    </div>
</div>