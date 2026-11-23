<?php
function writeMgoCacheFile($filePath, $update) {
    $directory = dirname($filePath);
    if (!is_dir($directory)) {
        set_error_handler(function () { return true; });
        try { $created = mkdir($directory, 0775, true); }
        finally { restore_error_handler(); }
        if (!$created && !is_dir($directory)) throw new RuntimeException('Folder cache tidak dapat dibuat.');
    }
    $file = fopen($filePath, 'c+');
    if (!$file) throw new RuntimeException('File cache tidak dapat dibuka.');
    try {
        if (!flock($file, LOCK_EX)) throw new RuntimeException('File cache tidak dapat dikunci.');
        $stat = fstat($file);
        $data = json_decode(stream_get_contents($file), true);
        $updated = $update(is_array($data) ? $data : [], $stat['mtime']);
        $json = json_encode($updated, JSON_THROW_ON_ERROR);
        rewind($file);
        if (!ftruncate($file, 0)) throw new RuntimeException('File cache tidak dapat diperbarui.');
        $offset = 0;
        while ($offset < strlen($json)) {
            $written = fwrite($file, substr($json, $offset));
            if (!$written) throw new RuntimeException('Penulisan cache tidak selesai.');
            $offset += $written;
        }
        fflush($file);
    } finally { flock($file, LOCK_UN); fclose($file); }
}

function updateStoreCache($store_id, $module) {
    $modules = array_unique((array)$module);
    if (!$modules) return;
    $filePath = $_ENV['BASE_PATH_UPLOAD'] . '/temp/dataset/store_' . $store_id . '.json';
    writeMgoCacheFile($filePath, function ($data) use ($modules) {
        // Satu versi baru selalu lebih tinggi dari seluruh penanda sebelumnya.
        // Ini menjaga pembaca lama yang membandingkan beberapa modul memakai MAX.
        $version = time();
        foreach ($data as $key => $value) {
            if (str_ends_with($key, '_updated_at') && is_numeric($value)) $version = max($version, (int)$value + 1);
        }
        foreach ($modules as $name) $data[$name . '_updated_at'] = $version;
        return $data;
    });
}

function updateOrderTrigger($store_id, $order_id) {
    $filePath = $_ENV['BASE_PATH_UPLOAD'] . '/temp/orders/store_' . $store_id . '.json';
    writeMgoCacheFile($filePath, function ($data, $modified) use ($order_id) {
        if (date('Y-m-d', $modified) !== date('Y-m-d')) $data = [];
        $data[(string)$order_id] = max(time(), (int)($data[(string)$order_id] ?? 0) + 1);
        $expireTime = time() - 86400;
        foreach ($data as $id => $timestamp) if ($timestamp < $expireTime) unset($data[$id]);
        return $data;
    });
}
