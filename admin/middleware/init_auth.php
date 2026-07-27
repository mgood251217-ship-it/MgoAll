<?php
require_once 'AuthMiddleware.php';

$auth = new AuthMiddleware($koneksi);
$auth->handle();

extract($GLOBALS);