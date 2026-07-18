<?php

require_once __DIR__ . '/middleware/cors.php';
require_once __DIR__ . '/autoload.php';

require_once  '../connect.php';

require_once BASE_PATH . '/global_functions.php';
require_once BASE_PATH . '/functions/helpers.php';
require_once BASE_PATH . '/functions/setInfo.php';
require_once BASE_PATH . '/functions/Otp.php';

foreach (glob(BASE_PATH . '/controllers/*.php') as $file) {
	require_once $file;
}