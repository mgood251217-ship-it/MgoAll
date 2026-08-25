<?php
require_once BASE_PATH . '/functions/helpers.php';

class AuthMiddleware {
    private $koneksi;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
    }

    public function handle() {
        $this->initSession();
        $this->setTimezone();

        if ($this->hasValidCookie()) {
            if (!$this->hasValidSession() || $_SESSION['user']['store_id'] !== $_COOKIE['user_store_id']) {
                $this->loadFromCookie(); 
            } else {
                $this->loadFromSession();
            }
        } elseif ($this->hasValidSession()) {
            $this->loadFromSession();
        } else {
            $this->redirectLogin();
        }

        $this->checkAdministrator();
        $this->validateDatabaseUser();
    }

    public function initSession() {
        ini_set('session.cookie_domain', '.mgood.my.id'); 
        ini_set('session.cookie_samesite', 'None');
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_httponly', 1);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function hasValidSession() {
        return isset(
            $_SESSION['user']['user_id'],
            $_SESSION['user']['store_id'],
            $_SESSION['user']['role'],
            $_SESSION['user']['username'],
            $_SESSION['user']['initial'],
            $_SESSION['user']['name'],
            $_SESSION['user']['foto'],
            $_SESSION['user']['store_name'],
            $_SESSION['user']['store_address'],
            $_SESSION['user']['store_logo']
        );
    }

    public function hasValidCookie() {
        return isset(
            $_COOKIE['user_user_id'],
            $_COOKIE['user_username'],
            $_COOKIE['user_name'],
            $_COOKIE['user_initial'],
            $_COOKIE['user_store_id'],
            $_COOKIE['user_role'],
            $_COOKIE['store_name'],
            $_COOKIE['store_logo'],
            $_COOKIE['store_address']
        );
    }

    public function loadFromSession() {
        $GLOBALS['user_id'] = startEnk('dek', $_SESSION['user']['user_id']);
        $GLOBALS['store_id'] = startEnk('dek', $_SESSION['user']['store_id']);
        $GLOBALS['role'] = startEnk('dek', $_SESSION['user']['role']);
        $GLOBALS['username'] = startEnk('dek', $_SESSION['user']['username']);
        $GLOBALS['initial'] = startEnk('dek', $_SESSION['user']['initial']);
        $GLOBALS['name'] = startEnk('dek', $_SESSION['user']['name']);
        $GLOBALS['foto'] = startEnk('dek', $_SESSION['user']['foto']);
        $GLOBALS['storeName'] = startEnk('dek', $_SESSION['user']['store_name']);
        $GLOBALS['storeAddress'] = startEnk('dek', $_SESSION['user']['store_address']);
        $GLOBALS['storeLogo'] = startEnk('dek', $_SESSION['user']['store_logo']);
    }

    public function loadFromCookie() {
        $userId = startEnk('dek', $_COOKIE['user_user_id']);
        
        if ($userId) {
            $_SESSION['user'] = [
                'user_id'       => $_COOKIE['user_user_id'],
                'store_id'      => $_COOKIE['user_store_id'],
                'role'          => $_COOKIE['user_role'],
                'username'      => $_COOKIE['user_username'],
                'initial'       => $_COOKIE['user_initial'],
                'name'          => $_COOKIE['user_name'],
                'foto'          => $_COOKIE['user_foto'],
                'store_name'    => $_COOKIE['store_name'],
                'store_address' => $_COOKIE['store_address'],
                'store_logo'    => $_COOKIE['store_logo']
            ];
            $this->loadFromSession();
        } else {
            $this->redirectLogin();
        }
    }

    public function checkAdministrator() {
        $GLOBALS['administrator'] = isset($_SESSION['admin_logged_in']['administrator_id']);
    }

    public function validateDatabaseUser() {
        $userId = $GLOBALS['user_id'] ?? null;

        if (!$userId) {
            $this->redirectLogin();
        }

        $lastCheck = $_SESSION['last_db_check'] ?? 0;
        $currentTime = time();
        $checkInterval = 300; 

        if (($currentTime - $lastCheck) < $checkInterval) {
            return;
        }

        $stmt = $this->koneksi->prepare("SELECT user_id FROM users WHERE user_id = ? AND is_deleted = 0");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            $this->redirectLogin();
        }

        $_SESSION['last_db_check'] = $currentTime;
    }

    public function setTimezone() {
        date_default_timezone_set('Asia/Jakarta');
        $GLOBALS['date'] = date("Y-m-d H:i:s");
    }

    public function redirectLogin() {
        session_unset();
        session_destroy();
        
        $clearOptions = [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => '.mgood.my.id',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'None',
        ];
        $cookiesToClear = ['user_user_id', 'user_username', 'user_name', 'user_initial', 'user_store_id', 'user_role', 'user_foto', 'store_name', 'store_address', 'store_logo', session_name()];
        foreach ($cookiesToClear as $c) {
            if (isset($_COOKIE[$c])) {
                setcookie($c, '', $clearOptions);
                unset($_COOKIE[$c]);
            }
        }

        header("Location: " . BASE_URL . "/login");
        exit;
    }

    public function hasRole($roles) {
        $currentRole = $GLOBALS['role'] ?? '';
        
        if (is_array($roles)) {
            return in_array(strtoupper($currentRole), $roles);
        }
        
        return strtoupper($currentRole) === strtoupper($roles);
    }

    public function isAdminOrManager() {
        return $this->hasRole(['ADMIN', 'MANAGER']);
    }

    public function isSetting() {
        return $this->hasRole('SETTING');
    }

    public function isOnline() {
        return $this->hasRole('ONLINE');
    }

    public function isProduksi() {
        return $this->hasRole('PRODUKSI');
    }
}