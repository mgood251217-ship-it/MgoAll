<?php
spl_autoload_register(function ($class) {
    $paths = [__DIR__ . '/core', __DIR__ . '/../controllers'];

    foreach ($paths as $path) {
        $file = $path . '/' . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});