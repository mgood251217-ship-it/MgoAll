<?php
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/AuthMiddleware.php';
global $koneksi;
$auth = new AuthMiddleware($koneksi);
$auth->handle();

extract($GLOBALS);