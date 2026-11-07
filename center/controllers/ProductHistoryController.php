<?php
require_once __DIR__ . '/../functions/helpers.php';

class ProductHistoryController {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function getAdministrator() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $encryptedId = $_SESSION['admin_logged_in']['administrator_id'] ?? '';
        if (!is_string($encryptedId) || $encryptedId === '') return null;
        $id = filter_var(startEnk('dek', $encryptedId), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id) return null;
        $stmt = $this->db->prepare('SELECT administrator_id, access FROM administrator WHERE administrator_id = ?');
        $stmt->bind_param('i', $id); $stmt->execute(); $admin = $stmt->get_result()->fetch_assoc(); $stmt->close();
        return $admin;
    }

    public function canView() {
        $admin = $this->getAdministrator();
        return $admin && $admin['access'] === 'ALL';
    }

    public function isAvailable() {
        return (bool)$this->db->query("SELECT EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_creation_logs') AS ready")->fetch_assoc()['ready'];
    }

    // Dipanggil dalam transaksi penambahan produk, sebelum commit.
    public function record($productId, $administratorId) {
        $stmt = $this->db->prepare('INSERT INTO product_creation_logs (product_id, administrator_id) VALUES (?, ?)');
        $stmt->bind_param('ii', $productId, $administratorId);
        $stmt->execute(); $count = $stmt->affected_rows; $stmt->close();
        if ($count !== 1) throw new RuntimeException('Riwayat penambahan produk gagal disimpan.');
    }

    public function getData() {
        if (!$this->canView()) throw new InvalidArgumentException('Halaman ini hanya untuk akun dengan akses ALL.');
        $ready = $this->isAvailable(); $rows = $stores = []; $total = 0; $totalPages = 1;
        $store = max(0, (int)($_GET['store_id'] ?? 0));
        $search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
        $page = max(1, (int)($_GET['page'] ?? 1)); $limit = 25;
        if ($ready) {
            $stores = $this->db->query('SELECT DISTINCT s.store_id, s.name FROM product_creation_logs l JOIN products p ON p.product_id = l.product_id JOIN stores s ON s.store_id = p.store_id ORDER BY s.name, s.store_id')->fetch_all(MYSQLI_ASSOC);
            $from = ' FROM product_creation_logs l
                LEFT JOIN products p ON p.product_id = l.product_id
                LEFT JOIN administrator a ON a.administrator_id = l.administrator_id
                LEFT JOIN stores s ON s.store_id = p.store_id
                LEFT JOIN categories c ON c.category_id = p.category_id AND c.store_id = p.store_id';
            $where = []; $params = [];
            if ($store) { $where[] = 'p.store_id = ?'; $params[] = $store; }
            if ($search !== '') {
                $where[] = '(p.name LIKE ? OR a.name LIKE ? OR a.username LIKE ? OR CAST(l.product_id AS CHAR) = ?)';
                array_push($params, '%' . $search . '%', '%' . $search . '%', '%' . $search . '%', $search);
            }
            $where = $where ? ' WHERE ' . implode(' AND ', $where) : '';
            $stmt = $this->db->prepare('SELECT COUNT(*) AS total' . $from . $where);
            if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
            $stmt->execute(); $total = (int)$stmt->get_result()->fetch_assoc()['total']; $stmt->close();
            $totalPages = max(1, (int)ceil($total / $limit)); $page = min($page, $totalPages); $offset = ($page - 1) * $limit;
            $stmt = $this->db->prepare('SELECT l.*, p.name AS product_name, p.unit_type, p.price,
                s.store_id, s.name AS store_name, c.name AS category_name,
                a.name AS administrator_name, a.username AS administrator_username' . $from . $where . ' ORDER BY l.id DESC LIMIT ? OFFSET ?');
            $types = str_repeat('s', count($params)) . 'ii'; array_push($params, $limit, $offset);
            $stmt->bind_param($types, ...$params); $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        }
        return compact('ready', 'rows', 'stores', 'total', 'totalPages', 'store', 'search', 'page', 'limit');
    }
}
