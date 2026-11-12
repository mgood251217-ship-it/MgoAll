<?php
require_once __DIR__ . '/ProductHistoryController.php';

class AdministratorAccessController {
    private $db;
    private $administrator;
    private $permissions;

    public function __construct($db) {
        $this->db = $db;
        $this->administrator = (new ProductHistoryController($db))->getAdministrator();
    }

    public static function definitions() {
        return [
            'Produk' => [
                'add_product' => 'Tambah produk', 'edit_product' => 'Ubah produk',
                'delete_product' => 'Hapus produk', 'migrate_product' => 'Migrasi item order',
                'copy_products' => 'Salin produk ke cabang lain',
                'refresh_product_cache' => 'Update cache toko',
            ],
            'Kategori dan finishing' => [
                'add_category' => 'Tambah kategori', 'delete_category' => 'Hapus kategori kosong',
                'add_finishing' => 'Tambah finishing', 'edit_finishing' => 'Ubah finishing',
                'delete_finishing' => 'Hapus finishing',
            ],
            'Harga dan varian' => [
                'save_product_variant' => 'Kelola varian dan jenis pilihan',
                'save_production_component' => 'Kelola nama meteran',
                'save_package_price' => 'Kelola harga paket',
                'save_laser_conversion' => 'Kelola konversi print laser',
                'save_price_tiers' => 'Kelola harga bertingkat',
                'save_promotion' => 'Kelola promo beli gratis',
            ],
        ];
    }

    public static function actionPermission($action) {
        $aliases = [
            'bulk_update_product_category' => 'edit_product',
            'bulk_delete_products' => 'delete_product',
            'link_production_components' => 'save_production_component',
            // Salin seluruh katalog juga dapat membuat produk, kategori, dan finishing.
            'copy_store_catalog' => 'copy_products',
        ];
        $key = $aliases[$action] ?? $action;
        foreach (self::definitions() as $group) if (isset($group[$key])) return $key;
        return null;
    }

    public function isAll() { return $this->administrator && $this->administrator['access'] === 'ALL'; }

    public function isAvailable() {
        return (bool)$this->db->query("SELECT EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'administrator_permissions') AS ready")->fetch_assoc()['ready'];
    }

    public function permissionsFor($id) {
        if (!$this->isAvailable()) return [];
        $stmt = $this->db->prepare('SELECT permission, allowed FROM administrator_permissions WHERE administrator_id = ?');
        $stmt->bind_param('i', $id); $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        return array_column($rows, 'allowed', 'permission');
    }

    public function can($permission) {
        if (!$this->administrator || self::actionPermission($permission) === null) return false;
        if ($this->isAll()) return true;
        if ($this->permissions === null) $this->permissions = $this->permissionsFor((int)$this->administrator['administrator_id']);
        // Izin yang belum diatur mengikuti perilaku lama. Pembatasan toko tetap diperiksa controller.
        $key = self::actionPermission($permission);
        return !isset($this->permissions[$key]) || (int)$this->permissions[$key] === 1;
    }

    public function requiredFor($action, $input) {
        $permission = self::actionPermission($action);
        $required = $permission ? [$permission] : [];
        if ($action === 'migrate_product' && !empty($input['delete_source_products'])) $required[] = 'delete_product';
        if ($action === 'copy_products') $required = array_merge($required, ['add_product', 'add_category']);
        if ($action === 'copy_store_catalog') $required = array_merge($required, ['add_product', 'add_category', 'add_finishing']);
        return array_values(array_unique($required));
    }

    public function enforce($action, $input = []) {
        foreach ($this->requiredFor($action, $input) as $permission) {
            if (!$this->can($permission)) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk tindakan ini. Hubungi administrator dengan akses ALL.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    public function save() {
        $transaction = false;
        try {
            if (!$this->isAll()) { http_response_code(403); exit('Halaman ini hanya untuk akun dengan akses ALL.'); }
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !is_string($_POST['csrf'] ?? null)
                || empty($_SESSION['administrator_access_csrf']) || !hash_equals($_SESSION['administrator_access_csrf'], $_POST['csrf'])) {
                throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
            }
            if (!$this->isAvailable()) throw new InvalidArgumentException('Jalankan SQL administrator_permissions.sql terlebih dahulu.');
            $id = filter_var($_POST['administrator_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $selected = $_POST['permissions'] ?? [];
            $keys = array_merge(...array_values(array_map('array_keys', self::definitions())));
            if (!$id || !is_array($selected) || array_filter($selected, function ($key) use ($keys) { return !is_string($key) || !in_array($key, $keys, true); })) {
                throw new InvalidArgumentException('Administrator atau pilihan izin tidak valid.');
            }
            $this->db->begin_transaction(); $transaction = true;
            $stmt = $this->db->prepare('SELECT administrator_id, access FROM administrator WHERE administrator_id = ? FOR UPDATE');
            $stmt->bind_param('i', $id); $stmt->execute(); $target = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$target || $target['access'] === 'ALL') throw new InvalidArgumentException('Izin akun ALL tidak dapat dibatasi melalui halaman ini.');
            $stmt = $this->db->prepare('INSERT INTO administrator_permissions (administrator_id, permission, allowed) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)');
            foreach ($keys as $key) {
                $allowed = in_array($key, $selected, true) ? 1 : 0;
                $stmt->bind_param('isi', $id, $key, $allowed); $stmt->execute();
            }
            $stmt->close(); $this->db->commit(); $transaction = false;
            $_SESSION['swal_success'] = 'Akses administrator disimpan. Berlaku pada request berikutnya tanpa login ulang.';
        } catch (Throwable $e) {
            if ($transaction) $this->db->rollback();
            error_log('Administrator permissions: ' . $e->getMessage());
            $_SESSION['swal_error'] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Gagal menyimpan akses administrator.';
        }
        $id = max(0, (int)($_POST['administrator_id'] ?? 0));
        header('Location: /administrator-access?administrator_id=' . $id); exit;
    }
}
