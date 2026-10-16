<div class="center-login-card">
    <span class="center-logo bg-primary">OP</span>
    <div class="center-login-heading">
        <h1>Admin Center</h1>
        <p class="text-muted">Masuk untuk memantau seluruh toko optik.</p>
    </div>
    <?php if ($error !== ''): ?>
        <div class="center-alert border-danger text-danger" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form action="<?= htmlspecialchars($center_base) ?>/login" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="form-group">
            <label for="center_username">Username</label>
            <input id="center_username" type="text" name="username" value="<?= htmlspecialchars($username) ?>" autocomplete="username" placeholder="Username admin center" required autofocus>
        </div>
        <div class="form-group">
            <label for="center_password">Password</label>
            <input id="center_password" type="password" name="password" autocomplete="current-password" placeholder="Masukkan password" required>
        </div>
        <button type="submit" class="btn bg-primary w-full mt-10">Masuk ke Center</button>
    </form>
    <p class="center-login-footer text-muted">Pusat pengelolaan Ganesha Optik</p>
</div>
