<?php
$sidebarAllAccess = false;
if (isset($koneksi) && !empty($_SESSION['admin_logged_in'])) {
    require_once __DIR__ . '/../controllers/ProductHistoryController.php';
    $sidebarAllAccess = (new ProductHistoryController($koneksi))->canView();
}
$sidebarRoute = trim($route ?? 'dashboard', '/') ?: 'dashboard';
$sidebarGroups = [
    ['label' => 'Data Utama', 'icon' => 'fa-database', 'items' => [
        ['path' => 'products', 'label' => 'Produk', 'icon' => 'fa-box', 'routes' => ['products', 'product-variants', 'price-tiers', 'promotions', 'package-prices', 'laser-conversions']],
        ['path' => 'stores', 'label' => 'Toko', 'icon' => 'fa-store'],
        ['path' => 'users', 'label' => 'Pengguna', 'icon' => 'fa-users'],
    ]],
    ['label' => 'Operasional', 'icon' => 'fa-tasks', 'items' => [
        ['path' => 'orders', 'label' => 'Order', 'icon' => 'fa-shopping-cart', 'routes' => ['orders', 'order']],
        ['path' => 'productions', 'label' => 'Produksi', 'icon' => 'fa-industry'],
        ['path' => 'branch_check', 'label' => 'Checklist Cabang', 'icon' => 'fa-clipboard-check', 'routes' => ['branch_check', 'checklist_master']],
    ]],
    ['label' => 'Keuangan', 'icon' => 'fa-wallet', 'items' => [
        ['path' => 'transactions', 'label' => 'Pembayaran', 'icon' => 'fa-exchange-alt'],
        ['path' => 'finance', 'label' => 'Keuangan', 'icon' => 'fa-wallet'],
        ['path' => 'piutang', 'label' => 'Piutang', 'icon' => 'fa-file-invoice-dollar'],
    ]],
    ['label' => 'Laporan', 'icon' => 'fa-chart-line', 'items' => [
        ['path' => 'analysis', 'label' => 'Analisis', 'icon' => 'fa-chart-line'],
        ['path' => 'activities', 'label' => 'Aktivitas', 'icon' => 'fa-list-alt', 'all' => true],
        ['path' => 'product-history', 'label' => 'Riwayat Produk', 'icon' => 'fa-history', 'all' => true],
    ]],
    ['label' => 'Pengaturan', 'icon' => 'fa-cog', 'items' => [
        ['path' => 'setting', 'label' => 'Pengaturan Umum', 'icon' => 'fa-cog'],
        ['path' => 'administrator-access', 'label' => 'Akses Administrator', 'icon' => 'fa-user-shield', 'all' => true],
    ]],
    ['label' => 'Bantuan', 'icon' => 'fa-question-circle', 'items' => [
        ['path' => 'helpdesk', 'label' => 'Help Desk', 'icon' => 'fa-headset'],
        ['path' => 'tutorials', 'label' => 'Tutorial', 'icon' => 'fa-play-circle'],
    ]],
];
$sidebarActive = function ($item) use ($sidebarRoute) { return in_array($sidebarRoute, $item['routes'] ?? [$item['path']], true); };
$sidebarEscape = function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
?>
<nav class="sidebar" aria-label="Menu utama Center">
    <div class="sidebar-brand"><i class="fas fa-layer-group" aria-hidden="true"></i><span>App Center</span></div>
    <ul class="sidebar-menu">
        <li><a href="<?= $sidebarEscape($centerBaseUrl . '/dashboard') ?>" title="Dashboard" <?= $sidebarRoute === 'dashboard' ? 'class="active" aria-current="page"' : '' ?>><i class="fas fa-home" aria-hidden="true"></i><span>Dashboard</span></a></li>
        <?php foreach ($sidebarGroups as $sidebarGroup): ?>
            <?php
            $sidebarItems = array_filter($sidebarGroup['items'], function ($item) use ($sidebarAllAccess) { return empty($item['all']) || $sidebarAllAccess; });
            $sidebarGroupActive = (bool)array_filter($sidebarItems, $sidebarActive);
            ?>
            <?php if ($sidebarItems): ?><li>
                <details class="sidebar-group<?= $sidebarGroupActive ? ' has-active' : '' ?>" name="center-menu" <?= $sidebarGroupActive ? 'open' : '' ?>>
                    <summary title="<?= $sidebarEscape($sidebarGroup['label']) ?>"><i class="fas <?= $sidebarEscape($sidebarGroup['icon']) ?>" aria-hidden="true"></i><span><?= $sidebarEscape($sidebarGroup['label']) ?></span><i class="fas fa-chevron-down sidebar-chevron" aria-hidden="true"></i></summary>
                    <ul class="sidebar-submenu">
                        <?php foreach ($sidebarItems as $sidebarItem): ?><li>
                            <a href="<?= $sidebarEscape($centerBaseUrl . '/' . $sidebarItem['path']) ?>" <?= $sidebarActive($sidebarItem) ? 'class="active" aria-current="true"' : '' ?>><i class="fas <?= $sidebarEscape($sidebarItem['icon']) ?>" aria-hidden="true"></i><span><?= $sidebarEscape($sidebarItem['label']) ?></span></a>
                        </li><?php endforeach; ?>
                    </ul>
                </details>
            </li><?php endif; ?>
        <?php endforeach; ?>
    </ul>
</nav>
<script src="<?= $sidebarEscape($centerBaseUrl . '/assets/js/sidebar.js?v=' . filemtime(__DIR__ . '/../assets/js/sidebar.js')) ?>" defer></script>
