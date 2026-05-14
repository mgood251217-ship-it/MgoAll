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

$product_actions = ['store_cat', 'store_brand', 'store_product', 'edit_stock', 'delete_product', 'update_product', 'api_detail', 'print_label', 'update_brand', 'delete_brand'];

if ($module === 'product' && in_array($action, $product_actions)) {
    include 'views/product/store_action.php';
    exit();
}

if ($module === 'logout') {
    session_destroy();
    header("Location: /login");
    exit();
}

if ($module === 'login') {
    include 'login.php';
    exit();
}

if (!isset($_SESSION['user_logged_in'])) {
    header("Location: /login");
    exit();
}

$ajax_routes = ['store', 'api_detail', 'api_pay', 'print'];

if ($module === 'order' && in_array($action, $ajax_routes)) {
    include "views/order/" . $action . ".php";
    exit(); 
}

$allowed_pages = ['dashboard', 'order', 'product', 'pelanggan', 'setting', 'aruskas'];
if (!in_array($module, $allowed_pages)) {
    $module = 'order'; 
}

// =======================================================
// TAMBAHKAN INI: Bypass Layout jika ada request AJAX
// =======================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $file_path = "views/" . $module . "/index.php";
    if (file_exists($file_path)) {
        include $file_path;
    }
    exit(); // Hentikan proses, jangan load footer
}

include 'views/layout/header.php';
include 'views/layout/sidebar.php';

echo '<div class="main-layout">'; 
    
    include 'views/layout/navbar.php'; 
    
    echo '<div class="content-area">'; 
        $file_path = "views/" . $module . "/index.php";
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