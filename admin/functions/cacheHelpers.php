<?php
function updateStoreCache($store_id, $module) {
    $tempDir = __DIR__ . '/../temp';
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

?>