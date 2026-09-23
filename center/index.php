<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$request = trim($request, '/');
$scriptDirectory = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$route = $request;

if ($scriptDirectory !== '' && ($route === $scriptDirectory || strpos($route, $scriptDirectory . '/') === 0)) {
    $route = substr($route, strlen($scriptDirectory));
}

if ($route === 'center' || strpos($route, 'center/') === 0) {
    $route = substr($route, strlen('center'));
}

$route = trim($route, '/');
$route = trim($_GET['url'] ?? $route, '/');

if ($route === 'action' || strpos($route, 'action/') === 0) {
    require 'actions/index.php';
    exit;
}

ob_start();

switch ($route) {
    case '':
    case 'dashboard':
        require 'pages/dashboard.php';
        break;

    case 'login':
        require 'pages/login.php';
        break;

    case 'orders':
        require 'pages/orders.php';
        break;

    case 'order':
        require 'pages/order.php';
        break;

    case 'products':
        require 'pages/products.php';
        break;

    case 'transactions':
        require 'pages/transactions.php';
        break;

    case 'stores':
        require 'pages/stores.php';
        break;

    case 'finance':
        require 'pages/finance.php';
        break;

    case 'users':
        require 'pages/users.php';
        break;

    case 'productions':
        require 'pages/productions.php';
        break;

    case 'analysis':
        require 'pages/analysis.php';
        break;

    case 'setting':
        require 'pages/settings.php';
        break;

    case 'helpdesk':
        require 'pages/helpdesk.php';
        break;

    case 'piutang':
        require 'pages/piutang.php';
        break;

    case 'branch_check':
        require 'pages/branch_check.php';
        break;

    case 'checklist_master':
        require 'pages/checklist_master.php';
        break;
        
    default:
        http_response_code(404);
        require 'pages/404.php';
        break;
}

$content = ob_get_clean();

if ($route === 'login') {
    echo $content;
} else {
    require 'utils/layout.php';
}