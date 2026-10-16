<?php

class Center {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function findByUsername($username) {
        $stmt = $this->pdo->prepare('SELECT id, username, password FROM center WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findById($id) {
        $stmt = $this->pdo->prepare('SELECT id, username FROM center WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getSummary() {
        $stmt = $this->pdo->query('SELECT
            (SELECT COUNT(*) FROM stores) AS total_stores,
            (SELECT COUNT(*) FROM users) AS total_users,
            (SELECT COUNT(*) FROM products) AS total_products,
            (SELECT COALESCE(SUM(stock), 0) FROM products) AS total_stock');
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findStoreById($id) {
        $stmt = $this->pdo->prepare('SELECT id, name FROM stores WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getStoreOptions() {
        $stmt = $this->pdo->query('SELECT id, name FROM stores ORDER BY name ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countStores($search = '') {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM stores WHERE name LIKE :name_search OR address LIKE :address_search');
        $stmt->execute(['name_search' => '%' . $search . '%', 'address_search' => '%' . $search . '%']);
        return (int) $stmt->fetchColumn();
    }

    public function getStores($search = '', $limit = 15, $offset = 0) {
        $limit = max(1, (int) $limit);
        $offset = max(0, (int) $offset);
        $stmt = $this->pdo->prepare("SELECT s.id, s.name, s.address,
            (SELECT COUNT(*) FROM users u WHERE u.store_id = s.id) AS users_count,
            (SELECT COUNT(*) FROM products p WHERE p.store_id = s.id) AS products_count,
            (SELECT COALESCE(SUM(p.stock), 0) FROM products p WHERE p.store_id = s.id) AS total_stock
            FROM stores s
            WHERE s.name LIKE :name_search OR s.address LIKE :address_search
            ORDER BY s.id DESC LIMIT $limit OFFSET $offset");
        $stmt->execute(['name_search' => '%' . $search . '%', 'address_search' => '%' . $search . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
