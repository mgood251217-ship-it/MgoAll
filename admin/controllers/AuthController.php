<?php
require_once BASE_PATH . '/models/User.php';
require_once BASE_PATH . '/functions/helpers.php';
require_once BASE_PATH . '/controllers/UserController.php';
if (!class_exists('AuthMiddleware')) {
    require_once BASE_PATH . '/middleware/AuthMiddleware.php';
}

class AuthController {
    private $userModel;
    private $koneksi;

    public function __construct($koneksi) {
        $this->userModel = new User($koneksi);
        $this->koneksi = $koneksi;
    }

    public function testConnection(){
        $query = $this->koneksi->query("SELECT NOW() AS server_time");
        if (!$query) {
            echo json_encode(["success" => false, "message" => $this->koneksi->error]);
            exit;
        }
        $data = $query->fetch_assoc();
        send_json_response(true, "Database Connected", $data);
    }

    public function session(){
        if (!self::checkSession()) {
            Response::error('Belum login.', 401);
        }

        require_once BASE_PATH . '/middleware/init_auth.php';
        if ($foto) {
            $fotoLink = BASE_URL . "/assets/img/user/" . $foto;
        }else{
            $fotoLink = BASE_URL . "/assets/img/user/" . 'default.png';
        }

        if ($storeLogo) {
            $storeLogoLink = BASE_URL . "/assets/img/store/" . $storeLogo;
        }else{
            $storeLogoLink = BASE_URL . "/assets/img/store/" . 'default.jpg';
        }

        Response::success(
            'Session aktif.',
            [
                'user' => [
                    'role' => $role,
                    'username' => $username,
                    'initial' => $initial,
                    'name' => $name,
                    'foto' => $foto,
                    'foto_link' => $fotoLink
                ],
                'store' => [
                    'name' => $storeName,
                    'address' => $storeAddress,
                    'logo' => $storeLogo,
                    'logo_link' =>  $storeLogoLink
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
            $middleware = new AuthMiddleware(null);
            $middleware->setSessionFromCookies();
            return true;
        }
        return false;
    }

    public function login() {
        $username_input = strtolower(trim($_POST['username'])) ?? '';
        $password = $_POST['password'] ?? '';
        $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
        $address = $this->getClientIP();

        date_default_timezone_set('Asia/Jakarta');
        $date = date("Y-m-d H:i:s");

        $secret_key = "6LfKclYtAAAAAKEHLpfWAOv_riDy4PJOtleE0Pw9";
        $is_localhost = isLocalhostRequest();
        $is_desktop_app = (($_SERVER['HTTP_X_CLIENT_TYPE'] ?? '') === 'desktop-app');
        $client_type = $_SERVER['HTTP_X_CLIENT_TYPE'] ?? '';

        if ($client_type === 'mobile-app') {
            $score_threshold = 0.1;
        } elseif ($client_type === 'desktop-app') {
            $score_threshold = 0.3;
        } else {
            $score_threshold = 0.5;
        }

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
                $response_keys['score'] < $score_threshold ||
                !isset($response_keys['action']) ||
                $response_keys['action'] !== 'login'
            ) {
                send_json_response(false, "Aktivitas mencurigakan terdeteksi!");
                exit;
            }
        }

        if ($this->userModel->checkUser($username_input)) {
            $userAuth = $this->userModel->getUserAuthData($username_input);
            $dataStore = $this->dataStore($userAuth['store_id']);

            if (password_verify($password, $userAuth['password'])) {
                $fullUserData = $this->userModel->getUserByUsername($username_input);

                $this->setInfo($fullUserData, $dataStore);
                $this->insertActivity($fullUserData['user_id'], $address, $date);

                $tempDir = BASE_PATH . '/temp/login';
                $filePath = $tempDir . '/' . date("Y-m-d") . '.json';

                if (!is_dir($tempDir)) {
                    mkdir($tempDir, 0775, true);
                }

                $login_stats = [
                    'website' => 0,
                    'desktop_app' => 0,
                    'users_data' => []
                ];

                if (file_exists($filePath)) {
                    $json = file_get_contents($filePath);
                    $decoded_data = json_decode($json, true);
                    if (is_array($decoded_data)) {
                        $login_stats = array_merge($login_stats, $decoded_data);
                        if (!isset($login_stats['users_data']) || !is_array($login_stats['users_data'])) {
                            $login_stats['users_data'] = [];
                        }
                    }
                }

                if ($is_desktop_app) {
                    $login_stats['desktop_app'] += 1;
                } else {
                    $login_stats['website'] += 1;
                }

                $fullUserData['login_time'] = $date;
                $fullUserData['login_source'] = $is_desktop_app ? 'desktop-app' : 'website';
                $fullUserData['ip_address'] = $address;

                $login_stats['users_data'][] = $fullUserData;

                file_put_contents($filePath, json_encode($login_stats, JSON_PRETTY_PRINT));

                $extra = [];
                if (!$is_desktop_app) {
                    $downloadUrl = $this->getDesktopDownloadUrl();
                    if ($downloadUrl) {
                        $extra['show_desktop_promo'] = true;
                        $extra['download_url_desktop'] = $downloadUrl['desktop'];
                        $extra['download_url_mobile'] = $downloadUrl['mobile'];
                    }
                }

                send_json_response(true, "Login Berhasil", $extra);
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
            'store_address', 'store_logo', 'user_mode'
        ];
        
        $options = [
            'expires'  => time() - (86400 * 365),
            'path'     => '/',
            'domain'   => '.mgood.my.id',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'None',
        ];

        foreach ($cookies_to_delete as $cookie_name) {
            if (isset($_COOKIE[$cookie_name])) {
                setcookie($cookie_name, '', $options);
                unset($_COOKIE[$cookie_name]); 
            }
        }

        send_json_response(true, "Berhasil logout");
        exit;
    }

    private function getDesktopDownloadUrl() {
        $versionFile = __DIR__ . '/version.json';
        if (!file_exists($versionFile)) {
            return null;
        }
        $data = json_decode(file_get_contents($versionFile), true);
        $result = [
            'desktop' => $data['download_url'] ?? null,
            'mobile' => $data['download_url_mobile'] ?? null
        ];
        return $result;
    }

    private function getClientIP() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }

    private function dataStore($storeId) {
        $stmt = $this->koneksi->prepare("SELECT name, logo, email, address FROM stores WHERE store_id = ?");
        $stmt->bind_param("i", $storeId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $data = $result->fetch_assoc()) {
            return [
                'name' => $data['name'],
                'logo' => $data['logo'],
                'address' => $data['address'],
                'email' => $data['email']
            ];
        }

        return null;
    }

    private function setInfo($user, $dataStore) {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_domain', '.mgood.my.id');
            ini_set('session.cookie_samesite', 'None');
            ini_set('session.cookie_secure', 1);
            ini_set('session.cookie_httponly', 1);
            session_start();
        }

        session_destroy();

        ini_set('session.cookie_domain', '.mgood.my.id');
        ini_set('session.cookie_samesite', 'None');
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_httponly', 1);
        session_start();

        $mode = 0;
        $stmt = $this->koneksi->prepare("SELECT mode FROM user_setting WHERE user_id = ?");
        $stmt->bind_param("i", $user['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $mode = (int)$row['mode'] ?? 0;
        }
        $stmt->close();

        $encryptedData = $this->buildEncryptedUserData($user, $dataStore, $mode);

        $this->setUserSession($encryptedData);
        $this->setUserCookie($encryptedData);
    }

    private function buildEncryptedUserData($user, $dataStore, $mode) {
        return [
            'user_id'       => startEnk('enk', $user['user_id']),
            'username'      => startEnk('enk', $user['username']),
            'name'          => startEnk('enk', $user['name']),
            'initial'       => startEnk('enk', $user['initial']),
            'store_id'      => startEnk('enk', $user['store_id']),
            'role'          => startEnk('enk', $user['role']),
            'foto'          => startEnk('enk', $user['picture']),
            'store_name'    => startEnk('enk', $dataStore['name']),
            'store_address' => startEnk('enk', $dataStore['address']),
            'store_logo'    => startEnk('enk', $dataStore['logo']),
            'mode'          => startEnk('enk', $mode)
        ];
    }

    private function insertActivity($userId, $address, $date) {
        $insert = $this->koneksi->prepare("
            INSERT INTO login_activity (user_id, address, date)
            VALUES (?, ?, ?)
        ");
        $insert->bind_param("iss", $userId, $address, $date);
        $insert->execute();
        $insert->close();
    }

    private function setUserSession($encryptedData) {
        $_SESSION['user'] = $encryptedData;
    }

    private function setUserCookie($encryptedData) {
        $expire = time() + (1 * 24 * 60 * 60);

        $options = [
            'expires'  => $expire,
            'path'     => '/',
            'domain'   => '.mgood.my.id',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'None',
        ];

        $cookieNameMap = [
            'user_id'       => 'user_user_id',
            'username'      => 'user_username',
            'name'          => 'user_name',
            'initial'       => 'user_initial',
            'store_id'      => 'user_store_id',
            'role'          => 'user_role',
            'foto'          => 'user_foto',
            'store_name'    => 'store_name',
            'store_address' => 'store_address',
            'store_logo'    => 'store_logo',
            'mode'          => 'user_mode'
        ];

        foreach ($cookieNameMap as $dataKey => $cookieName) {
            setcookie($cookieName, $encryptedData[$dataKey], $options);
        }
    }
}
?>
