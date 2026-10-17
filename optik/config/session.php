<?php

global $user_id, $user_name, $user_img, $store_id, $store_name, $store_img, $store_address;

$user_id = $_SESSION['user_id_optik'] ?? 0;
$user_name = $_SESSION['user_name_optik'] ?? '';
$user_img = $_SESSION['user_img_optik'] ?? 'default-user.png';
$store_id = $_SESSION['store_id_optik'] ?? 0;
$store_name = $_SESSION['store_name_optik'] ?? '';
$store_img = $_SESSION['store_img_optik'] ?? 'default-store.png';
$store_address = $_SESSION['store_address_optik'] ?? '';
