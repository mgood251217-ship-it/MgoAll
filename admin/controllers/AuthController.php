<?php
require_once BASE_PATH . '/models/User.php';
require_once BASE_PATH . '/functions/helpers.php';
require_once BASE_PATH . '/controllers/UserController.php';

class AuthController {
    private $userModel;
    private $koneksi;

    public function __construct($koneksi) {
        $this->userModel = new User($koneksi);
        $this->koneksi = $koneksi;
    }

    public function session(){
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!self::checkSession()) {
            Response::error('Belum login.', 401);
        }

        require_once BASE_PATH . '/session.php';

        Response::success(
            'Session aktif.',
            [
                'user' => [
                    'user_id' => $user_id,
                    'store_id' => $store_id,
                    'role' => $role,
                    'username' => $username,
                    'initial' => $initial,
                    'name' => $name,
                    'foto' => $foto
                ],
                'store' => [
                    'name' => $storeName,
                    'address' => $storeAddress,
                    'logo' => $storeLogo
                ],
                'administrator' => $administrator ?? false
            ]
        );
    }

    public static function checkSession() {
        if (isset($_SESSION['user']['user_id']) &&
            isset($_SESSION['user']['store_id']) &&
            isset($_SESSION['user']['role']) &&
            isset($_SESSION['user']['username']) &&
            isset($_SESSION['user']['initial']) &&
            isset($_SESSION['user']['name']) &&
            isset($_SESSION['user']['foto']) &&
            isset($_SESSION['user']['store_name']) &&
            isset($_SESSION['user']['store_address']) &&
            isset($_SESSION['user']['store_logo']) 
        ) {
            return true;
        } elseif (isset($_COOKIE['user_user_id']) &&
            isset($_COOKIE['user_username']) &&
            isset($_COOKIE['user_name']) &&
            isset($_COOKIE['user_initial']) &&
            isset($_COOKIE['user_store_id']) &&
            isset($_COOKIE['user_role']) &&
            isset($_COOKIE['user_foto']) &&
            isset($_COOKIE['store_name']) &&
            isset($_COOKIE['store_address']) &&
            isset($_COOKIE['store_logo']) 
        ) {
            $_SESSION['user'] = [
                'user_id'       => $_COOKIE['user_user_id'],
                'store_id'      => $_COOKIE['user_store_id'],
                'role'          => $_COOKIE['user_role'],
                'username'      => $_COOKIE['user_username'],
                'initial'       => $_COOKIE['user_initial'],
                'name'          => $_COOKIE['user_name'],
                'foto'          => $_COOKIE['user_foto']
            ];
            return true;
        }
        return false;
    }

    public function login() {
        $username_input = strtolower(trim($_POST['username'])) ?? '';
        $password = $_POST['password'] ?? '';
        $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
        $address = getClientIP();

        date_default_timezone_set('Asia/Jakarta');
        $date = date("Y-m-d H:i:s");

        $secret_key = "6LfKclYtAAAAAKEHLpfWAOv_riDy4PJOtleE0Pw9";
        $is_localhost = isLocalhostRequest();

        if (!$is_localhost) {
            if (empty($recaptcha_response)) {
                send_json_response(false, "reCAPTCHA tidak valid!");
                exit;
            }

            $verify_url = "https://www.google.com/recaptcha/api/siteverify";
            $context = stream_context_create([
                'http' => ['timeout' => 5]
            ]);
            $response = @file_get_contents(
                $verify_url . "?secret=" . $secret_key . "&response=" . urlencode($recaptcha_response),
                false,
                $context
            );

            if ($response === false) {
                send_json_response(false, "Gagal memverifikasi reCAPTCHA. Coba lagi.");
                exit;
            }

            $response_keys = json_decode($response, true);

            if (
                !is_array($response_keys) ||
                empty($response_keys['success']) ||
                !isset($response_keys['score']) ||
                $response_keys['score'] < 0.5 ||
                !isset($response_keys['action']) ||
                $response_keys['action'] !== 'login'
            ) {
                send_json_response(false, "Aktivitas mencurigakan terdeteksi!");
                exit;
            }
        }

        if ($this->userModel->checkUser($username_input)) {
            $userAuth = $this->userModel->getUserAuthData($username_input);
            $dataStore = dataStore($userAuth['store_id']);

            if (password_verify($password, $userAuth['password'])) {
                $fullUserData = $this->userModel->getUserByUsername($username_input);

                setInfo($fullUserData, $dataStore);
                insertActivity($fullUserData['user_id'], $address, $date);

                send_json_response(true, "Login Berhasil");
                exit;
            } else {
                send_json_response(false, "Username atau password salah!");
                exit;
            }
        } else {
            send_json_response(false, "Username atau password salah!");
            exit;
        }
    }

    public static function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
        session_destroy();

        $cookies_to_delete = [
            'user_user_id', 'user_username', 'user_name', 'user_initial', 
            'user_store_id', 'user_role', 'user_foto', 'store_name', 
            'store_address', 'store_logo'
        ];
        
        foreach ($cookies_to_delete as $cookie_name) {
            if (isset($_COOKIE[$cookie_name])) {
                setcookie($cookie_name, '', time() - 3600, '/');
            }
        }

        send_json_response(true, "Berhasil logout");
        exit;
    }
}
?>