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

    public static function orderFields() {
        return 'variant_master.options_json AS variant_options_json, variant_master.price AS variant_master_price,
            variant_product.name AS variant_product_name, variant_category.name AS variant_category_name';
    }

    public static function orderJoins($alias = 'oi') {
        return "LEFT JOIN product_variants variant_master ON variant_master.id = $alias.product_variant_id
                AND variant_master.product_id = $alias.product_id AND variant_master.store_id = $alias.store_id
            LEFT JOIN products variant_product ON variant_product.product_id = $alias.product_id AND variant_product.store_id = $alias.store_id
            LEFT JOIN categories variant_category ON variant_category.category_id = variant_product.category_id AND variant_category.store_id = $alias.store_id";
    }

    public function getOrderItem($store, $product, $order, $item) {
        $fields = self::orderFields(); $joins = self::orderJoins();
        $stmt = $this->db->prepare("SELECT oi.*, $fields FROM order_items oi $joins WHERE oi.order_item_id = ? AND oi.order_id = ? AND oi.store_id = ? AND oi.product_id = ?");
        $stmt->bind_param('iiii', $item, $order, $store, $product);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row;
    }

    public static function productionSnapshotFilter() {
        return "oi.product_variant_data IS NOT NULL AND COALESCE(oi.product_variant_id, JSON_EXTRACT(IF(JSON_VALID(oi.product_variant_data), oi.product_variant_data, '{}'), '$.id'), 0) > 0";
    }

    public function getProductionRows($store, $start, $end, $withUnitType) {
        $unitField = $withUnitType ? ', p.unit_type AS current_unit_type' : '';
        $productJoin = $withUnitType ? ' LEFT JOIN products p ON p.product_id = oi.product_id AND p.store_id = oi.store_id' : '';
        $hasSnapshot = self::productionSnapshotFilter();
        $fields = self::orderFields(); $joins = self::orderJoins();
        $stmt = $this->db->prepare("SELECT oi.product_id, oi.product_variant_id, oi.quantity, oi.product_variant_data, $fields$unitField
            FROM order_items oi JOIN orders o ON o.order_id = oi.order_id AND o.store_id = oi.store_id
            $productJoin $joins WHERE oi.store_id = ? AND o.date BETWEEN ? AND ?
                AND $hasSnapshot");
        $stmt->bind_param('iss', $store, $start, $end);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        return $result;
    }
}
