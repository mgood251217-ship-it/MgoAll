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
		global $koneksi;
		if (!isset($this->routes[$action])) {
			Response::error('Action tidak ditemukan.', 404);
		}

		$route = $this->routes[$action];
		if (in_array('auth', $route['middlewares'])) {
			require_once BASE_PATH . '/session.php';
		}

		$controller = new $route['controller']($koneksi);

		$result = call_user_func([$controller, $route['method']]);

		// Controller lama sudah mengirim JSON sendiri
		if ($result === null) {
			return;
		}

		// Controller mengembalikan array
		if (is_array($result)) {
			Response::success('Success', $result);
		}

		// Controller mengembalikan string
		if (is_string($result)) {
			Response::success($result);
		}

		// Controller mengembalikan boolean
		if (is_bool($result)) {
			$result
				? Response::success()
				: Response::error('Gagal');
		}

		// Selain itu kirim apa adanya
		Response::success('Success', (array) $result);
	}
}