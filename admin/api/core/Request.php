<?php

class Request
{
	private static ?array $json = null;

	private static function json(): array
	{
		if (self::$json !== null) {
			return self::$json;
		}

		$body = file_get_contents("php://input");

		self::$json = json_decode($body, true);

		if (!is_array(self::$json)) {
			self::$json = [];
		}

		return self::$json;
	}

	public static function input(string $key, mixed $default = null): mixed
	{
		if (array_key_exists($key, $_POST)) {
			return $_POST[$key];
		}

		if (array_key_exists($key, $_GET)) {
			return $_GET[$key];
		}

		$json = self::json();

		if (array_key_exists($key, $json)) {
			return $json[$key];
		}

		return $default;
	}

	public static function all(): array
	{
		return array_merge(
			$_GET,
			$_POST,
			self::json()
		);
	}

	public static function file(string $key)
	{
		return $_FILES[$key] ?? null;
	}

	public static function method(): string
	{
		return $_SERVER['REQUEST_METHOD'];
	}
}