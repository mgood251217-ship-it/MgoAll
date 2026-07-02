<?php

class Router
{
	private array $routes = [];

	public function add(
		string $action,
		string $controller,
		string $method,
		array $middlewares = []
	): void {

		$this->routes[$action] = [
			'controller' => $controller,
			'method' => $method,
			'middlewares' => $middlewares
		];
	}

	public function dispatch(string $action): void {
		if (!isset($this->routes[$action])) {
			Response::error('Action tidak ditemukan.', 404);
		}

		$route = $this->routes[$action];

		Middleware::handle($route['middlewares']);

		$controller = new $route['controller']($GLOBALS['koneksi']);

		if (!method_exists($controller, $route['method'])) {
			Response::error('Method tidak ditemukan.', 404);
		}

		$controller->{$route['method']}();
	}
}