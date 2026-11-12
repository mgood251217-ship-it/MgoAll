<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: /login'); exit; }
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../controllers/AdministratorAccessController.php';
$accessController = new AdministratorAccessController($koneksi);
if (!$accessController->isAll()) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Halaman ini hanya untuk akun dengan akses ALL.</div>';
    return;
}
$ready = $accessController->isAvailable();
$administrators = $koneksi->query('SELECT administrator_id, name, username, access FROM administrator ORDER BY name, administrator_id')->fetch_all(MYSQLI_ASSOC);
$selectedId = max(0, (int)($_GET['administrator_id'] ?? 0));
$selected = null;
foreach ($administrators as $administrator) if ((int)$administrator['administrator_id'] === $selectedId) $selected = $administrator;
$permissions = $selected && $ready ? $accessController->permissionsFor($selectedId) : [];
$_SESSION['administrator_access_csrf'] = $_SESSION['administrator_access_csrf'] ?? bin2hex(random_bytes(32));
$escape = function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
?>
<div class="page-header"><h2>Akses Administrator</h2></div>
<p>Atur izin tindakan di Center. Akses toko tetap mengikuti pengaturan administrator yang sudah ada. Akun ALL selalu memiliki akses penuh.</p>
<?php if (!$ready): ?><div class="alert alert-warning">Jalankan <strong>center/utils/administrator_permissions.sql</strong> terlebih dahulu agar izin dapat disimpan.</div><?php endif; ?>
<form method="get" action="/administrator-access" class="filter-card">
    <label for="administrator_id">Administrator</label>
    <select class="form-control" id="administrator_id" name="administrator_id" onchange="this.form.submit()">
        <option value="">Pilih administrator</option>
        <?php foreach ($administrators as $administrator): ?>
        <option value="<?= (int)$administrator['administrator_id'] ?>" <?= (int)$administrator['administrator_id'] === $selectedId ? 'selected' : '' ?>><?= $escape($administrator['name'] . ' (' . $administrator['username'] . ') — ' . $administrator['access']) ?></option>
        <?php endforeach; ?>
    </select>
</form>
<?php if ($selected && $selected['access'] === 'ALL'): ?>
    <div class="alert alert-info">Akun ini memiliki akses ALL. Seluruh tindakan diizinkan.</div>
<?php elseif ($selected && $ready): ?>
    <p>Centang untuk mengizinkan. Izin yang belum pernah diatur mengikuti akses lama. Perubahan berlaku tanpa login ulang. Salin produk memerlukan izin tambah produk dan kategori; impor katalog toko memerlukan izin tambah produk, kategori, dan finishing. Menghapus produk asal setelah migrasi memerlukan izin hapus produk.</p>
    <form method="post" action="/action?action=save_administrator_access">
        <input type="hidden" name="csrf" value="<?= $escape($_SESSION['administrator_access_csrf']) ?>">
        <input type="hidden" name="administrator_id" value="<?= $selectedId ?>">
        <?php foreach (AdministratorAccessController::definitions() as $group => $items): ?>
        <fieldset class="filter-card"><legend style="font-size:1.1rem"><?= $escape($group) ?></legend>
            <div style="display:flex;flex-wrap:wrap;gap:12px 24px">
            <?php foreach ($items as $key => $label): ?>
                <label style="display:flex;align-items:center;gap:10px;min-width:240px;cursor:pointer;padding:8px 0">
                    <input type="checkbox" name="permissions[]" value="<?= $escape($key) ?>" <?= !isset($permissions[$key]) || (int)$permissions[$key] === 1 ? 'checked' : '' ?> style="width:22px;height:22px;flex-shrink:0">
                    <?= $escape($label) ?>
                </label>
            <?php endforeach; ?>
            </div>
        </fieldset>
        <?php endforeach; ?>
        <button class="btn-primary-custom" type="submit">Simpan Akses</button>
    </form>
<?php endif; ?>
