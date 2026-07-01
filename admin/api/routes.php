<?php

$router = new Router();

$router->add(
	'login',
	AuthController::class,
	'login'
);

$router->add(
	'logout',
	AuthController::class,
	'logout',
	[
		'auth'
	]
);

$router->add(
	'theme',
	SettingController::class,
	'changeTheme',
	[
		'auth'
	]
);

$router->add(
	'test',
	SettingController::class,
	'test'
);

$router->add(
    'session',
    AuthController::class,
    'session'
);

return $router;