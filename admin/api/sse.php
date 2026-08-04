<?php

set_time_limit(0);
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);

require_once '../connect.php';
require_once BASE_PATH . '/middleware/init_auth.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    "http://localhost:51730",
    "http://localhost:5173",
    "https://mgood.my.id",
];

if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header("Access-Control-Allow-Credentials: true");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Client-Type");
    http_response_code(200);
    exit;
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

global $store_id;

if (!$store_id) {
    die();
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}


$get_modules = isset($_GET['modules']) ? $_GET['modules'] : 'dataset,orders,users,machines';
$modules = array_filter(explode(',', $get_modules)); 

$last_mtimes = [];
foreach ($modules as $module) {
    $last_mtimes[$module] = 0;
}

$ping_counter = 0;

while (true) {
    clearstatcache();
    
    foreach ($modules as $module) {
        $file_path = BASE_PATH . '/temp/' . $module . '/store_' . $store_id . '.json';
        
        if (file_exists($file_path)) {
            $current_mtime = filemtime($file_path);
            
            if ($current_mtime > $last_mtimes[$module]) {
                $json_data = file_get_contents($file_path);
                
                $data_array = json_decode($json_data, true);
                if (is_array($data_array)) {
                    $data_array['module'] = $module;
                    
                    echo "data: " . json_encode($data_array) . "\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
                
                $last_mtimes[$module] = $current_mtime;
            }
        }
    }
    
    $ping_counter++;
    if ($ping_counter >= 15) {
        echo ": ping\n\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
        $ping_counter = 0;
    }

    sleep(1);
}
?>