<?php

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$allowedOrigins = [
	"http://localhost:51730",
	"http://localhost:5173",
	"https://mgood.my.id",
	'http://tauri.localhost'
];

if (in_array($origin, $allowedOrigins, true)) {
	header("Access-Control-Allow-Origin: {$origin}");
	header("Access-Control-Allow-Credentials: true");
	header("Vary: Origin");
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Client-Type");

header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	http_response_code(200);
	exit;
}