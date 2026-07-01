<?php

class Middleware
{
	public static function handle(array $middlewares): void {
		foreach ($middlewares as $middleware) {

			$parts = explode(':', $middleware);
			$name = $parts[0];
			$parameter = $parts[1] ?? null;
			$file = __DIR__ . '/../middleware/' . $name . '.php';
			if (!file_exists($file)) {
				Response::error("Middleware '{$name}' tidak ditemukan.", 500);
			}
			require_once $file;
			$class = ucfirst($name) . 'Middleware';
			if (!class_exists($class)) {
				Response::error("Class {$class} tidak ditemukan.", 500);
			}
			$instance = new $class();
			$instance->handle($parameter);
		}
	}
}