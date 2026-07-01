<?php

require_once __DIR__ . '/bootstrap.php';

$router = require __DIR__ . '/routes.php';

$action = $_GET['action'] ?? '';

$router->dispatch($action);