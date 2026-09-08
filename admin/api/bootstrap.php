<?php

require_once __DIR__ . '/middleware/cors.php';
require_once __DIR__ . '/autoload.php';

require_once  __DIR__ . '/../connect.php';

require_once __DIR__ . '/../functions/helpers.php';

foreach (glob(__DIR__ . '/../controllers/*.php') as $file) {
	require_once $file;
}