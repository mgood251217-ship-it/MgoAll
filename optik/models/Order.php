<?php

class Order {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getNextInvoiceNumber() {
        return $this->buildNextInvoiceNumber();
    }

    private function buildNextInvoiceNumber() {
        $stmt = $this->pdo->query("SELECT inv_no FROM orders WHERE inv_no REGEXP '-[0-9]{6}$' ORDER BY CAST(SUBSTRING_INDEX(inv_no, '-', -1) AS UNSIGNED) DESC, id DESC LIMIT 1");
        $last_invoice = $stmt->fetchColumn();
        $sequence = $last_invoice === false ? 10000 : (int) substr($last_invoice, -6) + 1;

        if ($sequence > 999999) {
            throw new RuntimeException('Nomor urut invoice sudah mencapai batas 6 angka.');
        }

        return 'INV-' . date('Ymd') . '-' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    public function createTransaction($customer_name, $nomor_konsumen, $items) {
        $lock_name = 'optik_order_invoice_sequence';
        $lock_acquired = false;

        try {
            $lock_stmt = $this->pdo->prepare('SELECT GET_LOCK(:lock_name, 10)');
            $lock_stmt->execute(['lock_name' => $lock_name]);
            $lock_acquired = (int) $lock_stmt->fetchColumn() === 1;

            if (!$lock_acquired) {
                throw new RuntimeException('Tidak dapat membuat nomor invoice. Silakan coba kembali.');
            }

            $this->pdo->beginTransaction();
            $current_time = date('Y-m-d H:i:s');
            $inv_no = $this->buildNextInvoiceNumber();
            $real_grand_total = 0;
            $processed_items = [];

            foreach ($items as $item) {
                $diskon_persen = isset($item['discount']) ? (float)$item['discount'] : 0;
                $potongan_harga = $item['price'] * ($diskon_persen / 100);
                $harga_final    = $item['price'] - $potongan_harga;
                $total_amount   = $harga_final * $item['quantity'];
                $real_grand_total += $total_amount;

                $processed_items[] = [
                    'name'       => $item['name'],
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'price'      => $harga_final,
                    'diskon'     => $diskon_persen,
                    'amount'     => $total_amount
                ];
            }

            $sqlOrder = "INSERT INTO orders (store_id, customer_name, inv_no, nomor, total, create_at) 
                         VALUES (:store_id, :customer_name, :inv_no, :nomor, :total, :create_at)";
            $stmtOrder = $this->pdo->prepare($sqlOrder);

            $stmtOrder->execute([
                'store_id'      => $_SESSION['store_id'],
                'customer_name' => $customer_name,
                'inv_no'        => $inv_no,
                'nomor'         => $nomor_konsumen,
                'total'         =>  floor($real_grand_total / 500) * 500,
                'create_at'     => $current_time
            ]);

            $order_id = $this->pdo->lastInsertId();

            $sqlItem = "INSERT INTO order_items (order_id, name, product_id, quantity, price, diskon, amount) 
                        VALUES (:order_id, :name, :product_id, :quantity, :price, :diskon, :amount)";
            $stmtItem = $this->pdo->prepare($sqlItem);

            foreach ($processed_items as $p_item) {
                $stmtItem->execute([
                    'order_id'   => $order_id,
                    'name'       => $p_item['name'],
                    'product_id' => $p_item['product_id'],
                    'quantity'   => $p_item['quantity'],
                    'price'      => $p_item['price'],
                    'diskon'     => $p_item['diskon'],
                    'amount'     => $p_item['amount']
                ]);
            }

            $this->pdo->commit();
            return ['order_id' => $order_id, 'inv_no' => $inv_no];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        } finally {
            if ($lock_acquired) {
                $release_stmt = $this->pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
                $release_stmt->execute(['lock_name' => $lock_name]);
            }
        }
    }

    public function getOrders($start_date, $end_date, $search = '') {
        try {
            $sql = "SELECT * FROM orders 
                    WHERE store_id = :store_id 
                    AND DATE(create_at) >= :start_date 
                    AND DATE(create_at) <= :end_date";
            
            $params = [
                'store_id'   => $_SESSION['store_id'],
                'start_date' => $start_date,
                'end_date'   => $end_date
            ];

            if (!empty($search)) {
                $sql .= " AND (customer_name LIKE :search OR inv_no LIKE :search OR nomor LIKE :search)";
                $params['search'] = "%$search%";
            }

            $sql .= " ORDER BY create_at DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Error ambil riwayat order: " . $e->getMessage());
        }
    }

    public function getOrderItems($order_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM order_items WHERE order_id = :id");
            $stmt->execute(['id' => $order_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function addPayment($order_id, $nominal, $method, $info) {
        try {
            $current_time = date('Y-m-d H:i:s');

            $sql = "INSERT INTO payments (store_id, order_id, nominal, payment_method, information, create_at) 
                    VALUES (:store_id, :order_id, :nominal, :method, :info, :create_at)";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                'store_id'  => $_SESSION['store_id'],
                'order_id'  => $order_id,
                'nominal'   => $nominal,
                'method'    => $method,
                'info'      => $info,
                'create_at' => $current_time
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function deleteOrder($id){
        try {
            $queries = [
                    "DELETE FROM order_items WHERE order_id = :id",
                    "DELETE FROM payments WHERE order_id = :id",
                    "DELETE FROM orders WHERE id = :id"
                ];

            foreach ($queries as $query) {
                $stmt = $this->pdo->prepare($query);
                $stmt->execute([
                    ':id' => $id
                ]);
            }
            $this->pdo->commit();
            return true;

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
}