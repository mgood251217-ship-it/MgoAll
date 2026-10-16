<?php

class Product {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getCategoriesGlobal() {
        $stmt = $this->pdo->query("SELECT * FROM categories ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBrandsByStore($store_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM brands WHERE store_id = :store_id");
        $stmt->execute(['store_id' => $store_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countProductsByStore($store_id, $brand_id = '', $search = '') {
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
    }

    public function getProductsByStorePaginated($store_id, $brand_id = '', $search = '', $limit = 14, $offset = 0) {
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
    }

    public function getAllProducts($store_id) {
        $sql = "SELECT p.*, b.name as brand_name, c.name as cat_name
                FROM products p
                LEFT JOIN brands b ON p.brand_id = b.id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.store_id = :store_id
                ORDER BY p.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['store_id' => $store_id]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result ? $result : [];
    }

    public function getProductById($id) {
        $sql = "SELECT * FROM products WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function kurangiStok($product_id, $jumlah) {
        $sql = "UPDATE products SET stock = stock - :jumlah WHERE id = :id AND stock >= :jumlah";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'jumlah' => $jumlah,
            'id' => $product_id
        ]);
    }

    public function checkProductCodeExists($code, $store_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM products WHERE product_code = :code AND store_id = :store_id");
        $stmt->execute(['code' => $code, 'store_id' => $store_id]);
        return $stmt->fetchColumn() > 0;
    }

    public function getProductsByBrand($store_id, $brand_id = '') {
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
    }

    public function getDetailByStore($id, $store_id) {
        $stmt = $this->pdo->prepare("SELECT p.*, b.brand_code FROM products p LEFT JOIN brands b ON p.brand_id = b.id WHERE p.id = :id AND p.store_id = :store_id");
        $stmt->execute(['id' => $id, 'store_id' => $store_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getBrandByStore($id, $store_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM brands WHERE id = :id AND store_id = :store_id");
        $stmt->execute(['id' => $id, 'store_id' => $store_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createCategory($name) {
        $stmt = $this->pdo->prepare("INSERT INTO categories (name) VALUES (:name)");
        return $stmt->execute(['name' => $name]);
    }

    public function createBrand($store_id, array $data) {
        $stmt = $this->pdo->prepare("INSERT INTO brands (store_id, brand_code, name, img) VALUES (:store_id, :brand_code, :name, :img)");
        return $stmt->execute($data + ['store_id' => $store_id]);
    }

    public function updateBrand($id, $store_id, array $data) {
        $stmt = $this->pdo->prepare("UPDATE brands SET brand_code = :brand_code, name = :name, img = :img WHERE id = :id AND store_id = :store_id");
        return $stmt->execute($data + ['id' => $id, 'store_id' => $store_id]);
    }

    public function createProduct($store_id, array $data) {
        $stmt = $this->pdo->prepare("INSERT INTO products (product_code, name, info, year, brand_id, category_id, color, stock, price, img, store_id) VALUES (:product_code, :name, :info, :year, :brand_id, :category_id, :color, :stock, :price, :img, :store_id)");
        return $stmt->execute($data + ['store_id' => $store_id]);
    }

    public function updateProduct($id, $store_id, array $data) {
        $stmt = $this->pdo->prepare("UPDATE products SET product_code = :product_code, name = :name, info = :info, year = :year, brand_id = :brand_id, category_id = :category_id, color = :color, price = :price, img = :img WHERE id = :id AND store_id = :store_id");
        return $stmt->execute($data + ['id' => $id, 'store_id' => $store_id]);
    }

    public function updateStock($id, $store_id, $quantity) {
        $stmt = $this->pdo->prepare("UPDATE products SET stock = :qty WHERE id = :id AND store_id = :store_id");
        return $stmt->execute(['qty' => $quantity, 'id' => $id, 'store_id' => $store_id]);
    }

    public function deleteProduct($id, $store_id) {
        $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = :id AND store_id = :store_id");
        return $stmt->execute(['id' => $id, 'store_id' => $store_id]);
    }

    public function detachBrand($id, $store_id) {
        $stmt = $this->pdo->prepare("UPDATE products SET brand_id = 0 WHERE brand_id = :id AND store_id = :store_id");
        return $stmt->execute(['id' => $id, 'store_id' => $store_id]);
    }

    public function deleteBrand($id, $store_id) {
        $stmt = $this->pdo->prepare("DELETE FROM brands WHERE id = :id AND store_id = :store_id");
        return $stmt->execute(['id' => $id, 'store_id' => $store_id]);
    }

    public function transaction(callable $callback) {
        if (!$this->pdo->beginTransaction()) {
            throw new RuntimeException('Transaksi database tidak dapat dimulai.');
        }

        try {
            $result = $callback();
            if (!$this->pdo->commit()) {
                throw new RuntimeException('Transaksi database gagal disimpan.');
            }
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function getStockSummary($store_id) {
        $stmt = $this->pdo->prepare("SELECT SUM(stock) as total_stok, COUNT(*) as total_produk FROM products WHERE store_id = ?");
        $stmt->execute([$store_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getLowStockProducts($store_id) {
        $stmt = $this->pdo->prepare("SELECT name, stock FROM products WHERE store_id = ? AND stock < 5 ORDER BY stock ASC LIMIT 6");
        $stmt->execute([$store_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
