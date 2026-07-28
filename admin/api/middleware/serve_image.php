<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$allowedDir = realpath('../../assets/img');
$requested = $_GET['path'] ?? '';
$fullPath = realpath($allowedDir . '/' . $requested);

if (!$fullPath || strpos($fullPath, $allowedDir) !== 0 || !is_file($fullPath)) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mimeTypes = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'svg'  => 'image/svg+xml',
];

if (!isset($mimeTypes[$ext])) {
    http_response_code(415);
    exit;
}

header('Content-Type: ' . $mimeTypes[$ext]);
header('Cache-Control: public, max-age=86400');
readfile($fullPath);