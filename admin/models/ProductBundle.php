<?php
class ProductBundle {
    private $db;
    private $ready;

    public function __construct($db) {
        $this->db = $db;
    }

    public function isAvailable() {
        if ($this->ready === null) {
            $r = $this->db
                ->query(
                    "SELECT
                (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('product_bundles','product_bundle_items','product_price_tiers')) AS tables_ready,
                EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_items' AND COLUMN_NAME='tier_price_data') AS orders_ready"
                )
                ->fetch_assoc();
            $this->ready = (int) $r['tables_ready'] === 3 && (bool) $r['orders_ready'];
        }
        return $this->ready;
    }

    private function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        if ($params) {
            $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        }
        $stmt->execute();
        return $stmt;
    }

    public function countsForProducts($store, $productIds) {
        if (!$productIds || !$this->isAvailable()) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $params = array_merge([(int)$store], array_map('intval', $productIds));
        $stmt = $this->db->prepare("SELECT product_id, COUNT(*) AS total FROM product_bundles WHERE store_id=? AND product_id IN ($placeholders) GROUP BY product_id");
        $stmt->bind_param(str_repeat('i', count($params)), ...$params);
        $stmt->execute();
        $counts = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'total', 'product_id');
        $stmt->close();
        return $counts;
    }

    public function getByStore($store) {
        if (!$this->isAvailable()) {
            return [];
        }
        $stmt = $this->query(
            'SELECT b.*, p.name, p.category_id FROM product_bundles b LEFT JOIN products p ON p.product_id=b.product_id AND p.store_id=? WHERE b.store_id=? ORDER BY b.id',
            [$store, $store]
        );
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if (!$rows) {
            return [];
        }
        $ids = array_column($rows, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->query(
            "SELECT i.*, p.name, c.name AS category FROM product_bundle_items i LEFT JOIN products p ON p.product_id=i.product_id AND p.store_id=? LEFT JOIN categories c ON c.category_id=p.category_id AND c.store_id=? WHERE i.bundle_id IN ($placeholders) ORDER BY i.id",
            array_merge([$store, $store], $ids)
        );
        $items = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $item) {
            $items[(int)$item['bundle_id']][] = $item;
        }
        $stmt->close();
        foreach ($rows as &$row) {
            $row['items'] = $items[(int)$row['id']] ?? [];
        }
        unset($row);
        return $rows;
    }

    public function find($store, $product) {
        if (!$this->isAvailable()) {
            return null;
        }
        $stmt = $this->query('SELECT * FROM product_bundles WHERE store_id=? AND product_id=?', [$store, $product]);
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        $stmt = $this->query('SELECT * FROM product_bundle_items WHERE bundle_id=? ORDER BY id', [$row['id']]);
        $row['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $row;
    }

    public function delete($store, $id) {
        $stmt = $this->query('SELECT id FROM product_bundles WHERE store_id=? AND id=? FOR UPDATE', [$store, $id]);
        $exists = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$exists) return false;
        $this->query('DELETE FROM product_bundle_items WHERE bundle_id=?', [$id])->close();
        $this->query('DELETE FROM product_bundles WHERE store_id=? AND id=?', [$store, $id])->close();
        return true;
    }

    public function productsByIds($store, $productIds) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = $this->query(
            "SELECT p.*,c.name AS category FROM products p JOIN categories c ON c.category_id=p.category_id AND c.store_id=? WHERE p.store_id=? AND p.product_id IN ($placeholders)",
            array_merge([$store, $store], $productIds)
        );
        $products = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), null, 'product_id');
        $stmt->close();
        return $products;
    }

    public function parentProduct($targetStore, $targetProduct) {
        $stmt = $this->query(
            'SELECT p.unit_type,c.name AS category FROM products p JOIN categories c ON c.category_id=p.category_id AND c.store_id=? WHERE p.store_id=? AND p.product_id=?',
            [$targetStore, $targetStore, $targetProduct]
        );
        $parent = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $parent;
    }

    public function insert($targetStore, $targetProduct, $price, $items) {
        $stmt = $this->query('INSERT INTO product_bundles(store_id,product_id,price) VALUES(?,?,?)', [
            $targetStore,
            $targetProduct,
            $price
        ]);
        $bundleId = (int) $this->db->insert_id;
        $stmt->close();
        foreach ($items as $item) {
            $this->query(
                'INSERT INTO product_bundle_items(bundle_id,product_id,product_variant_id,laser_conversion_id,quantity,size) VALUES(?,?,?,?,?,?)',
                [
                    $bundleId,
                    $item['product_id'],
                    $item['product_variant_id'],
                    $item['laser_conversion_id'],
                    $item['quantity'],
                    $item['size']
                ]
            )->close();
        }
        $this->query('UPDATE products SET price=? WHERE store_id=? AND product_id=?', [
            $price,
            $targetStore,
            $targetProduct
        ])->close();
    }

    public function savedItem($store, $order, $item, $product) {
        $stmt = $this->query(
            'SELECT tier_price_data FROM order_items WHERE store_id=? AND order_id=? AND order_item_id=? AND product_id=?',
            [$store, $order, $item, $product]
        );
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row;
    }

    public function itemProduct($store, $id) {
        $stmt = $this->query(
            'SELECT p.*, c.name AS category FROM products p JOIN categories c ON c.category_id=p.category_id AND c.store_id=? WHERE p.store_id=? AND p.product_id=?',
            [$store, $store, $id]
        );
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $p;
    }

    public function refundItems($store, $order) {
        $stmt = $this->query(
            "SELECT quantity,tier_price_data FROM order_items WHERE store_id=? AND order_id=? AND JSON_EXTRACT(IF(JSON_VALID(tier_price_data),tier_price_data,'{}'),'$.bundle') IS NOT NULL FOR UPDATE",
            [$store, $order]
        );
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function restoreStock($table, $column, $quantity, $store, $id) {
        $stmt = $this->query("UPDATE $table SET stock=stock+? WHERE store_id=? AND $column=?", [
            $quantity,
            $store,
            $id
        ]);
        $stmt->close();
    }

    public function orderItems($store, $start, $end) {
        $stmt = $this->query(
            "SELECT oi.quantity,oi.tier_price_data FROM order_items oi JOIN orders o ON o.order_id=oi.order_id AND o.store_id=? WHERE oi.store_id=? AND o.date BETWEEN ? AND ? AND JSON_EXTRACT(IF(JSON_VALID(oi.tier_price_data),oi.tier_price_data,'{}'),'$.bundle') IS NOT NULL",
            [$store, $store, $start, $end]
        );
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }
}
