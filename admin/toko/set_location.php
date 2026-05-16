<?php
require_once '../connect.php';
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/set_location_errors.log'); // File log error di folder sama

$store_id = isset($_POST['store_id']) ? (int)$_POST['store_id'] : 0;
$lat = isset($_POST['latitude']) ? (float)$_POST['latitude'] : 0;
$lng = isset($_POST['longitude']) ? (float)$_POST['longitude'] : 0;
$storeName = isset($_POST['store_name']) ? trim($_POST['store_name']) : 'Toko';

error_log("POST received - store_id: $store_id, lat: $lat, lng: $lng, storeName: $storeName");

// Validasi input
if (!$store_id || !$lat || !$lng) {
    http_response_code(400);
    error_log("Invalid input data");
    echo 'Data tidak valid';
    exit;
}

// Cek koneksi
if ($koneksi->connect_error) {
    http_response_code(500);
    error_log("DB connection error: " . $koneksi->connect_error);
    echo 'Koneksi database gagal';
    exit;
}

// Cek apakah sudah ada lokasi untuk store_id ini
$stmt = $koneksi->prepare("SELECT id FROM locations WHERE store_id = ?");
if (!$stmt) {
    http_response_code(500);
    error_log("Prepare SELECT error: " . $koneksi->error);
    echo 'Kesalahan database';
    exit;
}
$stmt->bind_param("i", $store_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    // Update lokasi
    $update = $koneksi->prepare("UPDATE locations SET latitude = ?, longitude = ?, name = ? WHERE store_id = ?");
    if (!$update) {
        http_response_code(500);
        error_log("Prepare UPDATE error: " . $koneksi->error);
        echo 'Kesalahan database';
        exit;
    }
    $update->bind_param("ddsi", $lat, $lng, $storeName, $store_id);
    if (!$update->execute()) {
        http_response_code(500);
        error_log("Execute UPDATE error: " . $update->error);
        echo 'Gagal memperbarui lokasi';
        exit;
    }
    error_log("Lokasi berhasil diupdate untuk store_id: $store_id");
} else {
    // Insert lokasi baru
    $insert = $koneksi->prepare("INSERT INTO locations (store_id, name, latitude, longitude) VALUES (?, ?, ?, ?)");
    if (!$insert) {
        http_response_code(500);
        error_log("Prepare INSERT error: " . $koneksi->error);
        echo 'Kesalahan database';
        exit;
    }
    $insert->bind_param("issd", $store_id, $storeName, $lat, $lng);
    if (!$insert->execute()) {
        http_response_code(500);
        error_log("Execute INSERT error: " . $insert->error);
        echo 'Gagal menyimpan lokasi baru';
        exit;
    }
    error_log("Lokasi berhasil disimpan untuk store_id: $store_id");
}

echo 'OK';
