<?php
class AuthController {
    
    public function login() {
        session_start();
        require_once __DIR__ . "/../config/connect.php";
        require_once __DIR__ . "/../functions/helpers.php";

        $is_localhost = in_array($_SERVER['HTTP_HOST'], ['localhost', 'center.mgoall.test', '127.0.0.1', '::1']);
        $site_key   = "6LegPm0sAAAAACMlVF_Q0hQmj2cRMXNl2Pj8pldB";
        $secret_key = "6LegPm0sAAAAAD028ehVM8ZVd1yn_cXLN2rNEkDA";

        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $username_input = strtolower(trim($_POST['usernames']));
            $password = $_POST['password'];
            $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
            $login_ok = true;
            $pesan_error = '';

            if (!$is_localhost) {
                if (empty($recaptcha_response)) {
                    $pesan_error = "reCAPTCHA tidak valid!";
                    $login_ok = false;
                } else {
                    $verify_url = "https://www.google.com/recaptcha/api/siteverify";
                    $response = file_get_contents($verify_url . "?secret=" . $secret_key . "&response=" . $recaptcha_response);
                    $response_keys = json_decode($response, true);

                    if (!$response_keys['success'] || $response_keys['score'] < 0.5 || $response_keys['action'] !== 'login') {
                        $pesan_error = "Aktivitas mencurigakan terdeteksi!";
                        $login_ok = false;
                    }
                }
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

                        $expire = time() + (1 * 24 * 60 * 60);
                        $path   = '/';

                        // Set Cookies
                        setcookie('admin_administrator_id', startEnk('enk', $user['administrator_id']), $expire, $path, "", true, true);
                        setcookie('admin_username',         startEnk('enk', $user['username']),         $expire, $path, "", true, true);
                        setcookie('admin_access',           startEnk('enk', $user['access']),           $expire, $path, "", true, true);

                        // Set Session
                        $_SESSION['admin_logged_in'] = [
                            'administrator_id' => startEnk('enk', $user['administrator_id']),
                            'username'         => startEnk('enk', $user['username']),
                            'access'           => startEnk('enk', $user['access'])
                        ];

                        header("Location: /dashboard");
                        exit;
                    } else {
                        $pesan_error = "Password salah!";
                    }
                } else {
                    $pesan_error = "Username tidak ditemukan!";
                }
            }

            if ($pesan_error) {
                $_SESSION['login_error'] = $pesan_error;
                header("Location: /login");
                exit;
            }
        }
    }
    
    public function logout() {
        session_start();
        session_destroy();
        
        // Hapus Cookies
        setcookie('admin_administrator_id', '', time() - 3600, '/');
        setcookie('admin_username', '', time() - 3600, '/');
        setcookie('admin_access', '', time() - 3600, '/');
        
        header('Location: /login');
        exit;
    }
    
}