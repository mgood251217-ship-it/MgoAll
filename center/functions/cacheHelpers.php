<?php
function updateStoreCache($store_id, $module) {
    $tempDir = dirname(__DIR__) . '/../admin/temp/dataset';
    $filePath = $tempDir . '/store_' . $store_id . '.json';

    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0775, true);
    }

    $data = [];

    if (file_exists($filePath)) {
        $json = file_get_contents($filePath);
        $data = json_decode($json, true) ?: [];
    }

    $data[$module . '_updated_at'] = time();

    file_put_contents($filePath, json_encode($data));
}

function updateOrderTrigger($store_id, $order_id) {
    $tempDir = dirname(__DIR__) . '/../admin/temp/orders';
    
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0775, true);
    }
    $filePath = $tempDir . '/store_' . $store_id . '.json';
    $data = [];

    if (file_exists($filePath)) {
        $fileDate = date('Y-m-d', filemtime($filePath));
        $todayDate = date('Y-m-d');
        
        if ($fileDate !== $todayDate) {
            unlink($filePath);
        } else {
            $json = file_get_contents($filePath);
            $data = json_decode($json, true) ?: [];
        }
    }

    $data[(string)$order_id] = time();
    $expireTime = time() - (24 * 3600);
    foreach ($data as $id => $timestamp) {
        if ($timestamp < $expireTime) {
            unset($data[$id]);
        }
    }

    file_put_contents($filePath, json_encode($data));
}
?>