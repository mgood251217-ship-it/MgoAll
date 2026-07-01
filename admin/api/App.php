<?php

class App
{
	private static array $container = [];
	public static function set(string $key, mixed $value): void
	{
		self::$container[$key] = $value;
	}

	public static function get(string $key): mixed
	{
		return self::$container[$key] ?? null;
	}

	public static function has(string $key): bool
	{
		return isset(self::$container[$key]);
	}
}