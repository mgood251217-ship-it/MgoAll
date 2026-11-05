<?php

class OrderDiscount {
    private $koneksi;
    private $hasSnapshots;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
    }

    public function getByOrder($order_id, $store_id) {
        $productMatch = $this->productMatch();
        $stmt = $this->koneksi->prepare("SELECT d.product_id, d.diskon AS discount,
                COALESCE(p.name, CONCAT('Produk #', d.product_id)) AS product_name,
                NOT EXISTS(SELECT 1 FROM order_items oi WHERE oi.order_id = d.order_id AND $productMatch) AS can_delete
            FROM diskon_order_items d
            JOIN orders o ON o.order_id = d.order_id AND o.store_id = ?
            LEFT JOIN products p ON p.product_id = d.product_id AND p.store_id = o.store_id
            WHERE d.order_id = ? AND d.diskon > 0 ORDER BY product_name, d.product_id");
        $stmt->bind_param('ii', $store_id, $order_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    private function productMatch() {
        if ($this->hasSnapshots === null) {
            $row = $this->koneksi->query("SELECT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'tier_price_data') AS available")->fetch_assoc();
            $this->hasSnapshots = (bool)$row['available'];
        }
        return $this->hasSnapshots ? "(oi.product_id = d.product_id OR JSON_CONTAINS(oi.tier_price_data, CAST(d.product_id AS CHAR), '$.migration_product_ids') = 1)" : 'oi.product_id = d.product_id';
    }

    public function delete($order_id, $product_id, $store_id) {
        if ($order_id <= 0 || $product_id <= 0 || $store_id <= 0) throw new InvalidArgumentException('Order dan produk tidak valid.');
        $transaction = false;
        $driver = new mysqli_driver();
        $previousMode = $driver->report_mode;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $productMatch = $this->productMatch();
            $this->koneksi->begin_transaction();
            $transaction = true;
            $stmt = $this->koneksi->prepare('SELECT order_id FROM orders WHERE order_id = ? AND store_id = ? FOR UPDATE');
            $stmt->bind_param('ii', $order_id, $store_id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$order) throw new InvalidArgumentException('Order tidak ditemukan pada toko ini.');
            $stmt = $this->koneksi->prepare("SELECT d.product_id FROM diskon_order_items d WHERE d.order_id = ? AND d.product_id = ?
                AND EXISTS(SELECT 1 FROM order_items oi WHERE oi.order_id = d.order_id AND $productMatch) FOR UPDATE");
            $stmt->bind_param('ii', $order_id, $product_id);
            $stmt->execute();
            $hasItem = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            if ($hasItem) throw new InvalidArgumentException('Hapus semua item produk ini dari order terlebih dahulu sebelum menghapus diskonnya.');
            $stmt = $this->koneksi->prepare("DELETE d FROM diskon_order_items d WHERE d.order_id = ? AND d.product_id = ?
                AND NOT EXISTS(SELECT 1 FROM order_items oi WHERE oi.order_id = d.order_id AND $productMatch)");
            $stmt->bind_param('ii', $order_id, $product_id);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
            $stmt->close();
            if (!$deleted) throw new InvalidArgumentException('Diskon tidak ditemukan atau produk masih ada di order ini. Muat ulang daftar item.');
            $this->koneksi->commit();
            $transaction = false;
        } catch (Throwable $e) {
            if ($transaction) $this->koneksi->rollback();
            throw $e;
        } finally {
            mysqli_report($previousMode);
        }
    }
}
