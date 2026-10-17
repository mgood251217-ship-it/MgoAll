<?php

require_once __DIR__ . '/../config/session_start.php';
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/CenterController.php';
require_once __DIR__ . '/../controllers/CenterOrderController.php';
require_once __DIR__ . '/../controllers/CenterFinanceController.php';

$script_directory = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/center/index.php')), '/');
$app_base = basename($script_directory) === 'center'
    ? rtrim(str_replace('\\', '/', dirname($script_directory)), '/.')
    : $script_directory;
$center_base = $app_base . '/center';
$center_action = isset($module) && $module === 'center' ? $action : ($_GET['action'] ?? '');
$center_action = is_string($center_action) ? trim($center_action, '/') : '';
$centerModel = new Center($pdo);
$orderModel = new Order($pdo);
$productModel = new Product($pdo);
$financeModel = new Finance($pdo);
$financeController = new FinanceController($financeModel);
$orderController = new OrderController($orderModel, $productModel, $financeController);
$centerOrders = new CenterOrderController($centerModel, $orderModel, $productModel, $orderController, $center_base);
$centerFinance = new CenterFinanceController($centerModel, $financeModel, $financeController, $center_base);
$centerController = new CenterController($centerModel, $center_base, $centerOrders, $centerFinance);
header('Cache-Control: no-store');

try {
    $centerData = $centerController->handle($center_action, $_GET, $_POST, $_SESSION, $_SERVER['REQUEST_METHOD'] ?? 'GET');
} catch (Throwable $e) {
    error_log((string) $e);
    $centerData = ['view' => 'error', 'status_code' => 500, 'error' => 'Data admin center belum dapat dimuat. Silakan coba lagi.'];
}

if (!empty($centerData['regenerate_session'])) {
    session_regenerate_id(true);
}
if (isset($centerData['redirect'])) {
    header('Location: ' . $centerData['redirect'], true, ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 303 : 302);
    exit();
}

http_response_code($centerData['status_code'] ?? 200);
extract($centerData, EXTR_SKIP);
$styles_url = $app_base . '/styles/pages.css?v=' . filemtime(__DIR__ . '/../styles/pages.css');
require __DIR__ . '/../views/center/layout.php';
