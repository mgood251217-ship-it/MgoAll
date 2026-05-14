<?php
$host = "localhost";
$db   = "u130468871_optik";
$user = "u130468871_optik";
$pass = "Mgo221###";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}

define('BASE_URL', 'http://optik.mgood.my.id/');
?>