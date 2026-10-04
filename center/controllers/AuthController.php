<?php
require_once __DIR__ . '/../functions/helpers.php';

class AuthController {
    
    public function login() {
        configureCenterSession();
        session_start();
        require_once __DIR__ . "/../config/connect.php";
        global $koneksi;

        $host = strtolower((string)parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), PHP_URL_HOST));
        $is_localhost = isCenterLocalRequest();
        $secret_key = $_ENV['RECAPTCHA_SECRET_KEY'] ?? '';

        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $username_input = strtolower(trim((string)($_POST['usernames'] ?? '')));
            $password = (string)($_POST['password'] ?? '');
            $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
            $login_ok = true;
            $pesan_error = '';
            $submittedToken = $_POST['csrf_token'] ?? '';
            $sessionToken = $_SESSION['login_csrf_token'] ?? '';
            unset($_SESSION['login_csrf_token']);

            if (!is_string($submittedToken) || !is_string($sessionToken)
                || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)
            ) {
                $_SESSION['login_error'] = 'Permintaan login tidak valid. Silakan coba lagi.';
                header("Location: /login");
                exit;
            }

            if (!$is_localhost) {
                if ($secret_key === '' || !is_string($recaptcha_response) || $recaptcha_response === '') {
                    $login_ok = false;
                } else {
                    $verify_url = "https://www.google.com/recaptcha/api/siteverify";
                    $context = stream_context_create([
                        'http' => [
                            'method' => 'POST',
                            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                            'content' => http_build_query([
                                'secret' => $secret_key,
                                'response' => $recaptcha_response
                            ]),
                            'timeout' => 5,
                            'ignore_errors' => true
                        ]
                    ]);
                    $response = @file_get_contents($verify_url, false, $context);
                    $response_keys = is_string($response) ? json_decode($response, true) : null;

                    $login_ok = is_array($response_keys)
                        && !empty($response_keys['success'])
                        && isset($response_keys['score'], $response_keys['action'], $response_keys['hostname'])
                        && (float)$response_keys['score'] >= 0.5
                        && $response_keys['action'] === 'login'
                        && strcasecmp((string)$response_keys['hostname'], $host) === 0;
                }
                if (!$login_ok) $pesan_error = 'Login tidak berhasil. Periksa data dan coba lagi.';
            }

            if ($login_ok) {
                $sql = "SELECT administrator_id, username, name, password, access FROM administrator WHERE LOWER(username) = ?";
                $stmt = $koneksi->prepare($sql);
                $stmt->bind_param("s", $username_input);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();

                    if (password_verify($password, $user['password'])) {
                        unset($user['password']);
                        $_SESSION = [];
                        session_regenerate_id(true);
                        $this->clearHostOnlySessionCookie();

                        $_SESSION['admin_logged_in'] = [
                            'administrator_id' => startEnk('enk', $user['administrator_id']),
                            'username'         => startEnk('enk', $user['username']),
                            'access'           => startEnk('enk', $user['access'])
                        ];

                        $this->clearLegacyAuthCookies();

                        header("Location: /dashboard");
                        exit;
                    } else {
                        $pesan_error = 'Login tidak berhasil. Periksa data dan coba lagi.';
                    }
                } else {
                    $pesan_error = 'Login tidak berhasil. Periksa data dan coba lagi.';
                }
                $stmt->close();
            }

            if ($pesan_error) {
                $_SESSION['login_error'] = $pesan_error;
                header("Location: /login");
                exit;
            }
        }
    }
    
    public function logout() {
        configureCenterSession();
        session_start();
        $sessionCookie = session_name();
        $sessionParams = session_get_cookie_params();
        $_SESSION = [];
        session_destroy();

        $clearSessionOptions = [
            'expires' => time() - 3600,
            'path' => $sessionParams['path'] ?: '/',
            'secure' => $sessionParams['secure'],
            'httponly' => $sessionParams['httponly'],
            'samesite' => $sessionParams['samesite'] ?? 'Strict'
        ];
        if (!empty($sessionParams['domain'])) {
            $clearSessionOptions['domain'] = $sessionParams['domain'];
        }
        setcookie($sessionCookie, '', $clearSessionOptions);

        $hostOnlySessionOptions = $clearSessionOptions;
        unset($hostOnlySessionOptions['domain']);
        setcookie($sessionCookie, '', $hostOnlySessionOptions);

        $domainSessionOptions = $clearSessionOptions;
        $domainSessionOptions['domain'] = 'mgood.my.id';
        setcookie($sessionCookie, '', $domainSessionOptions);
        $this->clearLegacyAuthCookies();
        
        header('Location: /login');
        exit;
    }

    private function clearHostOnlySessionCookie() {
        $sessionParams = session_get_cookie_params();
        if (empty($sessionParams['domain'])) {
            return;
        }

        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $sessionParams['path'] ?: '/',
            'secure' => $sessionParams['secure'],
            'httponly' => $sessionParams['httponly'],
            'samesite' => $sessionParams['samesite'] ?? 'Strict'
        ]);
    }

    private function clearLegacyAuthCookies() {
        $options = [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => 'mgood.my.id',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'None'
        ];

        foreach (['admin_administrator_id', 'admin_username', 'admin_access'] as $cookieName) {
            setcookie($cookieName, '', $options);
        }
    }
    
}