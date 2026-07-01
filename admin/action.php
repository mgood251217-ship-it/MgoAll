<?php

require_once "connect.php";
require_once "global_functions.php";
require_once "functions/setInfo.php";
require_once "functions/Otp.php";
require_once "controllers/UserController.php";
require_once "controllers/SettingController.php";
require_once "controllers/AuthController.php";

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        $auth = new AuthController($koneksi);
        $auth->login();
        break;
    case 'logout':
        require_once BASE_PATH . '/session.php';
        $auth = new AuthController($koneksi);
        $auth->logout();
        break;
    case 'theme':
        require_once BASE_PATH . '/session.php';
        $settingController = new SettingController($koneksi);
        $settingController->changeTheme();
        break;
    default:
        echo json_encode(["status" => "error", "message" => "Aksi tidak ditemukan."]);
        break;
}
exit;
?>