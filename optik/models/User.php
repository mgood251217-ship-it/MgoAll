<?php 

class User {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getUserByUsername($username) {
        $stmt = $this->pdo->prepare("
            SELECT u.*, s.name as store_name, s.img as store_img, s.address as store_address
                FROM users u 
                LEFT JOIN stores s ON u.store_id = s.id
                WHERE u.username = :username LIMIT 1
        ");
        $stmt->execute(['username' => $username]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}