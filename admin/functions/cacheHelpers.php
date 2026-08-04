<?php
function updateStoreCache($store_id, $module) {
    $tempDir = BASE_PATH . '/temp/dataset';
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
    $tempDir = __DIR__ . '/../temp/orders';
    
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0775, true);
    }

    $filePath = $tempDir . '/store_' . $store_id . '.json';
    
    $data = [
        "order_id" => (int) $order_id,
        "last_update" => time()
    ];

    file_put_contents($filePath, json_encode($data));
}
?>