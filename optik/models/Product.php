<?php

class Product {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getCategoriesGlobal() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM categories ORDER BY name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getBrandsByStore($store_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM brands WHERE store_id = :store_id");
            $stmt->execute(['store_id' => $store_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function countProductsByStore($store_id, $brand_id = '', $search = '') {
        try {
            $where = "store_id = :store_id";
            $params = [':store_id' => $store_id];

            if ($brand_id !== '') {
                $where .= " AND brand_id = :brand_id";
                $params[':brand_id'] = $brand_id;
            }
            if ($search !== '') {
                $where .= " AND (name LIKE :search OR product_code LIKE :search OR info LIKE :search)";
                $params[':search'] = "%$search%";
            }

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM products WHERE $where");
            $stmt->execute($params);
            return $stmt->fetchColumn();
            
        } catch (PDOException $e) {
            die("<div style='padding:20px; background:#fee2e2; color:#991b1b;'><b>Error Count DB:</b> " . $e->getMessage() . "</div>");
        }
    }

    public function getProductsByStorePaginated($store_id, $brand_id = '', $search = '', $limit = 14, $offset = 0) {
        try {
            $where = "store_id = :store_id";
            $params = [':store_id' => $store_id];

            if ($brand_id !== '') {
                $where .= " AND brand_id = :brand_id";
                $params[':brand_id'] = $brand_id;
            }
            if ($search !== '') {
                $where .= " AND (name LIKE :search OR product_code LIKE :search OR info LIKE :search)";
                $params[':search'] = "%$search%";
            }

            $limit = max(1, (int) $limit);
            $offset = max(0, (int) $offset);

            $sql = "SELECT * FROM products WHERE $where ORDER BY id DESC LIMIT $limit OFFSET $offset";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $result ? $result : []; 
            
        } catch (PDOException $e) {
            die("<div style='padding:20px; background:#fee2e2; color:#991b1b;'><b>Error Get Data DB:</b> " . $e->getMessage() . "</div>");
        }
    }

    public function getAllProducts() {
        try {
            $sid = $_SESSION['store_id'];
            $sql = "SELECT p.*, b.name as brand_name, c.name as cat_name 
                    FROM products p 
                    LEFT JOIN brands b ON p.brand_id = b.id
                    LEFT JOIN categories c ON p.category_id = c.id
                    WHERE p.store_id = :store_id
                    ORDER BY p.id DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['store_id' => $sid]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $result ? $result : [];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function getProductById($id) {
        try {
            $sql = "SELECT * FROM products WHERE id = :id LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function kurangiStok($product_id, $jumlah) {
        try {
            $sql = "UPDATE products SET stock = stock - :jumlah WHERE id = :id AND stock >= :jumlah";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                'jumlah' => $jumlah,
                'id' => $product_id
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function checkProductCodeExists($code, $store_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM products WHERE product_code = :code AND store_id = :store_id");
            $stmt->execute(['code' => $code, 'store_id' => $store_id]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
    public function getProductsByBrand($store_id, $brand_id = '') {
        try {
            $sql = "
                SELECT p.*, b.name as brand_name, c.name as cat_name 
                FROM products p 
                LEFT JOIN brands b ON p.brand_id = b.id 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.store_id = :store_id
            ";
            $params = [':store_id' => $store_id];

            if ($brand_id !== '') {
                $sql .= " AND p.brand_id = :brand_id";
                $params[':brand_id'] = $brand_id;
            }

            $sql .= " ORDER BY p.id DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            die("Error getProductsByBrand: " . $e->getMessage());
        }
    }
}
?>