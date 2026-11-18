<?php

class ProductVariant {
    private $db;
    private $ready;

    public function __construct($db) {
        $this->db = $db;
    }

    public function isAvailable() {
        if ($this->ready === null) {
            $row = $this->db->query("SELECT
                (SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME IN ('product_variants', 'product_variant_components')) AS tables_ready,
                (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items'
                      AND COLUMN_NAME IN ('product_variant_id', 'product_variant_data')) AS columns_ready
            ")->fetch_assoc();
            $this->ready = (int)$row['tables_ready'] === 2 && (int)$row['columns_ready'] === 2;
        }

        return $this->ready;
    }

    public function getByProducts($store, $productIds) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $params = array_merge([(int)$store, (int)$store], $productIds);
        $stmt = $this->db->prepare("SELECT v.* FROM product_variants v
            JOIN products p ON p.product_id=v.product_id AND p.store_id=?
            WHERE v.store_id=? AND v.product_id IN ($placeholders) ORDER BY v.id");
        $stmt->bind_param(str_repeat('i', count($params)), ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    public function getComponentsByProducts($store, $productIds, $hasMasters) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $fields = 'c.id AS recipe_id, c.variant_id, c.name, c.quantity';
        $join = '';
        $params = [(int)$store];
        if ($hasMasters) {
            $fields = 'c.id AS recipe_id, c.variant_id, c.component_id, COALESCE(m.name, c.name) AS name, c.quantity';
            $join = ' LEFT JOIN production_components m ON m.id = c.component_id AND m.store_id = ?';
            $params = [(int)$store, (int)$store];
        }
        $params = array_merge($params, $productIds);
        $stmt = $this->db->prepare("SELECT $fields FROM product_variant_components c
            JOIN product_variants v ON v.id = c.variant_id $join
            WHERE v.store_id = ? AND v.product_id IN ($placeholders) ORDER BY c.id");
        $stmt->bind_param(str_repeat('i', count($params)), ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    public function getOrderItem($store, $product, $order, $item) {
        $stmt = $this->db->prepare('SELECT product_variant_id, product_variant_data FROM order_items WHERE order_item_id = ? AND order_id = ? AND store_id = ? AND product_id = ?');
        $stmt->bind_param('iiii', $item, $order, $store, $product);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row;
    }

    public function getProductionRows($store, $start, $end, $withUnitType) {
        $unitField = $withUnitType ? ', p.unit_type AS current_unit_type' : '';
        $productJoin = $withUnitType ? ' LEFT JOIN products p ON p.product_id = oi.product_id AND p.store_id = oi.store_id' : '';
        $stmt = $this->db->prepare("SELECT oi.quantity, oi.product_variant_data$unitField
            FROM order_items oi JOIN orders o ON o.order_id = oi.order_id AND o.store_id = oi.store_id
            $productJoin WHERE oi.store_id = ? AND o.date BETWEEN ? AND ?
                AND oi.product_variant_data IS NOT NULL");
        $stmt->bind_param('iss', $store, $start, $end);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        return $result;
    }
}
