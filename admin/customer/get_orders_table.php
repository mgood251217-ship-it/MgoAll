<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

$users = []; // ambil user list dari DB atau array user ID => nama user

// Ambil user list (contoh)
$sqlUsers = "SELECT user_id, nama FROM users WHERE store_id = ?";
$stmt = $koneksi->prepare($sqlUsers);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$resUsers = $stmt->get_result();
while ($u = $resUsers->fetch_assoc()) {
    $users[$u['user_id']] = $u['nama'];
}
$stmt->close();

// Cek hak akses user (sesuai session)
$is_all_access = ($_SESSION['user']['is_all_access'] ?? 0) == 1;

// Ambil orders sesuai akses
if ($is_all_access) {
    $sqlOff = "SELECT * FROM orders WHERE store_id = ? AND system = 'OFFLINE' ORDER BY deadline ASC";
    $stmt = $koneksi->prepare($sqlOff);
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $ordersOffline = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $sqlOn = "SELECT * FROM orders WHERE store_id = ? AND system = 'ONLINE' ORDER BY deadline ASC";
    $stmt = $koneksi->prepare($sqlOn);
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $ordersOnline = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $system = $_SESSION['user']['system'] ?? 'OFFLINE'; // atau sesuai cara kamu simpan
    $sql = "SELECT * FROM orders WHERE store_id = ? AND system = ? ORDER BY deadline ASC";
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("is", $store_id, $system);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

require_once 'path_to_functions.php'; // tempat fungsi tampilkanTabelOrders()

if ($is_all_access) {
    echo "<h5>Order OFFLINE</h5>";
    tampilkanTabelOrders($ordersOffline, $koneksi, $users, $role);

    echo "<h5 class='mt-4'>Order ONLINE</h5>";
    tampilkanTabelOrders($ordersOnline, $koneksi, $users, $role);
} else {
    echo "<h5>Data " . htmlspecialchars($system) . "</h5>";
    tampilkanTabelOrders($orders, $koneksi, $users, $role);
}
