<?php

if (!function_exists('findEnvFile')) {
    function findEnvFile(string $startDir): ?string{
        $dir = realpath($startDir);
        while ($dir !== false) {
            $env = $dir . DIRECTORY_SEPARATOR . '.env';
            if (file_exists($env)) {
                return $env;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }
        return null;
    }
}

if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void
    {
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
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

$envFile = findEnvFile(__DIR__);
if ($envFile === null) {
    die('.env tidak ditemukan.');
}

loadEnv($envFile);

$db_host = $_ENV['DB_HOST'] ?? 'localhost';
$db_user = $_ENV['DB_USER'] ?? '';
$db_pass = base64_decode($_ENV['DB_PASS'] ?? '');
$db_name = $_ENV['DB_NAME'] ?? '';
$db_port = (int)($_ENV['DB_PORT'] ?? 3306);

$koneksi = new mysqli(
    $db_host,
    $db_user,
    $db_pass,
    $db_name,
    $db_port
);

if ($koneksi->connect_errno) {
    Response::error(
        'Database gagal terhubung.',
        500,
        [
            'error' => $koneksi->connect_error
        ]
    );
}

$koneksi->set_charset('utf8mb4');