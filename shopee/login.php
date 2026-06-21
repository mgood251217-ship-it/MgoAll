<?php

session_start();
require_once "connect.php";

$pesan_error = '';

header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);

date_default_timezone_set('Asia/Jakarta');
$date = date("Y-m-d H:i:s");

$secret_key = $input['secret_key'];

$username_input = strtolower(trim($input['username']));
$password = $input['password'];
$recaptcha_response = $input['g_recaptcha_response'];
$success = false;
$message = '';

if (empty($recaptcha_response)) {
    $message = "reCAPTCHA tidak valid!";
} else {

    $verify_url = "https://www.google.com/recaptcha/api/siteverify";
    $response = file_get_contents(
        $verify_url . "?secret=" . $secret_key . "&response=" . $recaptcha_response
    );
    $response_keys = json_decode($response, true);

    if (
        !$response_keys['success'] ||
        $response_keys['score'] < 0.5 ||
        $response_keys['action'] !== 'login'
    ) {
        $message = "Aktivitas mencurigakan terdeteksi!";
    } else {
        $sql = "SELECT user_id, username, name, password 
                FROM users WHERE LOWER(username) = ?";
        $stmt = $koneksi->prepare($sql);
        $stmt->bind_param("s", $username_input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {

                unset($user['password']);
                $_SESSION['shopee_users'] = [
                    'user_id'  => $user['user_id'],
                    'username' => $user['username'],
                    'name'     => $user['name']
                ];
                $success = true;
            } else {
                $message = "Username atau password salah!";
            }
        } else {
            $message = "Username atau password salah!";
        }
    }
}

$output = [
    'success' => $success,
    'message' => $message
];
echo json_encode($output);
?>