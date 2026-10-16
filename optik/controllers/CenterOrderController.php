<?php

require_once __DIR__ . '/OrderController.php';
require_once __DIR__ . '/../models/Center.php';

class CenterOrderController {
    private $center;
    private $order;
    private $product;
    private $orders;
    private $base_url;

    public function __construct(Center $center, Order $order, Product $product, OrderController $orders, $base_url = '/center') {
        $this->center = $center;
        $this->order = $order;
        $this->product = $product;
        $this->orders = $orders;
        $this->base_url = rtrim($base_url, '/');
    }

    public function handle($action, array $query, array $post) {
        try {
            if ($action === 'orders') {
                return $this->index($query);
            }

            $id = filter_var($action === 'order' ? ($query['id'] ?? 0) : ($post['order_id'] ?? 0), FILTER_VALIDATE_INT);
            $context = $id > 0 ? $this->order->getAdminOrderById($id) : false;
            if (!$context) {
                return ['view' => 'error', 'status_code' => 404, 'error' => 'Order tidak ditemukan.'];
            }
            $store_id = (int) $context['store_id'];
            if ($action === 'order') {
                return $this->detail($store_id, $id, $context['store_name'] ?? '-');
            }

            if ($action === 'update-order') {
                $this->orders->update($store_id, $post);
                $notice = 'Order berhasil diperbarui.';
            } elseif ($action === 'delete-order') {
                $this->orders->delete($store_id, $id);
                return ['redirect' => $this->base_url . '/orders', 'notice' => 'Order beserta pembayarannya berhasil dihapus.'];
            } else {
                $payment_id = filter_var($post['payment_id'] ?? 0, FILTER_VALIDATE_INT);
                if (!$payment_id || $payment_id < 1) {
                    throw new InvalidArgumentException('Pembayaran tidak valid.');
                }
                if ($action === 'update-payment') {
                    $this->orders->changePaymentMethod($store_id, $id, $payment_id, $post['payment_method'] ?? '');
                    $notice = 'Metode pembayaran berhasil diperbarui.';
                } elseif ($action === 'delete-payment') {
                    $this->orders->deletePayment($store_id, $id, $payment_id);
                    $notice = 'Pembayaran berhasil dihapus.';
                } else {
                    throw new InvalidArgumentException('Aksi order tidak tersedia.');
                }
            }

            return ['redirect' => $this->base_url . '/order?id=' . $id, 'notice' => $notice];
        } catch (InvalidArgumentException $e) {
            return ['view' => 'error', 'status_code' => 400, 'error' => $e->getMessage(), 'return_url' => $this->base_url . '/orders'];
        }
    }

    private function index(array $query) {
        $start = $this->text($query, 'start', date('Y-m-01'));
        $end = $this->text($query, 'end', date('Y-m-t'));
        foreach ([$start, $end] as $date) {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                throw new InvalidArgumentException('Tanggal filter tidak valid.');
            }
        }
        if ($start > $end) {
            throw new InvalidArgumentException('Tanggal awal tidak boleh melewati tanggal akhir.');
        }
        $search = $this->text($query, 'search');
        $store_id = max(0, (int) ($query['store_id'] ?? 0));
        $total = $this->order->getAdminOrders($store_id, $start, $end, $search, 20, 0, true);
        $total_pages = max(1, (int) ceil($total / 20));
        $current_page = min($total_pages, max(1, (int) ($query['hal'] ?? 1)));
        $orders = $this->order->getAdminOrders($store_id, $start, $end, $search, 20, ($current_page - 1) * 20);
        foreach ($orders as &$order) {
            $order = $this->decorateOrder($order);
        }
        unset($order);
        $page_links = [];
        for ($page = max(1, $current_page - 2); $page <= min($total_pages, $current_page + 2); $page++) {
            $page_links[] = [
                'number' => $page,
                'url' => $this->base_url . '/orders?' . http_build_query(['store_id' => $store_id, 'start' => $start, 'end' => $end, 'search' => $search, 'hal' => $page]),
                'active' => $page === $current_page
            ];
        }
        return [
            'view' => 'orders', 'orders' => $orders, 'store_options' => $this->center->getStoreOptions(),
            'store_id' => $store_id, 'start_date' => $start, 'end_date' => $end, 'search' => $search,
            'total_orders' => $total, 'total_pages' => $total_pages, 'current_page' => $current_page, 'page_links' => $page_links
        ];
    }

    private function detail($store_id, $id, $store_name) {
        $order = $this->order->getOrderById($store_id, $id);
        if (!$order) {
            return ['view' => 'error', 'status_code' => 404, 'error' => 'Order tidak ditemukan.'];
        }
        $items = $this->order->getOrderItems($store_id, $id);
        foreach ($items as &$item) {
            $discount = max(0, min(100, (float) $item['diskon']));
            $item['gross_price'] = $discount < 100 ? rtrim(rtrim(sprintf('%.10F', (float) $item['price'] / (1 - $discount / 100)), '0'), '.') : '0';
        }
        unset($item);
        $payments = $this->order->getPayments($store_id, $id);
        foreach ($payments as &$payment) {
            $payment['method_value'] = strtoupper($payment['payment_method']) === 'CASH' ? 'Cash' : 'TF';
            $payment['display_date'] = date('d M Y, H:i', strtotime($payment['create_at']));
        }
        unset($payment);
        return [
            'view' => 'order', 'order' => $this->decorateOrder($order), 'store_name' => $store_name,
            'items' => $items, 'payments' => $payments, 'products' => $this->product->getAllProducts($store_id)
        ];
    }

    private function decorateOrder(array $order) {
        $remaining = (float) $order['total'] - (float) $order['total_dibayar'];
        $order['remaining'] = $remaining;
        $order['status_text'] = $remaining <= 0 ? 'Lunas' : ((float) $order['total_dibayar'] > 0 ? 'DP' : 'Belum Bayar');
        $order['badge_class'] = $remaining <= 0 ? 'bg-success' : ((float) $order['total_dibayar'] > 0 ? 'bg-warning' : 'bg-danger');
        $order['display_date'] = date('d M Y, H:i', strtotime($order['create_at']));
        return $order;
    }

    private function text(array $data, $key, $default = '') {
        return is_string($data[$key] ?? $default) ? trim($data[$key] ?? $default) : $default;
    }
}
