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

$router->add(
    'users',
    UserController::class,
    'index',
    [
        'auth'
    ]
);

$router->add(
    'products',
    ProductController::class,
    'index',
    [
        'auth'
    ]
);

$router->add(
    'pagination_products',
    ProductController::class,
    'getProductByPagination',
    [
        'auth'
    ]
);

$router->add(
    'orders',
    OrderController::class,
    'index',
    [
        'auth'
    ]
);

$router->add(
    'locations',
    LocationController::class,
    'index',
    [
        'auth'
    ]
);

$router->add(
    'create_product',
    ProductController::class,
    'createProduct',
    [
        'auth'
    ]
);

$router->add(
    'update_product',
    ProductController::class,
    'updateProduct',
    [
        'auth'
    ]
);

$router->add(
    'delete_product',
    ProductController::class,
    'deleteProduct',
    [
        'auth'
    ]
);





return $router;