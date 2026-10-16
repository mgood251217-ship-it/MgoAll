<?php

require_once __DIR__ . '/../models/Center.php';

class CenterController {
    private $center;
    private $base_url;
    private $orders;

    public function __construct(Center $center, $base_url = '/center', $orders = null) {
        $this->center = $center;
        $this->base_url = rtrim($base_url, '/');
        $this->orders = $orders;
    }

    public function handle($action, array $query, array $post, array &$session, $request_method = 'GET') {
        if (empty($session['center_csrf_token'])) {
            $session['center_csrf_token'] = bin2hex(random_bytes(32));
        }

        if ($action === 'login') {
            return $this->login($post, $session, $request_method);
        }

        $admin = isset($session['center_admin']['id'])
            ? $this->center->findById($session['center_admin']['id'])
            : false;
        if (!$admin) {
            unset($session['center_admin']);
            return ['redirect' => $this->base_url . '/login'];
        }

        if ($action === 'logout') {
            if ($request_method !== 'POST') {
                return ['view' => 'error', 'status_code' => 405, 'error' => 'Gunakan tombol Keluar untuk mengakhiri sesi.'];
            }
            if (!$this->validToken($post, $session)) {
                return ['view' => 'error', 'status_code' => 419, 'error' => 'Sesi formulir berakhir. Muat ulang halaman dan coba lagi.'];
            }
            unset($session['center_admin']);
            $session['center_csrf_token'] = bin2hex(random_bytes(32));
            return ['redirect' => $this->base_url . '/login', 'regenerate_session' => true];
        }

        $data = ['admin' => $admin, 'csrf_token' => $session['center_csrf_token']];
        $order_actions = ['orders', 'order', 'update-order', 'delete-order', 'update-payment', 'delete-payment'];
        if (in_array($action, $order_actions, true)) {
            $writing = !in_array($action, ['orders', 'order'], true);
            if ($request_method !== ($writing ? 'POST' : 'GET')) {
                return $data + ['view' => 'error', 'status_code' => 405, 'error' => 'Metode request tidak sesuai.'];
            }
            if ($writing && !$this->validToken($post, $session)) {
                return $data + ['view' => 'error', 'status_code' => 419, 'error' => 'Sesi formulir berakhir. Muat ulang halaman dan coba lagi.'];
            }
            if (!$this->orders) {
                throw new RuntimeException('Pengelolaan order belum tersedia.');
            }
            $result = $this->orders->handle($action, $query, $post);
            if (isset($result['redirect'], $result['notice'])) {
                $session['center_notice'] = $result['notice'];
            } elseif (!$writing && isset($session['center_notice'])) {
                $result['notice'] = $session['center_notice'];
                unset($session['center_notice']);
            }
            return $data + $result;
        }

        if ($request_method !== 'GET') {
            return ['view' => 'error', 'status_code' => 405, 'error' => 'Metode request tidak sesuai.'];
        }

        if ($action === '' || $action === 'dashboard') {
            return $data + [
                'view' => 'dashboard',
                'summary' => $this->center->getSummary(),
                'stores' => $this->center->getStores('', 5)
            ];
        }

        if ($action === 'stores') {
            $search = is_string($query['search'] ?? '') ? trim($query['search'] ?? '') : '';
            $total_stores = $this->center->countStores($search);
            $total_pages = max(1, (int) ceil($total_stores / 15));
            $current_page = min($total_pages, max(1, (int) ($query['hal'] ?? 1)));
            $page_links = [];
            for ($page = max(1, $current_page - 2); $page <= min($total_pages, $current_page + 2); $page++) {
                $page_links[] = [
                    'number' => $page,
                    'url' => $this->base_url . '/stores?' . http_build_query(['search' => $search, 'hal' => $page]),
                    'active' => $page === $current_page
                ];
            }

            return $data + [
                'view' => 'stores',
                'stores' => $this->center->getStores($search, 15, ($current_page - 1) * 15),
                'search' => $search,
                'total_stores' => $total_stores,
                'current_page' => $current_page,
                'total_pages' => $total_pages,
                'page_links' => $page_links
            ];
        }

        return $data + ['view' => 'error', 'status_code' => 404, 'error' => 'Halaman tidak ditemukan.'];
    }

    private function login(array $post, array &$session, $request_method) {
        if (isset($session['center_admin']['id'])) {
            if ($this->center->findById($session['center_admin']['id'])) {
                return ['redirect' => $this->base_url . '/'];
            }
            unset($session['center_admin']);
        }

        $data = ['view' => 'login', 'error' => '', 'username' => '', 'csrf_token' => $session['center_csrf_token']];
        if ($request_method === 'GET') {
            return $data;
        }
        if ($request_method !== 'POST') {
            return array_replace($data, ['status_code' => 405, 'error' => 'Metode request tidak sesuai.']);
        }
        if (!$this->validToken($post, $session)) {
            return array_replace($data, ['status_code' => 419, 'error' => 'Sesi formulir berakhir. Muat ulang halaman dan coba lagi.']);
        }

        $username = is_string($post['username'] ?? null) ? trim($post['username']) : '';
        $password = is_string($post['password'] ?? null) ? $post['password'] : '';
        $account = $username !== '' && $password !== '' ? $this->center->findByUsername($username) : false;
        if (!$account || !password_verify($password, $account['password'])) {
            return array_replace($data, ['username' => $username, 'error' => 'Username atau password salah.']);
        }

        $session['center_admin'] = ['id' => $account['id'], 'username' => $account['username']];
        $session['center_csrf_token'] = bin2hex(random_bytes(32));
        return ['redirect' => $this->base_url . '/', 'regenerate_session' => true];
    }

    private function validToken(array $post, array $session) {
        return is_string($post['csrf_token'] ?? null) && hash_equals($session['center_csrf_token'], $post['csrf_token']);
    }
}
