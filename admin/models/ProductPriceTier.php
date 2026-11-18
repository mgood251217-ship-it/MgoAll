<?php
require_once __DIR__ . '/ProductVariant.php';
require_once __DIR__ . '/ProductFinishingPrice.php';
require_once __DIR__ . '/ProductLaserConversion.php';

class ProductPriceTier {
    private $db;
    private $ready;
    private $variantTiers;

    public function __construct($db) {
        $this->db = $db;
    }

    public function isAvailable() {
        if ($this->ready === null) {
            $row = $this->db->query("SELECT
                EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_price_tiers') AS rules_ready,
                EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'tier_price_data') AS items_ready")->fetch_assoc();
            $this->ready = (bool)$row['rules_ready'] && (bool)$row['items_ready'];
        }
        return $this->ready;
    }

    public function hasVariantTiers() {
        if ($this->variantTiers === null) {
            $row = $this->db->query("SELECT EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_variant_price_tiers') AS available")->fetch_assoc();
            $this->variantTiers = (bool)$row['available'] && (new ProductVariant($this->db))->isAvailable();
        }
        return $this->variantTiers;
    }

    public function rules($store, $product, $variant = 0) {
        if (!$this->isAvailable()) {
            return [];
        }
        if ($variant) {
            if (!$this->hasVariantTiers()) {
                return [];
            }
            $stmt = $this->query('SELECT quantity_above, price FROM product_variant_price_tiers WHERE store_id = ? AND product_id = ? AND product_variant_id = ? ORDER BY quantity_above', 'iii', [$store, $product, $variant]);
        } else {
            $stmt = $this->query('SELECT quantity_above, price FROM product_price_tiers WHERE store_id = ? AND product_id = ? ORDER BY quantity_above', 'ii', [$store, $product]);
        }
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function rulesForProducts($store, $productIds) {
        if (!$productIds || !$this->isAvailable()) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $values = array_merge([(int)$store], array_map('intval', $productIds));
        $stmt = $this->query("SELECT product_id, quantity_above, price FROM product_price_tiers WHERE store_id=? AND product_id IN ($placeholders) ORDER BY product_id, quantity_above", str_repeat('i', count($values)), $values);
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function rulesForVariants($store, $product) {
        if (!$this->isAvailable() || !$this->hasVariantTiers()) {
            return [];
        }
        $stmt = $this->query('SELECT product_variant_id, quantity_above, price FROM product_variant_price_tiers WHERE store_id=? AND product_id=? ORDER BY product_variant_id, quantity_above', 'ii', [$store, $product]);
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getItems($store, $order, $product = 0) {
        if (!$this->isAvailable()) {
            return [];
        }
        $stmt = $this->query('SELECT * FROM order_items WHERE store_id = ? AND order_id = ? AND (? = 0 OR product_id = ?) AND tier_price_data IS NOT NULL ORDER BY order_item_id', 'iiii', [$store, $order, $product, $product]);
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getOrder($store, $order) {
        $stmt = $this->query('SELECT order_id FROM orders WHERE order_id = ? AND store_id = ? FOR UPDATE', 'ii', [$order, $store]);
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row;
    }

    public function getItem($store, $order, $id) {
        $stmt = $this->query('SELECT * FROM order_items WHERE store_id = ? AND order_id = ? AND order_item_id = ? FOR UPDATE', 'iii', [$store, $order, $id]);
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row;
    }

    public function getProduct($store, $id) {
        $stmt = $this->query('SELECT p.*, c.name AS category FROM products p LEFT JOIN categories c ON c.category_id = p.category_id WHERE p.product_id = ? AND p.store_id = ?', 'ii', [$id, $store]);
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row;
    }

    public function getFinishing($store, $id) {
        $stmt = $this->query('SELECT unit_type FROM finishings WHERE finishing_id = ? AND store_id = ?', 'ii', [$id, $store]);
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row;
    }

    public function getStock($store, $table, $id) {
        $column = $table === 'products' ? 'product_id' : 'finishing_id';
        $stmt = $this->query("SELECT stock FROM $table WHERE $column = ? AND store_id = ? FOR UPDATE", 'ii', [$id, $store]);
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row;
    }

    public function updateStock($store, $table, $id, $quantity) {
        $column = $table === 'products' ? 'product_id' : 'finishing_id';
        $stmt = $this->query("UPDATE $table SET stock = stock + ? WHERE $column = ? AND store_id = ?", 'dii', [$quantity, $id, $store]);
        $stmt->close();
    }

    public function updateItemAmount($store, $id, $amount) {
        $stmt = $this->query('UPDATE order_items SET amount = ? WHERE order_item_id = ? AND store_id = ?', 'dii', [$amount, $id, $store]);
        $stmt->close();
    }

    public function updateItemPricing($store, $id, $unit, $amount) {
        $stmt = $this->query('UPDATE order_items SET unit = ?, amount = ? WHERE order_item_id = ? AND store_id = ?', 'ddii', [$unit, $amount, $id, $store]);
        $stmt->close();
    }

    public function saveItem($store, $order, $id, $item, $update) {
        $fields = ['product_id', 'judul', 'size', 'quantity', 'unit', 'amount', 'finishing', 'tier_price_data'];
        $values = [$item['product_id'], $item['judul'], $item['size'], $item['quantity'], $item['unit'], $item['amount'], $item['finishing_str'], $item['tier_price_data'] ?? null];
        $types = 'issiddss';
        if ((new ProductVariant($this->db))->isAvailable()) {
            array_push($fields, 'product_variant_id', 'product_variant_data');
            array_push($values, $item['product_variant_id'] ?? null, $item['product_variant_data'] ?? null);
            $types .= 'is';
        }
        if ((new ProductFinishingPrice($this->db))->isAvailable()) {
            $fields[] = 'product_finishing_price_id';
            $values[] = $item['product_finishing_price_id'];
            $types .= 'i';
        }
        if ((new ProductLaserConversion($this->db))->hasOrderMaterials()) {
            array_push($fields, 'laser_product_id', 'laser_material_name');
            array_push($values, $item['laser_product_id'], $item['laser_material_name']);
            $types .= 'is';
        }
        if ($update) {
            $sql = 'UPDATE order_items SET ' . implode(', ', array_map(function ($field) { return "$field = ?"; }, $fields)) . ' WHERE order_item_id = ? AND store_id = ?';
            array_push($values, $id, $store);
            $types .= 'ii';
        } else {
            array_push($fields, 'store_id', 'order_id');
            array_push($values, $store, $order);
            $types .= 'ii';
            $sql = 'INSERT INTO order_items (' . implode(', ', $fields) . ') VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')';
        }
        $stmt = $this->query($sql, $types, $values);
        $stmt->close();
    }

    public function deleteItem($store, $id) {
        $stmt = $this->query('DELETE FROM order_items WHERE order_item_id = ? AND store_id = ?', 'ii', [$id, $store]);
        $stmt->close();
    }

    public function beginTransaction() {
        return $this->db->begin_transaction();
    }

    public function commit() {
        return $this->db->commit();
    }

    public function rollback() {
        return $this->db->rollback();
    }

    private function query($sql, $types = '', $values = []) {
        $stmt = $this->db->prepare($sql);
        if ($types) {
            $stmt->bind_param($types, ...$values);
        }
        $stmt->execute();
        return $stmt;
    }
}
