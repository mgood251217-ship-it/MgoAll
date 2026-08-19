<?php
$action = isset($_GET['action']) ? $_GET['action'] : '';

if (empty($action)) {
    $parts = explode('/', $route);
    if (isset($parts[1])) {
        $action = $parts[1];
    }
}

require_once __DIR__ . '/../controllers/AuthController.php';

$authController = new AuthController();

switch ($action) {
    case 'login':
        $authController->login();
        break;
        
    case 'logout':
        $authController->logout();
        break;
        
    default:
        http_response_code(404);
        echo "Action not found";
        break;
}