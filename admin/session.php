<?php
require_once BASE_PATH . '/connect.php';
require_once BASE_PATH . '/middleware/AuthMiddleware.php';
$authMiddleware = new AuthMiddleware($koneksi);

$authMiddleware->handle();
extract($GLOBALS);

?>