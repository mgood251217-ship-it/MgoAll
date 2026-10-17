<?php

if (!isset($login_action)) {
    $_GET['page'] = 'login';
    require __DIR__ . '/index.php';
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Kasir Optik</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($styles_url, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="app-page center-page">
    <main class="center-login">
        <div class="center-login-card">
            <div class="center-logo bg-primary">OP</div>
            <div class="center-login-heading">
                <h1>Selamat Datang</h1>
                <p class="text-muted">Masuk ke kasir optik.</p>
            </div>
            <?php if ($error !== ''): ?>
                <div class="center-alert border-danger text-danger" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form action="<?= htmlspecialchars($login_action) ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input id="username" type="text" name="username" value="<?= htmlspecialchars($username) ?>" autocomplete="username" placeholder="Masukkan username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" autocomplete="current-password" placeholder="Masukkan password" required>
                </div>
                <button type="submit" class="btn bg-primary w-full mt-10">Masuk</button>
            </form>
        </div>
    </main>
</body>
</html>
