<?php

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	http_response_code(200);
	exit;
}

header("Content-Type: application/json");

echo json_encode([
	"success" => true,
	"message" => "CORS OK",
	"time" => date('Y-m-d H:i:s')
]);