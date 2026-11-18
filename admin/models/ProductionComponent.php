<?php

class ProductionComponent {
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
                      AND TABLE_NAME IN ('production_components', 'production_component_links')) AS tables_ready,
                EXISTS(SELECT 1 FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_variant_components'
                      AND COLUMN_NAME = 'component_id') AS column_ready
            ")->fetch_assoc();
            $this->ready = (int)$row['tables_ready'] === 2 && (bool)$row['column_ready'];
        }

        return $this->ready;
    }

    public function findByName($store, $name) {
        $stmt = $this->db->prepare('SELECT id, name FROM production_components WHERE store_id = ? AND name = ?');
        $stmt->bind_param('is', $store, $name);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row;
    }

    public function findById($store, $id) {
        $stmt = $this->db->prepare('SELECT name FROM production_components WHERE id = ? AND store_id = ?');
        $stmt->bind_param('ii', $id, $store);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row;
    }

    public function create($store, $name) {
        $stmt = $this->db->prepare('INSERT INTO production_components (store_id, name) VALUES (?, ?)');
        $stmt->bind_param('is', $store, $name);
        $stmt->execute();
        $id = (int)$this->db->insert_id;
        $stmt->close();

        return $id;
    }

    public function rename($store, $id, $name) {
        $stmt = $this->db->prepare('UPDATE production_components SET name = ? WHERE id = ? AND store_id = ?');
        $stmt->bind_param('sii', $name, $id, $store);
        $stmt->execute();
        $stmt->close();
    }

    public function updateVariantComponents($store, $source, $target) {
        $stmt = $this->db->prepare('UPDATE product_variant_components c JOIN product_variants v ON v.id = c.variant_id SET c.component_id = ? WHERE v.store_id = ? AND c.component_id = ?');
        $stmt->bind_param('iii', $target, $store, $source);
        $stmt->execute();
        $stmt->close();
    }

    public function updateLinks($store, $source, $target) {
        $stmt = $this->db->prepare('UPDATE production_component_links SET component_id = ? WHERE store_id = ? AND component_id = ?');
        $stmt->bind_param('iii', $target, $store, $source);
        $stmt->execute();
        $stmt->close();
    }

    public function saveAlias($store, $key, $name, $target) {
        $stmt = $this->db->prepare('INSERT INTO production_component_links (store_id, product_id, variant_id, legacy_key, legacy_name, component_id) VALUES (?, 0, 0, ?, ?, ?) ON DUPLICATE KEY UPDATE component_id = VALUES(component_id)');
        $stmt->bind_param('issi', $store, $key, $name, $target);
        $stmt->execute();
        $stmt->close();
    }

    public function delete($store, $id) {
        $stmt = $this->db->prepare('DELETE FROM production_components WHERE id = ? AND store_id = ?');
        $stmt->bind_param('ii', $id, $store);
        $stmt->execute();
        $stmt->close();
    }

    public function getByStore($store) {
        if (!$this->isAvailable()) {
            return [];
        }
        $stmt = $this->db->prepare('SELECT id, name FROM production_components WHERE store_id = ? ORDER BY name, id');
        $stmt->bind_param('i', $store);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    public function getLinks($store) {
        if (!$this->isAvailable()) {
            return [];
        }
        $stmt = $this->db->prepare('SELECT legacy_key, component_id FROM production_component_links WHERE store_id = ?');
        $stmt->bind_param('i', $store);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    public function saveLink($store, $product, $variant, $key, $name, $component, $onlyMissing) {
        $update = $onlyMissing ? 'component_id = production_component_links.component_id' : 'component_id = VALUES(component_id)';
        $stmt = $this->db->prepare("INSERT INTO production_component_links (store_id, product_id, variant_id, legacy_key, legacy_name, component_id) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE $update");
        $stmt->bind_param('iiissi', $store, $product, $variant, $key, $name, $component);
        $stmt->execute();
        $stmt->close();
    }

    public function getLegacyRows($store, $product) {
        $stmt = $this->db->prepare('SELECT product_id, quantity, product_variant_data FROM order_items WHERE store_id = ? AND (? = 0 OR product_id = ?) AND product_variant_data IS NOT NULL ORDER BY order_item_id');
        $stmt->bind_param('iii', $store, $product, $product);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        return $result;
    }
}
