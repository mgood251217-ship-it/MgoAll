<?php
require_once '../connect.php';
require_once 'AuthMiddleware.php';
global $koneksi;
$auth = new AuthMiddleware($koneksi);
$auth->handle();

extract($GLOBALS);