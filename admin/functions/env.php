<?php

if (!function_exists('loadEnv')) {

    function loadEnv($path)
    {
        if (!file_exists($path)) {
            throw new Exception(".env file tidak ditemukan: {$path}");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {

            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $key = trim($key);
            $value = trim($value);

            $value = trim($value, "\"'");

            $_ENV[$key] = $value;
        }
    }
}

if (!function_exists('env')) {

    function env($key, $default = null)
    {
        return $_ENV[$key] ?? $default;
    }
}