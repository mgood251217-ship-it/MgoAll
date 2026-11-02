<?php

class OrderDiscount {
    private $koneksi;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
    }

    public function getByOrder($order_id, $store_id) {
        $stmt = $this->koneksi->prepare("SELECT d.product_id, d.diskon AS discount,
                COALESCE(p.name, CONCAT('Produk #', d.product_id)) AS product_name,
                NOT EXISTS(SELECT 1 FROM order_items oi WHERE oi.order_id = d.order_id AND oi.product_id = d.product_id) AS can_delete
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

    public function delete($order_id, $product_id, $store_id) {
        if ($order_id <= 0 || $product_id <= 0 || $store_id <= 0) throw new InvalidArgumentException('Order dan produk tidak valid.');
        $transaction = false;
        $driver = new mysqli_driver();
        $previousMode = $driver->report_mode;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $this->koneksi->begin_transaction();
            $transaction = true;
            $stmt = $this->koneksi->prepare('SELECT order_id FROM orders WHERE order_id = ? AND store_id = ? FOR UPDATE');
            $stmt->bind_param('ii', $order_id, $store_id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$order) throw new InvalidArgumentException('Order tidak ditemukan pada toko ini.');
            $stmt = $this->koneksi->prepare('SELECT order_item_id FROM order_items WHERE order_id = ? AND product_id = ? LIMIT 1 FOR UPDATE');
            $stmt->bind_param('ii', $order_id, $product_id);
            $stmt->execute();
            $hasItem = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            if ($hasItem) throw new InvalidArgumentException('Hapus semua item produk ini dari order terlebih dahulu sebelum menghapus diskonnya.');
            $stmt = $this->koneksi->prepare('DELETE d FROM diskon_order_items d WHERE d.order_id = ? AND d.product_id = ?
                AND NOT EXISTS(SELECT 1 FROM order_items oi WHERE oi.order_id = d.order_id AND oi.product_id = d.product_id)');
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
