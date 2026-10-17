<?php

require_once __DIR__ . '/../models/User.php';

class AuthController {
    private $userModel;
    private $base_url;

    public function __construct(User $userModel, $base_url = '') {
        $this->userModel = $userModel;
        $this->base_url = rtrim($base_url, '/');
    }

    public function login(array $post, array &$session, $request_method = 'GET') {
        if (!empty($session['user_logged_in'])) {
            return ['redirect' => $this->base_url . '/order'];
        }
        if (empty($session['auth_csrf_token'])) {
            $session['auth_csrf_token'] = bin2hex(random_bytes(32));
        }
        $data = [
            'error' => '', 'username' => '', 'csrf_token' => $session['auth_csrf_token'],
            'login_action' => $this->base_url . '/index.php?page=login'
        ];
        if ($request_method === 'GET') {
            return $data;
        }
        if ($request_method !== 'POST') {
            return array_replace($data, ['status_code' => 405, 'error' => 'Metode request tidak sesuai.']);
        }
        if (!is_string($post['csrf_token'] ?? null) || !hash_equals($session['auth_csrf_token'], $post['csrf_token'])) {
            return array_replace($data, ['status_code' => 419, 'error' => 'Sesi formulir berakhir. Muat ulang halaman dan coba lagi.']);
        }
        $username = is_string($post['username'] ?? null) ? trim($post['username']) : '';
        $password = is_string($post['password'] ?? null) ? $post['password'] : '';
        $data['username'] = $username;
        if ($username === '' || $password === '') {
            return array_replace($data, ['error' => 'Username dan password wajib diisi.']);
        }
        try {
            $user = $this->userModel->getUserByUsername($username);
        } catch (PDOException $e) {
            error_log((string) $e);
            return array_replace($data, ['status_code' => 500, 'error' => 'Login belum dapat diproses. Silakan coba lagi.']);
        }
        if (!$user || !is_string($user['password'] ?? null) || !password_verify($password, $user['password'])) {
            return array_replace($data, ['error' => 'Username atau password salah.']);
        }
        $session['user_logged_in'] = true;
        $session['user_id'] = $user['id'];
        $session['nama_user'] = $user['name'] ?? '';
        $session['user_img'] = $user['img'] ?? 'default-user.png';
        $session['store_id'] = $user['store_id'];
        $session['store_name'] = $user['store_name'] ?? '';
        $session['store_img'] = $user['store_img'] ?? 'default-store.png';
        $session['store_address'] = $user['store_address'] ?? '';
        $session['auth_csrf_token'] = bin2hex(random_bytes(32));
        return ['redirect' => $this->base_url . '/order', 'regenerate_session' => true];
    }

    public function logout(array &$session) {
        foreach (['user_logged_in', 'user_id', 'nama_user', 'user_img', 'store_id', 'store_name', 'store_img', 'store_address', 'auth_csrf_token'] as $key) {
            unset($session[$key]);
        }
        return ['redirect' => $this->base_url . '/login', 'regenerate_session' => true];
    }
}
