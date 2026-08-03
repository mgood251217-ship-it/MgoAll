<?php
require_once '../connect.php';
require_once BASE_PATH . '/middleware/init_auth.php';

header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Credentials: true');
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

$file_path = BASE_PATH . '/temp/store_' . $store_id . '.json';
$last_mtime = 0;
$ping_counter = 0;

while (true) {
    clearstatcache();
    
    if (file_exists($file_path)) {
        $current_mtime = filemtime($file_path);
        
        if ($current_mtime > $last_mtime) {
            $json_data = file_get_contents($file_path);
            
            echo "data: " . $json_data . "\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
            
            $last_mtime = $current_mtime;
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