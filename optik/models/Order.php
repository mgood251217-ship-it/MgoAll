<?php

class Order {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function createTransaction($customer_name, $inv_no, $nomor_konsumen, $total_harga, $items) {
        try {
            $this->pdo->beginTransaction();

            $current_time = date('Y-m-d H:i:s');
            
            // 1. Kalkulasi Ulang Total & Harga Diskon secara Aman di Backend
            $real_grand_total = 0;
            $processed_items = [];

            foreach ($items as $item) {
                // Tangkap persen diskon
                $diskon_persen = isset($item['discount']) ? (float)$item['discount'] : 0;
                
                // Kalkulasi harga
                $potongan_harga = $item['price'] * ($diskon_persen / 100);
                $harga_final    = $item['price'] - $potongan_harga; // <-- Harga setelah diskon
                $total_amount   = $harga_final * $item['quantity']; // <-- Price x Quantity

                // Jumlahkan ke total keseluruhan untuk tabel orders
                $real_grand_total += $total_amount;

                // Simpan sementara ke array agar mudah di-insert nanti
                $processed_items[] = [
                    'name'       => $item['name'],
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'price'      => $harga_final,     // Menyimpan harga yang SUDAH DIDISKON
                    'diskon'     => $diskon_persen,
                    'amount'     => $total_amount     // Harga final x Quantity
                ];
            }

            // 2. Insert ke tabel `orders` menggunakan Total Harga yang Valid
            $sqlOrder = "INSERT INTO orders (store_id, customer_name, inv_no, nomor, total, create_at) 
                         VALUES (:store_id, :customer_name, :inv_no, :nomor, :total, :create_at)";
            $stmtOrder = $this->pdo->prepare($sqlOrder);

            $stmtOrder->execute([
                'store_id'      => $_SESSION['store_id'],
                'customer_name' => $customer_name,
                'inv_no'        => $inv_no,
                'nomor'         => $nomor_konsumen,
                'total'         => $real_grand_total, // <-- Terisi total yang sudah terpotong diskon
                'create_at'     => $current_time
            ]);

            $order_id = $this->pdo->lastInsertId();

            // 3. Insert ke tabel `order_items` menggunakan data hasil kalkulasi
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
            return $order_id;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            die("Transaksi Gagal: " . $e->getMessage());
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
}