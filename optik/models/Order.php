<?php

class Order {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function acquireInvoiceLock() {
        $stmt = $this->pdo->prepare('SELECT GET_LOCK(:lock_name, 10)');
        $stmt->execute(['lock_name' => 'optik_order_invoice_sequence']);
        return (int) $stmt->fetchColumn() === 1;
    }

    public function releaseInvoiceLock() {
        $stmt = $this->pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $stmt->execute(['lock_name' => 'optik_order_invoice_sequence']);
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

    public function getLastInvoiceNumber() {
        $stmt = $this->pdo->query("SELECT inv_no FROM orders WHERE inv_no REGEXP '-[0-9]{6}$' ORDER BY CAST(SUBSTRING_INDEX(inv_no, '-', -1) AS UNSIGNED) DESC, id DESC LIMIT 1");
        return $stmt->fetchColumn();
    }

    public function insertOrder(array $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO orders (store_id, customer_name, inv_no, nomor, total, create_at)
            VALUES (:store_id, :customer_name, :inv_no, :nomor, :total, :create_at)
        ");

        if (!$stmt->execute($data)) {
            throw new RuntimeException('Pesanan gagal disimpan.');
        }

        return $this->pdo->lastInsertId();
    }

    public function insertOrderItem(array $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO order_items (order_id, name, product_id, quantity, price, diskon, amount)
            VALUES (:order_id, :name, :product_id, :quantity, :price, :diskon, :amount)
        ");

        if (!$stmt->execute($data)) {
            throw new RuntimeException('Item pesanan gagal disimpan.');
        }
    }

    public function getOrders($store_id, $start_date, $end_date, $search = '') {
        $sql = "
            SELECT o.*, COALESCE(p.total_dibayar, 0) as total_dibayar
            FROM orders o
            LEFT JOIN (
                SELECT order_id, SUM(nominal) as total_dibayar
                FROM payments WHERE store_id = :payment_store_id GROUP BY order_id
            ) p ON p.order_id = o.id
            WHERE o.store_id = :store_id
                AND DATE(o.create_at) >= :start_date
                AND DATE(o.create_at) <= :end_date
        ";
        $params = [
            'payment_store_id' => $store_id,
            'store_id' => $store_id,
            'start_date' => $start_date,
            'end_date' => $end_date
        ];

        if ($search !== '') {
            $sql .= ' AND (o.customer_name LIKE :customer_search OR o.inv_no LIKE :invoice_search OR o.nomor LIKE :phone_search)';
            $params['customer_search'] = '%' . $search . '%';
            $params['invoice_search'] = '%' . $search . '%';
            $params['phone_search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY o.create_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrderById($store_id, $id, $lock = false) {
        $sql = "
            SELECT o.*, (
                SELECT COALESCE(SUM(p.nominal), 0) FROM payments p
                WHERE p.order_id = o.id AND p.store_id = o.store_id
            ) as total_dibayar
            FROM orders o WHERE o.id = :id AND o.store_id = :store_id
        ";
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id, 'store_id' => $store_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getOrderItems($store_id, $order_id) {
        $stmt = $this->pdo->prepare("
            SELECT oi.*, COALESCE(p.info, '') as info, COALESCE(p.color, '') as color
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            LEFT JOIN products p ON p.id = oi.product_id AND p.store_id = o.store_id
            WHERE oi.order_id = :order_id AND o.store_id = :store_id
        ");
        $stmt->execute(['order_id' => $order_id, 'store_id' => $store_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertPayment(array $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO payments (store_id, order_id, nominal, payment_method, information, create_at)
            VALUES (:store_id, :order_id, :nominal, :method, :info, :create_at)
        ");
        return $stmt->execute($data);
    }

    public function getPaymentDates($store_id, $order_id) {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT DATE(create_at) as date FROM payments
            WHERE store_id = :store_id AND order_id = :order_id
        ");
        $stmt->execute(['store_id' => $store_id, 'order_id' => $order_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteOrderItems($store_id, $id) {
        $stmt = $this->pdo->prepare("
            DELETE oi FROM order_items oi JOIN orders o ON o.id = oi.order_id
            WHERE o.id = :id AND o.store_id = :store_id
        ");
        return $stmt->execute(['id' => $id, 'store_id' => $store_id]);
    }

    public function deletePayments($store_id, $order_id) {
        $stmt = $this->pdo->prepare('DELETE FROM payments WHERE order_id = :id AND store_id = :store_id');
        return $stmt->execute(['id' => $order_id, 'store_id' => $store_id]);
    }

    public function deleteOrder($store_id, $id) {
        $stmt = $this->pdo->prepare('DELETE FROM orders WHERE id = :id AND store_id = :store_id');
        return $stmt->execute(['id' => $id, 'store_id' => $store_id]);
    }

    public function getAdminOrders($store_id, $start_date, $end_date, $search, $limit = 20, $offset = 0, $count = false) {
        $where = 'DATE(o.create_at) >= :start_date AND DATE(o.create_at) <= :end_date';
        $params = ['start_date' => $start_date, 'end_date' => $end_date];
        if ($store_id > 0) {
            $where .= ' AND o.store_id = :store_id';
            $params['store_id'] = $store_id;
        }
        if ($search !== '') {
            $where .= ' AND (o.inv_no LIKE :invoice OR o.customer_name LIKE :customer OR o.nomor LIKE :phone)';
            $params += ['invoice' => '%' . $search . '%', 'customer' => '%' . $search . '%', 'phone' => '%' . $search . '%'];
        }
        $sql = $count
            ? "SELECT COUNT(*) FROM orders o WHERE $where"
            : "SELECT o.*, s.name AS store_name,
                (SELECT COALESCE(SUM(p.nominal), 0) FROM payments p WHERE p.order_id = o.id AND p.store_id = o.store_id) AS total_dibayar
                FROM orders o LEFT JOIN stores s ON s.id = o.store_id WHERE $where
                ORDER BY o.create_at DESC, o.id DESC LIMIT " . max(1, (int) $limit) . ' OFFSET ' . max(0, (int) $offset);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $count ? (int) $stmt->fetchColumn() : $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAdminOrderById($id) {
        $stmt = $this->pdo->prepare('SELECT o.id, o.store_id, s.name AS store_name FROM orders o LEFT JOIN stores s ON s.id = o.store_id WHERE o.id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateOrder($store_id, $id, array $data) {
        $stmt = $this->pdo->prepare('UPDATE orders SET customer_name = :customer_name, nomor = :nomor, total = :total WHERE id = :id AND store_id = :store_id');
        return $stmt->execute($data + ['id' => $id, 'store_id' => $store_id]);
    }

    public function getPayments($store_id, $order_id) {
        $stmt = $this->pdo->prepare('SELECT p.* FROM payments p JOIN orders o ON o.id = p.order_id AND o.store_id = p.store_id WHERE p.store_id = :store_id AND p.order_id = :order_id ORDER BY p.create_at DESC, p.id DESC');
        $stmt->execute(['store_id' => $store_id, 'order_id' => $order_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPaymentById($store_id, $order_id, $id) {
        $stmt = $this->pdo->prepare('SELECT * FROM payments WHERE id = :id AND order_id = :order_id AND store_id = :store_id');
        $stmt->execute(['id' => $id, 'order_id' => $order_id, 'store_id' => $store_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updatePaymentMethod($store_id, $order_id, $id, $method) {
        $stmt = $this->pdo->prepare('UPDATE payments SET payment_method = :method WHERE id = :id AND order_id = :order_id AND store_id = :store_id');
        return $stmt->execute(['method' => $method, 'id' => $id, 'order_id' => $order_id, 'store_id' => $store_id]);
    }

    public function deletePayment($store_id, $order_id, $id) {
        $stmt = $this->pdo->prepare('DELETE FROM payments WHERE id = :id AND order_id = :order_id AND store_id = :store_id');
        return $stmt->execute(['id' => $id, 'order_id' => $order_id, 'store_id' => $store_id]);
    }

    public function countDailyOrders($store_id, $date) {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM orders WHERE store_id = ? AND DATE(create_at) = ?');
        $stmt->execute([$store_id, $date]);
        return $stmt->fetchColumn();
    }

    public function countUnpaidOrders($store_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM orders o
            LEFT JOIN (
                SELECT order_id, SUM(nominal) as paid FROM payments
                WHERE store_id = :payment_store_id GROUP BY order_id
            ) p ON o.id = p.order_id
            WHERE o.store_id = :store_id AND (p.paid IS NULL OR p.paid < o.total)
        ");
        $stmt->execute(['payment_store_id' => $store_id, 'store_id' => $store_id]);
        return $stmt->fetchColumn();
    }
}
