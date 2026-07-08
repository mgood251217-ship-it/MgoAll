<?php

class App {
	private static array $container = [];
	public static function set($key, $value) {
		self::$container[$key] = $value;
	}

	public static function get($key) {
		return self::$container[$key] ?? null;
	}

	public static function has($key) {
		return isset(self::$container[$key]);
	}
}