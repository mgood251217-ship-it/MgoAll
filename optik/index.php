<?php
ob_start(); 

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
date_default_timezone_set('Asia/Jakarta');
require_once 'config/database.php';

$page = isset($_GET['page']) ? $_GET['page'] : 'order';
$url_parts = explode('/', $page);
$module = $url_parts[0];

$action = isset($url_parts[1]) ? $url_parts[1] : '';

if ($module === 'center') {
    require __DIR__ . '/center/index.php';
    exit();
}

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';

if ($module === 'login' || $module === 'logout') {
    header('Cache-Control: no-store');
    $auth_base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/.');
    $authController = new AuthController(new User($pdo), $auth_base);
    $authData = $module === 'logout'
        ? $authController->logout($_SESSION)
        : $authController->login($_POST, $_SESSION, $_SERVER['REQUEST_METHOD'] ?? 'GET');

    if (!empty($authData['regenerate_session'])) {
        session_regenerate_id(true);
    }
    if (isset($authData['redirect'])) {
        header('Location: ' . $authData['redirect'], true, ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 303 : 302);
        exit();
    }
    http_response_code($authData['status_code'] ?? 200);
    extract($authData, EXTR_SKIP);
    $styles_url = $auth_base . '/styles/pages.css?v=' . filemtime(__DIR__ . '/styles/pages.css');
    require __DIR__ . '/login.php';
    exit();
}

AuthMiddleware::authCheck();

if ($module === 'product' || $module === 'dashboard') {
    require_once __DIR__ . '/controllers/ProductController.php';
    $productController = new ProductController(new Product($pdo));
}

$product_actions = ['store_cat', 'store_brand', 'store_product', 'edit_stock', 'delete_product', 'update_product', 'api_detail', 'print_label', 'update_brand', 'delete_brand'];

if ($module === 'product' && in_array($action, $product_actions, true)) {
    $json_response = $action === 'api_detail';
    if ($json_response) {
        header('Content-Type: application/json');
    }

    try {
        $response = $productController->dispatch($action, $store_id, $_GET, $_POST, $_FILES, $_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (isset($response['redirect'])) {
            header('Location: ' . $response['redirect'], true, 303);
        } elseif (array_key_exists('json', $response)) {
            echo json_encode($response['json'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        } else {
            echo $response['body'] ?? '';
        }
    } catch (Throwable $e) {
        http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
        echo $json_response
            ? json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
            : htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    }

    exit();
}

if ($module === 'order' || $module === 'dashboard') {
    require_once __DIR__ . '/controllers/OrderController.php';
    $orderController = new OrderController(
        new Order($pdo),
        new Product($pdo),
        new FinanceController(new Finance($pdo))
    );
}

$order_actions = ['store', 'api_detail', 'api_pay', 'api_delete', 'print', 'next-invoice'];

if ($module === 'order' && in_array($action, $order_actions, true)) {
    header('Content-Type: application/json');

    try {
        $response = $orderController->dispatch(
            $action,
            $store_id,
            $_GET,
            file_get_contents('php://input')
        );
        echo json_encode($response, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        http_response_code(500);
        $message = $action === 'next-invoice' ? 'Tidak dapat mengambil nomor invoice.' : $e->getMessage();
        $response = $action === 'api_delete'
            ? ['success' => false, 'message' => $message]
            : ['status' => 'error', 'message' => $message];
        echo json_encode($response, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    exit();
}

$allowed_pages = ['dashboard', 'order', 'product', 'pelanggan', 'setting', 'aruskas'];
if (!in_array($module, $allowed_pages)) {
    $module = 'order'; 
}

if ($module === 'aruskas' || $module === 'dashboard') {
    require_once __DIR__ . '/controllers/FinanceController.php';
    $financeController = new FinanceController(new Finance($pdo));
    $financeData = $module === 'aruskas'
        ? $financeController->index($store_id, $_GET, $_POST, $_SERVER['REQUEST_METHOD'] ?? 'GET')
        : $financeController->getDashboardData($store_id);

    if (isset($financeData['redirect'])) {
        header('Location: ' . $financeData['redirect'], true, 303);
        exit();
    }

    extract($financeData, EXTR_SKIP);
}

if ($module === 'order') {
    if (!isset($orderController)) {
        require_once __DIR__ . '/controllers/OrderController.php';
        $orderController = new OrderController(new Order($pdo), new Product($pdo), new FinanceController(new Finance($pdo)));
    }

    $orderData = $action === 'create'
        ? $orderController->create($store_id, $_GET)
        : $orderController->index($store_id, $_GET);

    if ($action === 'create' && ($_GET['ajax'] ?? '') === '1') {
        header('Content-Type: application/json');
        echo json_encode($orderController->catalog($orderData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit();
    }

    extract($orderData, EXTR_SKIP);
} elseif ($module === 'dashboard') {
    extract($orderController->getDashboardData($store_id), EXTR_SKIP);
}

if ($module === 'product') {
    $productData = $productController->index($store_id, $_GET);
    if (($_GET['ajax'] ?? '') === '1') {
        header('Content-Type: application/json');
        echo json_encode($productController->catalog($productData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit();
    }
    extract($productData, EXTR_SKIP);
} elseif ($module === 'dashboard') {
    extract($productController->getDashboardData($store_id), EXTR_SKIP);
}

$view_action = $module === 'order' && $action === 'create' ? 'create' : 'index';
$file_path = "views/" . $module . "/" . $view_action . ".php";

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if (file_exists($file_path)) {
        include $file_path;
    }
    exit();
}

include 'views/layout/header.php';
include 'views/layout/sidebar.php';

echo '<div class="main-layout">'; 
    
    include 'views/layout/navbar.php'; 
    
    echo '<div class="content-area">'; 
        if (file_exists($file_path)) {
            include $file_path;
        } else {
            echo "<div style='padding:40px;'><h2>Modul $module belum tersedia.</h2></div>";
        }
    echo '</div>'; 
    
    include 'views/layout/footer.php'; 

echo '</div>'; 

ob_end_flush();
?>
