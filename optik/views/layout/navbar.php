

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