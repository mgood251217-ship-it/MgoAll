<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/connect.php';

header("Content-Type: application/json");

$query = $koneksi->query("SELECT NOW() AS server_time");

if (!$query) {
	echo json_encode([
		"success" => false,
		"message" => $koneksi->error
	]);
	exit;
}

$data = $query->fetch_assoc();

echo json_encode([
	"success" => true,
	"message" => "Database Connected",
	"data" => $data
]);