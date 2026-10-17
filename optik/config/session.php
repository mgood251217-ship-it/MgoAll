<?php

global $user_id, $user_name, $user_img, $store_id, $store_name, $store_img, $store_address;

$user_id = $_SESSION['user_id'] ?? 0;
$user_name = $_SESSION['nama_user'] ?? '';
$user_img = $_SESSION['user_img'] ?? 'default-user.png';
$store_id = $_SESSION['store_id'] ?? 0;
$store_name = $_SESSION['store_name'] ?? '';
$store_img = $_SESSION['store_img'] ?? 'default-store.png';
$store_address = $_SESSION['store_address'] ?? '';
