<?php

require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/FinanceController.php';

class OrderController {
    private $order;
    private $product;
    private $finance;

    public function __construct(Order $order, Product $product, FinanceController $finance) {
        $this->order = $order;
        $this->product = $product;
        $this->finance = $finance;
    }

    public function index($store_id, array $query) {
        $start_date = $query['start'] ?? date('Y-m-01');
        $end_date = $query['end'] ?? date('Y-m-t');
        $search = $query['search'] ?? '';
        $orders = $this->order->getOrders($store_id, $start_date, $end_date, $search);

        foreach ($orders as &$order) {
            $order['total_dibayar'] = (float) $order['total_dibayar'];
            $order['sisa_tagihan'] = (float) $order['total'] - $order['total_dibayar'];
            $order['is_lunas'] = $order['sisa_tagihan'] <= 0;
            $order['status_text'] = $order['is_lunas'] ? 'Lunas' : ($order['total_dibayar'] > 0 ? 'DP' : 'Belum Bayar');
            $order['badge_class'] = $order['is_lunas'] ? 'bg-success' : ($order['total_dibayar'] > 0 ? 'bg-warning' : 'bg-danger');
            $order['display_date'] = date('d M Y, H:i', strtotime($order['create_at']));
            $phone = preg_replace('/[^0-9]/', '', $order['nomor'] ?? '');
            $order['wa_number'] = substr($phone, 0, 1) === '0' ? '62' . substr($phone, 1) : $phone;
        }
        unset($order);

        return [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'search_query' => $search,
            'orders' => $orders
        ];
    }

    public function create($store_id, array $query) {
        $limit = 14;
        $hal = max(1, (int) ($query['hal'] ?? 1));
        $brand_id = $query['brand'] ?? '';
        $search = $query['search'] ?? '';
        $brands = $this->product->getBrandsByStore($store_id);
        $total_products = $this->product->countProductsByStore($store_id, $brand_id, $search);
        $products = $this->product->getProductsByStorePaginated($store_id, $brand_id, $search, $limit, ($hal - 1) * $limit);
        $mapped_products = array_map(function ($product) {
            return [
                'id' => $product['id'],
                'code' => $product['product_code'] ?? '',
                'name' => $product['name'] ?? '',
                'price' => $product['price'] ?? 0,
                'maxStock' => $product['stock'] ?? 0,
                'img' => $product['img'] ?? '',
                'info' => $product['info'] ?? ''
            ];
        }, $products);

        return [
            'hal' => $hal,
            'brand_id' => $brand_id,
            'search' => $search,
            'brands' => $brands,
            'total_products' => $total_products,
            'total_pages' => (int) ceil($total_products / $limit),
            'produk_db' => $products,
            'mapped_products' => $mapped_products
        ];
    }

    public function catalog(array $data) {
        return [
            'products' => $data['mapped_products'],
            'total_pages' => $data['total_pages'],
            'current_page' => $data['hal']
        ];
    }

    public function dispatch($action, $store_id, array $query, $body, array $session) {
        switch ($action) {
            case 'next-invoice':
                return ['inv_no' => $this->getNextInvoiceNumber()];
            case 'store':
                return $this->store($store_id, $this->decodeInput($body));
            case 'api_detail':
                return $this->detail($store_id, (int) ($query['id'] ?? 0));
            case 'api_pay':
                return $this->pay($store_id, $this->decodeInput($body));
            case 'api_delete':
                return $this->delete($store_id, (int) ($query['id'] ?? 0));
            case 'print':
                return $this->printData($store_id, (int) ($query['id'] ?? 0), $session);
        }

        throw new InvalidArgumentException('Aksi pesanan tidak tersedia.');
    }

    public function getNextInvoiceNumber() {
        $last_invoice = $this->order->getLastInvoiceNumber();
        $sequence = $last_invoice === false ? 10000 : (int) substr($last_invoice, -6) + 1;

        if ($sequence > 999999) {
            throw new RuntimeException('Nomor urut invoice sudah mencapai batas 6 angka.');
        }

        return 'INV-' . date('Ymd') . '-' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    public function store($store_id, array $data) {
        $customer_name = trim($data['customer_name'] ?? '');

        if ($customer_name === '' || empty($data['items']) || !is_array($data['items'])) {
            throw new InvalidArgumentException('Nama pelanggan dan data keranjang wajib diisi.');
        }

        $items = [];
        $total = 0;

        foreach ($data['items'] as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Data item pesanan tidak valid.');
            }

            $product_id = filter_var($item['product_id'] ?? 0, FILTER_VALIDATE_INT);
            $quantity = filter_var($item['quantity'] ?? 0, FILTER_VALIDATE_INT);
            $price = $item['price'] ?? null;
            $discount = $item['discount'] ?? 0;

            if (!$product_id || $product_id < 1 || !$quantity || $quantity < 1 || !is_numeric($price) || !is_numeric($discount)) {
                throw new InvalidArgumentException('Produk, jumlah, harga, atau diskon tidak valid.');
            }

            $price = (float) $price;
            $discount = (float) $discount;

            if (!is_finite($price) || !is_finite($discount) || $price < 0 || $discount < 0 || $discount > 100) {
                throw new InvalidArgumentException('Harga atau diskon tidak valid.');
            }

            $product = $this->product->getProductById($product_id);

            if (!$product || (int) $product['store_id'] !== (int) $store_id) {
                throw new InvalidArgumentException('Produk tidak ditemukan di toko ini.');
            }

            $final_price = $price - $price * ($discount / 100);
            $amount = $final_price * $quantity;
            $total += $amount;
            $items[] = [
                'name' => $product['name'],
                'product_id' => $product_id,
                'quantity' => $quantity,
                'price' => $final_price,
                'diskon' => $discount,
                'amount' => $amount
            ];
        }

        if (!$this->order->acquireInvoiceLock()) {
            throw new RuntimeException('Tidak dapat membuat nomor invoice. Silakan coba kembali.');
        }

        try {
            return $this->order->transaction(function () use ($store_id, $customer_name, $data, $total, $items) {
                $inv_no = $this->getNextInvoiceNumber();
                $order_id = $this->order->insertOrder([
                    'store_id' => $store_id,
                    'customer_name' => $customer_name,
                    'inv_no' => $inv_no,
                    'nomor' => $data['nomor'] ?? '',
                    'total' => floor($total / 500) * 500,
                    'create_at' => date('Y-m-d H:i:s')
                ]);

                foreach ($items as $item) {
                    $this->order->insertOrderItem(['order_id' => $order_id] + $item);
                }

                return [
                    'status' => 'success',
                    'message' => 'Pesanan berhasil disimpan',
                    'order_id' => $order_id,
                    'inv_no' => $inv_no
                ];
            });
        } finally {
            $this->order->releaseInvoiceLock();
        }
    }

    public function detail($store_id, $id) {
        $this->findOrder($store_id, $id);
        return $this->order->getOrderItems($store_id, $id);
    }

    public function pay($store_id, array $data) {
        $id = (int) ($data['order_id'] ?? 0);
        $nominal = $data['nominal'] ?? null;

        if ($id < 1 || !is_numeric($nominal) || !is_finite((float) $nominal) || (float) $nominal <= 0) {
            throw new InvalidArgumentException('Data pembayaran tidak valid.');
        }

        return $this->order->transaction(function () use ($store_id, $id, $nominal, $data) {
            $order = $this->findOrder($store_id, $id);
            $remaining = (float) $order['total'] - (float) $order['total_dibayar'];
            $method = strtoupper(trim($data['payment_method'] ?? 'Cash'));
            $today = date('Y-m-d');
            $saved = $this->order->insertPayment([
                'store_id' => $store_id,
                'order_id' => $id,
                'nominal' => (float) $nominal,
                'method' => in_array($method, ['TF', 'TRANSFER'], true) ? 'TF' : 'Cash',
                'info' => (float) $nominal >= $remaining ? 'Lunas' : 'DP',
                'create_at' => date('Y-m-d H:i:s')
            ]);

            if (!$saved || !$this->finance->refreshDailyFinance($store_id, $today)) {
                throw new RuntimeException('Pembayaran gagal disimpan.');
            }

            return ['status' => 'success'];
        });
    }

    public function delete($store_id, $id) {
        return $this->order->transaction(function () use ($store_id, $id) {
            $this->findOrder($store_id, $id);
            $dates = $this->order->getPaymentDates($store_id, $id);

            if (!$this->order->deleteOrderItems($store_id, $id) || !$this->order->deletePayments($store_id, $id) || !$this->order->deleteOrder($store_id, $id)) {
                throw new RuntimeException('Pesanan gagal dihapus.');
            }

            foreach ($dates as $date) {
                if (!$this->finance->refreshDailyFinance($store_id, $date['date'])) {
                    throw new RuntimeException('Ringkasan pembayaran gagal diperbarui.');
                }
            }

            return ['success' => true];
        });
    }

    public function printData($store_id, $id, array $session) {
        $order = $this->findOrder($store_id, $id);
        $paid = (float) $order['total_dibayar'];

        return [
            'status' => 'success',
            'order' => $order,
            'items' => $this->order->getOrderItems($store_id, $id),
            'payment' => [
                'total_dibayar' => $paid,
                'sisa' => (float) $order['total'] - $paid
            ],
            'store' => [
                'name' => $session['store_name'] ?? 'OPTIK KITA',
                'img' => $session['store_img'] ?? '',
                'address' => $session['store_address'] ?? ''
            ]
        ];
    }

    public function getDashboardData($store_id) {
        return [
            'order_hari_ini' => $this->order->countDailyOrders($store_id, date('Y-m-d')) ?: 0,
            'order_belum_lunas' => $this->order->countUnpaidOrders($store_id) ?: 0
        ];
    }

    private function findOrder($store_id, $id) {
        $order = $this->order->getOrderById($store_id, $id);

        if (!$order) {
            throw new RuntimeException('Pesanan tidak ditemukan.');
        }

        return $order;
    }

    private function decodeInput($body) {
        $data = json_decode($body, true);

        if (!is_array($data)) {
            throw new InvalidArgumentException('Format data pesanan tidak valid.');
        }

        return $data;
    }
}
