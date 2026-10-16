<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $view === 'login' ? 'Login' : 'Admin' ?> Center - Ganesha Optik</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($styles_url, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="app-page center-page">
    <?php if (isset($admin)): ?>
        <header class="center-header">
            <a href="<?= htmlspecialchars($center_base) ?>/" class="center-brand">
                <span class="center-logo bg-primary">OP</span>
                <span>Admin Center<small>Ganesha Optik</small></span>
            </a>
            <nav class="center-nav" aria-label="Menu admin center">
                <a href="<?= htmlspecialchars($center_base) ?>/" class="center-nav-link <?= $view === 'dashboard' ? 'active' : '' ?>" <?= $view === 'dashboard' ? 'aria-current="page"' : '' ?>>Dashboard</a>
                <a href="<?= htmlspecialchars($center_base) ?>/stores" class="center-nav-link <?= $view === 'stores' ? 'active' : '' ?>" <?= $view === 'stores' ? 'aria-current="page"' : '' ?>>Daftar Toko</a>
                <a href="<?= htmlspecialchars($center_base) ?>/orders" class="center-nav-link <?= in_array($view, ['orders', 'order'], true) ? 'active' : '' ?>" <?= in_array($view, ['orders', 'order'], true) ? 'aria-current="page"' : '' ?>>Order</a>
            </nav>
            <div class="center-account">
                <span class="font-semibold"><?= htmlspecialchars($admin['username']) ?></span>
                <form action="<?= htmlspecialchars($center_base) ?>/logout" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <button type="submit" class="btn bg-secondary text-muted">Keluar</button>
                </form>
            </div>
        </header>
    <?php endif; ?>
    <main class="<?= $view === 'login' ? 'center-login' : 'page-container center-content' ?>">
        <?php if (!empty($notice)): ?>
            <div class="center-alert border-success text-success" role="status"><?= htmlspecialchars($notice) ?></div>
        <?php endif; ?>
        <?php if ($view === 'login'): ?>
            <?php require __DIR__ . '/login.php'; ?>
        <?php elseif ($view === 'dashboard'): ?>
            <?php require __DIR__ . '/dashboard.php'; ?>
        <?php elseif ($view === 'stores'): ?>
            <?php require __DIR__ . '/stores.php'; ?>
        <?php elseif ($view === 'orders'): ?>
            <?php require __DIR__ . '/orders.php'; ?>
        <?php elseif ($view === 'order'): ?>
            <?php require __DIR__ . '/order.php'; ?>
        <?php else: ?>
            <div class="table-container">
                <h1 class="page-title mb-20">Halaman belum dapat ditampilkan</h1>
                <p role="alert" class="text-danger mb-20"><?= htmlspecialchars($error) ?></p>
                <a href="<?= htmlspecialchars($return_url ?? ($center_base . '/')) ?>" class="btn bg-primary">Kembali</a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
